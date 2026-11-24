#!/usr/bin/env bash

set -Eeuo pipefail

readonly COMPOSE_VERSION="v5.7.1"
readonly BUILDX_VERSION="v0.39.1"
readonly COMPOSE_URL="https://github.com/docker/compose/releases/download/v5.7.1/docker-compose-linux-x86_64"
readonly COMPOSE_SHA256="af426722a8af5de812fcee30cc1c39f0639f5f723cbadefb25e5d4db054064cf"
readonly BUILDX_URL="https://github.com/docker/buildx/releases/download/v0.39.1/buildx-v0.39.1.linux-amd64"
readonly BUILDX_SHA256="903090816da68f8bc1a2ff5346dd163a5adacfce4548d003c75df42cd47a9bf4"
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

if [[ "$(uname -m)" != "x86_64" ]]; then
    printf '当前部署包只允许在已确认的 x86_64 服务器运行，实际架构：%s\n' "$(uname -m)" >&2
    exit 1
fi

mkdir -p "${PLUGIN_DIR}"
temporary_dir="$(mktemp -d)"
trap 'rm -rf "${temporary_dir}"' EXIT

current_compose_version="$(docker compose version --short 2>/dev/null || true)"
if [[ "v${current_compose_version#v}" != "${COMPOSE_VERSION}" ]]; then
    compose_name="docker-compose-linux-x86_64"

    printf '下载 Docker Compose %s...\n' "${COMPOSE_VERSION}"
    download "${COMPOSE_URL}" "${temporary_dir}/${compose_name}"

    (
        cd "${temporary_dir}"
        printf '%s  %s\n' "${COMPOSE_SHA256}" "${compose_name}" | sha256sum --check
    )

    install -m 0755 "${temporary_dir}/${compose_name}" "${PLUGIN_DIR}/docker-compose"
fi

current_buildx_version="$(docker buildx version 2>/dev/null | awk '{print $2}' || true)"
if [[ "${current_buildx_version}" != "${BUILDX_VERSION}" ]]; then
    buildx_name="buildx-v0.39.1.linux-amd64"

    printf '下载 Docker Buildx %s...\n' "${BUILDX_VERSION}"
    download "${BUILDX_URL}" "${temporary_dir}/${buildx_name}"

    (
        cd "${temporary_dir}"
        printf '%s  %s\n' "${BUILDX_SHA256}" "${buildx_name}" | sha256sum --check
    )

    install -m 0755 "${temporary_dir}/${buildx_name}" "${PLUGIN_DIR}/docker-buildx"
fi

docker compose version
docker buildx version
