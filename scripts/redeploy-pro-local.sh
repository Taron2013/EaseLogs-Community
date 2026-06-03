#!/usr/bin/env bash
#
# Redeploy the current EaseLogs working tree to the LOCAL PRO intranet copy at
# /var/www/projects/easelog-pro (https://easelogs.pro).
#
# LOCAL INTRANET ONLY — separate from Community at /var/www/projects/easelogs.
# This script NEVER modifies the Community deployment.
#
# Modes:
#   First-time setup — creates Pro tree; optional one-time copy of Community DB + uploads.
#   Preserve (1)     — keeps Pro database.sqlite and Pro storage/app/public.
#   Reset (2)        — resets Pro database only; Community is never touched.
#
# Usage:
#   ./scripts/redeploy-pro-local.sh           # interactive
#   ./scripts/redeploy-pro-local.sh --preserve   # keep Pro DB and storage/app/public
#
set -euo pipefail

DEPLOY_PRESERVE=0
for arg in "$@"; do
  if [[ "$arg" == "--preserve" ]]; then
    DEPLOY_PRESERVE=1
  fi
done

readonly PROD="/var/www/projects/easelog-pro"
readonly COMMUNITY_PROD="/var/www/projects/easelogs"
readonly DEPLOY_USER="${SUDO_USER:-${USER}}"
readonly DEPLOY_GROUP="http"
readonly DEPLOY_URL="https://easelogs.pro"
readonly COMMUNITY_URL="https://easelogs.local"

readonly -a REQUIRED_DEPLOY_PATHS=(
  "scripts/redeploy-pro-local.sh"
  "resources/views/artworks/index.blade.php"
  "resources/views/artworks/pagination.blade.php"
  "app/Http/Controllers/ArtworkController.php"
)

DB_CHOICE=""
FIRST_TIME_SETUP="0"
SEED_FROM_COMMUNITY="0"

step() {
  echo ""
  echo "==> $1"
}

die() {
  echo "ERROR: $1" >&2
  exit 1
}

warn() {
  echo "WARNING: $1" >&2
}

canonical_path() {
  local path="$1"
  readlink -f "$path" 2>/dev/null || true
}

assert_non_empty_path() {
  local label="$1"
  local path="$2"

  if [[ -z "${path// }" ]]; then
    die "Empty $label path."
  fi
}

is_community_deploy_path() {
  local path="$1"
  local canonical community_canonical

  canonical="$(canonical_path "$path")"
  community_canonical="$(canonical_path "$COMMUNITY_PROD")"

  [[ -n "$canonical" && -n "$community_canonical" && "$canonical" == "$community_canonical" ]]
}

is_pro_deploy_path() {
  local path="$1"
  local canonical pro_canonical

  canonical="$(canonical_path "$path")"
  pro_canonical="$(canonical_path "$PROD")"

  [[ -n "$canonical" && -n "$pro_canonical" && "$canonical" == "$pro_canonical" ]]
}

# Use for any path this script writes to (rsync target, chmod, migrate, rm, .env, etc.).
assert_pro_write_target() {
  local path="$1"
  local label="$2"

  assert_non_empty_path "$label" "$path"

  if is_community_deploy_path "$path"; then
    die "Refusing to write to Community deployment ($COMMUNITY_PROD). $label was: $path"
  fi

  if ! is_pro_deploy_path "$path"; then
    die "Pro write target must resolve to $PROD. $label was: $path (resolved: $(canonical_path "$path"))"
  fi
}

# Use before read-only copy FROM Community during one-time seed (cp/rsync source only).
assert_community_seed_source() {
  local path="$1"
  local label="$2"

  assert_non_empty_path "$label" "$path"

  if ! is_community_deploy_path "$path"; then
    die "Community seed source must resolve to $COMMUNITY_PROD. $label was: $path (resolved: $(canonical_path "$path"))"
  fi
}

print_banner() {
  echo ""
  echo "============================================================"
  echo " EaseLogs PRO — LOCAL intranet redeploy"
  echo " Target:  $PROD"
  echo " URL:     $DEPLOY_URL"
  echo "============================================================"
  echo " PRO ONLY — Community ($COMMUNITY_URL) is NOT modified."
  echo " Community data path (read-only seed source): $COMMUNITY_PROD"
  echo ""
}

