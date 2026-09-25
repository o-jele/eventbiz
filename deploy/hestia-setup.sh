#!/bin/bash
# HestiaCP self-hosted setup for the Glamorous PHP app. Run as ROOT over SSH.
# Usage:
#   sudo ./hestia-setup.sh --user glamorous --domain glamorous.mw --db glamorous \
#       --dbuser glamorous --dbpass 'STRONG-PASSWORD' [--php 8.3] [--src /root/glamorous-bundle.zip]
#
# What it does:
#   1. Creates the Hestia user + web domain (+ DB) if missing.
#   2. Unpacks the app bundle (contents of public_html/) into public_html.
#   3. Sets PHP version, ownership and permissions.
#   4. Prints next steps (open /install.php, then DELETE it).
set -euo pipefail

USER=""; DOMAIN=""; DB=""; DBUSER=""; DBPASS=""; PHPV="8.3"; SRC=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --user) USER="$2"; shift 2;;
    --domain) DOMAIN="$2"; shift 2;;
    --db) DB="$2"; shift 2;;
    --dbuser) DBUSER="$2"; shift 2;;
    --dbpass) DBPASS="$2"; shift 2;;
    --php) PHPV="$2"; shift 2;;
    --src) SRC="$2"; shift 2;;
    *) echo "Unknown arg: $1"; exit 1;;
  esac
done
[[ -z "$USER" || -z "$DOMAIN" || -z "$DB" || -z "$DBUSER" || -z "$DBPASS" ]] && {
  echo "Missing required args. See header usage."; exit 1; }
[[ $EUID -ne 0 ]] && { echo "Run as root."; exit 1; }

if [[ -d /usr/local/hestia/bin ]]; then
  BIN=/usr/local/hestia/bin
elif [[ -x /usr/local/vesta/bin/v-list-users ]]; then
  BIN=/usr/local/vesta/bin
else
  echo "HestiaCP not found (looked in /usr/local/hestia and /usr/local/vesta)."
  exit 1
fi

if ! "$BIN/v-list-users" | grep -q "^$USER:"; then
  echo "-> Creating Hestia user $USER (you will set its password in the panel)"
  "$BIN/v-add-user" "$USER" "$(openssl rand -base64 18)" "info@$DOMAIN" default
fi
if ! "$BIN/v-list-web-domains" "$USER" 2>/dev/null | grep -q "$DOMAIN"; then
  echo "-> Adding web domain $DOMAIN"
  "$BIN/v-add-web-domain" "$USER" "$DOMAIN"
fi
if ! "$BIN/v-list-databases" "$USER" 2>/dev/null | grep -q "$DB"; then
  echo "-> Creating database $DB"
  "$BIN/v-add-database" "$USER" "$DB" "$DBUSER" "$DBPASS" mysql
fi
echo "-> Setting PHP $PHPV backend template"
"$BIN/v-change-web-domain-tpl" "$USER" "$DOMAIN" "default" "PHP-$PHPV" 2>/dev/null || true

WEBROOT="/home/$USER/web/$DOMAIN/public_html"
[[ -n "$SRC" && -f "$SRC" ]] && {
  echo "-> Unpacking bundle into $WEBROOT"
  unzip -oq "$SRC" -d "$WEBROOT"
}
chown -R "$USER:$USER" "$WEBROOT"
find "$WEBROOT" -type d -exec chmod 755 {} \;
find "$WEBROOT" -type f -exec chmod 644 {} \;
mkdir -p "$WEBROOT/uploads"
chown -R "$USER:www-data" "$WEBROOT/uploads"
chmod 775 "$WEBROOT/uploads"

echo
echo "Done. Next steps:"
echo "  1. Visit https://$DOMAIN/install.php and complete the installer"
echo "     (DB host=localhost, DB name=$DB / user=$DBUSER)."
echo "  2. DELETE install.php from $WEBROOT immediately afterwards."
echo "  3. Log in at https://$DOMAIN/login and build your catalogue in Staff → Items."
