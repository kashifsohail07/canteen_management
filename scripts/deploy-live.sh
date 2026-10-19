#!/usr/bin/env bash
# Quick production deploy — run AFTER manual git pull.
# Hostinger has Composer + PHP, but usually NO npm — commit public/dist from local build.
#
# Usage (on server, from project root OR scripts/):
#   bash scripts/deploy-live.sh
#
# Optional env overrides:
#   LARAVEL_DIR=/path/to/canteen_management
#   PUBLIC_HTML=/path/to/public_html

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_DIR="${LARAVEL_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
PUBLIC_HTML="${PUBLIC_HTML:-${PUBLIC_HTML_PATH:-$(dirname "$LARAVEL_DIR")/public_html}}"

# shellcheck source=ensure-storage-links.sh
source "$LARAVEL_DIR/scripts/ensure-storage-links.sh"

clean_env_value() {
    printf '%s' "$1" | tr -d '\r' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'
}

read_env() {
    local key="$1"
    local default="${2:-}"

    if [[ -f "$LARAVEL_DIR/.env" ]]; then
        local value
        value="$(grep -E "^${key}=" "$LARAVEL_DIR/.env" | tail -n 1 | cut -d= -f2- || true)"
        value="$(clean_env_value "${value}")"
        value="${value%\"}"
        value="${value#\"}"
        value="${value%\'}"
        value="${value#\'}"

        if [[ -n "${value}" ]]; then
            printf '%s' "${value}"
            return
        fi
    fi

    printf '%s' "${default}"
}

cd "$LARAVEL_DIR"

APP_NAME="$(read_env APP_NAME 'Canteen Hisab')"
APP_URL="$(read_env APP_URL '')"
SITE_URL="${APP_URL:-https://your-domain.com}"
SITE_URL="${SITE_URL%/}"

echo "==> Live deploy: ${APP_NAME}"
echo "    Laravel:     $LARAVEL_DIR"
echo "    Public HTML: $PUBLIC_HTML"
echo ""

if [ ! -f .env ]; then
    echo "ERROR: .env missing in $LARAVEL_DIR"
    exit 1
fi

if [ ! -f public/dist/manifest.json ]; then
    echo "ERROR: public/dist/manifest.json missing."
    echo "Hostinger has no npm — build on your PC first:"
    echo "  npm ci && npm run build"
    echo "  git add public/dist && git commit -m 'build assets' && git push"
    echo "Then git pull on the server and re-run this script."
    exit 1
fi

if command -v composer &>/dev/null; then
    echo "==> composer install --no-dev --optimize-autoloader"
    composer install --no-dev --optimize-autoloader --no-interaction
    echo ""
elif [ -f composer.phar ]; then
    echo "==> php composer.phar install --no-dev --optimize-autoloader"
    php composer.phar install --no-dev --optimize-autoloader --no-interaction
    echo ""
else
    echo "Warning: composer not found — skipping install (vendor/ must already exist)"
    echo ""
fi

# Never run npm on Hostinger (usually unavailable)
if command -v npm &>/dev/null; then
    echo "Note: npm is available here, but deploy-live does not run it."
    echo "      Prefer building assets locally and committing public/dist."
    echo ""
fi

echo "==> php artisan migrate --force"
php artisan migrate --force
echo ""

echo "==> php artisan optimize:clear"
php artisan optimize:clear
echo ""

echo "==> php artisan config:cache"
php artisan config:cache
echo ""

echo "==> php artisan route:cache"
php artisan route:cache
echo ""

echo "==> php artisan view:cache"
php artisan view:cache
echo ""

mkdir -p "$PUBLIC_HTML"

echo "==> Storage links + folders"
ensure_storage_links "$LARAVEL_DIR" "$PUBLIC_HTML"
echo ""

# First deploy (or broken public_html): wire index.php + asset symlinks
needs_public_setup=0
for item in dist index.php; do
    if [ ! -e "$PUBLIC_HTML/$item" ] && [ ! -L "$PUBLIC_HTML/$item" ]; then
        needs_public_setup=1
        break
    fi
done

if [ "$needs_public_setup" -eq 1 ]; then
    echo "==> public_html incomplete — running setup-public-html.sh"
    bash "$LARAVEL_DIR/scripts/setup-public-html.sh"
    echo ""
fi

warn_missing_public_assets "$LARAVEL_DIR" "$PUBLIC_HTML"

echo ""
echo "Deploy complete!"
echo "  Site:    ${SITE_URL}"
echo "  Login:   ${SITE_URL}/login"
echo "  Assets:  ${SITE_URL}/dist/manifest.json"
echo "  PWA:     ${SITE_URL}/manifest.json"