prompt_deploy_mode() {
  if [[ "$DEPLOY_PRESERVE" == "1" ]]; then
    if [[ ! -f "$PROD/.env" ]]; then
      die "Pro is not initialized yet. Run without --preserve for first-time setup."
    fi
    FIRST_TIME_SETUP="0"
    DB_CHOICE="1"
    echo "Pro deployment detected at $PROD"
    echo "Database handling: preserve (non-interactive --preserve)"
    echo "  Keeps Pro database/database.sqlite and storage/app/public/"
    echo "  Community at $COMMUNITY_PROD is not modified."
    return 0
  fi

  if [[ -f "$PROD/.env" ]]; then
    FIRST_TIME_SETUP="0"
    echo "Pro deployment detected at $PROD"
    echo ""
    echo "Database handling for PRO ($PROD only):"
    echo "  1) Preserve Pro database and storage"
    echo "  2) Reset Pro database (migrate:fresh) — Community unchanged"
    echo ""

    while true; do
      read -r -p "Enter choice [1/2]: " DB_CHOICE
      case "$DB_CHOICE" in
        1|2) break ;;
        *) echo "Please enter 1 or 2." ;;
      esac
    done

    return 0
  fi

  FIRST_TIME_SETUP="1"
  echo "No Pro .env found — running FIRST-TIME Pro setup."
  echo ""
  echo "Optional: copy Community data into Pro (one-time):"
  echo "  - $COMMUNITY_PROD/database/database.sqlite"
  echo "  - $COMMUNITY_PROD/storage/app/public/"
  echo ""
  echo "After this, Pro and Community stay independent."
  echo ""

  while true; do
    read -r -p "Seed Pro from Community data? [y/N]: " seed_answer
    case "${seed_answer,,}" in
      y|yes) SEED_FROM_COMMUNITY="1"; break ;;
      n|no|"") SEED_FROM_COMMUNITY="0"; break ;;
      *) echo "Please enter y or n." ;;
    esac
  done

  DB_CHOICE="1"
}

confirm_pro_database_reset() {
  echo ""
  warn "This will DESTROY the Pro SQLite database at:"
  echo "         $PROD/database/database.sqlite"
  echo ""
  echo "Community at $COMMUNITY_PROD will NOT be changed."
  echo ""

  read -r -p "Type RESET PRO to continue: " confirm

  if [[ "$confirm" != "RESET PRO" ]]; then
    die "Pro database reset aborted (confirmation did not match RESET PRO)."
  fi
}

reset_pro_database() {
  step "Reset Pro database (SQLite only)"
  assert_pro_write_target "$PROD" "database reset"
  rm -f "$PROD/database/database.sqlite"
  touch "$PROD/database/database.sqlite"
  echo "    Recreated $PROD/database/database.sqlite"
  echo "    Pro storage/app/public/ was left in place (orphan files are harmless)."
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
  step "Sync scripts/ to Pro deploy tree"
  mkdir -p "$PROD/scripts"
  rsync -av \
    --include='*.sh' \
    --include='README*' \
    --exclude='*' \
    "$DEV/scripts/" "$PROD/scripts/"
  chmod +x "$PROD/scripts/"*.sh 2>/dev/null || true
}

ensure_pro_directory_layout() {
  step "Ensure Pro directory layout"
  mkdir -p "$PROD/database" "$PROD/storage/app/public" "$PROD/bootstrap/cache" "$PROD/storage/framework/"{cache,sessions,views} "$PROD/storage/logs"
  echo "    Directories ready under $PROD"
}

copy_community_seed_data() {
  step "One-time seed: copy Community database and uploads into Pro"

  assert_community_seed_source "$COMMUNITY_PROD" "seed source"
  assert_pro_write_target "$PROD" "seed destination"

  if [[ ! -f "$COMMUNITY_PROD/database/database.sqlite" ]]; then
    die "Community database missing at $COMMUNITY_PROD/database/database.sqlite"
  fi

  if [[ ! -d "$COMMUNITY_PROD/storage/app/public" ]]; then
    die "Community storage missing at $COMMUNITY_PROD/storage/app/public"
  fi

  echo "    Source (read-only): $COMMUNITY_PROD"
  echo "    Target (Pro only):  $PROD"

  cp -a "$COMMUNITY_PROD/database/database.sqlite" "$PROD/database/database.sqlite"
  rsync -a "$COMMUNITY_PROD/storage/app/public/" "$PROD/storage/app/public/"
  echo "    Copied database.sqlite and storage/app/public/"
}

