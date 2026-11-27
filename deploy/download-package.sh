#!/usr/bin/env bash

set -Eeuo pipefail

# 进入项目根目录，确保从任意目录调用脚本时都能找到 Dockerfile 和 compose.yaml。
cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"
readonly OUTPUT_DIR="${1:-deploy/artifacts}"
readonly PACKAGE_NAME="taskhub-base-images-${TASKHUB_VERSION}-x86_64.tar"
readonly REDIS_DIR="deploy/cache"
readonly REDIS_PACKAGE="${REDIS_DIR}/redis-6.3.0.tgz"
readonly REDIS_URL="https://pecl.php.net/get/redis-6.3.0.tgz"
readonly REDIS_SHA256="0d5141f634bd1db6c1ddcda053d25ecf2c4fc1c395430d534fd3f8d51dd7f0b5"
readonly BASE_IMAGES=(
    "php:8.5.13-fpm-trixie"
    "nginx:1.30.7-alpine3.24"
)

# 本部署包只面向已经确认的 x86_64 服务器，避免下载错误架构的镜像。
if [[ "$(uname -m)" != "x86_64" ]]; then
    printf '仅支持 x86_64，当前架构：%s\n' "$(uname -m)" >&2
    exit 1
fi

# 服务器必须已经具备 Docker；本脚本不会安装任何工具。
docker version >/dev/null

mkdir -p "${OUTPUT_DIR}" "${REDIS_DIR}"

# 拉取 Dockerfile 使用的两个固定版本基础镜像。
for image in "${BASE_IMAGES[@]}"; do
    docker pull "${image}"
done

# 下载离线构建 PHP 镜像时需要的 Redis 扩展源码。
curl --fail --location --retry 3 --connect-timeout 10 \
    --output "${REDIS_PACKAGE}" \
    "${REDIS_URL}"
printf '%s  %s\n' "${REDIS_SHA256}" "${REDIS_PACKAGE}" | sha256sum --check

# 保存基础镜像，确保断网后仍可重新加载并构建。
docker image save \
    "${BASE_IMAGES[@]}" \
    --output "${OUTPUT_DIR}/${PACKAGE_NAME}"

# 生成校验文件，供断网后确认镜像包在复制过程中没有损坏。
(
    cd "${OUTPUT_DIR}"
    sha256sum "${PACKAGE_NAME}" > "${PACKAGE_NAME}.sha256"
)

printf '基础镜像包已生成：%s/%s\n' "${OUTPUT_DIR}" "${PACKAGE_NAME}"
printf 'Redis 扩展已下载：%s\n' "${REDIS_PACKAGE}"
printf '校验文件已生成：%s/%s.sha256\n' "${OUTPUT_DIR}" "${PACKAGE_NAME}"

