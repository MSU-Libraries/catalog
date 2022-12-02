#!/bin/bash

# Map folio harvest directory onto the shared storage (need the added storage capacity)
mkdir -p /mnt/shared/local_harvest_folio/
mv /usr/local/vufind/local/harvest/folio/ /tmp/
ln -s /mnt/shared/local_harvest_folio/ /usr/local/vufind/local/harvest/folio

# Replace the $NODE in the crontab entry
envsubst < /etc/cron.d/crontab | sponge /etc/cron.d/crontab

# Change to using file sessions
sed -i 's/type\s*=\s*Database/type=File/' /usr/local/vufind/local/config/vufind/config.ini

# If not catalog-prod remove the backup jobs
if [[ "${STACK_NAME}" != catalog-prod ]]; then
    rm /etc/cron.d/backups
fi

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
