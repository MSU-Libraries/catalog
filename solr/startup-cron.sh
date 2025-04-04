#!/bin/bash

# Save the logs in the logs docker volume
mkdir -p /mnt/logs/alphabrowse

# Set custom cron minute offsets for alphabrowse indexing
ALPHA_CRON_HOURS="1"  # catalog-prod
if [[ "${STACK_NAME}" == "catalog-beta" ]]; then
    ALPHA_CRON_HOURS="2"
elif [[ "${STACK_NAME}" == "catalog-preview" ]]; then
    ALPHA_CRON_HOURS="3"
fi
export ALPHA_CRON_HOURS

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
