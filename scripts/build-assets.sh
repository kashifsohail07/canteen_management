#!/usr/bin/env bash
# Build Vite assets LOCALLY (or on any machine that has Node/npm).
# Hostinger shared hosting usually has NO npm — commit public/dist after this.
#
# Usage (on your PC / WSL):
#   bash scripts/build-assets.sh
#   bash scripts/build-assets.sh --commit

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DO_COMMIT=0

for arg in "$@"; do
    case "$arg" in
        --commit|-c) DO_COMMIT=1 ;;
    esac
done

cd "$LARAVEL_DIR"

if ! command -v npm &>/dev/null; then
    echo "ERROR: npm not found. Install Node.js 20+ on this machine."
    exit 1
fi

echo "==> npm ci (or npm install)"
if [ -f package-lock.json ]; then
    npm ci
else
    npm install
fi
echo ""

echo "==> npm run build → public/dist"
npm run build
echo ""

if [ ! -f public/dist/manifest.json ]; then
    echo "ERROR: public/dist/manifest.json was not created."
    exit 1
fi

echo "✓ Assets ready in public/dist/"
ls -la public/dist/manifest.json public/dist/assets/ | head -20
echo ""

if [ "$DO_COMMIT" -eq 1 ]; then
    if [ ! -d .git ]; then
        echo "No .git — skip commit."
        exit 0
    fi
    git add public/dist
    if git diff --cached --quiet; then
        echo "No asset changes to commit."
    else
        git commit -m "$(cat <<'EOF'
Build frontend assets for Hostinger deploy (no npm on server).

EOF
)"
        echo "✓ Committed public/dist — push, then on server: bash scripts/deploy-live.sh"
    fi
else
    echo "Next:"
    echo "  git add public/dist && git commit -m 'build assets' && git push"
    echo "  # on Hostinger:"
    echo "  bash scripts/deploy-live.sh"
    echo ""
    echo "Or re-run: bash scripts/build-assets.sh --commit"
fi
