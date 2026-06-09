#!/usr/bin/env bash
#
# Shared post-deploy validation helpers for EaseLogs redeploy scripts.
# Source from redeploy-local.sh / redeploy-pro-local.sh (do not execute directly).
#
# Expects callers to define: step(), die(), warn()
# Sets report globals: REPORT_DB_READ, REPORT_DB_WRITE, REPORT_STORAGE_SYMLINK,
# REPORT_HTTP_HEALTH, REPORT_OVERALL

# shellcheck disable=SC2034
REPORT_DB_READ="FAIL"
REPORT_DB_WRITE="FAIL"
REPORT_STORAGE_SYMLINK="FAIL"
REPORT_HTTP_HEALTH="FAIL"
REPORT_OVERALL="FAIL"

redeploy_validation_reset_report() {
  REPORT_DB_READ="FAIL"
  REPORT_DB_WRITE="FAIL"
  REPORT_STORAGE_SYMLINK="FAIL"
  REPORT_HTTP_HEALTH="FAIL"
  REPORT_OVERALL="FAIL"
}

redeploy_detect_web_server_group() {
  local deploy_root="$1"
  local -a candidates=(http www-data nginx apache)
  local detected="" candidate existing

  if [[ -d "$deploy_root/storage" ]]; then
    existing="$(stat -c '%G' "$deploy_root/storage" 2>/dev/null || true)"
    for candidate in "${candidates[@]}"; do
      if [[ "$existing" == "$candidate" ]]; then
        echo "$candidate"
        return 0
      fi
    done
  fi

  if command -v ps >/dev/null 2>&1; then
    detected="$(ps -eo group=,comm= 2>/dev/null | awk '/php-fpm/ {print $1; exit}' | tr -d ' ' || true)"
    for candidate in "${candidates[@]}"; do
      if [[ "$detected" == "$candidate" ]]; then
        echo "$detected"
        return 0
      fi
    done
  fi

  for candidate in "${candidates[@]}"; do
    if getent group "$candidate" >/dev/null 2>&1; then
      echo "$candidate"
      return 0
    fi
  done

  warn "Could not detect web-server group; falling back to http"
  echo "http"
}

redeploy_ensure_public_storage_symlink() {
  local deploy_root="$1"
  local expected current

  expected="$(readlink -f "$deploy_root/storage/app/public" 2>/dev/null || true)"

  if [[ -z "$expected" || ! -d "$expected" ]]; then
    die "storage/app/public missing under $deploy_root"
  fi

  if [[ -L "$deploy_root/public/storage" ]]; then
    current="$(readlink -f "$deploy_root/public/storage" 2>/dev/null || true)"
    if [[ -n "$current" && "$current" == "$expected" ]]; then
      echo "    public/storage symlink OK -> $current"
      return 0
    fi
    echo "    Removing incorrect public/storage symlink (was: ${current:-unresolvable})"
    rm -f "$deploy_root/public/storage"
  elif [[ -e "$deploy_root/public/storage" ]]; then
    echo "    Removing non-symlink public/storage entry"
    rm -rf "$deploy_root/public/storage"
  fi

  (
    cd "$deploy_root"
    php artisan storage:link
  )
  current="$(readlink -f "$deploy_root/public/storage" 2>/dev/null || true)"
  echo "    public/storage symlink -> ${current:-storage/app/public}"
}

redeploy_verify_storage_symlink() {
  local deploy_root="$1"
  local expected current

  expected="$(readlink -f "$deploy_root/storage/app/public" 2>/dev/null || true)"
  current="$(readlink -f "$deploy_root/public/storage" 2>/dev/null || true)"

  if [[ -n "$expected" && -n "$current" && "$current" == "$expected" && -L "$deploy_root/public/storage" ]]; then
    REPORT_STORAGE_SYMLINK="PASS"
    echo "    Storage symlink validation: PASS"
    return 0
  fi

  REPORT_STORAGE_SYMLINK="FAIL"
  echo "    Storage symlink validation: FAIL (expected $expected, got ${current:-missing})" >&2
  return 1
}

