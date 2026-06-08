#!/usr/bin/env bash
# WordPress.org / manual upload zip with correct top-level folder layout.
#
# Output: planit-event-manager.zip under PLANIT_DEV_ROOT containing:
#   planit-event-manager/planit-event-manager.php
#
# A flat zip (files at archive root) causes WordPress to add planit-event-manager-1
# when the folder already exists, which breaks Premium's free-companion detection.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLANIT_DEV_ROOT="/Users/randy/wordpress-plugins/WordpressDev/planit"
ZIP_OUTPUT_DIR="${PLANIT_DEV_ROOT}"

PLUGIN_SLUG="planit-event-manager"
ZIP_NAME="${PLUGIN_SLUG}.zip"
ZIP_PATH="${ZIP_OUTPUT_DIR}/${ZIP_NAME}"
TEMP_DIR="${SCRIPT_DIR}/.zip-temp-$$"
TEMP_PLUGIN="${TEMP_DIR}/${PLUGIN_SLUG}"

cleanup() {
	if [[ -d "${TEMP_DIR}" ]]; then
		rm -rf "${TEMP_DIR}"
	fi
}
trap cleanup EXIT

echo "Creating WordPress plugin zip: ${ZIP_PATH}"

if [[ ! -f "${SCRIPT_DIR}/planit-event-manager.php" ]]; then
	echo "✗ Error: run from the planit-event-manager plugin root (planit-event-manager.php missing)."
	exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
	echo "✗ Error: 'zip' not found"
	exit 1
fi

mkdir -p "${ZIP_OUTPUT_DIR}" "${TEMP_PLUGIN}"

if command -v rsync >/dev/null 2>&1; then
	rsync -a \
		--exclude='.zip-temp-*' \
		--exclude='.git' \
		--exclude='.gitignore' \
		--exclude='.github' \
		--exclude='.cursorrules' \
		--exclude='.cursor' \
		--exclude='.vscode' \
		--exclude='.idea' \
		--exclude='node_modules' \
		--exclude='vendor' \
		--exclude='dist' \
		--exclude='build' \
		--exclude='assets' \
		--exclude='tests' \
		--exclude='.cache' \
		--exclude='*.log' \
		--exclude='.DS_Store' \
		--exclude='*.swp' \
		--exclude='*.swo' \
		--exclude='*~' \
		--exclude='*.tmp' \
		--exclude='*.temp' \
		--exclude='*.md' \
		--exclude='phpcs.xml' \
		--exclude='phpcs.xml.dist' \
		--exclude='composer.json' \
		--exclude='composer.lock' \
		--exclude='PLUGIN-IDENTIFIER.txt' \
		--exclude='release-data.json' \
		--exclude='*.zip' \
		--exclude='*.sh' \
		--exclude='*.gz' \
		--exclude='*.tar' \
		--exclude='*.tar.gz' \
		--exclude='*.tgz' \
		--exclude='*.rar' \
		--exclude='*.7z' \
		--exclude='*.bz2' \
		--exclude='*.xz' \
		"${SCRIPT_DIR}/" "${TEMP_PLUGIN}/"
else
	echo "✗ Error: rsync is required for this script (macOS/Linux)."
	exit 1
fi

rm -f "${ZIP_PATH}"

(
	cd "${TEMP_DIR}" || exit 1
	zip -qr "${ZIP_PATH}" "${PLUGIN_SLUG}"
)

echo "✓ Zip file created: ${ZIP_PATH}"
echo "  File size: $(ls -lh "${ZIP_PATH}" | awk '{print $5}')"
echo ""
echo "Verifying zip layout and exclusions..."

ZIP_LISTING=$( unzip -l "${ZIP_PATH}" 2>/dev/null || true )

if echo "${ZIP_LISTING}" | grep -qF "${PLUGIN_SLUG}/planit-event-manager.php"; then
	echo "✓ Confirmed: main file is under ${PLUGIN_SLUG}/"
else
	echo "✗ ERROR: expected ${PLUGIN_SLUG}/planit-event-manager.php — zip layout is wrong."
	exit 1
fi

if echo "${ZIP_LISTING}" | grep -qE '^[[:space:]]+[0-9]+[[:space:]]+[0-9-]+[[:space:]]+[0-9:]+[[:space:]]+planit-event-manager\.php$'; then
	echo "✗ ERROR: planit-event-manager.php is at zip root (flat layout). WordPress may install planit-event-manager-1."
	exit 1
fi

if echo "${ZIP_LISTING}" | grep -qE '(\.cursorrules|\.cursor/|/\.git/|create-plugin-zip\.sh|\.sh$|tests/|phpcs\.xml)'; then
	echo "⚠ WARNING: dev files may be present — review listing"
	echo "${ZIP_LISTING}" | grep -E '(\.cursorrules|\.cursor/|/\.git/|create-plugin-zip\.sh|\.sh$|tests/|phpcs\.xml)' || true
else
	echo "✓ Confirmed: dev exclusions look good"
fi
