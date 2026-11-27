#!/usr/bin/env bash

set -Eeuo pipefail

# 进入项目根目录，确保能够读取完整项目、vendor 和 public/build。
cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"
readonly REDIS_PACKAGE="deploy/cache/redis-6.3.0.tgz"
readonly REDIS_SHA256="0d5141f634bd1db6c1ddcda053d25ecf2c4fc1c395430d534fd3f8d51dd7f0b5"

if [[ "$(uname -m)" != "x86_64" ]]; then
    printf '仅支持 x86_64，当前架构：%s\n' "$(uname -m)" >&2
    exit 1
fi

if [[ ! -f vendor/autoload.php ]]; then
    printf '缺少开发机生成的 vendor/autoload.php。\n' >&2
    exit 1
fi

if [[ ! -f public/build/manifest.json ]]; then
    printf '缺少开发机生成的 public/build/manifest.json。\n' >&2
    exit 1
fi

if [[ ! -f "${REDIS_PACKAGE}" ]]; then
    printf '缺少 Redis 扩展源码：%s\n' "${REDIS_PACKAGE}" >&2
    exit 1
fi

# 确认联网期间的下载结果完整，避免断网构建到一半才失败。
docker image inspect php:8.5.13-fpm-trixie >/dev/null
docker image inspect nginx:1.30.7-alpine3.24 >/dev/null
printf '%s  %s\n' "${REDIS_SHA256}" "${REDIS_PACKAGE}" | sha256sum --check

# 使用本地代码、vendor、public/build 和 Redis 扩展离线构建最终镜像。
DOCKER_BUILDKIT=1 \
    docker build \
        --pull=false \
        --network=none \
        --target app \
        --tag "taskhub-app:${TASKHUB_VERSION}" \
        .

DOCKER_BUILDKIT=1 \
    docker build \
        --pull=false \
        --network=none \
        --target web \
        --tag "taskhub-web:${TASKHUB_VERSION}" \
        .

printf 'TaskHub 镜像已离线构建，未启动任何容器。\n'

