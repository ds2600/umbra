# Umbra
### Stream Control · Allegheny Eclipse

Umbra is a self-hosted, single-page stream relay control panel. Connect a camera or phone via RTMP using a private ingest key, and Umbra rebroadcasts your feed simultaneously to YouTube, Facebook, and TikTok — with a live status dashboard, real-time ingest monitoring, per-platform controls, and system stats.

---

## Repository structure

```
umbra/
├── web/                       # nginx root — all web-served files live here
│   ├── index.php              # Single-page app (login + dashboard)
│   └── api/
│       ├── db.php             # SQLite database layer
│       ├── auth.php           # Web login / session auth
│       ├── auth_stream.php    # RTMP ingest key validator (nginx on_publish)
│       ├── get_config.php     # Load saved keys + settings
│       ├── apply.php          # Save settings, write nginx include, reload
│       ├── ingest_status.php  # Real-time RTMP ingest status
│       └── stats.php          # CPU / memory / network stats
├── data/                      # SQLite database lives here after install
│                              # (outside web root — never served)
├── nginx/
│   └── umbra.conf             # nginx site block for the control panel
├── scripts/
│   └── umbra-reload.sh        # Privileged reload script (root via sudoers)
├── install.sh                 # One-shot installer
├── update.sh                  # Git pull + sync updater
└── README.md
```

---

## How ingest security works

nginx-rtmp's `on_publish` directive fires an HTTP POST to `auth_stream.php` every time a camera tries to connect on port 1935. The POST includes the stream key the client provided. If it matches what's in SQLite, the script returns HTTP 200 and nginx allows the stream. Any other response and nginx drops the connection immediately.

You set the ingest key in your camera app as the **stream key** field. The RTMP URL stays as `rtmp://YOUR_SERVER_IP/live`. You can rotate the key any time from the Umbra dashboard — the old key stops working the moment nginx reloads.

---

## Prerequisites

- Ubuntu 22.04 or 20.04
- A static IP or domain name
- Root / sudo access
- Ports to open: **22** (SSH), **1935** (RTMP ingest), **8080** (Umbra UI)

---

## Installation

### Step 1 — Clone the repo

```bash
cd ~
git clone https://github.com/ds2600/umbra.git
cd umbra
```

### Step 2 — Run the installer

```bash
sudo bash install.sh
```

The installer handles: adding the PHP PPA, installing all packages, copying files to `/var/www/umbra/web`, setting up `/var/lib/umbra` for writable runtime files, installing the reload script, configuring sudoers, and enabling the nginx site.

### Step 3 — Edit the main nginx config

```bash
sudo nano /etc/nginx/nginx.conf
```

Add the RTMP module loader near the top, before the `events {}` block:

```nginx
load_module modules/ngx_rtmp_module.so;
```

