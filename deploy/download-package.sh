#!/usr/bin/env bash

set -Eeuo pipefail

# 进入项目根目录，确保从任意目录调用脚本时都能找到 Dockerfile 和 compose.yaml。
cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"
readonly OUTPUT_DIR="${1:-deploy/artifacts}"
readonly PACKAGE_NAME="taskhub-${TASKHUB_VERSION}-x86_64.tar"
readonly BASE_IMAGES=(
    "php:8.5.13-fpm-trixie"
    "node:24.22.3-alpine3.24"
    "composer:2.11.2"
    "nginx:1.30.7-alpine3.24"
)

# 本部署包只面向已经确认的 x86_64 服务器，避免下载错误架构的镜像。
if [[ "$(uname -m)" != "x86_64" ]]; then
    printf '仅支持 x86_64，当前架构：%s\n' "$(uname -m)" >&2
    exit 1
fi

# 服务器必须已经具备 Docker、Compose 和 Buildx；本脚本不会安装任何工具。
docker version >/dev/null
docker compose version >/dev/null
docker buildx version >/dev/null

mkdir -p "${OUTPUT_DIR}"

# 拉取 Dockerfile 使用的四个固定版本基础镜像。
for image in "${BASE_IMAGES[@]}"; do
    docker pull "${image}"
done

# 构建过程会把 Composer 依赖、npm 依赖、字体和 Redis 扩展下载并封装进最终镜像。
# 此命令只构建镜像，不会创建或启动 TaskHub 容器。
TASKHUB_VERSION="${TASKHUB_VERSION}" \
TASKHUB_ENV_FILE=.env.production.example \
DOCKER_BUILDKIT=1 \
    docker compose build app web

# 导出离线服务器真正需要的两个最终镜像。
docker image save \
    "taskhub-app:${TASKHUB_VERSION}" \
    "taskhub-web:${TASKHUB_VERSION}" \
    --output "${OUTPUT_DIR}/${PACKAGE_NAME}"

# 生成校验文件，供断网后确认镜像包在复制过程中没有损坏。
(
    cd "${OUTPUT_DIR}"
    sha256sum "${PACKAGE_NAME}" > "${PACKAGE_NAME}.sha256"
)

printf '下载包已生成：%s/%s\n' "${OUTPUT_DIR}" "${PACKAGE_NAME}"
printf '校验文件已生成：%s/%s.sha256\n' "${OUTPUT_DIR}" "${PACKAGE_NAME}"

