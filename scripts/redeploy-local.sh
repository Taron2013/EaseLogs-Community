#!/usr/bin/env bash
#
# Redeploy the current EaseLogs working tree to the local intranet copy at
# /var/www/projects/easelogs (https://easelogs.local).
#
# LOCAL INTRANET ONLY — do not use on remote production servers.
#
# Database / upload consistency:
#   Preserve (1): keeps database/database.sqlite and storage/app/public on deploy.
#     Artwork photo rows must match files under storage/app/public/artworks/.
#   Reset (2): wipes the DB (migrate:fresh). Orphan files may remain on disk but
#     no artwork records reference them until you upload again.
#   Always: validates public/storage -> storage/app/public (fixes broken symlinks).
#
# Usage:
#   ./scripts/redeploy-local.sh           # interactive: choose preserve (1) or reset (2)
#   ./scripts/redeploy-local.sh --preserve   # keep database.sqlite and storage/app/public
#
set -euo pipefail

DEPLOY_PRESERVE=0
for arg in "$@"; do
  if [[ "$arg" == "--preserve" ]]; then
    DEPLOY_PRESERVE=1
  fi
done

readonly PROD="/var/www/projects/easelogs"
readonly DEPLOY_USER="${SUDO_USER:-${USER}}"
readonly DEPLOY_GROUP="http"
readonly DEPLOY_URL="https://easelogs.local"

# Application paths that must exist in source and on easelogs.local after sync.
readonly -a REQUIRED_DEPLOY_PATHS=(
  "scripts/redeploy-local.sh"
  "resources/views/artworks/index.blade.php"
  "resources/views/artworks/pagination.blade.php"
  "resources/views/vendor/pagination/easelogs.blade.php"
  "app/Http/Controllers/ArtworkController.php"
)

DB_CHOICE=""

step() {
  echo ""
  echo "==> $1"
}

die() {
  echo "ERROR: $1" >&2
  exit 1
}

print_banner() {
  echo ""
  echo "============================================================"
  echo " EaseLogs LOCAL intranet redeploy"
  echo " Target:  $PROD"
  echo " URL:     $DEPLOY_URL"
  echo "============================================================"
  echo " LOCAL easelogs.local testing only — not for remote production."
  echo ""
}

prompt_database_choice() {
  if [[ "$DEPLOY_PRESERVE" == "1" ]]; then
    DB_CHOICE="1"
    echo "Database handling: preserve (non-interactive --preserve)"
    echo "  Keeps database/database.sqlite and storage/app/public/"
    return 0
  fi

  echo "Database handling:"
  echo "  1) Preserve current local database data"
  echo "  2) Reset local database with migrate:fresh"
  echo ""

  while true; do
    read -r -p "Enter choice [1/2]: " DB_CHOICE
    case "$DB_CHOICE" in
      1|2) break ;;
      *) echo "Please enter 1 or 2." ;;
    esac
  done
}

confirm_database_reset() {
  echo ""
  echo "TODO: Trigger Community Edition CSV metadata export backup before DB reset once CSV export is available."
  echo ""

  read -r -p "Type RESET to destroy local database contents: " confirm

  if [[ "$confirm" != "RESET" ]]; then
    die "Database reset aborted (confirmation did not match RESET)."
  fi
}

reset_deploy_database() {
  step "Reset deploy database (SQLite)"
  rm -f "$PROD/database/database.sqlite"
  touch "$PROD/database/database.sqlite"
  echo "    Recreated $PROD/database/database.sqlite"
  echo "    Uploaded files under storage/app/public/ were left in place (orphans are harmless)."
}

