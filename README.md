# Glamorous — self-hosted app for HestiaCP

Two brands, one site: **Glamorous Creations** (retail baking supplies) +
**Glamorous Delights** (cakes, catering, rentals, events). Plain **PHP 8.1+ +
MySQL/MariaDB**, no Redis/Node/supervisor — runs as an ordinary HestiaCP
website on <4GB RAM. Domain model: `SPEC.md` (stack section superseded —
ERPNext/Frappe path kept under `eventbiz/` for reference only).

## Deploy (5 min + installer)

Upload the contents of `public_html/` to the HestiaCP web domain's
`public_html/`, or as root over SSH:

```bash
sudo ./deploy/hestia-setup.sh --user glamorous --domain glamorous.mw \
  --db glamorous --dbuser glamorous --dbpass 'STRONG-PASSWORD' --php 8.3 \
  --src /root/glamorous-bundle.zip
```

Then open `https://glamorous.mw/install.php` → enter DB details → create
admin → **delete install.php**. Full steps: `docs/hestia-deployment.md`.

## Local development (Windows)

Needs: PHP 8.1+ with `pdo_mysql`, Docker Desktop.

```powershell
# 1. Database (isolated MariaDB on port 3307)
docker run -d --name glamorous-db -p 127.0.0.1:3307:3306 `
  -e MARIADB_ROOT_PASSWORD=GlamDevRoot123 -e MARIADB_DATABASE=glamorous mariadb:10.6
& 'C:\Program Files\MariaDB 10.6\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root -pGlamDevRoot123 glamorous -e 'SOURCE public_html/app/database/schema.sql'
& 'C:\Program Files\MariaDB 10.6\bin\mysql.exe' -h 127.0.0.1 -P 3307 -u root -pGlamDevRoot123 glamorous -e 'SOURCE public_html/app/database/seed.sql'

# 2. Config: copy public_html/config.example.php -> public_html/config.php
#    (gitignored) and set db_host 127.0.0.1, db_port 3307, creds above.

# 3. Serve + open http://127.0.0.1:8000
php -S 127.0.0.1:8000 -t public_html dev-router.php
```

Flow: develop locally → `.\deploy\make-bundle.ps1` → `git push` → `git pull`
on the server → upload bundle → rerun `hestia-setup.sh` (or panel Files).

## Layout

```text
public_html/      # deployable bundle (docroot)
  index.php       # front controller + route table
  install.php     # web installer (DELETE after use)
  app/            # bootstrap, db, auth, business context, controllers, SQL
  assets/         # css
  uploads/        # payment proofs, cake refs (created on deploy)
config.php        # created by installer, NEVER committed
deploy/hestia-setup.sh
docs/hestia-deployment.md
```

## Routes → Company (never hard-coded elsewhere)

`/creations|shop|products|cart|checkout…` → Glamorous Creations ·
`/delights|cakes|catering|rentals|events|request…` → Glamorous Delights.
Single source: `public_html/app/context.php`.

## Local checks (no DB needed)

```bash
php -l public_html/index.php            # lint (do for every PHP file)
php <temp>/glam-smoke.php               # pure-logic smoke test
```
