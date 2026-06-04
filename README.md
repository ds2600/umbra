# Umbra
### Stream Control · Allegheny Eclipse

Self-hosted RTMP relay control panel. One camera in, push to YouTube, Facebook, and TikTok simultaneously.

---

## Fresh install — step by step

### Before you start

You need a VPS or server running Ubuntu 22.04 with:
- Root or sudo access
- A static IP address
- Ports **22**, **1935**, and **8080** open

---

### Step 1 — Wipe any previous Umbra install

If this is a brand new server, skip to Step 2.

If you had a previous version installed, run this first:

```bash
sudo systemctl stop nginx php8.1-fpm stunnel4 2>/dev/null || true

sudo rm -rf /var/www/umbra
sudo rm -rf /var/lib/umbra
sudo rm -f /etc/nginx/sites-enabled/umbra
sudo rm -f /etc/nginx/sites-available/umbra
sudo rm -f /etc/sudoers.d/umbra
sudo rm -f /usr/local/bin/umbra-reload
```

Then open nginx.conf and remove three things we added previously:

```bash
sudo nano /etc/nginx/nginx.conf
```

Remove:
- The `load_module modules/ngx_rtmp_module.so;` line
- The `server { listen 8088; ... }` stat block inside `http {}`
- The entire `rtmp { ... }` block after `http {}`

Then confirm nginx is clean:

```bash
sudo nginx -t && sudo systemctl start nginx
```

---

### Step 2 — Clone the repo

```bash
cd ~
git clone https://github.com/YOUR_USERNAME/umbra.git
cd umbra
```

---

### Step 3 — Run the installer

```bash
sudo bash install.sh
```

The installer automatically handles:
- Adding the ondrej/php PPA
- Installing nginx, libnginx-mod-rtmp, php8.1-fpm, php8.1-sqlite3, php8.1-xml, stunnel4, sqlite3
- Deploying web files to `/var/www/umbra/web/`
- Creating `/var/lib/umbra/` and initializing the SQLite database as `www-data`
- Installing and permissioning the reload script at `/usr/local/bin/umbra-reload`
- Configuring sudoers so `www-data` can run the reload script
- Enabling the nginx site on port 8080
- Configuring stunnel for Facebook and TikTok RTMPS

---

### Step 4 — Edit /etc/nginx/nginx.conf

This is the one step the installer cannot do automatically. Open the file:

```bash
sudo nano /etc/nginx/nginx.conf
```

You need to make three additions. The exact text to paste is in `nginx/nginx_additions.conf` in the repo.

**Addition 1** — Add this line before the `events {}` block:

```nginx
load_module modules/ngx_rtmp_module.so;
```

**Addition 2** — Add this server block inside the `http {}` block, before its closing `}`:

```nginx
server {
    listen 8088;
    server_name 127.0.0.1;
    location /stat {
        rtmp_stat all;
        allow 127.0.0.1;
        deny  all;
    }
}
```

**Addition 3** — Add this block after the closing `}` of the `http {}` block:

```nginx
rtmp {
  server {
    listen 1935;
    chunk_size 4096;
    application live {
      live on;
      record off;
      on_publish http://127.0.0.1:8080/api/auth_stream.php;
      include /var/lib/umbra/rtmp_pushes.conf;
    }
  }
}
```

---

### Step 5 — Test and reload nginx

```bash
sudo nginx -t
```