write_pro_env_from_community() {
  assert_community_seed_source "$COMMUNITY_PROD" "seed source"
  assert_pro_write_target "$PROD" "seed destination"

  cp "$COMMUNITY_PROD/.env" "$PROD/.env"
  sed -i \
    -e 's|^APP_URL=.*|APP_URL=https://easelogs.pro|' \
    -e 's|^APP_NAME=.*|APP_NAME="EaseLogs Pro"|' \
    -e 's|^EASELOGS_EDITION=.*|EASELOGS_EDITION=Pro|' \
    "$PROD/.env" 2>/dev/null || true

  if ! grep -q '^EASELOGS_EDITION=' "$PROD/.env" 2>/dev/null; then
    echo 'EASELOGS_EDITION=Pro' >> "$PROD/.env"
  fi

  echo "    Pro .env created from Community template (APP_URL -> https://easelogs.pro)"
}

write_pro_env_fresh() {
  assert_pro_write_target "$PROD" "seed destination"

  if [[ -f "$DEV/.env.example" ]]; then
    cp "$DEV/.env.example" "$PROD/.env"
  elif [[ -f "$DEV/.env" ]]; then
    cp "$DEV/.env" "$PROD/.env"
  else
    die "No .env.example or .env in source tree to bootstrap Pro .env"
  fi

  sed -i \
    -e 's|^APP_URL=.*|APP_URL=https://easelogs.pro|' \
    -e 's|^APP_NAME=.*|APP_NAME="EaseLogs Pro"|' \
    -e 's|^APP_ENV=.*|APP_ENV=local|' \
    -e 's|^APP_DEBUG=.*|APP_DEBUG=true|' \
    "$PROD/.env" 2>/dev/null || true

  if ! grep -q '^EASELOGS_EDITION=' "$PROD/.env" 2>/dev/null; then
    echo 'EASELOGS_EDITION=Pro' >> "$PROD/.env"
  fi

  echo "    Pro .env created from dev template"
}

ensure_pro_app_key() {
  (
    cd "$PROD"
    if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
      echo "    Generating APP_KEY for Pro .env"
      php artisan key:generate --force
    else
      echo "    APP_KEY already set in Pro .env"
    fi
  )
}

ensure_public_storage_symlink() {
  local expected current

  (
    cd "$PROD"
    expected="$(readlink -f "$PROD/storage/app/public")"

    if [[ -L public/storage ]]; then
      current="$(readlink -f public/storage 2>/dev/null || true)"
      if [[ -n "$current" && "$current" == "$expected" ]]; then
        echo "    public/storage symlink OK -> $current"
        return 0
      fi
      echo "    Removing incorrect public/storage symlink (was: ${current:-unresolvable})"
      rm -f public/storage
    elif [[ -e public/storage ]]; then
      echo "    Removing non-symlink public/storage entry"
      rm -rf public/storage
    fi

    php artisan storage:link
    current="$(readlink -f public/storage 2>/dev/null || true)"
    echo "    public/storage symlink -> ${current:-storage/app/public}"
  )
}

run_laravel_deploy_commands() {
  step "Run Laravel commands on Pro deploy tree"
  (
    cd "$PROD"

    if [[ "$FIRST_TIME_SETUP" == "1" && "$SEED_FROM_COMMUNITY" == "1" ]]; then
      echo "    Database mode: seeded from Community (migrate --force)"
      php artisan migrate --force
    elif [[ "$DB_CHOICE" == "1" ]]; then
      echo "    Database mode: preserve Pro data (migrate --force)"
      php artisan migrate --force
    else
      echo "    Database mode: reset Pro (migrate:fresh --force)"
      php artisan migrate:fresh --force
    fi

    ensure_public_storage_symlink

    php artisan optimize:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
  )
}

fix_pro_permissions() {
  step "Fix permissions for nginx/php-fpm on Pro tree (requires sudo)"
  assert_pro_write_target "$PROD" "permission fix"
  sudo chown -R "${DEPLOY_USER}:${DEPLOY_GROUP}" "$PROD/storage" "$PROD/bootstrap/cache" "$PROD/database"
  sudo find "$PROD/storage" "$PROD/bootstrap/cache" -type d -exec chmod 775 {} \;
  sudo find "$PROD/storage" "$PROD/bootstrap/cache" -type f -exec chmod 664 {} \;
  sudo chmod 775 "$PROD/database"
  if [[ -f "$PROD/database/database.sqlite" ]]; then
    sudo chmod 664 "$PROD/database/database.sqlite"
  fi
}

