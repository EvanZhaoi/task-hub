#!/usr/bin/env bash

set -Eeuo pipefail

# 进入项目根目录，确保默认离线包路径始终一致。
cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"
readonly DEFAULT_PACKAGE="deploy/artifacts/taskhub-${TASKHUB_VERSION}-x86_64.tar"
readonly PACKAGE_PATH="${1:-${DEFAULT_PACKAGE}}"
readonly CHECKSUM_PATH="${PACKAGE_PATH}.sha256"

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

# sha256sum 需要在镜像包所在目录执行，因为校验文件只保存文件名。
(
    cd "$(dirname "${PACKAGE_PATH}")"
    sha256sum --check "$(basename "${CHECKSUM_PATH}")"
)

# 将两个最终镜像加载到离线服务器的 Docker 中，不创建或启动任何容器。
docker image load --input "${PACKAGE_PATH}"

printf '离线镜像已加载，未启动任何容器。\n'

