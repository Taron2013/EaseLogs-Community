# Local operations — IP and certificate rotation (Community)

Generic checklist for **self-hosted Community Edition** on a local workstation or home LAN. Use this when you change your workstation LAN address, rotate mkcert TLS material, or move DNS for `easelogs.local`.

**Do not commit real LAN IP addresses into this repository.** Keep live values in local system files, router admin, or device Wi‑Fi settings only.

> **Critical — `/etc/dnsmasq.d/upstream.conf`:** If you run dnsmasq on the workstation, this file is **required** for internet DNS (`google.com`, updates, etc.). It must contain **`server=`** lines (router + fallbacks). Setting PC `resolv.conf` to `127.0.0.1` alone does **not** give dnsmasq upstream. **Never** add `no-resolv` without `server=` lines — that caused a full internet DNS outage in testing. Local easelogs names live in a **separate** file (for example `easelogs.conf`); do not merge upstream into `resolv.conf`.

See also: [LOCAL_INTRANET_DEPLOYMENT.md](LOCAL_INTRANET_DEPLOYMENT.md), [COMMUNITY_BACKUP.md](COMMUNITY_BACKUP.md).

---

## Scope (Community)

| Item | Value |
|------|--------|
| Hostname | `easelogs.local` |
| Typical deploy path | `/var/www/projects/easelogs` |
| Workstation browser | `/etc/hosts` → `127.0.0.1 easelogs.local` |
| LAN devices | DNS or hosts → `<WORKSTATION_LAN_IP>` (local only) |

---

## Files to check when changing LAN IP

These files live **on your machine or router**, not in git. Open each locally and search for outdated private IPv4 literals.

| Location | What to update |
|----------|----------------|
| `/etc/hosts` | PC should use **`127.0.0.1 easelogs.local`** only — not a LAN IP |
| `/etc/resolv.conf` | PC resolver should use **`127.0.0.1`** (via NetworkManager) — not the workstation LAN IP |
| `/etc/dnsmasq.d/easelogs*.conf` | `address=/easelogs.local/<WORKSTATION_LAN_IP>`, `listen-address=` |
| `/etc/dnsmasq.d/upstream.conf` | Upstream `server=` lines for internet DNS — **do not remove** |
| `/etc/nginx/conf.d/easelogs.local.conf` | `server_name` should be **hostname only** (no IP) |
| NetworkManager connection | `ipv4.dns=127.0.0.1`, `ipv4.ignore-auto-dns=yes` on the active Ethernet profile |
| Router admin | LAN DHCP DNS → `<WORKSTATION_LAN_IP>`; workstation DHCP reservation |
| Phone/tablet Wi‑Fi DNS | Inherits router DNS when DHCP is configured correctly |

**Repository:** run an IP audit (see commands below). Docs should only contain placeholders like `192.168.x.x`.

### iOS / `.local` note

Apple devices often resolve `.local` via **mDNS**, not your router DNS. If `easelogs.local` fails on iPhone/iPad with “server not found”, try router local DNS that overrides `.local`, re-check dnsmasq `listen-address=`, or use the public demo at `https://demo.easelogs.com/community/` instead of LAN Community HTTPS.

---

## DNS architecture (workstation dnsmasq)

When dnsmasq on the workstation serves LAN clients, four settings must stay **distinct**:

```text
PC apps          →  /etc/resolv.conf: 127.0.0.1        →  dnsmasq
dnsmasq          →  upstream.conf: router + fallbacks  →  internet (google.com, etc.)
dnsmasq          →  easelogs*.conf: address=/…/         →  easelogs.local for LAN clients
Router DHCP DNS  →  <WORKSTATION_LAN_IP>                 →  phones/tablets query dnsmasq
PC /etc/hosts    →  127.0.0.1 easelogs.local             →  PC browser hits local nginx
```

**Common mistake:** Putting the workstation **LAN IP** (or only `127.0.0.1` without upstream config) everywhere. That breaks either PC browsing, phone DNS, or **internet resolution** on the workstation.

---

## Workstation resolver (`/etc/resolv.conf`)

The PC should ask **local dnsmasq on loopback**, not the Ethernet NIC address.

Set via NetworkManager (replace `YOUR_CONNECTION` with the active profile from `nmcli con show`):

