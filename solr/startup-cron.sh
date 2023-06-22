#!/bin/bash

# Save the logs in the logs docker volume
mkdir -p /mnt/logs/solr

# Replace $STACK_NAME and $NODE in the crontab entry
envsubst < /etc/cron.d/crontab | sponge /etc/cron.d/crontab

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
