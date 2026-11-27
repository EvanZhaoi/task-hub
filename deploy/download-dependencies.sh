#!/usr/bin/env bash

set -Eeuo pipefail

# 进入项目根目录，确保从任意目录调用时都把文件下载到正确位置。
cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly REDIS_DIR="deploy/cache"
readonly REDIS_PACKAGE="${REDIS_DIR}/redis-6.3.0.tgz"
readonly REDIS_URL="https://pecl.php.net/get/redis-6.3.0.tgz"
readonly REDIS_SHA256="0d5141f634bd1db6c1ddcda053d25ecf2c4fc1c395430d534fd3f8d51dd7f0b5"
readonly BASE_IMAGES=(
    "php:8.5.13-fpm-trixie"
    "nginx:1.30.7-alpine3.24"
)

if [[ "$(uname -m)" != "x86_64" ]]; then
    printf '仅支持 x86_64，当前架构：%s\n' "$(uname -m)" >&2
    exit 1
fi

docker version >/dev/null
mkdir -p "${REDIS_DIR}"

# 只拉取离线构建需要的两个基础镜像，不构建或启动 TaskHub。
for image in "${BASE_IMAGES[@]}"; do
    docker pull "${image}"
done

# Redis 扩展源码必须在联网期间下载，断网构建时直接读取本地文件。
curl --fail --location --retry 3 --connect-timeout 10 \
    --output "${REDIS_PACKAGE}" \
    "${REDIS_URL}"
printf '%s  %s\n' "${REDIS_SHA256}" "${REDIS_PACKAGE}" | sha256sum --check

printf '所需镜像和 Redis 扩展已经下载完成，未构建或启动 TaskHub。\n'