rsync_application_code() {
  step "Sync application code to Pro (rsync)"
  echo "    Source:  $DEV"
  echo "    Target:  $PROD"
  echo "    Preserved on Pro: .env, database/database.sqlite, storage/app/public/"
  echo "    Community path is never written: $COMMUNITY_PROD"

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
}

bootstrap_first_time_pro() {
  step "First-time Pro setup"
  ensure_pro_directory_layout

  if [[ "$SEED_FROM_COMMUNITY" == "1" ]]; then
    if [[ ! -f "$COMMUNITY_PROD/.env" ]]; then
      die "Community .env missing — deploy Community first or choose fresh Pro setup."
    fi
    write_pro_env_from_community
    copy_community_seed_data
  else
    write_pro_env_fresh
    touch "$PROD/database/database.sqlite"
    echo "    Created empty Pro database/database.sqlite"
  fi

  ensure_pro_app_key
}

if [[ ! -f artisan ]]; then
  die "Run this script from the EaseLogs project root (where artisan lives)."
fi

DEV="$(pwd -P)"

assert_non_empty_path "source" "$DEV"
assert_pro_write_target "$PROD" "deploy target"

if [[ "$DEV" == "$(canonical_path "$PROD")" ]]; then
  die "Source and Pro deploy paths are the same. Refusing to sync."
fi

if [[ "$DEV" == "$(canonical_path "$COMMUNITY_PROD")" ]]; then
  warn "You are running from the Community deploy tree. Use ~/EaseLogs (dev repo) as source instead."
fi

print_banner
prompt_deploy_mode

if [[ "$DB_CHOICE" == "2" ]]; then
  confirm_pro_database_reset
fi

if [[ "$FIRST_TIME_SETUP" == "1" ]]; then
  step "Prepare Pro deploy root (requires sudo)"
  sudo mkdir -p "$PROD"
  sudo chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "$PROD"
fi

if [[ ! -d "$PROD" ]]; then
  die "Pro deploy directory missing: $PROD"
fi

verify_tree_paths "$DEV" "Source tree"

step "Prepare ownership for Pro sync (requires sudo)"
sudo chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "$PROD"

if [[ "$FIRST_TIME_SETUP" == "1" ]]; then
  rsync_application_code
  bootstrap_first_time_pro
else
  if [[ ! -f "$PROD/.env" ]]; then
    die "Pro .env missing at $PROD/.env — run first-time setup or create .env manually."
  fi
  rsync_application_code
fi

sync_deploy_scripts
verify_tree_paths "$PROD" "Pro deploy tree"

step "Install PHP dependencies (Pro)"
(
  cd "$PROD"
  composer install --no-interaction --prefer-dist
)

step "Install Node dependencies and build assets (Pro)"
(
  cd "$PROD"
  npm install
  npm run build
)

if [[ "$DB_CHOICE" == "2" ]]; then
  reset_pro_database
fi

run_laravel_deploy_commands
fix_pro_permissions

step "Pro redeploy complete"
echo "    Open Pro:        $DEPLOY_URL/artworks"
echo "    Open Community:  $COMMUNITY_URL/artworks (unchanged by this script)"
echo "    Redeploy Pro:    $PROD/scripts/redeploy-pro-local.sh"
echo ""
echo "    Verify:"
echo "      curl -k -I $DEPLOY_URL"
echo "      curl -k -I $COMMUNITY_URL"
if [[ "$FIRST_TIME_SETUP" == "1" ]]; then
  if [[ "$SEED_FROM_COMMUNITY" == "1" ]]; then
    echo "    First-time: Pro was seeded from Community; future deploys are independent."
  else
    echo "    First-time: fresh Pro DB — complete /setup on $DEPLOY_URL if needed."
  fi
elif [[ "$DB_CHOICE" == "1" ]]; then
  echo "    Pro .env, SQLite, and storage/app/public were preserved."
else
  echo "    Pro database was reset; Pro .env and storage/app/public were preserved."
fi
