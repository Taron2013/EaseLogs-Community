# Community Edition — local intranet deployment (nginx)

Optional **local nginx/php-fpm** deployment for testing or home-network hosting on Linux (for example Manjaro).

| Item | Value |
|------|--------|
| Hostname | `easelogs.local` |
| Deploy path | `/var/www/projects/easelogs` |
| Redeploy script | `scripts/redeploy-local.sh` |

Back up before reset or `migrate:fresh`: [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md).

This install uses its own:

- `.env`
- `database/database.sqlite`
- `storage/app/public/` (artwork uploads)
- `public/storage` symlink → that install’s `storage/app/public`

---

## Prerequisites

- PHP 8.3+, Composer, Node.js/npm
- nginx + php-fpm
- Dev repo at `~/EaseLogs` (or your clone path)

---

## DNS / hosts

Local HTTPS uses **three separate DNS roles**. Do not point all of them at the same address.

| Role | Where | Typical value |
|------|--------|----------------|
| **PC browser** | `/etc/hosts` | `127.0.0.1 easelogs.local` → local nginx |
| **LAN clients (phones/tablets)** | Router DHCP DNS or Wi‑Fi DNS | `<WORKSTATION_LAN_IP>` → dnsmasq on the workstation |
| **PC system resolver** | `/etc/resolv.conf` (via NetworkManager) | `127.0.0.1` → local dnsmasq (not the Ethernet LAN IP) |
| **dnsmasq upstream (internet)** | `/etc/dnsmasq.d/upstream.conf` | **Required** — router + public fallbacks (`server=` lines). Without this file, the workstation loses internet DNS when using local dnsmasq. See [LOCAL_OPS_ROTATION.md](LOCAL_OPS_ROTATION.md#dnsmasq-upstream-required-for-internet-dns) |

**Privacy:** Do not commit your actual LAN IP into this repository. Keep live values in local system files, router admin, or device settings only.

### Workstation `/etc/hosts`

```text
127.0.0.1 easelogs.local
```

Use loopback on the PC. Do not put the workstation LAN IP here for browser use.

### Phone / tablet (same LAN)

Point `easelogs.local` at your workstation’s LAN IP (from `ip -4 addr`, your router’s DHCP client list, or a static reservation you configure). Replace `192.168.x.x` with that address:

```text
192.168.x.x easelogs.local
```

On iOS, `.local` may still prefer **mDNS** over router DNS. If Safari shows “server not found”, see [LOCAL_OPS_ROTATION.md](LOCAL_OPS_ROTATION.md) (iOS / `.local` notes) or use the public demo.

For LAN IP changes, dnsmasq upstream, mkcert rotation, and mobile trust, see [LOCAL_OPS_ROTATION.md](LOCAL_OPS_ROTATION.md).

---

## Deploy and redeploy

From the project root:

```bash
cd ~/EaseLogs
chmod +x scripts/redeploy-local.sh scripts/verify-local-deployments.sh
./scripts/redeploy-local.sh
./scripts/redeploy-local.sh --preserve
```

See [README.md](../README.md#local-intranet-redeploy-manjaro) for preserve vs reset behavior. Use `--preserve` when redeploying code without touching `database.sqlite` or `storage/app/public/`.

The script syncs your working tree to `/var/www/projects/easelogs`, runs Composer and `npm run build`, repairs `public/storage`, and fixes permissions for php-fpm.

---

## Verification

### HTTP

```bash
curl -k -I https://easelogs.local
```

Expect `HTTP/2 200` or `302` (not connection refused).

### Symlink

```bash
readlink -f /var/www/projects/easelogs/public/storage
```

Should resolve to that install’s `storage/app/public`.

### Script (optional)

```bash
./scripts/verify-local-deployments.sh
```

---

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| Broken thumbnails | `public/storage` symlink; re-run `redeploy-local.sh` |
| 502 / blank page | nginx error log, php-fpm running |
| Wrong site | `/etc/hosts` and nginx `server_name easelogs.local` |
| After DB reset | Complete `/setup` again at `https://easelogs.local` |
| **Internet stops resolving on workstation** | `/etc/dnsmasq.d/upstream.conf` — must have `server=` lines; see [LOCAL_OPS_ROTATION.md](LOCAL_OPS_ROTATION.md#dnsmasq-upstream-required-for-internet-dns) |
| **Phone: server not found** | Router LAN DHCP DNS → workstation; dnsmasq `listen-address=`; reconnect Wi‑Fi |
| **Phone: certificate warning** | Install mkcert `rootCA.pem` + enable full trust (iOS Certificate Trust Settings) |
| **PC: `.local` resolves wrong** | `/etc/nsswitch.conf` — put `files` before `mdns_minimal` |
| dnsmasq log: `ignoring nameserver … local interface` | Usually harmless if `upstream.conf` defines `server=`; PC `resolv.conf` should use `127.0.0.1`, not the LAN IP — see rotation doc |

---

## Standard install (no nginx)

Most artists use `php artisan serve` per [INSTALL_GUIDE.md](INSTALL_GUIDE.md). This document is only for optional local nginx testing.