verify_tree_paths() {
  local root="$1"
  local label="$2"
  local missing=()

  for path in "${REQUIRED_DEPLOY_PATHS[@]}"; do
    if [[ ! -e "$root/$path" ]]; then
      missing+=("$path")
    fi
  done

  if ((${#missing[@]} > 0)); then
    echo "    Missing under $root:" >&2
    printf '      - %s\n' "${missing[@]}" >&2
    die "$label is incomplete; refusing to continue."
  fi

  echo "    $label: required application paths present (${#REQUIRED_DEPLOY_PATHS[@]} checked)"
}

sync_deploy_scripts() {
  step "Sync scripts/ (redeploy + local helpers)"
  mkdir -p "$PROD/scripts"
  rsync -av \
    --include='*.sh' \
    --include='README*' \
    --exclude='*' \
    "$DEV/scripts/" "$PROD/scripts/"
  chmod +x "$PROD/scripts/"*.sh 2>/dev/null || true

  echo "    Scripts on deploy target:"
  for script in "$PROD/scripts/"*.sh; do
    [[ -f "$script" ]] || continue
    echo "      - $script"
  done
}

ensure_public_storage_symlink() {
  local expected current

  expected="$(readlink -f "$PROD/storage/app/public")"

  if [[ -L "$PROD/public/storage" ]]; then
    current="$(readlink -f "$PROD/public/storage" 2>/dev/null || true)"
    if [[ -n "$current" && "$current" == "$expected" ]]; then
      echo "    public/storage symlink OK -> $current"
      return 0
    fi
    echo "    Removing incorrect public/storage symlink (was: ${current:-unresolvable})"
    rm -f "$PROD/public/storage"
  elif [[ -e "$PROD/public/storage" ]]; then
    echo "    Removing non-symlink public/storage entry"
    rm -rf "$PROD/public/storage"
  fi

  php artisan storage:link
  current="$(readlink -f "$PROD/public/storage" 2>/dev/null || true)"
  echo "    public/storage symlink -> ${current:-storage/app/public}"
}

run_database_migrations() {
  step "Run Laravel deployment commands"
  (
    cd "$PROD"

    if [[ "$DB_CHOICE" == "1" ]]; then
      echo "    Database mode: preserve (migrate --force)"
      echo "    Uploads: preserving storage/app/public on deploy (must match SQLite photo paths)"
      php artisan migrate --force
    else
      echo "    Database mode: reset (migrate:fresh --force)"
      echo "    Uploads: files may remain on disk; fresh DB has no artwork photo records"
      php artisan migrate:fresh --force
    fi

    ensure_public_storage_symlink

    php artisan optimize:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
  )
}

if [[ ! -f artisan ]]; then
  die "Run this script from the EaseLogs project root (where artisan lives)."
fi

DEV="$(pwd -P)"

if [[ "$DEV" == "$(readlink -f "$PROD" 2>/dev/null || true)" ]]; then
  die "Source and deploy paths are the same. Refusing to sync."
fi

if [[ "$PROD" != "/var/www/projects/easelogs" ]]; then
  die "Unexpected deploy path."
fi

if [[ ! -d "$PROD" ]]; then
  die "Deploy directory missing: $PROD"
fi

if [[ ! -f "$PROD/.env" ]]; then
  die "Deploy .env missing at $PROD/.env — create it before redeploying."
fi

print_banner
prompt_database_choice

if [[ "$DB_CHOICE" == "2" ]]; then
  confirm_database_reset
fi

step "Redeploy EaseLogs to local intranet"
echo "    Source:  $DEV"
echo "    Target:  $PROD"
echo "    Always preserved: .env, storage/app/public uploaded artwork files"
if [[ "$DB_CHOICE" == "1" ]]; then
  echo "    Database: preserve existing database/database.sqlite"
else
  echo "    Database: reset with migrate:fresh after sync"
fi

verify_tree_paths "$DEV" "Source tree"

step "Prepare ownership for sync (requires sudo)"
sudo chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "$PROD"

step "Sync application code (rsync)"
echo "    Synced: app/, resources/views/, routes/, config/, database/migrations/, public/build assets source, etc."
echo "    Preserved on target: .env, database/database.sqlite, storage/app/public uploads"
echo "    Excluded from overwrite: Composer vendor/ (root only), node_modules/, compiled views/cache/sessions"
rsync -av --delete \
  --exclude=".git" \
  --exclude="node_modules" \
  --exclude="/vendor/" \
  --exclude=".env" \
  --exclude="database/database.sqlite" \
  --exclude="public/storage" \
  --exclude="public/hot" \
  --exclude="storage/logs/" \
  --exclude="storage/framework/cache/" \
  --exclude="storage/framework/sessions/" \
  --exclude="storage/framework/views/" \
  --exclude="storage/app/public/" \
  "$DEV/" "$PROD/"

sync_deploy_scripts
verify_tree_paths "$PROD" "Deploy tree"

step "Install PHP dependencies"
(
  cd "$PROD"
  composer install --no-interaction --prefer-dist
)

step "Install Node dependencies and build assets"
(
  cd "$PROD"
  npm install
  npm run build
)

if [[ "$DB_CHOICE" == "2" ]]; then
  reset_deploy_database
fi

run_database_migrations

step "Fix permissions for nginx/php-fpm (requires sudo)"
sudo chown -R "${DEPLOY_USER}:${DEPLOY_GROUP}" "$PROD/storage" "$PROD/bootstrap/cache" "$PROD/database"
sudo find "$PROD/storage" "$PROD/bootstrap/cache" -type d -exec chmod 775 {} \;
sudo find "$PROD/storage" "$PROD/bootstrap/cache" -type f -exec chmod 664 {} \;
sudo chmod 775 "$PROD/database"
if [[ -f "$PROD/database/database.sqlite" ]]; then
  sudo chmod 664 "$PROD/database/database.sqlite"
fi

step "Redeploy complete"
echo "    Open: $DEPLOY_URL/artworks"
echo "    Redeploy again from: $PROD/scripts/redeploy-local.sh"
if [[ -x "$PROD/scripts/community-edition.sh" ]]; then
  echo "    Local dev helper: $PROD/scripts/community-edition.sh (setup|check|test|serve)"
fi
if [[ "$DB_CHOICE" == "1" ]]; then
  echo "    Deploy .env, SQLite data, and storage/app/public uploads were preserved."
  echo "    public/storage symlink was verified."
else
  echo "    Deploy .env and storage/app/public were preserved; database was reset."
  echo "    public/storage symlink was verified."
fi
