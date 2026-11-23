#!/usr/bin/env bash

set -Eeuo pipefail

readonly COMPOSE_VERSION="v5.7.1"
readonly BUILDX_VERSION="v0.39.1"
readonly PLUGIN_DIR="${DOCKER_CONFIG:-${HOME}/.docker}/cli-plugins"

download() {
    local url="$1"
    local output="$2"

    if command -v curl >/dev/null 2>&1; then
        curl --fail --location --retry 3 --connect-timeout 10 --output "${output}" "${url}"
        return
    fi

    if command -v wget >/dev/null 2>&1; then
        wget --tries=3 --timeout=20 --output-document="${output}" "${url}"
        return
    fi

    printf '安装 Docker 插件需要 curl 或 wget。\n' >&2
    exit 1
}

case "$(uname -m)" in
    x86_64)
        compose_arch="x86_64"
        buildx_arch="amd64"
        ;;
    aarch64 | arm64)
        compose_arch="aarch64"
        buildx_arch="arm64"
        ;;
    *)
        printf '暂不支持的服务器架构：%s\n' "$(uname -m)" >&2
        exit 1
        ;;
esac

mkdir -p "${PLUGIN_DIR}"
temporary_dir="$(mktemp -d)"
trap 'rm -rf "${temporary_dir}"' EXIT

current_compose_version="$(docker compose version --short 2>/dev/null || true)"
if [[ "v${current_compose_version#v}" != "${COMPOSE_VERSION}" ]]; then
    compose_name="docker-compose-linux-${compose_arch}"
    compose_base_url="https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}"

    printf '下载 Docker Compose %s...\n' "${COMPOSE_VERSION}"
    download "${compose_base_url}/${compose_name}" "${temporary_dir}/${compose_name}"
    download "${compose_base_url}/checksums.txt" "${temporary_dir}/compose-checksums.txt"

    (
        cd "${temporary_dir}"
        grep "\\*${compose_name}$" compose-checksums.txt | sha256sum --check
    )

    install -m 0755 "${temporary_dir}/${compose_name}" "${PLUGIN_DIR}/docker-compose"
fi

current_buildx_version="$(docker buildx version 2>/dev/null | awk '{print $2}' || true)"
if [[ "${current_buildx_version}" != "${BUILDX_VERSION}" ]]; then
    buildx_name="buildx-${BUILDX_VERSION}.linux-${buildx_arch}"
    buildx_base_url="https://github.com/docker/buildx/releases/download/${BUILDX_VERSION}"

    printf '下载 Docker Buildx %s...\n' "${BUILDX_VERSION}"
    download "${buildx_base_url}/${buildx_name}" "${temporary_dir}/${buildx_name}"
    download "${buildx_base_url}/checksums.txt" "${temporary_dir}/buildx-checksums.txt"

    (
        cd "${temporary_dir}"
        grep "\\*${buildx_name}$" buildx-checksums.txt | sha256sum --check
    )

    install -m 0755 "${temporary_dir}/${buildx_name}" "${PLUGIN_DIR}/docker-buildx"
fi

docker compose version
docker buildx version