```bash
nmcli con show
sudo nmcli con mod "YOUR_CONNECTION" ipv4.dns "127.0.0.1"
sudo nmcli con mod "YOUR_CONNECTION" ipv4.ignore-auto-dns yes
sudo nmcli con down "YOUR_CONNECTION" && sudo nmcli con up "YOUR_CONNECTION"
```

Verify locally (keep output private):

```bash
grep -v '^#' /etc/resolv.conf | grep -v '^$'
```

Expected: **`nameserver 127.0.0.1`**.

**Do not** put the workstation LAN IP in `resolv.conf` on the same machine — dnsmasq logs `ignoring nameserver … - local interface` and will not use that address as upstream.

---

## dnsmasq — local hostname (`/etc/dnsmasq.d/easelogs.conf`)

Example (replace placeholders locally; do not commit real IPs):

```text
address=/easelogs.local/<WORKSTATION_LAN_IP>
listen-address=127.0.0.1
listen-address=<WORKSTATION_LAN_IP>
```

```bash
sudo dnsmasq --test
sudo systemctl restart dnsmasq
dig @127.0.0.1 easelogs.local +short
```

Allow DNS through host firewall if enabled:

```bash
sudo firewall-cmd --permanent --add-service=dns
sudo firewall-cmd --reload
```

---

## dnsmasq upstream (required for internet DNS)

**This file is mandatory** when dnsmasq runs on the workstation. Local hostname overrides in `easelogs*.conf` answer `easelogs.local` only; **everything else** (including `google.com`) depends on upstream servers defined here.

Create **`/etc/dnsmasq.d/upstream.conf`** (local only — not in git). Keep it **separate** from `easelogs*.conf` so a hostname edit cannot accidentally remove internet DNS.

```text
server=<ROUTER_IP>
server=1.1.1.1
server=8.8.8.8
no-resolv
```

Replace `<ROUTER_IP>` with your router’s LAN address (often ends in `.1`).

### Upstream warnings

| Change | Risk |
|--------|------|
| Add **`no-resolv`** without any **`server=`** lines | **Breaks all internet DNS** on the workstation |
| Remove **`upstream.conf`** or all **`server=`** lines | Same — external sites stop resolving |
| Put only **`127.0.0.1`** in `upstream.conf` | DNS loop — dnsmasq cannot use itself as upstream |

The optional **`no-resolv`** line tells dnsmasq **not** to read `/etc/resolv.conf` for upstream. It is safe **only when** the `server=` lines above are present. Without `no-resolv`, dnsmasq may log `ignoring nameserver 127.0.0.1 - local interface` while still using your `server=` lines — noisy but often functional.

After any dnsmasq edit:

```bash
sudo dnsmasq --test
sudo systemctl restart dnsmasq
dig @127.0.0.1 google.com +short
dig @127.0.0.1 easelogs.local +short
```

Both should return answers. If `google.com` fails, fix **`upstream.conf`** before troubleshooting easelogs.

---

## Router LAN DHCP DNS

For phones and tablets, set the router’s **LAN DHCP DNS server** to **`<WORKSTATION_LAN_IP>`** (the workstation running dnsmasq).

After changing router DNS:

1. Reconnect Wi‑Fi on each phone/tablet (or forget and rejoin).
2. Confirm `https://easelogs.local` in Safari (may still hit iOS `.local` / mDNS limits).

---

## dnsmasq log messages (troubleshooting)

| Log line | Meaning | Action |
|----------|---------|--------|
| `ignoring nameserver 127.0.0.1 - local interface` | `resolv.conf` lists loopback; dnsmasq will not use it as upstream | Normal if **`upstream.conf`** has `server=` lines |
| `ignoring nameserver … - local interface` (LAN IP) | `resolv.conf` lists this PC’s own Ethernet address | Fix NetworkManager → **`127.0.0.1`** only |
| `using nameserver …#53` | Upstream loaded | Good |
| No `using nameserver` and external DNS fails | No usable upstream | Add or restore **`/etc/dnsmasq.d/upstream.conf`** |
| `read /etc/hosts` | Local static names loaded | Informational |

---

## Privacy — IP audit without exposing addresses

Prefer commands that print **filenames only** when checking for stale octets (for example `.3` / `.33` after a DHCP change):

