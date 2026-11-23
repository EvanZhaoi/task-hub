#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"

if [[ ! -f .env.production ]]; then
    printf '缺少 .env.production。\n' >&2
    exit 1
fi

# 先检查本地镜像，确保 Compose 不会在断网环境尝试拉取或重新构建。
docker image inspect "taskhub-app:${TASKHUB_VERSION}" >/dev/null
docker image inspect "taskhub-web:${TASKHUB_VERSION}" >/dev/null

TASKHUB_VERSION="${TASKHUB_VERSION}" \
    docker compose up -d --no-build --pull never

docker compose ps

printf '\nTaskHub 已使用本地镜像启动。访问端口：%s\n' "${TASKHUB_HTTP_PORT:-18080}"
