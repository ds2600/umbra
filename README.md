# Umbra
### Stream Control · Allegheny Eclipse

Umbra is a self-hosted, single-page stream relay control panel. Connect a camera or phone via RTMP using a private ingest key, and Umbra rebroadcasts your feed simultaneously to YouTube, Facebook, and TikTok — with a live status dashboard, real-time ingest monitoring, per-platform controls, and system stats.

---

## What's Inside

```
umbra/
├── index.php                  # Single-page app (login + dashboard)
├── api/
│   ├── db.php                 # SQLite database layer
│   ├── auth.php               # Web login / session auth
│   ├── auth_stream.php        # RTMP ingest key validator (nginx on_publish)
│   ├── get_config.php         # Load saved keys + settings
│   ├── apply.php              # Save settings, write nginx include, reload
│   ├── ingest_status.php      # Real-time RTMP ingest status
│   └── stats.php              # CPU / memory / network stats
├── data/                      # SQLite database (auto-created, web-inaccessible)
├── umbra-reload.sh            # Privileged reload script (root via sudoers)
└── umbra-nginx.conf           # nginx site block for the control panel
```

---

## How ingest security works

nginx-rtmp's `on_publish` directive fires an HTTP POST to `auth_stream.php` every time a camera tries to connect. The POST includes the stream key the client provided as `name`. If the key matches what's stored in SQLite, the script returns HTTP 200 and nginx allows the stream. Any other response and nginx drops the connection immediately — the camera gets a connection refused.

You set the ingest key in the **Insta360 app** (or Larix, etc.) as the **stream key** field. The RTMP URL stays as `rtmp://YOUR_SERVER_IP/live`.

You can rotate the key any time from the Umbra dashboard using the Regenerate button, then hit Apply. The old key stops working the moment nginx reloads.

---

## Prerequisites

- Ubuntu 22.04 (or 20.04)
- A static IP or domain name
- Root / sudo access
- Ports open: **22** (SSH), **1935** (RTMP ingest), **8080** (Umbra UI)

---

## Step 1 — System update

```bash
sudo apt update && sudo apt upgrade -y
```

---

## Step 2 — Install packages

```bash
sudo apt install -y nginx libnginx-mod-rtmp php8.1-fpm php8.1-sqlite3 stunnel4
```

Verify RTMP module is present:
```bash
ls /usr/lib/nginx/modules/ | grep rtmp
# → ngx_rtmp_module.so
```

---

## Step 3 — Configure nginx

### 3a — Back up and edit the main config

```bash
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.bak
sudo nano /etc/nginx/nginx.conf
```

Near the top, before the `events {}` block, ensure the RTMP module loads:

```nginx
load_module modules/ngx_rtmp_module.so;
```

Inside the existing `http {}` block, add the internal stat server:

```nginx
server {
    listen 8080;
    server_name 127.0.0.1;

    location /stat {
        rtmp_stat all;
        allow 127.0.0.1;
        deny  all;
    }
}
```

At the **very bottom**, after the closing `}` of the `http {}` block, add the RTMP block:

```nginx
rtmp {
  server {
    listen 1935;
    chunk_size 4096;

    application live {
      live on;
      record off;

      # Ingest key validation — rejects cameras with wrong key
      on_publish http://127.0.0.1:8080/api/auth_stream.php;

      # Platform push destinations — written by Umbra on Apply
      include /etc/umbra/rtmp_pushes.conf;
    }
  }
}
```

### 3b — Create the nginx include directory

```bash
sudo mkdir -p /etc/umbra
sudo touch /etc/umbra/rtmp_pushes.conf
```

### 3c — Test and reload nginx

```bash
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl enable nginx
```

---

## Step 4 — Configure stunnel (Facebook RTMPS)

Facebook requires RTMPS (RTMP over TLS). stunnel wraps the connection locally.

```bash
sudo nano /etc/default/stunnel4
```
Set:
```
ENABLED=1
```

```bash
sudo nano /etc/stunnel/stunnel.conf
```
Add:
```ini
[facebook-rtmp]
client  = yes
accept  = 127.0.0.1:19350
connect = live-api-s.facebook.com:443
```

```bash
sudo systemctl enable stunnel4
sudo systemctl start stunnel4
```

Verify:
```bash
sudo ss -tlnp | grep 19350
```

---

## Step 5 — Deploy Umbra

### 5a — Copy files to web root

```bash
sudo mkdir -p /var/www/umbra
sudo cp -r /path/to/umbra/* /var/www/umbra/
sudo mkdir -p /var/www/umbra/data
sudo chown -R www-data:www-data /var/www/umbra
sudo chmod 750 /var/www/umbra/data
```

### 5b — Install the nginx site config

```bash
sudo cp /var/www/umbra/umbra-nginx.conf /etc/nginx/sites-available/umbra
sudo ln -s /etc/nginx/sites-available/umbra /etc/nginx/sites-enabled/umbra
```

Lock it down to your IP (strongly recommended):
```bash
sudo nano /etc/nginx/sites-available/umbra
```

Uncomment and set:
```nginx
allow 203.0.113.42;   # your home / office IP
deny  all;
```

