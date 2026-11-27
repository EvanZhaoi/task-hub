#!/usr/bin/env bash

set -Eeuo pipefail

# 进入项目根目录，确保默认离线包路径始终一致。
cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"
readonly DEFAULT_PACKAGE="deploy/artifacts/taskhub-base-images-${TASKHUB_VERSION}-x86_64.tar"
readonly PACKAGE_PATH="${1:-${DEFAULT_PACKAGE}}"
readonly CHECKSUM_PATH="${PACKAGE_PATH}.sha256"
readonly REDIS_PACKAGE="deploy/cache/redis-6.3.0.tgz"

if [[ "$(uname -m)" != "x86_64" ]]; then
    printf '仅支持 x86_64，当前架构：%s\n' "$(uname -m)" >&2
    exit 1
fi

if [[ ! -f "${PACKAGE_PATH}" ]]; then
    printf '找不到离线镜像包：%s\n' "${PACKAGE_PATH}" >&2
    exit 1
fi

if [[ ! -f "${CHECKSUM_PATH}" ]]; then
    printf '找不到校验文件：%s\n' "${CHECKSUM_PATH}" >&2
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

# sha256sum 需要在镜像包所在目录执行，因为校验文件只保存文件名。
(
    cd "$(dirname "${PACKAGE_PATH}")"
    sha256sum --check "$(basename "${CHECKSUM_PATH}")"
)

# 加载联网期间下载的基础镜像。
docker image load --input "${PACKAGE_PATH}"

# 使用本地代码、vendor、public/build 和 Redis 扩展离线构建最终镜像。
TASKHUB_VERSION="${TASKHUB_VERSION}" \
TASKHUB_ENV_FILE=.env.production.example \
DOCKER_BUILDKIT=1 \
    docker compose build app web

printf 'TaskHub 镜像已离线构建，未启动任何容器。\n'

