#!/usr/bin/env bash
# Copy the plugin into the local Docker WordPress tree (see docker/docker-compose.yml).
# Preserves docker-only config.php (not in git).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${ROOT}/dataviz-ai-woocommerce-plugin"
DEST="${ROOT}/docker/wordpress/wp-content/plugins/dataviz-ai-woocommerce-plugin"

if [[ ! -d "${SRC}" ]]; then
	echo "Missing: ${SRC}" >&2
	exit 1
fi
mkdir -p "${DEST}"

rsync -av --delete --filter='protect config.php' "${SRC}/" "${DEST}/"
echo "Synced to ${DEST}"
