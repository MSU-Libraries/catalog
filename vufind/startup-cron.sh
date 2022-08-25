#!/bin/bash

# Map folio harvest directory onto the shared storage (need the added storage capacity)
mkdir -p /mnt/shared/harvest_folio/
mv /usr/local/vufind/local/harvest/folio/ /tmp/
ln -s /mnt/shared/harvest_folio/ /usr/local/vufind/local/harvest/folio

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