```bash
PAT='(192\.168\.[0-9]{1,3}\.(3|33)|10\.[0-9]{1,3}\.[0-9]{1,3}\.(3|33))'
grep -rlE "$PAT" /etc/resolv.conf /etc/dnsmasq.d/ /etc/nginx/conf.d/ 2>/dev/null | grep -v '/etc/hosts'
```

To inspect line numbers locally with addresses redacted before sharing:

```bash
grep -nE "$PAT" /etc/resolv.conf /etc/dnsmasq.d/*.conf 2>/dev/null \
  | sed -E 's/[0-9]{1,3}(\.[0-9]{1,3}){3}/x.x.x.x/g'
```

Do not commit literal LAN IPs into this repository.

---

## IP audit commands (run locally)

Discover current workstation LAN address (do not commit output):

```bash
ip -4 addr show scope global
```

Search common **system** configs for private IPv4 literals:

```bash
grep -rE '192\.168\.[0-9]+\.[0-9]+|10\.[0-9]+\.[0-9]+\.[0-9]+' \
  /etc/hosts /etc/dnsmasq.d/ /etc/nginx/conf.d/ 2>/dev/null
```

Search **git repo** from clone root:

```bash
cd /path/to/EaseLogs-Community
git grep -E '192\.168\.[0-9]+\.[0-9]+|10\.[0-9]+\.[0-9]+\.[0-9]+' || true
```

Optional — find octets ending in `.3` or `.33` (common old DHCP leases):

```bash
grep -rE '\.[0-9]+\.(3|33)\b' /etc/hosts /etc/dnsmasq.d/ /etc/nginx/ 2>/dev/null
git grep -E '\.[0-9]+\.(3|33)\b' || true
```

Verify DNS (when dnsmasq is used):

```bash
dig @127.0.0.1 easelogs.local +short
dig @127.0.0.1 google.com +short
getent hosts easelogs.local
```

Workstation browser should resolve via hosts to `127.0.0.1`. If `google.com` via `@127.0.0.1` fails, fix **`upstream.conf`** first.

Manjaro **nsswitch**: put `files` before `mdns_minimal` in `/etc/nsswitch.conf` if `.local` resolves via mDNS instead of hosts:

```text
hosts: files mymachines mdns_minimal [NOTFOUND=return] resolve [!UNAVAIL=return] myhostname dns
```

---

## Workstation `/etc/hosts` (after IP change)

```text
127.0.0.1 easelogs.local
```

Comment obsolete LAN lines instead of deleting if you want a paper trail:

```text
# retired LAN IP — do not use on workstation
# 192.168.x.x easelogs.local
```

---

## dnsmasq example (local file only)

See **dnsmasq — local hostname** and **dnsmasq upstream** sections above for the full split between `easelogs*.conf` and `upstream.conf`.

Legacy single-file example (Community hostname only):

`/etc/dnsmasq.d/easelogs.conf`:

```text
address=/easelogs.local/<WORKSTATION_LAN_IP>
listen-address=127.0.0.1
listen-address=<WORKSTATION_LAN_IP>
```

Plus **`/etc/dnsmasq.d/upstream.conf`** with router and fallback `server=` lines (required).

---

## mkcert certificate rotation (Community)

Hostname-only certs (no IP in SAN) are recommended. nginx paths on this install typically use:

| File | Path |
|------|------|
| Certificate | `/etc/nginx/certs/easelogs/easelogs.local+1.pem` |
| Private key | `/etc/nginx/certs/easelogs/easelogs.local+1-key.pem` |

### 1. Install tooling (once)

```bash
sudo pacman -S --needed mkcert nss
mkcert -install
```

### 2. Optional — rotate mkcert root CA (invalidates all prior mkcert certs on this PC)

```bash
rm -rf "$(mkcert -CAROOT)"
mkcert -install
```

### 3. Generate and install leaf cert (never inside git clone)

