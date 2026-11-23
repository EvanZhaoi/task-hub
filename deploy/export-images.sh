#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

readonly TASKHUB_VERSION="${TASKHUB_VERSION:-1.0.0}"
readonly OUTPUT_DIR="${1:-deploy/artifacts}"

mkdir -p "${OUTPUT_DIR}"

docker image save \
    "taskhub-app:${TASKHUB_VERSION}" \
    "taskhub-web:${TASKHUB_VERSION}" \
    --output "${OUTPUT_DIR}/taskhub-runtime-${TASKHUB_VERSION}.tar"

(
    cd "${OUTPUT_DIR}"
    sha256sum "taskhub-runtime-${TASKHUB_VERSION}.tar" > SHA256SUMS
)

printf '镜像已导出到：%s/taskhub-runtime-%s.tar\n' "${OUTPUT_DIR}" "${TASKHUB_VERSION}"