You must see:
```
nginx: configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

Then reload:

```bash
sudo systemctl reload nginx
```

---

### Step 6 — Open UFW

```bash
sudo ufw allow 22/tcp
sudo ufw allow 1935/tcp
sudo ufw allow 8080/tcp
sudo ufw enable
sudo ufw status verbose
```

If your VPS provider has a separate cloud firewall (DigitalOcean, AWS, Vultr), open the same three ports there too.

---

### Step 7 — Log in

Open in your browser:

```
http://YOUR_SERVER_IP:8080
```

Default credentials:

```
Username: admin
Password: umbra
```

---

### Step 8 — Change the password immediately

Always use single quotes to avoid shell variable expansion mangling passwords that contain `$`:

```bash
php -r '
$pdo  = new PDO("sqlite:/var/lib/umbra/umbra.db");
$hash = password_hash("YOUR_NEW_PASSWORD", PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password=? WHERE username=?")->execute([$hash,"admin"]);
echo "Done. Rows updated: " . $pdo->query("SELECT changes()")->fetchColumn() . PHP_EOL;
'
```

---

### Step 9 — Set your ingest key

In the dashboard, expand the **Ingest security** section. Copy the auto-generated key and set it as the stream key in your camera app:

- **Insta360:** Live → Custom RTMP → URL: `rtmp://YOUR_SERVER_IP/live` · Stream key: *(your key)*
- **Larix Broadcaster:** Settings → Connections → URL: `rtmp://YOUR_SERVER_IP/live/YOUR_KEY`

Bitrate: 4–6 Mbps recommended.

---

### Step 10 — Set your platform stream keys

In the **Platforms** section, enter the stream key for each platform and hit **Apply Changes**.

**YouTube**
studio.youtube.com → Create → Go Live → Stream
Enable **"Reuse stream key"** for a permanent key that survives session resets.

**Facebook**
facebook.com/live/producer → Go Live → Streaming software
Keys expire after 7 days of inactivity. Grab a fresh one before each event.

**TikTok**
Requires 1,000+ followers. TikTok app → + → Live → Cast/PC streaming.
Keys are session-based — get one right before going live.

---

## Updating

```bash
cd ~/umbra
sudo bash update.sh
```

Pulls the latest code, syncs files, updates the reload script and nginx config, reloads nginx. Your database, stream keys, and all settings are untouched.

---

## Updating stream URLs (if platforms change their endpoints)

If TikTok or Facebook change their RTMPS ingest URL:

1. Open the Umbra dashboard
2. On the relevant platform card, click **Advanced**
3. Update the **Stunnel destination** field with the new `hostname:port`
4. Hit **Apply Changes**

Umbra will rewrite the stunnel config and restart stunnel automatically. No SSH required.

The **Push URL** field (the local `rtmp://127.0.0.1:...` address) should only need changing if you move the stunnel port, which is rare.

---

## How RTMPS works

Facebook and TikTok require RTMPS (RTMP over TLS). nginx-rtmp doesn't support TLS natively, so stunnel runs as a local proxy:

| Platform | Local port | Tunnels to |
|----------|-----------|------------|
| Facebook | 127.0.0.1:19350 | live-api-s.facebook.com:443 |
| TikTok   | 127.0.0.1:19351 | push-rtmp-f5-tt01.tiktokcdn-us.com:443 |

nginx pushes plain RTMP to localhost, stunnel wraps it in TLS. Both the local push URL and the stunnel destination are configurable from the Umbra UI under Advanced on each platform card.

---

## File layout on the server

```
/var/www/umbra/web/              ← nginx web root (all PHP)
/var/lib/umbra/umbra.db          ← SQLite: users, stream keys, URLs, settings
/var/lib/umbra/rtmp_pushes.conf  ← nginx RTMP push destinations (Umbra writes this)
/var/lib/umbra/stunnel.conf      ← stunnel config staging (copied to /etc/stunnel on Apply)
/usr/local/bin/umbra-reload      ← privileged reload script
/etc/sudoers.d/umbra             ← www-data sudo grant for reload only
/etc/nginx/sites-available/umbra ← Umbra nginx site (port 8080)
/etc/stunnel/stunnel.conf        ← live stunnel config
```

---

## Troubleshooting

**UI shows 404**
```bash
ls /var/www/umbra/web/index.php          # must exist
grep root /etc/nginx/sites-available/umbra  # must say /var/www/umbra/web
sudo nginx -t && sudo systemctl reload nginx
```

**Camera connects but drops immediately**
```bash
curl -X POST http://127.0.0.1:8080/api/auth_stream.php \
  -d "name=YOUR_INGEST_KEY&app=live&addr=127.0.0.1"
# 200 = good, 403 = wrong key, 404 = routing problem

sudo tail -20 /var/log/nginx/error.log
```

**Apply Changes fails**
```bash
# Check /var/lib/umbra is writable by www-data
ls -la /var/lib/umbra/

# Test reload script directly
sudo -u www-data sudo /usr/local/bin/umbra-reload

# Check sudoers (must be 0440)
ls -la /etc/sudoers.d/umbra
sudo chmod 0440 /etc/sudoers.d/umbra && sudo visudo -c
```

**Ingest indicator always red**
```bash
curl http://127.0.0.1:8088/stat    # must return XML
sudo ss -tlnp | grep 8088
sudo nginx -t
```

**Facebook or TikTok not receiving stream**
```bash
sudo systemctl status stunnel4
sudo ss -tlnp | grep 1935          # should show 19350 and 19351
cat /var/lib/umbra/rtmp_pushes.conf
sudo tail -20 /var/log/nginx/error.log
```

**PHP / database errors**
```bash
sudo tail -f /var/log/php8.1-fpm.log
ls -la /var/lib/umbra/umbra.db     # must be owned by www-data
```

---

## Security checklist

- [ ] Default password changed
- [ ] Ingest key set and tested with camera app
- [ ] IP allowlist uncommented in `/etc/nginx/sites-available/umbra`
- [ ] UFW enabled: ports 22, 1935, 8080 only
- [ ] Cloud firewall matches UFW
- [ ] `/etc/sudoers.d/umbra` is mode `0440`
- [ ] `/var/lib/umbra/` owned by www-data

---

*Umbra — built for Allegheny Eclipse*
