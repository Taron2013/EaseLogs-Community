#!/usr/bin/env bash
#
# Read-only checks for Community + Pro local intranet deployments.
#
set -euo pipefail

readonly COMMUNITY="/var/www/projects/easelogs"
readonly PRO="/var/www/projects/easelog-pro"
readonly PRO_HOST="easelogs.pro"
readonly LEGACY_PRO_HOST="easelog.pro"

pass() { echo "OK:   $1"; }
fail() { echo "FAIL: $1" >&2; ERR=1; }
hint() { echo "HINT: $1" >&2; }

ERR=0

echo "EaseLogs local deployment verification"
echo ""

if [[ -d "$COMMUNITY" ]]; then
  pass "Community deploy dir exists ($COMMUNITY)"
else
  fail "Community deploy dir missing ($COMMUNITY)"
fi

if [[ -d "$PRO" ]]; then
  pass "Pro deploy dir exists ($PRO)"
else
  fail "Pro deploy dir missing ($PRO) — run scripts/redeploy-pro-local.sh first"
fi

if [[ -d "$COMMUNITY" && -d "$PRO" ]]; then
  comm_db="$(readlink -f "$COMMUNITY/database/database.sqlite" 2>/dev/null || true)"
  pro_db="$(readlink -f "$PRO/database/database.sqlite" 2>/dev/null || true)"
  if [[ -n "$comm_db" && -n "$pro_db" && "$comm_db" != "$pro_db" ]]; then
    pass "Community and Pro use separate SQLite files"
  elif [[ -f "$COMMUNITY/database/database.sqlite" && -f "$PRO/database/database.sqlite" ]]; then
    fail "Community and Pro database paths should differ"
  fi
fi

check_storage_symlink() {
  local label="$1"
  local path="$2"

  [[ -d "$path" ]] || return 0

  if [[ -L "$path/public/storage" ]]; then
    local target expected
    target="$(readlink -f "$path/public/storage" 2>/dev/null || true)"
    expected="$(readlink -f "$path/storage/app/public" 2>/dev/null || true)"
    if [[ "$target" == "$expected" ]]; then
      pass "$label public/storage symlink"
    else
      fail "$label public/storage -> ${target:-?} (expected $expected)"
    fi
  else
    fail "$label public/storage symlink missing"
  fi
}

check_storage_symlink "Community" "$COMMUNITY"
check_storage_symlink "Pro" "$PRO"

diagnose_pro_hostname() {
  local hosts_ok=0 nginx_ok=0

  if [[ -r /etc/hosts ]] && grep -qE "[[:space:]]${PRO_HOST}([[:space:]]|$)" /etc/hosts; then
    hosts_ok=1
    pass "hosts: ${PRO_HOST} resolves (see /etc/hosts)"
  else
    fail "hosts: ${PRO_HOST} not in /etc/hosts"
    if [[ -r /etc/hosts ]] && grep -qE "[[:space:]]${LEGACY_PRO_HOST}([[:space:]]|$)" /etc/hosts; then
      hint "Found legacy ${LEGACY_PRO_HOST} in /etc/hosts — add: 127.0.0.1 ${PRO_HOST}"
      hint "Or run: sudo bash scripts/migrate-pro-hostname-to-easelogs-pro.sh"
    else
      hint "Add to /etc/hosts: 127.0.0.1 ${PRO_HOST}"
    fi
  fi

  if [[ -f /etc/nginx/conf.d/easelogs.pro.conf ]] \
    && grep -q "server_name ${PRO_HOST}" /etc/nginx/conf.d/easelogs.pro.conf 2>/dev/null; then
    nginx_ok=1
    pass "nginx: easelogs.pro.conf lists server_name ${PRO_HOST}"
  elif [[ -f /etc/nginx/conf.d/easelog.pro.conf ]] \
    && grep -q "server_name ${LEGACY_PRO_HOST}" /etc/nginx/conf.d/easelog.pro.conf 2>/dev/null; then
    fail "nginx: still using legacy easelog.pro.conf (server_name ${LEGACY_PRO_HOST})"
    hint "Run: sudo bash scripts/migrate-pro-hostname-to-easelogs-pro.sh"
  else
    fail "nginx: no Pro vhost for ${PRO_HOST} found in /etc/nginx/conf.d/"
    hint "Install: sudo cp deploy/nginx/easelogs.pro.conf.example /etc/nginx/conf.d/easelogs.pro.conf"
  fi

  return $(( hosts_ok && nginx_ok ? 0 : 1 ))
}

diagnose_pro_hostname || true

if command -v curl >/dev/null 2>&1; then
  if curl -k -sf -o /dev/null -I "https://easelogs.local" 2>/dev/null; then
    pass "curl -k -I https://easelogs.local"
  else
    fail "curl -k -I https://easelogs.local (nginx/hosts/TLS?)"
  fi

  if curl -k -sf -o /dev/null -I "https://${PRO_HOST}" 2>/dev/null; then
    pass "curl -k -I https://${PRO_HOST}"
  else
    fail "curl -k -I https://${PRO_HOST} (nginx/hosts/TLS?)"
    if getent hosts "$PRO_HOST" >/dev/null 2>&1; then
      hint "${PRO_HOST} resolves to: $(getent hosts "$PRO_HOST" | awk '{print $1}')"
    else
      hint "${PRO_HOST} does not resolve — fix /etc/hosts first"
    fi
  fi
else
  echo "SKIP: curl not installed"
fi

echo ""
if [[ "$ERR" -eq 0 ]]; then
  echo "All checks passed."
else
  echo "Some checks failed."
  exit 1
fi