redeploy_verify_required_paths() {
  local deploy_root="$1"
  local missing=()
  local path

  for path in \
    storage \
    storage/app/public \
    public/storage \
    bootstrap/cache \
    database \
    database/database.sqlite; do
    if [[ ! -e "$deploy_root/$path" ]]; then
      missing+=("$path")
    fi
  done

  if ((${#missing[@]} > 0)); then
    echo "    Missing required paths under $deploy_root:" >&2
    printf '      - %s\n' "${missing[@]}" >&2
    return 1
  fi

  echo "    Required deploy paths present (storage, database, bootstrap/cache, public/storage)"
  return 0
}

redeploy_verify_filesystem_permissions() {
  local deploy_root="$1"
  local failed=0

  if [[ ! -w "$deploy_root/database" ]]; then
    echo "    FAIL: database/ directory is not writable" >&2
    failed=1
  fi

  if [[ ! -w "$deploy_root/database/database.sqlite" ]]; then
    echo "    FAIL: database/database.sqlite is not writable" >&2
    failed=1
  fi

  if [[ ! -w "$deploy_root/storage" ]]; then
    echo "    FAIL: storage/ is not writable" >&2
    failed=1
  fi

  if [[ ! -w "$deploy_root/bootstrap/cache" ]]; then
    echo "    FAIL: bootstrap/cache/ is not writable" >&2
    failed=1
  fi

  if [[ "$failed" -eq 0 ]]; then
    echo "    Filesystem write permissions: PASS (database/, database.sqlite, storage/, bootstrap/cache/)"
    return 0
  fi

  return 1
}

redeploy_verify_laravel_database_rw() {
  local deploy_root="$1"
  local output

  if ! output="$(
    cd "$deploy_root" && php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
try {
    DB::select('SELECT 1 AS deploy_read_ok');
    \$marker = 'deploy_write_'.uniqid('', true);
    DB::statement('CREATE TEMP TABLE _deploy_write_check (marker TEXT NOT NULL)');
    DB::insert('INSERT INTO _deploy_write_check (marker) VALUES (?)', [\$marker]);
    \$readBack = DB::scalar('SELECT marker FROM _deploy_write_check WHERE marker = ?', [\$marker]);
    if (\$readBack !== \$marker) {
        throw new RuntimeException('write verification mismatch');
    }
    DB::statement('DROP TABLE _deploy_write_check');
    echo 'PASS';
} catch (Throwable \$e) {
    echo 'FAIL:'.\$e->getMessage();
    exit(1);
}
" 2>&1)"; then
    echo "    Database Laravel read/write test: FAIL" >&2
    echo "    $output" >&2
    REPORT_DB_READ="FAIL"
    REPORT_DB_WRITE="FAIL"
    return 1
  fi

  if [[ "$output" == *PASS* ]]; then
    REPORT_DB_READ="PASS"
    REPORT_DB_WRITE="PASS"
    echo "    Database Laravel read test: PASS"
    echo "    Database Laravel write test: PASS (temp table rolled back with connection)"
    return 0
  fi

  echo "    Database Laravel read/write test: FAIL ($output)" >&2
  REPORT_DB_READ="FAIL"
  REPORT_DB_WRITE="FAIL"
  return 1
}

redeploy_verify_laravel_runtime() {
  local deploy_root="$1"

  (
    cd "$deploy_root"
    php artisan optimize:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
  )

  echo "    Laravel runtime cache cleared (optimize/config/route/view)"
}

redeploy_verify_laravel_health() {
  local deploy_root="$1"

  (
    cd "$deploy_root"
    php artisan about --no-ansi >/dev/null
    php artisan route:list --no-ansi >/dev/null
    php artisan migrate:status --no-ansi >/dev/null
  )

  echo "    Laravel health: PASS (about, route:list, migrate:status)"
}

redeploy_verify_http_endpoints() {
  local failed=0
  local url status

  if ! command -v curl >/dev/null 2>&1; then
    warn "curl not installed — skipping HTTP health checks"
    REPORT_HTTP_HEALTH="FAIL"
    return 1
  fi

  for url in "$@"; do
    status="$(curl -k -s -o /dev/null -w '%{http_code}' --connect-timeout 10 --max-time 30 "$url" 2>/dev/null || echo "000")"
    case "$status" in
      200|302)
        echo "    HTTP $url -> $status"
        ;;
      500)
        echo "    HTTP $url -> 500 (application error)" >&2
        failed=1
        ;;
      000)
        echo "    HTTP $url -> connection failure" >&2
        failed=1
        ;;
      *)
        echo "    HTTP $url -> $status (expected 200 or 302)" >&2
        failed=1
        ;;
    esac
  done

  if [[ "$failed" -eq 0 ]]; then
    REPORT_HTTP_HEALTH="PASS"
    return 0
  fi

  REPORT_HTTP_HEALTH="FAIL"
  return 1
}

