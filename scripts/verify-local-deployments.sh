#!/usr/bin/env bash
#
# Read-only checks for Community Edition local intranet deployment.
#
set -euo pipefail

readonly COMMUNITY="/var/www/projects/easelogs"
readonly COMMUNITY_HOST="easelogs.local"

pass() { echo "OK:   $1"; }
fail() { echo "FAIL: $1" >&2; ERR=1; }
hint() { echo "HINT: $1" >&2; }

ERR=0

echo "EaseLogs Community local deployment verification"
echo ""

if [[ -d "$COMMUNITY" ]]; then
  pass "Community deploy dir exists ($COMMUNITY)"
else
  fail "Community deploy dir missing ($COMMUNITY) — run scripts/redeploy-local.sh first"
fi

check_storage_symlink() {
  local path="$1"

  [[ -d "$path" ]] || return 0

  if [[ -L "$path/public/storage" ]]; then
    local target expected
    target="$(readlink -f "$path/public/storage" 2>/dev/null || true)"
    expected="$(readlink -f "$path/storage/app/public" 2>/dev/null || true)"
    if [[ "$target" == "$expected" ]]; then
      pass "public/storage symlink"
    else
      fail "public/storage -> ${target:-?} (expected $expected)"
    fi
  else
    fail "public/storage symlink missing"
  fi
}

check_storage_symlink "$COMMUNITY"

if [[ -r /etc/hosts ]] && grep -qE "[[:space:]]${COMMUNITY_HOST}([[:space:]]|$)" /etc/hosts; then
  pass "hosts: ${COMMUNITY_HOST} in /etc/hosts"
else
  fail "hosts: ${COMMUNITY_HOST} not in /etc/hosts"
  hint "Add: 127.0.0.1 ${COMMUNITY_HOST}"
fi

if command -v curl >/dev/null 2>&1; then
  if curl -k -sf -o /dev/null -I "https://${COMMUNITY_HOST}" 2>/dev/null; then
    pass "curl -k -I https://${COMMUNITY_HOST}"
  else
    fail "curl -k -I https://${COMMUNITY_HOST} (nginx/hosts/TLS?)"
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
