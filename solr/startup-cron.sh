#!/bin/bash

# Save the logs in the logs docker volume
mkdir -p /mnt/logs/alphabrowse

# Set custom cron minute offsets for alphabrowse indexing
ALPHA_CRON_HOURS="1"  # catalog-prod
if [[ "${STACK_NAME}" == *"-beta" ]]; then
    ALPHA_CRON_HOURS="2"
elif [[ "${STACK_NAME}" == *"-preview" ]]; then
    ALPHA_CRON_HOURS="3"
fi
export ALPHA_CRON_HOURS

# Replace $STACK_NAME, $NODE and $ALPHA_CRON_HOURS in the crontab entry
envsubst < /etc/cron.d/crontab | sponge /etc/cron.d/crontab

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