redeploy_fix_permissions() {
  local deploy_root="$1"
  local deploy_user="$2"
  local deploy_group="$3"

  deploy_group="$(redeploy_detect_web_server_group "$deploy_root")"
  echo "    Web-server group: $deploy_group"

  chmod 755 "$deploy_root" "$deploy_root/public" 2>/dev/null || true

  if sudo chown -R "${deploy_user}:${deploy_group}" "$deploy_root/storage" "$deploy_root/bootstrap/cache" "$deploy_root/database" 2>/dev/null; then
    sudo find "$deploy_root/storage" "$deploy_root/bootstrap/cache" -type d -exec chmod 775 {} \;
    sudo find "$deploy_root/storage" "$deploy_root/bootstrap/cache" -type f -exec chmod 664 {} \;
    sudo chmod 775 "$deploy_root/database"
    if [[ -f "$deploy_root/database/database.sqlite" ]]; then
      sudo chmod 664 "$deploy_root/database/database.sqlite"
    fi
    echo "    Permissions normalized for ${deploy_user}:${deploy_group} on storage/, bootstrap/cache/, database/"
    return 0
  fi

  warn "sudo unavailable — applying best-effort user permissions only"
  chmod -R u+rwX,go+rX "$deploy_root/storage" "$deploy_root/bootstrap/cache" "$deploy_root/database" 2>/dev/null || true
  if [[ -f "$deploy_root/database/database.sqlite" ]]; then
    chmod 664 "$deploy_root/database/database.sqlite" 2>/dev/null || true
  fi
  chmod 775 "$deploy_root/database" 2>/dev/null || true
}

redeploy_sync_scripts_with_lib() {
  local dev_root="$1"
  local deploy_root="$2"

  mkdir -p "$deploy_root/scripts/lib"
  rsync -av \
    --include='lib/' \
    --include='lib/**' \
    --include='*.sh' \
    --include='README*' \
    --exclude='*' \
    "$dev_root/scripts/" "$deploy_root/scripts/"
  chmod +x "$deploy_root/scripts/"*.sh 2>/dev/null || true
}

redeploy_print_report() {
  local source_path="$1"
  local deploy_path="$2"
  local edition_label="$3"

  if [[ "$REPORT_DB_READ" == "PASS" && "$REPORT_DB_WRITE" == "PASS" && "$REPORT_STORAGE_SYMLINK" == "PASS" && "$REPORT_HTTP_HEALTH" == "PASS" ]]; then
    REPORT_OVERALL="PASS"
  else
    REPORT_OVERALL="FAIL"
  fi

  echo ""
  echo "============================================================"
  echo " DEPLOYMENT REPORT — $edition_label"
  echo "============================================================"
  echo " Source path:        $source_path"
  echo " Deploy path:        $deploy_path"
  echo " DB read test:       $REPORT_DB_READ"
  echo " DB write test:      $REPORT_DB_WRITE"
  echo " Storage symlink:    $REPORT_STORAGE_SYMLINK"
  echo " HTTP health:        $REPORT_HTTP_HEALTH"
  echo " Overall status:     $REPORT_OVERALL"
  echo "============================================================"
}

redeploy_run_post_validation() {
  local deploy_root="$1"
  local source_path="$2"
  local edition_label="$3"
  shift 3
  local -a http_urls=("$@")
  local validation_failed=0

  redeploy_validation_reset_report

  step "Post-deploy validation"

  redeploy_ensure_public_storage_symlink "$deploy_root" || validation_failed=1

  if ! redeploy_verify_required_paths "$deploy_root"; then
    validation_failed=1
  fi

  if ! redeploy_verify_storage_symlink "$deploy_root"; then
    validation_failed=1
  fi

  redeploy_fix_permissions "$deploy_root" "${DEPLOY_USER:-${USER}}" "${DEPLOY_GROUP:-http}"

  if ! redeploy_verify_filesystem_permissions "$deploy_root"; then
    validation_failed=1
  fi

  redeploy_verify_laravel_runtime "$deploy_root"

  if ! redeploy_verify_laravel_database_rw "$deploy_root"; then
    validation_failed=1
  fi

  if ! redeploy_verify_laravel_health "$deploy_root"; then
    validation_failed=1
  fi

  if ! redeploy_verify_http_endpoints "${http_urls[@]}"; then
    validation_failed=1
  fi

  if declare -F redeploy_extra_deploy_checks >/dev/null 2>&1; then
    if ! redeploy_extra_deploy_checks "$deploy_root"; then
      validation_failed=1
    fi
  fi

  redeploy_print_report "$source_path" "$deploy_root" "$edition_label"

  if [[ "$validation_failed" -ne 0 ]]; then
    die "Post-deploy validation failed — see report above."
  fi
}
