#!/bin/bash
# Umbra installer
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

# ── Packages ──────────────────────────────────────────────────────────────────
echo "==> [1/8] Installing packages"
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update -q
apt-get install -y nginx libnginx-mod-rtmp php8.1-fpm php8.1-sqlite3 php8.1-xml stunnel4 sqlite3

# ── Web root ──────────────────────────────────────────────────────────────────
echo "==> [2/8] Deploying web files to $WEB_ROOT/web"
mkdir -p "$WEB_ROOT/web"
rsync -a --delete "$REPO_DIR/web/" "$WEB_ROOT/web/"
chown -R www-data:www-data "$WEB_ROOT"
chmod -R 750 "$WEB_ROOT"

# ── Runtime data dir ──────────────────────────────────────────────────────────
echo "==> [3/8] Setting up $DATA_DIR"
mkdir -p "$DATA_DIR"
touch "$DATA_DIR/rtmp_pushes.conf"
chown -R www-data:www-data "$DATA_DIR"
chmod 750 "$DATA_DIR"

# ── Initialize database as www-data ───────────────────────────────────────────
echo "==> [4/8] Initializing database"
sudo -u www-data php -r "
require '$WEB_ROOT/web/api/db.php';
new UmbraDB();
echo '    Database ready at /var/lib/umbra/umbra.db' . PHP_EOL;
"

# ── Reload script + sudoers ───────────────────────────────────────────────────
echo "==> [5/8] Installing reload script"
cp "$REPO_DIR/scripts/umbra-reload.sh" "$RELOAD_BIN"
chmod 755 "$RELOAD_BIN"
chown root:root "$RELOAD_BIN"

echo "==> [6/8] Configuring sudoers"
echo "www-data ALL=(root) NOPASSWD: $RELOAD_BIN" > /etc/sudoers.d/umbra
chmod 0440 /etc/sudoers.d/umbra
visudo -c

# ── nginx site ────────────────────────────────────────────────────────────────
echo "==> [7/8] Installing nginx site config"
cp "$REPO_DIR/nginx/umbra.conf" /etc/nginx/sites-available/umbra
if [ ! -L /etc/nginx/sites-enabled/umbra ]; then
    ln -s /etc/nginx/sites-available/umbra /etc/nginx/sites-enabled/umbra
fi

# ── stunnel ───────────────────────────────────────────────────────────────────
echo "==> [8/8] Configuring stunnel"
sed -i 's/^ENABLED=0/ENABLED=1/' /etc/default/stunnel4
cp "$REPO_DIR/nginx/stunnel.conf" /etc/stunnel/stunnel.conf
systemctl enable stunnel4
systemctl restart stunnel4

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  Automated setup complete. Two manual steps remain:          ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║                                                              ║"
echo "║  STEP A — Edit /etc/nginx/nginx.conf                        ║"
echo "║                                                              ║"
echo "║  1. Add BEFORE the events {} block:                         ║"
echo "║     load_module modules/ngx_rtmp_module.so;                 ║"
echo "║                                                              ║"
echo "║  2. Add INSIDE the http {} block:                           ║"
echo "║     server {                                                 ║"
echo "║       listen 8088;                                           ║"
echo "║       server_name 127.0.0.1;                                ║"
echo "║       location /stat {                                       ║"
echo "║         rtmp_stat all;                                       ║"
echo "║         allow 127.0.0.1;                                     ║"
echo "║         deny all;                                            ║"
echo "║       }                                                      ║"
echo "║     }                                                        ║"
echo "║                                                              ║"
echo "║  3. Add AFTER the closing } of the http {} block:           ║"
echo "║     rtmp {                                                   ║"
echo "║       server {                                               ║"
echo "║         listen 1935;                                         ║"
echo "║         chunk_size 4096;                                     ║"
echo "║         application live {                                   ║"
echo "║           live on;                                           ║"
echo "║           record off;                                        ║"
echo "║           on_publish http://127.0.0.1:8080/api/auth_stream.php; ║"
echo "║           include /var/lib/umbra/rtmp_pushes.conf;           ║"
echo "║         }                                                    ║"
echo "║       }                                                      ║"
echo "║     }                                                        ║"
echo "║                                                              ║"
echo "║  (See nginx/nginx_additions.conf in the repo to copy/paste) ║"
echo "║                                                              ║"
echo "║  STEP B — Test and reload nginx                              ║"
echo "║     nginx -t && systemctl reload nginx                       ║"
echo "║                                                              ║"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  Then open: http://YOUR_SERVER_IP:8080                       ║"
echo "║  Login: admin / umbra  ← CHANGE THIS IMMEDIATELY            ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
