# HestiaCP deployment (self-hosted PHP app)

Requirements on the server: HestiaCP with PHP 8.1+ and MySQL/MariaDB (both standard).
No Redis, no Node, no supervisor — this is why the app runs fine on <4GB RAM.

## Option A — automatic (root over SSH, recommended)

```bash
# 1. Copy this repo to the server, then:
unzip glamorous-hestia.zip   # or: git clone <repo> && cd repo && zip bundle from public_html/
sudo ./deploy/hestia-setup.sh --user glamorous --domain glamorous.mw \
  --db glamorous --dbuser glamorous --dbpass 'STRONG-PASSWORD' --php 8.3 \
  --src /root/glamorous-bundle.zip   # zip of public_html/ CONTENTS
```

Then open `https://glamorous.mw/install.php`, finish the installer, and **delete install.php**.

## Option B — manual (panel only)

1. HestiaCP → User `glamorous` → **Web** → Add domain `glamorous.mw` (enable SSL / Let's Encrypt).
2. Set **Backend Template / PHP version** to 8.1+.
3. HestiaCP → **DB** → Add database `glamorous` + user + strong password.
4. **Files** (or SFTP): upload the contents of `public_html/` into
   `/home/glamorous/web/glamorous.mw/public_html/`.
5. Open `https://glamorous.mw/install.php` → enter DB details → create admin → **delete install.php**.

## Nginx + PHP-FPM note (Hestia default)

Pretty URLs (`/creations/shop`, `/delights/...`) work out of the box on Apache.
On stock Hestia **Nginx+Apache hybrid** they also work via `.htaccess`.
On **Nginx-only** templates, add to the domain's nginx config:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ ^/(app|config\.php) { deny all; }
```

## After install

- `/login` → Staff → Items: build the catalogue, set stock, publish to website.
- Staff → Users: create `creations_staff`, `delights_sales`, `delights_ops`, `accounts` logins.
- Verify: place a test `/creations` order → declare payment → approve in Staff → Payments.
- Backups: HestiaCP user backup covers files + DB. Download one after go-live.

## Security checklist

- [ ] `install.php` deleted
- [ ] `config.php` is 640 and never committed to git
- [ ] Admin password 12+ chars; staff temp passwords changed at first login
- [ ] HestiaCP panel + `glamorous` passwords rotated (they were shared in chat)
- [ ] GitHub token used for push revoked if it was ever valid
