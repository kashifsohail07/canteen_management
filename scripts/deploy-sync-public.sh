#!/usr/bin/env bash
# Backward-compatible alias — public_html setup (symlinks + index.php).
# Prefer: bash scripts/setup-public-html.sh
#
# Usage:
#   bash scripts/deploy-sync-public.sh
#   PUBLIC_HTML=/path/to/public_html bash scripts/deploy-sync-public.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_DIR="${LARAVEL_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"

if [[ $# -gt 0 ]] && [[ "$1" != -* ]]; then
    export PUBLIC_HTML="$1"
    shift
fi

exec bash "$LARAVEL_DIR/scripts/setup-public-html.sh" "$@"
