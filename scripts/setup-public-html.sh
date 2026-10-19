#!/usr/bin/env bash
# ONE-TIME setup: link public_html assets to canteen_management/public
# so git pull never needs manual copy/paste of Vite dist / icons again.
#
# Usage (on server, from project root):
#   bash scripts/setup-public-html.sh
#
# Paths auto-detect from this script location (any Hostinger account).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_DIR="${LARAVEL_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
PUBLIC_HTML="${PUBLIC_HTML:-${PUBLIC_HTML_PATH:-$(dirname "$LARAVEL_DIR")/public_html}}"
PROJECT_NAME="$(basename "$LARAVEL_DIR")"

# shellcheck source=ensure-storage-links.sh
source "$LARAVEL_DIR/scripts/ensure-storage-links.sh"

if [ ! -d "$LARAVEL_DIR/public" ]; then
    echo "Error: $LARAVEL_DIR/public not found."
    exit 1
fi

mkdir -p "$PUBLIC_HTML"

echo "Setting up public_html → ${PROJECT_NAME}/public symlinks..."
echo "  Laravel:     $LARAVEL_DIR"
echo "  Public HTML: $PUBLIC_HTML"
echo ""

link_item() {
    local name="$1"
    local source="$LARAVEL_DIR/public/$name"

    if [ ! -e "$source" ]; then
        echo "– Skipped $name (not in public/)"
        return 0
    fi

    rm -rf "$PUBLIC_HTML/$name"
    ln -sfn "../${PROJECT_NAME}/public/$name" "$PUBLIC_HTML/$name"
    echo "✓ Linked $name"
}

# Bridge index.php (rewrite folder name to match this install)
if [ -f "$LARAVEL_DIR/scripts/public_html/index.php" ]; then
    sed "s|canteen_management|${PROJECT_NAME}|g" \
        "$LARAVEL_DIR/scripts/public_html/index.php" > "$PUBLIC_HTML/index.php"
    echo "✓ Wrote index.php → ../${PROJECT_NAME}"
else
    echo "Error: scripts/public_html/index.php missing."
    exit 1
fi

# Symlink .htaccess
ln -sfn "../${PROJECT_NAME}/public/.htaccess" "$PUBLIC_HTML/.htaccess"
echo "✓ Linked .htaccess"

# Vite build output + PWA / static assets (no npm on Hostinger — dist must be in git)
LINK_DIRS=(dist icons build)

for dir in "${LINK_DIRS[@]}"; do
    link_item "$dir"
done

ensure_storage_links "$LARAVEL_DIR" "$PUBLIC_HTML"

LINK_FILES=(
    robots.txt
    favicon.ico
    offline.html
    sw.js
    manifest.json
    web.config
)

for file in "${LINK_FILES[@]}"; do
    link_item "$file"
done

for junk in default.php index.html; do
    if [ -f "$PUBLIC_HTML/$junk" ]; then
        mv "$PUBLIC_HTML/$junk" "$PUBLIC_HTML/${junk}.bak_$(date +%Y%m%d_%H%M%S)"
        echo "✓ Moved aside $junk"
    fi
done

echo ""
echo "Done! public_html now reads assets from ${PROJECT_NAME}/public."
echo ""
echo "After every git pull, run:"
echo "  bash $LARAVEL_DIR/scripts/deploy-live.sh"
echo ""
echo "Verify (set APP_URL in .env):"
echo "  /login"
echo "  /dist/manifest.json"
echo "  /manifest.json"
