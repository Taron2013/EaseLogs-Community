#!/usr/bin/env bash
#
# Remove legacy easelog.pro hostname from this workstation (hosts, nginx, TLS).
# Keeps easelogs.pro and deploy path /var/www/projects/easelog-pro unchanged.
#
# Run: sudo bash ~/EaseLogs/scripts/cleanup-legacy-easelog-pro-hostname.sh
#
set -euo pipefail

readonly LEGACY_HOST="easelog.pro"
readonly PRO_HOST="easelogs.pro"
readonly HOSTS_FILE="/etc/hosts"
readonly CERT_DIR="/etc/nginx/certs/easelog-pro"
readonly NGINX_CONF="/etc/nginx/conf.d/easelogs.pro.conf"

if [[ $EUID -ne 0 ]]; then
  echo "Run with sudo: sudo bash $0" >&2
  exit 1
fi

step() { echo ""; echo "==> $1"; }
die() { echo "ERROR: $1" >&2; exit 1; }

step "Remove ${LEGACY_HOST} from ${HOSTS_FILE}"
if grep -qE "[[:space:]]${LEGACY_HOST}([[:space:]]|$)" "$HOSTS_FILE" 2>/dev/null; then
  sed -i "/[[:space:]]${LEGACY_HOST}\$/d" "$HOSTS_FILE"
  sed -i "/[[:space:]]${LEGACY_HOST}[[:space:]]/d" "$HOSTS_FILE"
  echo "    Removed lines containing ${LEGACY_HOST}"
else
  echo "    No ${LEGACY_HOST} entry in hosts"
fi

if ! grep -qE "[[:space:]]${PRO_HOST}([[:space:]]|$)" "$HOSTS_FILE" 2>/dev/null; then
  echo "127.0.0.1 ${PRO_HOST}" >> "$HOSTS_FILE"
  echo "    Added 127.0.0.1 ${PRO_HOST}"
fi

step "Remove disabled legacy nginx configs"
shopt -s nullglob
for f in /etc/nginx/conf.d/easelog.pro.conf.disabled.* /etc/nginx/conf.d/easelog.pro.conf; do
  if [[ -f "$f" ]]; then
    rm -f "$f"
    echo "    Removed $f"
  fi
done
shopt -u nullglob

step "Issue TLS certificate for ${PRO_HOST}"
if ! command -v mkcert >/dev/null 2>&1; then
  die "mkcert is required. Install mkcert, then re-run this script."
fi

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT
(
  cd "$WORKDIR"
  mkcert -cert-file "${PRO_HOST}.pem" -key-file "${PRO_HOST}-key.pem" "$PRO_HOST"
)
install -d -m 755 "$CERT_DIR"
install -m 644 "$WORKDIR/${PRO_HOST}.pem" "$CERT_DIR/${PRO_HOST}.pem"
install -m 600 "$WORKDIR/${PRO_HOST}-key.pem" "$CERT_DIR/${PRO_HOST}-key.pem"
echo "    Installed $CERT_DIR/${PRO_HOST}.pem"

step "Remove legacy certificate files"
for old in "$CERT_DIR/${LEGACY_HOST}.pem" "$CERT_DIR/${LEGACY_HOST}-key.pem" \
  "$CERT_DIR/${LEGACY_HOST}+1.pem" "$CERT_DIR/${LEGACY_HOST}+1-key.pem"; do
  if [[ -f "$old" ]]; then
    rm -f "$old"
    echo "    Removed $old"
  fi
done

step "Point ${NGINX_CONF} at ${PRO_HOST} certificates"
if [[ ! -f "$NGINX_CONF" ]]; then
  echo "ERROR: $NGINX_CONF missing — run migrate-pro-hostname-to-easelogs-pro.sh first" >&2
  exit 1
fi

sed -i \
  -e "s|ssl_certificate .*|ssl_certificate ${CERT_DIR}/${PRO_HOST}.pem;|" \
  -e "s|ssl_certificate_key .*|ssl_certificate_key ${CERT_DIR}/${PRO_HOST}-key.pem;|" \
  "$NGINX_CONF"

if ! grep -q "server_name ${PRO_HOST}" "$NGINX_CONF"; then
  sed -i "s/server_name .*/server_name ${PRO_HOST};/" "$NGINX_CONF"
fi

step "Test and reload nginx"
nginx -t
systemctl reload nginx

step "Scan for remaining ${LEGACY_HOST} on system (informational)"
FOUND=0
while IFS= read -r hit; do
  echo "    $hit"
  FOUND=1
done < <(grep -r --include='*.conf' --include='hosts' -l "${LEGACY_HOST}" /etc/hosts /etc/nginx 2>/dev/null || true)

if [[ "$FOUND" -eq 0 ]]; then
  echo "    No ${LEGACY_HOST} under /etc/hosts or /etc/nginx"
fi

echo ""
echo "Cleanup complete. Verify:"
echo "  getent hosts ${PRO_HOST}"
echo "  getent hosts ${LEGACY_HOST} || echo '(legacy host gone — good)'"
echo "  curl -k -I https://${PRO_HOST}"
