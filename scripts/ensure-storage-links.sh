#!/usr/bin/env bash
# Ensure upload directories and public storage symlinks.
# Sourced by deploy.sh and deploy-live.sh — do not run directly unless needed.

ensure_storage_links() {
    local laravel_dir="${1:?Laravel directory required}"
    local public_html="${2:-}"
    local project_name
    project_name="$(basename "$laravel_dir")"

    local storage_public="$laravel_dir/storage/app/public"

    mkdir -p \
        "$storage_public" \
        "$laravel_dir/storage/app/private/invoices" \
        "$laravel_dir/storage/framework/cache" \
        "$laravel_dir/storage/framework/sessions" \
        "$laravel_dir/storage/framework/views" \
        "$laravel_dir/storage/logs" \
        "$laravel_dir/bootstrap/cache"

    chmod -R ug+rwx "$laravel_dir/storage" "$laravel_dir/bootstrap/cache" 2>/dev/null || true

    # Hostinger often blocks PHP symlink() — use shell ln instead of artisan storage:link
    if [ -L "$laravel_dir/public/storage" ]; then
        echo "✓ public/storage symlink OK"
    elif [ -e "$laravel_dir/public/storage" ]; then
        echo "Warning: public/storage exists but is not a symlink — check manually"
    else
        ln -sfn "../storage/app/public" "$laravel_dir/public/storage"
        echo "✓ Created public/storage symlink"
    fi

    if [ -n "$public_html" ] && [ -d "$public_html" ]; then
        rm -rf "$public_html/storage"
        ln -sfn "../${project_name}/storage/app/public" "$public_html/storage"
        echo "✓ Linked public_html/storage"
    fi
}

warn_missing_public_assets() {
    local laravel_dir="${1:?}"
    local public_html="${2:-}"

    [ -d "$public_html" ] || return 0

    local missing=0

    for item in dist index.php; do
        if [ ! -e "$public_html/$item" ] && [ ! -L "$public_html/$item" ]; then
            echo "Warning: $public_html/$item is missing."
            missing=1
        fi
    done

    if [ ! -f "$laravel_dir/public/dist/manifest.json" ]; then
        echo "Warning: public/dist/manifest.json missing — CSS/JS will 404."
        echo "         Build locally (npm run build), commit public/dist, then git pull."
        missing=1
    fi

    if [ ! -L "$public_html/storage" ] && [ ! -d "$public_html/storage" ]; then
        echo "Warning: $public_html/storage is missing — public uploads will 404."
        missing=1
    fi

    if [ "$missing" -eq 1 ]; then
        echo ""
        echo "Fix once: bash $laravel_dir/scripts/setup-public-html.sh"
    fi
}
