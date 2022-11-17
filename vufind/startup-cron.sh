#!/bin/bash

# Map folio harvest directory onto the shared storage (need the added storage capacity)
mkdir -p /mnt/shared/local_harvest_folio/
mv /usr/local/vufind/local/harvest/folio/ /tmp/
ln -s /mnt/shared/local_harvest_folio/ /usr/local/vufind/local/harvest/folio

# Replace the $NODE in the crontab entry
envsubst < /etc/cron.d/crontab | sponge /etc/cron.d/crontab

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