Optional — add HTTP basic auth as a second layer:
```bash
sudo apt install -y apache2-utils
sudo htpasswd -c /etc/nginx/.umbra_htpasswd umbra
```
Then uncomment in the site config:
```nginx
auth_basic           "Umbra";
auth_basic_user_file /etc/nginx/.umbra_htpasswd;
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## Step 6 — Install the reload script

```bash
sudo cp /var/www/umbra/umbra-reload.sh /usr/local/bin/umbra-reload
sudo chmod 755 /usr/local/bin/umbra-reload
sudo chown root:root /usr/local/bin/umbra-reload
```

Grant `www-data` passwordless sudo for **only this script**:

```bash
sudo visudo -f /etc/sudoers.d/umbra
```

Add exactly:
```
www-data ALL=(root) NOPASSWD: /usr/local/bin/umbra-reload
```

Verify:
```bash
sudo visudo -c
```

---

## Step 7 — Configure UFW

```bash
sudo ufw allow 22/tcp      # SSH — do this FIRST
sudo ufw allow 1935/tcp    # RTMP ingest
sudo ufw allow 8080/tcp    # Umbra UI
sudo ufw enable
sudo ufw status verbose
```

> If your VPS has a cloud firewall (DigitalOcean, AWS, Vultr), open the same ports there too.

---

## Step 8 — First login

```
http://YOUR_SERVER_IP:8080
```

Default credentials:
```
Username: admin
Password: umbra
```

**Change the password immediately:**

```bash
cd /var/www/umbra
php -r "
require 'api/db.php';
\$pdo = new PDO('sqlite:data/umbra.db');
\$hash = password_hash('YOUR_NEW_PASSWORD', PASSWORD_DEFAULT);
\$pdo->prepare('UPDATE users SET password=? WHERE username=?')->execute([\$hash,'admin']);
echo 'Password updated.' . PHP_EOL;
"
```

---

## Step 9 — Set your ingest key

On first run, Umbra auto-generates a random 32-character ingest key. You'll see it in the **Ingest Security** section of the dashboard.

1. Copy the key (use the Copy button)
2. In your camera app:
   - **RTMP URL:** `rtmp://YOUR_SERVER_IP/live`
   - **Stream key:** *(paste the key from Umbra)*
3. Click **Apply Changes** to make it active

To rotate the key at any time, click **↻ Regenerate**, then Apply. The old key stops working immediately after nginx reloads.

### Camera app settings

**Insta360 app:**
- Live → Custom RTMP
- Server URL: `rtmp://YOUR_SERVER_IP/live`
- Stream key: *(your Umbra ingest key)*
- Bitrate: 4–6 Mbps

**Larix Broadcaster (iOS/Android — good for testing):**
- Settings → Connections → Add connection
- URL: `rtmp://YOUR_SERVER_IP/live/YOUR_INGEST_KEY`
  *(Larix appends the key to the URL path rather than a separate field)*

---

## Step 10 — Set your platform stream keys

In the **Platforms** section of the dashboard, enter the stream key for each platform you want to push to. Toggle platforms on or off individually.

**YouTube:** studio.youtube.com → Create → Go Live → Stream → enable "Reuse stream key" for a permanent key

**Facebook:** facebook.com/live/producer → Go Live → Streaming software → copy stream key (expires after 7 days of inactivity — regenerate before each event)

**TikTok:** Requires 1,000+ followers. Open TikTok app → + → Live → Cast/PC streaming → copy key (session-based, grab fresh before each stream)

---

## nginx config reference

**Full `/etc/nginx/nginx.conf` structure:**

```nginx
load_module modules/ngx_rtmp_module.so;

user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    # ... default http settings ...

    server {
        listen 8080;
        server_name 127.0.0.1;
        location /stat {
            rtmp_stat all;
            allow 127.0.0.1;
            deny  all;
        }
    }
}

rtmp {
    server {
        listen 1935;
        chunk_size 4096;

        application live {
            live on;
            record off;
            on_publish http://127.0.0.1:8080/api/auth_stream.php;
            include /etc/umbra/rtmp_pushes.conf;
        }
    }
}
```

**`/etc/umbra/rtmp_pushes.conf`** is written by Umbra on every Apply. View it:
```bash
cat /etc/umbra/rtmp_pushes.conf
```

---

## Troubleshooting

**Camera connects but immediately drops**
```bash
# Check auth_stream.php is reachable from loopback
curl -X POST http://127.0.0.1:8080/api/auth_stream.php -d "name=testkey&app=live&addr=127.0.0.1"
# Should return 403 for a wrong key, 200 for the correct one

# Watch nginx error log for on_publish failures
sudo tail -f /var/log/nginx/error.log
```

**Ingest shows red / no signal**
```bash
sudo ss -tlnp | grep 1935
sudo ufw status
sudo tail -f /var/log/nginx/error.log
```

**Apply Changes fails**
```bash
sudo -u www-data sudo /usr/local/bin/umbra-reload
sudo nginx -t
sudo cat /etc/sudoers.d/umbra
```

**Facebook push failing (RTMPS)**
```bash
sudo systemctl status stunnel4
sudo ss -tlnp | grep 19350
cat /etc/stunnel/stunnel.conf
```

**PHP errors / blank page**
```bash
sudo tail -f /var/log/php8.1-fpm.log
sudo tail -f /var/log/nginx/error.log
ls -la /var/www/umbra/data/   # should be owned by www-data
```

---

## Security checklist

- [ ] Default admin password changed
- [ ] Ingest key set and tested with camera app
- [ ] IP allowlist configured in nginx site config
- [ ] HTTP basic auth enabled as second factor
- [ ] UFW enabled with only ports 22, 1935, 8080 open
- [ ] Cloud firewall matches UFW rules
- [ ] `/var/www/umbra/data/` not web-accessible (nginx denies it)
- [ ] `auth_stream.php` restricted to 127.0.0.1 in nginx config

---

*Umbra — built for Allegheny Eclipse*