```bash
WORKDIR="$(mktemp -d /tmp/easelogs-certs.XXXXXX)"
STAMP="$(date +%Y%m%d-%H%M)"
cd "$WORKDIR"

mkcert -cert-file easelogs.local.pem -key-file easelogs.local-key.pem \
  easelogs.local localhost

openssl x509 -in easelogs.local.pem -noout -dates -ext subjectAltName

sudo cp -a /etc/nginx/certs/easelogs/easelogs.local+1.pem \
  "/etc/nginx/certs/easelogs/easelogs.local+1.pem.bak.${STAMP}"
sudo cp -a /etc/nginx/certs/easelogs/easelogs.local+1-key.pem \
  "/etc/nginx/certs/easelogs/easelogs.local+1-key.pem.bak.${STAMP}"

sudo install -m 644 easelogs.local.pem \
  /etc/nginx/certs/easelogs/easelogs.local+1.pem
sudo install -m 600 easelogs.local-key.pem \
  /etc/nginx/certs/easelogs/easelogs.local+1-key.pem

sudo nginx -t && sudo systemctl reload nginx
cd ~ && rm -rf "$WORKDIR"
```

**Never** paste `*-key.pem` or `rootCA-key.pem` contents into chat or commit them to git.

### 4. Verify

```bash
openssl x509 -in /etc/nginx/certs/easelogs/easelogs.local+1.pem -noout -dates -ext subjectAltName
curl -sI https://easelogs.local | head -3
```

---

## Trusting the mkcert root on other devices and browsers

Local HTTPS uses **mkcert**. Browsers only trust your sites after the **root CA** (`rootCA.pem`) is installed on each device. Install the **public root only** — never copy `rootCA-key.pem` or any `*-key.pem` leaf key.

### Locate the root on your workstation

```bash
mkcert -CAROOT
openssl x509 -in "$(mkcert -CAROOT)/rootCA.pem" -noout -subject -dates
```

Typical path: `~/.local/share/mkcert/rootCA.pem`

Transfer **`rootCA.pem`** to other devices via **LocalSend**, USB, temporary HTTP server on the LAN, or email. **AirDrop from Linux is not available.** Do not commit to git, email broadly, or paste contents into chat.

Temporary LAN transfer (stop the server when done):

```bash
cd "$(mkcert -CAROOT)"
python -m http.server 8765
```

On the phone/tablet browser, open `http://<WORKSTATION_LAN_IP>:8765/rootCA.pem` (type the address locally).

After you **rotate the mkcert root** (`rm -rf "$(mkcert -CAROOT)"` + `mkcert -install`), repeat installation on every phone/tablet and re-check desktop browsers.

---

### Workstation — Firefox (Manjaro / Linux)

1. Run `mkcert -install` once (trusts via system/NSS).
2. Settings → Privacy & Security → **View Certificates** → **Authorities** → **Import** → select `rootCA.pem`.
3. Enable **Trust this CA to identify websites**.
4. Remove any **older** mkcert authority (earlier “Not Before” date).
5. Optional: `about:config` → `security.enterprise_roots.enabled` = `true`.
6. **Servers** tab: remove any `easelogs.local` temporary certificate overrides.
7. Quit Firefox completely; reopen → `https://easelogs.local`.

---

### Workstation — Chrome / Chromium / Brave / Edge (Linux)

These use the **system trust store** on Linux when `mkcert -install` has run:

```bash
mkcert -install
sudo update-ca-trust
```

Restart the browser. If warnings persist, confirm only **one** current mkcert anchor exists under `/etc/ca-certificates/trust-source/anchors/` (see **Remove old mkcert roots** below).

### Workstation — curl / CLI

After `mkcert -install`, `curl https://easelogs.local` should verify without `-k`:

```bash
curl -sI https://easelogs.local | head -3
```

---

### iPhone / iPad (Safari)

**Prerequisites:** Phone on same Wi‑Fi; router DHCP DNS (or Wi‑Fi DNS) resolves `easelogs.local` to your workstation via dnsmasq. Use **`https://easelogs.local`**, not a raw IP URL.

1. Transfer **`rootCA.pem`** (LocalSend, USB, or temporary HTTP server — see above).
2. Open the file → **Allow** → **Install Profile** (Settings shows “Profile Downloaded”).
3. **Settings → General → VPN & Device Management** → install the mkcert profile if prompted.
4. **Settings → General → About → Certificate Trust Settings** → enable **full trust** for the mkcert root.
5. Open Safari → `https://easelogs.local`.

