#!/bin/bash
# Umbra installer — run once on a fresh server after cloning the repo
# Usage: sudo bash install.sh
set -e

REPO_DIR="$(cd "$(dirname "$0")" && pwd)"
WEB_ROOT="/var/www/umbra"
DATA_DIR="/var/lib/umbra"
RELOAD_SCRIPT="/usr/local/bin/umbra-reload"

echo "==> Installing system packages"
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y nginx libnginx-mod-rtmp php8.1-fpm php8.1-sqlite3 php8.1-xml stunnel4 sqlite3

echo "==> Creating directories"
mkdir -p "$WEB_ROOT"
mkdir -p "$DATA_DIR"

echo "==> Copying web files"
cp -r "$REPO_DIR/web/"* "$WEB_ROOT/"
chown -R www-data:www-data "$WEB_ROOT"
chmod 750 "$WEB_ROOT"

echo "==> Setting up data directory"
chown -R www-data:www-data "$DATA_DIR"
chmod 750 "$DATA_DIR"

echo "==> Installing reload script"
cp "$REPO_DIR/scripts/umbra-reload.sh" "$RELOAD_SCRIPT"
chmod 755 "$RELOAD_SCRIPT"
chown root:root "$RELOAD_SCRIPT"

echo "==> Configuring sudoers"
echo "www-data ALL=(root) NOPASSWD: $RELOAD_SCRIPT" > /etc/sudoers.d/umbra
chmod 0440 /etc/sudoers.d/umbra
visudo -c

echo "==> Setting up runtime files"
touch "$DATA_DIR/rtmp_pushes.conf"
chown www-data:www-data "$DATA_DIR/rtmp_pushes.conf"

echo "==> Initializing database"
sudo -u www-data php -r "
require '$WEB_ROOT/api/db.php';
new UmbraDB();
echo 'Database initialized.' . PHP_EOL;
"

echo "==> Installing nginx site config"
cp "$REPO_DIR/nginx/umbra.conf" /etc/nginx/sites-available/umbra
if [ ! -L /etc/nginx/sites-enabled/umbra ]; then
    ln -s /etc/nginx/sites-available/umbra /etc/nginx/sites-enabled/umbra
fi

echo ""
echo "======================================================"
echo "  Next steps:"
echo ""
echo "  1. Edit /etc/nginx/nginx.conf:"
echo "     - Add: load_module modules/ngx_rtmp_module.so;"
echo "       (before the events {} block)"
echo "     - Add the stat server block inside http {}:"
echo "         server {"
echo "             listen 8088;"
echo "             server_name 127.0.0.1;"
echo "             location /stat {"
echo "                 rtmp_stat all;"
echo "                 allow 127.0.0.1;"
echo "                 deny  all;"
echo "             }"
echo "         }"
echo "     - Add the rtmp {} block after the http {} block:"
echo "         rtmp {"
echo "           server {"
echo "             listen 1935;"
echo "             chunk_size 4096;"
echo "             application live {"
echo "               live on;"
echo "               record off;"
echo "               on_publish http://127.0.0.1:8080/api/auth_stream.php;"
echo "               include /var/lib/umbra/rtmp_pushes.conf;"
echo "             }"
echo "           }"
echo "         }"
echo ""
echo "  2. Configure stunnel:"
echo "     Edit /etc/default/stunnel4 — set ENABLED=1"
echo "     Edit /etc/stunnel/stunnel.conf — add:"
echo "       [facebook-rtmp]"
echo "       client  = yes"
echo "       accept  = 127.0.0.1:19350"
echo "       connect = live-api-s.facebook.com:443"
echo "     Then: systemctl enable stunnel4 && systemctl start stunnel4"
echo ""
echo "  3. Lock down the UI (edit /etc/nginx/sites-available/umbra):"
echo "     Uncomment and set: allow YOUR_IP; deny all;"
echo ""
echo "  4. Test and reload nginx:"
echo "     nginx -t && systemctl reload nginx"
echo ""
echo "  5. Open http://YOUR_SERVER_IP:8080"
echo "     Login: admin / umbra — CHANGE THIS PASSWORD IMMEDIATELY"
echo ""
echo "  See README.md for full instructions."
echo "======================================================"
