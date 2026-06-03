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

### Workstation

```text
127.0.0.1 easelogs.local
```

### Phone / tablet (same LAN)

Point `easelogs.local` at your workstation’s LAN IP, for example:

```text
192.168.50.3 easelogs.local
```

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

---

## Standard install (no nginx)

Most artists use `php artisan serve` per [INSTALL_GUIDE.md](INSTALL_GUIDE.md). This document is only for optional local nginx testing.
