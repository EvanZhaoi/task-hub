#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"
readonly PHP_IMAGE="${TASKHUB_PHP_IMAGE:-php:8.5.13-fpm-trixie}"
readonly NODE_IMAGE="${TASKHUB_NODE_IMAGE:-node:24.22.3-alpine3.24}"
readonly COMPOSER_IMAGE="${TASKHUB_COMPOSER_IMAGE:-composer:2.11.2}"
readonly NGINX_IMAGE="${TASKHUB_NGINX_IMAGE:-nginx:1.30.7-alpine3.24}"
readonly BASE_IMAGES=(
    "${PHP_IMAGE}"
    "${NODE_IMAGE}"
    "${COMPOSER_IMAGE}"
    "${NGINX_IMAGE}"
)

log() {
    printf '\n[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        printf '缺少命令：%s\n' "$1" >&2
        exit 1
    fi
}

require_command docker

# 自动补齐或更新构建和编排所需的官方 CLI 插件，避免使用带已知漏洞的旧版本。
bash deploy/install-docker-plugins.sh

log "检查 Compose 配置"
TASKHUB_VERSION="${TASKHUB_VERSION}" \
    TASKHUB_ENV_FILE=.env.production.example \
    docker compose config --quiet

log "并行拉取四个固定版本的官方基础镜像"
pull_pids=()
for image in "${BASE_IMAGES[@]}"; do
    (
        printf '开始拉取 %s\n' "${image}"
        docker pull "${image}"
    ) &
    pull_pids+=("$!")
done

pull_failed=0
for pid in "${pull_pids[@]}"; do
    if ! wait "${pid}"; then
        pull_failed=1
    fi
done

if [[ "${pull_failed}" -ne 0 ]]; then
    printf '至少一个基础镜像拉取失败，请保持联网并重新执行本脚本。\n' >&2
    exit 1
fi

log "构建 TaskHub PHP 与 Nginx 最终镜像"
# 构建过程会下载 PECL、Composer、npm 和 Bunny Fonts 资源。
TASKHUB_VERSION="${TASKHUB_VERSION}" \
    TASKHUB_ENV_FILE=.env.production.example \
    DOCKER_BUILDKIT=1 \
    docker compose --progress=plain build app web

log "确认最终离线运行镜像已经生成"
docker image inspect "taskhub-app:${TASKHUB_VERSION}" >/dev/null
docker image inspect "taskhub-web:${TASKHUB_VERSION}" >/dev/null

log "检查最终 PHP 镜像的扩展与 Laravel 启动状态"
docker run --rm "taskhub-app:${TASKHUB_VERSION}" php -r '
$required = ["curl", "dom", "fileinfo", "mbstring", "openssl", "pdo_mysql", "redis"];
$missing = array_values(array_filter($required, fn (string $extension): bool => ! extension_loaded($extension)));
if ($missing !== []) {
    fwrite(STDERR, "缺少 PHP 扩展：".implode(", ", $missing).PHP_EOL);
    exit(1);
}
echo "PHP 扩展检查通过。".PHP_EOL;
'

docker run --rm \
    "taskhub-app:${TASKHUB_VERSION}" \
    php artisan about --only=environment

log "联网下载和构建已经完成"
printf '%s\n' \
    "最终镜像：taskhub-app:${TASKHUB_VERSION}" \
    "最终镜像：taskhub-web:${TASKHUB_VERSION}" \
    "现在可以关闭公网；关闭后执行 bash deploy/offline-start.sh。"
