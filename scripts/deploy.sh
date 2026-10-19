#!/usr/bin/env bash
# Full production deploy (optional git pull + composer + live steps).
# Same idea as CMS-Dashboard scripts/deploy.sh — paths auto-detect.
#
# Usage:
#   bash scripts/deploy.sh
#   BRANCH=main bash scripts/deploy.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_DIR="${LARAVEL_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
PUBLIC_HTML="${PUBLIC_HTML:-${PUBLIC_HTML_PATH:-$(dirname "$LARAVEL_DIR")/public_html}}"
BRANCH="${BRANCH:-main}"

# shellcheck source=ensure-storage-links.sh
source "$LARAVEL_DIR/scripts/ensure-storage-links.sh"

cd "$LARAVEL_DIR"

echo "==> Deploying Canteen Hisab from $LARAVEL_DIR"
echo "    Public HTML: $PUBLIC_HTML"
echo ""

if [ -d ".git" ]; then
    echo "==> git pull origin $BRANCH"
    git pull origin "$BRANCH" || {
        echo ""
        echo "Git pull failed (local changes?). Fix conflicts, then re-run."
        exit 1
    }
    echo ""
else
    echo "Warning: .git not found — skipping git pull"
    echo ""
fi

export LARAVEL_DIR PUBLIC_HTML
exec bash "$LARAVEL_DIR/scripts/deploy-live.sh"
