#!/bin/bash
set -e
/usr/sbin/nginx -t 2>&1
/usr/bin/systemctl reload nginx
/usr/bin/systemctl restart stunnel4 2>/dev/null || true
echo "OK"