Add the internal stat server inside the `http {}` block (uses port 8088 to avoid conflicting with Umbra's UI on 8080):

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

Add the RTMP relay block **after** the closing `}` of the `http {}` block:

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

### Step 4 — Configure stunnel (Facebook RTMPS)

Facebook requires RTMPS. stunnel wraps the connection locally.

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

### Step 5 — Configure UFW

```bash
sudo ufw allow 22/tcp
sudo ufw allow 1935/tcp
sudo ufw allow 8080/tcp
sudo ufw enable
sudo ufw status verbose
```

### Step 6 — Lock down the UI

```bash
sudo nano /etc/nginx/sites-available/umbra
```

Uncomment and set your IP:
```nginx
allow 162.243.123.456; # <-- your IP here`
deny  all;
```

Optional — HTTP basic auth as a second layer:
```bash
sudo apt install -y apache2-utils
sudo htpasswd -c /etc/nginx/.umbra_htpasswd umbra
```
Then uncomment in the site config:
```nginx
auth_basic           "Umbra";
auth_basic_user_file /etc/nginx/.umbra_htpasswd;
```

### Step 7 — Test and start nginx

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl enable nginx
```

### Step 8 — First login

```
http://YOUR_SERVER_IP:8080
```

Default credentials:
```
Username: admin
Password: umbra
```

Change the password immediately — use single quotes to prevent shell variable expansion:

```bash
php -r '
$pdo  = new PDO("sqlite:/var/lib/umbra/umbra.db");
$hash = password_hash("YOUR_NEW_PASSWORD", PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password=? WHERE username=?")->execute([$hash,"admin"]);
echo "Done. Rows: " . $pdo->query("SELECT changes()")->fetchColumn() . PHP_EOL;
'
```

> Always use single quotes around the PHP block when your password contains `$` or other shell special characters. Double-quoted bash strings will silently expand `$variable` references before PHP sees them.

---

## Updating from GitHub

Pull the latest code and apply it in one command:

```bash
cd ~/umbra
sudo bash update.sh
```

`update.sh` does a `git pull`, rsyncs `web/` to the live web root (preserving `data/`), updates the reload script and nginx site config, and reloads nginx. Your database, stream keys, and ingest key are untouched.

---

## Setting your ingest key

On first run, Umbra auto-generates a random 32-character ingest key. Find it in the **Ingest Security** section of the dashboard.

1. Copy the key using the Copy button
2. In your camera app set:
   - **RTMP URL:** `rtmp://YOUR_SERVER_IP/live`
   - **Stream key:** *(your Umbra ingest key)*
3. Click **Apply Changes** to activate

**Insta360 app:** Live → Custom RTMP → set URL and stream key, bitrate 4–6 Mbps

**Larix Broadcaster:** Settings → Connections → Add connection → URL: `rtmp://YOUR_SERVER_IP/live/YOUR_KEY` *(Larix appends the key to the path)*

---

## Setting platform stream keys

Enter keys in the **Platforms** section and hit **Apply Changes**.

**YouTube:** studio.youtube.com → Create → Go Live → Stream → enable **"Reuse stream key"** for a permanent key

**Facebook:** facebook.com/live/producer → Go Live → Streaming software → copy stream key. Keys expire after 7 days of inactivity — grab a fresh one before each event

**TikTok:** Requires 1,000+ followers. TikTok app → + → Live → Cast/PC streaming → copy key. Keys are session-based — get one right before going live

---

## Full nginx.conf reference

Complete structure after all edits:

```nginx
load_module modules/ngx_rtmp_module.so;

user www-data;
worker_processes auto;
pid /run/nginx.pid;
include /etc/nginx/modules-enabled/*.conf;

events {
    worker_connections 768;
}

http {
    # ... leave all default http block content intact ...

    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;

    # Internal RTMP stat endpoint — port 8088, loopback only
    # Do NOT use 8080 here — that's the Umbra UI port
    server {
        listen 8088;
        server_name 127.0.0.1;
        location /stat {
            rtmp_stat all;
            allow 127.0.0.1;
            deny  all;
        }
    }
}

# RTMP relay — outside and after the http {} block
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

## Troubleshooting

**Camera connects but immediately drops**
```bash
# Test the auth endpoint directly
curl -X POST http://127.0.0.1:8080/api/auth_stream.php \
  -d "name=YOUR_INGEST_KEY&app=live&addr=127.0.0.1"
# 200 = good, 403 = wrong key, 404 = routing problem

sudo tail -f /var/log/nginx/error.log
```

**Ingest indicator always red**
```bash
# Confirm stat endpoint is on 8088
curl http://127.0.0.1:8088/stat
# Should return XML

sudo ss -tlnp | grep 8088
sudo nginx -t
```

**Apply Changes fails / rtmp_pushes.conf stays empty**
```bash
# Check permissions on /var/lib/umbra
ls -la /var/lib/umbra/
# rtmp_pushes.conf must be owned by www-data

sudo chown www-data:www-data /var/lib/umbra/rtmp_pushes.conf

# Test the reload script as www-data
sudo -u www-data sudo /usr/local/bin/umbra-reload

# Check sudoers permissions (must be exactly 0440)
ls -la /etc/sudoers.d/umbra
sudo chmod 0440 /etc/sudoers.d/umbra && sudo visudo -c
```

**Facebook push not working**
```bash
sudo systemctl status stunnel4
sudo ss -tlnp | grep 19350

# Manually write the push conf to test
sudo -u www-data bash -c 'echo "      push rtmp://127.0.0.1:19350/rtmp/YOUR_FB_KEY;" > /var/lib/umbra/rtmp_pushes.conf'
sudo nginx -t && sudo systemctl reload nginx
```

**PHP errors / simplexml missing**
```bash
sudo apt install -y php8.1-xml
sudo systemctl restart php8.1-fpm
php -m | grep SimpleXML
```

**Blank page / 500 errors**
```bash
sudo tail -f /var/log/php8.1-fpm.log
sudo tail -f /var/log/nginx/error.log
ls -la /var/www/umbra/web/
ls -la /var/lib/umbra/
# All files should be owned by www-data
```

---

## Security checklist

- [ ] Default admin password changed (use single quotes in the PHP command)
- [ ] Ingest key set and tested with camera app
- [ ] IP allowlist configured in `/etc/nginx/sites-available/umbra`
- [ ] HTTP basic auth enabled as second factor
- [ ] UFW enabled with only ports 22, 1935, 8080 open
- [ ] Cloud firewall matches UFW if on DigitalOcean / AWS / Vultr
- [ ] `/var/lib/umbra/` owned by www-data, not readable by others
- [ ] `auth_stream.php` restricted to 127.0.0.1 in nginx site config
- [ ] `/etc/sudoers.d/umbra` permissions are exactly `0440`

---

*Umbra — built for Allegheny Eclipse*
