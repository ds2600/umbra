#!/bin/bash
# Umbra updater — run to pull latest code from GitHub and apply it
# Usage: sudo bash update.sh
set -e

REPO_DIR="$(cd "$(dirname "$0")" && pwd)"
WEB_ROOT="/var/www/umbra"

echo "==> Pulling latest code"
git -C "$REPO_DIR" pull

echo "==> Syncing web files"
# Sync web/ to the live web root, preserving data/
rsync -av --delete \
    --exclude='data/' \
    "$REPO_DIR/web/" "$WEB_ROOT/web/"

echo "==> Fixing permissions"
chown -R www-data:www-data "$WEB_ROOT"
chmod 750 "$WEB_ROOT"

echo "==> Updating reload script"
cp "$REPO_DIR/scripts/umbra-reload.sh" /usr/local/bin/umbra-reload
chmod 755 /usr/local/bin/umbra-reload
chown root:root /usr/local/bin/umbra-reload

echo "==> Updating nginx site config"
cp "$REPO_DIR/nginx/umbra.conf" /etc/nginx/sites-available/umbra

echo "==> Testing and reloading nginx"
nginx -t && systemctl reload nginx

echo ""
echo "==> Umbra updated successfully."
