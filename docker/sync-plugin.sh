#!/bin/bash

# Sync Plugin Files to Docker
# This script copies the latest plugin files to the Docker WordPress installation

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SOURCE="$SCRIPT_DIR/../dataviz-ai-woocommerce-plugin"
PLUGIN_DEST="$SCRIPT_DIR/wordpress/wp-content/plugins/dataviz-ai-woocommerce-plugin"

echo "🔄 Syncing plugin files to Docker..."
echo "   Source: $PLUGIN_SOURCE"
echo "   Destination: $PLUGIN_DEST"
echo ""

# Check if source exists
if [ ! -d "$PLUGIN_SOURCE" ]; then
    echo "❌ Error: Plugin source directory not found: $PLUGIN_SOURCE"
    exit 1
fi

# Create destination directory if it doesn't exist
mkdir -p "$PLUGIN_DEST"

# Sync files (rsync preserves permissions and excludes unnecessary files).
# Exclude config.php so a missing or gitignored local file does not wipe the Docker install's API key.
rsync -av --delete \
    "$PLUGIN_SOURCE/" \
    "$PLUGIN_DEST/" \
    --exclude="config.php" \
    --exclude="*.log" \
    --exclude="node_modules" \
    --exclude=".git" \
    --exclude=".DS_Store"

echo ""
echo "✅ Plugin files synced successfully!"
echo ""
echo "📋 Next steps:"
echo "   1. Restart Docker containers if needed: docker compose restart wordpress"
echo "   2. Clear WordPress cache if using caching plugins"
echo "   3. Visit http://localhost:8080/wp-admin to verify plugin is updated"
echo ""