**Note:** `.local` hostnames may fail with “server not found” on iOS if mDNS overrides DNS. Re-check router DNS and dnsmasq `listen-address=`. If `.local` still fails, use [demo.easelogs.com](https://demo.easelogs.com/community/) for mobile testing.

---

### Android (Chrome)

**Prerequisites:** Same Wi‑Fi; DNS points `easelogs.local` to the workstation.

1. Copy **`rootCA.pem`** to the device (USB, Files, etc.).
2. **Settings → Security → Encryption & credentials → Install a certificate → CA certificate** (wording varies by Android version).
3. Confirm the security prompt (you are trusting a CA for Wi‑Fi browsing).
4. Select `rootCA.pem`.
5. Chrome → `https://easelogs.local`.

---

### Other tablets / browsers

| Client | Approach |
|--------|----------|
| **Firefox on mobile** | iOS Firefox cannot install custom CAs like desktop; use **Safari** on iOS or the public demo. |
| **Another Linux PC on LAN** | Copy `rootCA.pem`, run `mkcert -install` equivalent or import into browser; set DNS/hosts to reach the workstation. |
| **Public demo** | `https://demo.easelogs.com/community/` — Let's Encrypt, no mkcert on devices |

---

### Verify trust (any device)

- Padlock on `https://easelogs.local` with no “not secure” interstitial.
- Certificate **Issued By** shows mkcert development CA.
- After root rotation, **re-install** `rootCA.pem` on each mobile device and remove old mkcert roots on desktops.

---

## Remove old mkcert roots (workstation)

After root rotation, delete stale system anchors so Firefox does not restore old trust:

```bash
ls /etc/ca-certificates/trust-source/anchors/mkcert*.crt
openssl x509 -in "$(mkcert -CAROOT)/rootCA.pem" -noout -fingerprint -sha256
```

Compare fingerprints; remove **old** anchor files, then:

```bash
sudo update-ca-trust
certutil -d sql:$HOME/.pki/nssdb -L | grep mkcert
```

Delete duplicate mkcert nicknames in Firefox **Authorities** and in NSS if needed.

---

## Browser trust (workstation) — quick reference

See **Trusting the mkcert root** above for Firefox, Chrome, and mobile steps.

If an old mkcert root reappears in Firefox after restart, remove stale system anchors (section above).

---

## Mobile (LAN HTTPS) — quick reference

See **Trusting the mkcert root on other devices and browsers** above for iPhone and Android steps.

1. Same Wi‑Fi as workstation
2. Router LAN DHCP DNS → workstation (or Wi‑Fi DNS → `<WORKSTATION_LAN_IP>`)
3. Install **`rootCA.pem`**; enable full trust (iOS: Certificate Trust Settings)
4. Browse **`https://easelogs.local`** — not a raw IP URL
5. Allow `http`/`https`/`dns` on workstation firewall for LAN if needed:

```bash
sudo firewall-cmd --permanent --add-service={http,https,dns}
sudo firewall-cmd --reload
```

**Alternative without LAN DNS on phones:** `https://demo.easelogs.com/community/` (Let's Encrypt).

---

### When **not** to install mkcert on devices

| Scenario | Use instead |
|----------|-------------|
| Guest / untrusted device | Do not install your root CA |
| Quick mobile demo | `https://demo.easelogs.com/community/` (Let's Encrypt) |
| Production VPS | Let's Encrypt via certbot — no mkcert on clients |

---

## After rotation checklist

- [ ] Router LAN DHCP DNS → `<WORKSTATION_LAN_IP>`
- [ ] `/etc/dnsmasq.d/easelogs*.conf` — `address=` and `listen-address=` updated
- [ ] `/etc/dnsmasq.d/upstream.conf` — `server=` lines present; internet DNS works (`dig @127.0.0.1 google.com`)
- [ ] NetworkManager / `/etc/resolv.conf` on PC → `127.0.0.1` only
- [ ] `/etc/hosts` on PC: `127.0.0.1 easelogs.local`
- [ ] nginx `server_name easelogs.local` (no IP)
- [ ] New leaf cert dated today; SAN has no IP
- [ ] Old `.bak.*` cert files optional to delete from `/etc/nginx/certs/`
- [ ] git repo has no literal LAN IPs
- [ ] Stray `*.pem` / `*-key.pem` removed from project trees
- [ ] `rootCA.pem` re-installed on phones/tablets after root rotation (if applicable)

---

## Public demo (no LAN IP required)

For mobile testing without LAN DNS or mkcert on phones:

`https://demo.easelogs.com/community/`

Uses Let's Encrypt; no workstation IP exposure.
