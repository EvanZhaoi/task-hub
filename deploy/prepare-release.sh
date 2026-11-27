#!/usr/bin/env bash

set -Eeuo pipefail

# 本脚本在开发机执行，将源代码和已生成依赖打成服务器部署包。
cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"
readonly OUTPUT_DIR="${1:-deploy/artifacts}"
readonly PACKAGE_NAME="taskhub-source-${TASKHUB_VERSION}.tar.gz"

if [[ ! -f vendor/autoload.php ]]; then
    printf '缺少 vendor，请先执行 composer install --no-dev --optimize-autoloader。\n' >&2
    exit 1
fi

if [[ ! -f public/build/manifest.json ]]; then
    printf '缺少前端构建结果，请先执行 npm run build。\n' >&2
    exit 1
fi

mkdir -p "${OUTPUT_DIR}"

# 明确排除开发缓存、真实环境变量和历史部署产物，同时保留 vendor 与 public/build。
tar \
    --exclude='./.git' \
    --exclude='./.env' \
    --exclude='./.env.production' \
    --exclude='./node_modules' \
    --exclude='./deploy/artifacts' \
    --exclude='./deploy/cache' \
    --exclude='./storage/logs/*' \
    --exclude='./storage/framework/cache/*' \
    --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/views/*' \
    -czf "${OUTPUT_DIR}/${PACKAGE_NAME}" \
    .

printf '项目发布包已生成：%s/%s\n' "${OUTPUT_DIR}" "${PACKAGE_NAME}"
