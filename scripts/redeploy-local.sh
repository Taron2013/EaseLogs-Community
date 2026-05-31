#!/usr/bin/env bash
#
# Redeploy the current EaseLogs working tree to the local intranet copy at
# /var/www/projects/easelogs (https://easelogs.local).
#
# LOCAL INTRANET ONLY — do not use on remote production servers.
# This script will evolve over time.
#
set -euo pipefail

readonly PROD="/var/www/projects/easelogs"
readonly DEPLOY_USER="${SUDO_USER:-${USER:-artistdoug}}"
readonly DEPLOY_GROUP="http"
readonly DEPLOY_URL="https://easelogs.local"

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
}

run_database_migrations() {
  step "Run Laravel deployment commands"
  (
    cd "$PROD"

    if [[ "$DB_CHOICE" == "1" ]]; then
      echo "    Database mode: preserve (migrate --force)"
      php artisan migrate --force
    else
      echo "    Database mode: reset (migrate:fresh --force)"
      php artisan migrate:fresh --force
    fi

    if [[ ! -L public/storage ]]; then
      php artisan storage:link
    else
      echo "    public/storage symlink already exists"
    fi

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

step "Prepare ownership for sync (requires sudo)"
sudo chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "$PROD"

step "Sync code (rsync)"
rsync -av --delete \
  --exclude=".git" \
  --exclude="node_modules" \
  --exclude="vendor" \
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
if [[ "$DB_CHOICE" == "1" ]]; then
  echo "    Deploy .env, SQLite data, and uploaded photos were preserved."
else
  echo "    Deploy .env and uploaded photos were preserved; database was reset."
fi
