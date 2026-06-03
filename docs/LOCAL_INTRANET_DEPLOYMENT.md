# Local intranet deployment (Community + Pro)

Two **independent** local nginx/php-fpm deployments on one workstation:

| Edition | Hostname | Deploy path | Redeploy script |
|---------|----------|-------------|-----------------|
| Community | `easelogs.local` | `/var/www/projects/easelogs` | `scripts/redeploy-local.sh` |
| Pro | `easelogs.pro` | `/var/www/projects/easelog-pro` | `scripts/redeploy-pro-local.sh` |

Back up Community and Pro data before reset or `migrate:fresh`: [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md).

Each install has its own:

- `.env`
- `database/database.sqlite`
- `storage/app/public/` (artwork uploads)
- `public/storage` symlink → that install’s `storage/app/public`

**Naming:** Pro hostname is `easelogs.pro`; deploy directory remains `easelog-pro` (`/var/www/projects/easelog-pro`). Do not use a `.local` suffix for Pro (e.g. `easelogs.pro.local`).

---

## Prerequisites

- PHP 8.3+, Composer, Node.js/npm
- nginx + php-fpm
- Community already deployed at `/var/www/projects/easelogs` (for optional one-time seed)
- Dev repo at `~/EaseLogs`

---

## DNS / hosts

### Workstation

```text
127.0.0.1 easelogs.local
127.0.0.1 easelogs.pro
```

### Phone / tablet (same LAN)

Point both hostnames at your workstation’s LAN IP, for example:

```text
192.168.50.3 easelogs.local
192.168.50.3 easelogs.pro
```

---

## Community deployment (unchanged)

From the project root:

```bash
cd ~/EaseLogs
chmod +x scripts/redeploy-local.sh
./scripts/redeploy-local.sh
./scripts/redeploy-local.sh --preserve
```

See [README.md](../README.md#local-intranet-redeploy-manjaro) for preserve vs reset behavior. Use `--preserve` when redeploying code without touching `database.sqlite` or `storage/app/public/`.

---

## Pro deployment — first time

### 1. Nginx

```bash
cd ~/EaseLogs
sudo cp deploy/nginx/easelogs.pro.conf.example /etc/nginx/conf.d/easelogs.pro.conf
```

Create TLS certs (example with [mkcert](https://github.com/FiloSottile/mkcert)):

```bash
mkcert -cert-file easelogs.pro.pem -key-file easelogs.pro-key.pem easelogs.pro
sudo mkdir -p /etc/nginx/certs/easelog-pro
sudo install -m 644 easelogs.pro.pem /etc/nginx/certs/easelog-pro/
sudo install -m 600 easelogs.pro-key.pem /etc/nginx/certs/easelog-pro/
# Edit /etc/nginx/conf.d/easelogs.pro.conf if your cert filenames differ
sudo nginx -t && sudo systemctl reload nginx
```

### 2. Hosts

Add `127.0.0.1 easelogs.pro` to `/etc/hosts` (or your local DNS).

### 3. Run Pro setup / redeploy

```bash
cd ~/EaseLogs
chmod +x scripts/redeploy-pro-local.sh
./scripts/redeploy-pro-local.sh
```

When `.env` is missing under `/var/www/projects/easelog-pro`, the script runs **first-time setup** and asks:

- **Seed from Community?** `y` — copies Community `database.sqlite` and `storage/app/public/` into Pro once, and creates Pro `.env` from Community with `APP_URL=https://easelogs.pro`.
- **No** — fresh Pro SQLite and empty uploads; run `/setup` on Pro if needed.

After first setup, Pro and Community are **independent**. Later runs use preserve/reset prompts for **Pro only**.

---

## Pro deployment — routine redeploy

```bash
cd ~/EaseLogs
./scripts/redeploy-pro-local.sh
./scripts/redeploy-pro-local.sh --preserve
```

| Choice | Behavior |
|--------|----------|
| **1 — Preserve** | Keeps Pro DB and Pro `storage/app/public/`; `migrate --force` |
| **2 — Reset Pro** | Type `RESET PRO` to confirm; recreates Pro SQLite; `migrate:fresh` on Pro only |

Community at `/var/www/projects/easelogs` is never modified by `redeploy-pro-local.sh`.

---

## Verification

### HTTP checks

```bash
curl -k -I https://easelogs.pro
curl -k -I https://easelogs.local
```

Both should return `HTTP/2 200` or `302` (not connection refused).

### Independent data

1. Open https://easelogs.pro/artworks and https://easelogs.local/artworks.
2. On **Pro**, create or edit an artwork (e.g. title `Pro-only test`).
3. Reload **Community** — the change should **not** appear.
4. Optionally edit Community and confirm Pro is unchanged.

### Paths and symlinks

```bash
ls -la /var/www/projects/easelog-pro/public/storage
ls -la /var/www/projects/easelogs/public/storage
readlink -f /var/www/projects/easelog-pro/public/storage
readlink -f /var/www/projects/easelogs/public/storage
```

Each symlink should resolve to **its own** `storage/app/public`.

### Script safety check (optional)

```bash
./scripts/verify-local-deployments.sh
```

If Pro fails with “Could not resolve host”, run the hostname migration, then remove legacy `easelog.pro` system entries:

```bash
sudo bash ~/EaseLogs/scripts/migrate-pro-hostname-to-easelogs-pro.sh
sudo bash ~/EaseLogs/scripts/cleanup-legacy-easelog-pro-hostname.sh
./scripts/verify-local-deployments.sh
```

---

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| Broken thumbnails | `public/storage` symlink on **that** install; re-run redeploy script |
| Pro shows Community data after you wanted separation | You may have re-seeded manually; Pro reset does not touch Community |
| 502 / blank page | `sudo tail -f /var/log/nginx/easelog-pro.error.log`, php-fpm running |
| Wrong site | Hosts file / DNS; nginx `server_name` |

---

## What Pro redeploy does

1. Refuses empty paths and refuses targets under `/var/www/projects/easelogs`
2. Syncs dev tree → `/var/www/projects/easelog-pro` (preserves Pro `.env`, DB, uploads)
3. `composer install`, `npm install`, `npm run build`
4. `php artisan migrate` or `migrate:fresh` (Pro only)
5. Repairs `public/storage` symlink
6. Clears config/route/view cache
7. Sets `storage`, `bootstrap/cache`, `database` permissions for php-fpm

This task is **deployment infrastructure only** — Pro product features are not enabled here beyond separate install + branding in `.env`.
