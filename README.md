# Umbra
### Stream Control · Allegheny Eclipse

Self-hosted RTMP relay control panel. One camera in, simultaneous push to YouTube, Facebook, and TikTok.

---

## Wiping an old install and starting clean

If you have a previous version installed, run this first to remove everything:

```bash
# Stop services
sudo systemctl stop nginx php8.1-fpm stunnel4

# Remove web files and runtime data
sudo rm -rf /var/www/umbra
sudo rm -rf /var/lib/umbra

# Remove nginx site
sudo rm -f /etc/nginx/sites-enabled/umbra
sudo rm -f /etc/nginx/sites-available/umbra

# Remove sudoers entry
sudo rm -f /etc/sudoers.d/umbra

# Remove reload script
sudo rm -f /usr/local/bin/umbra-reload

# Restore nginx.conf to default (remove load_module, stat server, rtmp block)
sudo nano /etc/nginx/nginx.conf

# Restart nginx clean
sudo nginx -t && sudo systemctl start nginx
```

Then clone fresh and run the installer below.

---

## Fresh install

### 1 — Clone the repo

```bash
cd ~
git clone https://github.com/YOUR_USERNAME/umbra.git
cd umbra
```

### 2 — Run the installer

```bash
sudo bash install.sh
```

The installer handles everything automatically:
- Adds the ondrej/php PPA and installs all packages
- Deploys web files to `/var/www/umbra/web/`
- Creates `/var/lib/umbra/` for the database and push config
- Initializes the SQLite database as `www-data`
- Installs and permissions the reload script
- Configures sudoers
- Enables the nginx site
- Configures and starts stunnel for Facebook and TikTok RTMPS

### 3 — Edit /etc/nginx/nginx.conf (manual — two additions)

```bash
sudo nano /etc/nginx/nginx.conf
```

**Addition 1** — before the `events {}` block:
```nginx
load_module modules/ngx_rtmp_module.so;
```

**Addition 2** — inside the `http {}` block, before its closing `}`:
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

**Addition 3** — after the closing `}` of the `http {}` block:
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

These are also in `nginx/nginx_additions.conf` in the repo for easy copy/paste.

### 4 — Test and reload nginx

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 5 — Open the UI

```
http://YOUR_SERVER_IP:8080
```

Default login: `admin` / `umbra`

**Change the password immediately.** Use single quotes to avoid shell variable expansion issues with special characters like `$`:

```bash
php -r '
$pdo  = new PDO("sqlite:/var/lib/umbra/umbra.db");
$hash = password_hash("YOUR_NEW_PASSWORD", PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password=? WHERE username=?")->execute([$hash,"admin"]);
echo "Done: " . $pdo->query("SELECT changes()")->fetchColumn() . " row(s) updated." . PHP_EOL;
'
```

---

## Updating

```bash
cd ~/umbra
sudo bash update.sh
```

Pulls latest code, syncs files, updates configs, reloads nginx. Your database and stream keys are untouched.

---

## Setting your ingest key

Umbra auto-generates a random ingest key on first run. Find it in the **Ingest Security** section.

Set this as the **stream key** in your camera app:

- **RTMP URL:** `rtmp://YOUR_SERVER_IP/live`
- **Stream key:** *(copy from Umbra dashboard)*

**Insta360:** Live → Custom RTMP → paste URL and key, set bitrate 4–6 Mbps

**Larix Broadcaster:** Settings → Connections → Add → URL: `rtmp://YOUR_SERVER_IP/live/YOUR_KEY` *(Larix puts the key in the URL path)*

Rotate the key any time with the **↻ Regenerate** button, then hit Apply.

---

## Platform stream keys

**YouTube**
studio.youtube.com → Create → Go Live → Stream
Enable **"Reuse stream key"** for a permanent key.

**Facebook**
facebook.com/live/producer → Go Live → Streaming software
Keys expire after 7 days of inactivity — grab a fresh one before each event.

**TikTok**
Requires 1,000+ followers. TikTok app → + → Live → Cast/PC streaming
Keys are session-based — get one right before going live.

---

## How RTMPS works (Facebook + TikTok)

Both Facebook and TikTok require RTMPS (RTMP over TLS). nginx-rtmp doesn't support TLS natively, so stunnel runs as a local proxy:

| Platform | stunnel port | Destination |
|----------|-------------|-------------|
| Facebook | 127.0.0.1:19350 | live-api-s.facebook.com:443 |
| TikTok   | 127.0.0.1:19351 | push-rtmp-f5-tt01.tiktokcdn-us.com:443 |

nginx pushes plain RTMP to localhost on those ports, and stunnel wraps it in TLS. Config is in `nginx/stunnel.conf` and is deployed automatically by the installer and `update.sh`.

---

## File layout on the server

```
/var/www/umbra/web/        ← nginx root, all PHP served from here
/var/lib/umbra/umbra.db    ← SQLite database (stream keys, settings, auth)
/var/lib/umbra/rtmp_pushes.conf  ← nginx RTMP push destinations (written by Umbra)
/usr/local/bin/umbra-reload      ← privileged reload script
/etc/sudoers.d/umbra             ← grants www-data sudo for reload only
/etc/nginx/sites-available/umbra ← Umbra nginx site (port 8080)
/etc/stunnel/stunnel.conf        ← RTMPS tunnels for Facebook + TikTok
```

---

## Troubleshooting

**UI shows 404**
```bash
ls /var/www/umbra/web/index.php    # file must exist here
grep root /etc/nginx/sites-available/umbra  # must say /var/www/umbra/web
sudo nginx -t && sudo systemctl reload nginx
```

**Camera connects but drops immediately**
```bash
# Test auth endpoint
curl -X POST http://127.0.0.1:8080/api/auth_stream.php \
  -d "name=YOUR_INGEST_KEY&app=live&addr=127.0.0.1"
# Must return 200. 404 = nginx routing wrong. 403 = wrong key.

sudo tail -20 /var/log/nginx/error.log
```

**Apply Changes fails**
```bash
# Check permissions
ls -la /var/lib/umbra/
# rtmp_pushes.conf must be owned by www-data

# Test reload script
sudo -u www-data sudo /usr/local/bin/umbra-reload

# Check sudoers
ls -la /etc/sudoers.d/umbra   # must be 0440
sudo visudo -c
```

**Ingest indicator always red**
```bash
curl http://127.0.0.1:8088/stat   # must return XML
sudo ss -tlnp | grep 8088
```

**Facebook / TikTok not receiving stream**
```bash
sudo systemctl status stunnel4
sudo ss -tlnp | grep 1935   # should show 19350 and 19351
cat /var/lib/umbra/rtmp_pushes.conf  # should have push lines
sudo tail -20 /var/log/nginx/error.log
```

**PHP / database errors**
```bash
sudo tail -f /var/log/php8.1-fpm.log
# Verify database exists and is owned by www-data
ls -la /var/lib/umbra/umbra.db
```

---

## Security checklist

- [ ] Default password changed (use single quotes in php -r command)
- [ ] Ingest key set and tested
- [ ] IP allowlist set in `/etc/nginx/sites-available/umbra`
- [ ] UFW enabled: ports 22, 1935, 8080 only
- [ ] Cloud firewall matches UFW if applicable
- [ ] `/etc/sudoers.d/umbra` is mode `0440`

---

*Umbra — built for Allegheny Eclipse*
