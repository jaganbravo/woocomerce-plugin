#!/usr/bin/env bash
# Build a WordPress-installable ZIP (folder: dataviz-ai-woocommerce/).
# Folder name must match the plugin header Text Domain (see Plugin Check / .org slug rules).
# Excludes paths listed in ../.distignore plus dist/ and this script.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="dataviz-ai-woocommerce"
MAIN_PHP="${PLUGIN_DIR}/dataviz-ai-woocommerce.php"

if [[ ! -f "$MAIN_PHP" ]]; then
	echo "ERROR: Expected main file at $MAIN_PHP" >&2
	exit 1
fi

VERSION="$(grep -m1 '^[[:space:]]*\* Version:' "$MAIN_PHP" | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//;s/[[:space:]]*$//')"
if [[ -z "$VERSION" ]]; then
	echo "ERROR: Could not read Version from plugin header." >&2
	exit 1
fi

ZIP_NAME="${SLUG}-${VERSION}.zip"
DIST_DIR="${PLUGIN_DIR}/dist"
TMP="$(mktemp -d)"

cleanup() {
	rm -rf "$TMP"
}
trap cleanup EXIT

mkdir -p "$DIST_DIR" "$TMP/${SLUG}"

rsync -a \
	--exclude-from="${PLUGIN_DIR}/.distignore" \
	--exclude='/dist/' \
	--exclude='/bin/' \
	"${PLUGIN_DIR}/" "${TMP}/${SLUG}/"

rm -f "${DIST_DIR}/${ZIP_NAME}"
(
	cd "$TMP"
	zip -r -q "${DIST_DIR}/${ZIP_NAME}" "${SLUG}"
)

echo "Built: ${DIST_DIR}/${ZIP_NAME}"
