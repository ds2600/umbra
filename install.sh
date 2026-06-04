#!/bin/bash
# Umbra installer — run once after cloning
# Usage: sudo bash install.sh
set -e

REPO_DIR="$(cd "$(dirname "$0")" && pwd)"
WEB_ROOT="/var/www/umbra"
DATA_DIR="/var/lib/umbra"
RELOAD_BIN="/usr/local/bin/umbra-reload"

echo ""
echo "╔══════════════════════════════════════╗"
echo "║           Umbra Installer            ║"
echo "╚══════════════════════════════════════╝"
echo ""

echo "==> [1/9] Installing packages"
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update -q
apt-get install -y nginx libnginx-mod-rtmp php8.1-fpm php8.1-sqlite3 php8.1-xml stunnel4 sqlite3

echo "==> [2/9] Deploying web files → $WEB_ROOT/web"
mkdir -p "$WEB_ROOT/web"
rsync -a --delete "$REPO_DIR/web/" "$WEB_ROOT/web/"
chown -R www-data:www-data "$WEB_ROOT"
chmod -R 750 "$WEB_ROOT"

echo "==> [3/9] Setting up data directory → $DATA_DIR"
mkdir -p "$DATA_DIR"
touch "$DATA_DIR/rtmp_pushes.conf"
chown -R www-data:www-data "$DATA_DIR"
chmod 750 "$DATA_DIR"

echo "==> [4/9] Initializing database"
sudo -u www-data php -r "
require '$WEB_ROOT/web/api/db.php';
new UmbraDB();
echo '    OK: /var/lib/umbra/umbra.db' . PHP_EOL;
"

echo "==> [5/9] Installing reload script"
cp "$REPO_DIR/scripts/umbra-reload.sh" "$RELOAD_BIN"
chmod 755 "$RELOAD_BIN"
chown root:root "$RELOAD_BIN"

echo "==> [6/9] Configuring sudoers"
echo "www-data ALL=(root) NOPASSWD: $RELOAD_BIN" > /etc/sudoers.d/umbra
chmod 0440 /etc/sudoers.d/umbra
visudo -c

echo "==> [7/9] Installing nginx site"
cp "$REPO_DIR/nginx/umbra.conf" /etc/nginx/sites-available/umbra
if [ ! -L /etc/nginx/sites-enabled/umbra ]; then
    ln -s /etc/nginx/sites-available/umbra /etc/nginx/sites-enabled/umbra
fi

echo "==> [8/9] Configuring stunnel (Facebook + TikTok RTMPS)"
sed -i 's/^ENABLED=0/ENABLED=1/' /etc/default/stunnel4
# Write initial stunnel config (Umbra will manage this going forward)
cat > /etc/stunnel/stunnel.conf << 'STUNNEL'
[facebook-rtmp]
client  = yes
accept  = 127.0.0.1:19350
connect = live-api-s.facebook.com:443

[tiktok-rtmp]
client  = yes
accept  = 127.0.0.1:19351
connect = push-rtmp-f5-tt01.tiktokcdn-us.com:443
STUNNEL
systemctl enable stunnel4
systemctl restart stunnel4

echo "==> [9/9] Enabling PHP-FPM"
systemctl enable php8.1-fpm
systemctl start php8.1-fpm

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  Automated steps done. Two manual steps remain:                  ║"
echo "╠══════════════════════════════════════════════════════════════════╣"
echo "║                                                                  ║"
echo "║  STEP A: Edit /etc/nginx/nginx.conf                              ║"
echo "║          See nginx/nginx_additions.conf for exact copy/paste     ║"
echo "║                                                                  ║"
echo "║  STEP B: Test and reload nginx                                   ║"
echo "║          nginx -t && systemctl reload nginx                      ║"
echo "║                                                                  ║"
echo "╠══════════════════════════════════════════════════════════════════╣"
echo "║  Then open: http://YOUR_SERVER_IP:8080                           ║"
echo "║  Login: admin / umbra  ← CHANGE THIS PASSWORD IMMEDIATELY       ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
