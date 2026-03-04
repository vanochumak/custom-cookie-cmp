#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR_NAME="custom-cookie-cmp"

# Run from inside the plugin folder
PLUGIN_PATH="$(pwd)"
PARENT_DIR="$(dirname "$PLUGIN_PATH")"

# Try to detect version from plugin header: Version: x.y.z
VERSION="$(grep -m1 -E '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*' "$PLUGIN_PATH/custom-cookie-cmp.php" \
  | sed -E 's/.*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/')"

if [[ -z "${VERSION}" ]]; then
  echo "ERROR: Cannot detect plugin version from custom-cookie-cmp.php"
  exit 1
fi

ZIP_NAME="${PLUGIN_DIR_NAME}-${VERSION}.zip"
ZIP_PATH="${PARENT_DIR}/${ZIP_NAME}"

echo "Building: ${ZIP_NAME}"

# Create clean zip from parent, so the zip contains folder "custom-cookie-cmp/"
cd "$PARENT_DIR"

# Remove old zip if exists
rm -f "$ZIP_PATH"

zip -r "$ZIP_PATH" "$PLUGIN_DIR_NAME" \
  -x "$PLUGIN_DIR_NAME/.git/*" \
  -x "$PLUGIN_DIR_NAME/.gitignore" \
  -x "$PLUGIN_DIR_NAME/CLAUDE.md" \
  -x "$PLUGIN_DIR_NAME/PROJECT_LOG.md" \
  -x "$PLUGIN_DIR_NAME/build_zip.sh" \
  -x "$PLUGIN_DIR_NAME/node_modules/*" \
  -x "$PLUGIN_DIR_NAME/vendor/*" \
  -x "$PLUGIN_DIR_NAME/*.log" \
  -x "$PLUGIN_DIR_NAME/.DS_Store" \
  -x "$PLUGIN_DIR_NAME/**/.DS_Store"

echo "Done: $ZIP_PATH"