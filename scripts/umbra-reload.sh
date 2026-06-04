#!/bin/bash
# /usr/local/bin/umbra-reload
set -e

# Copy the stunnel config written by Umbra to the real location
if [ -f /var/lib/umbra/stunnel.conf ]; then
    cp /var/lib/umbra/stunnel.conf /etc/stunnel/stunnel.conf
fi

/usr/sbin/nginx -t 2>&1
/usr/bin/systemctl reload nginx
/usr/bin/systemctl restart stunnel4

echo "OK"
