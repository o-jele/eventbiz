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
