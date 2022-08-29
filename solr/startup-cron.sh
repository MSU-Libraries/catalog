#!/bin/bash

# Replace the $NODE in the crontab entry
envsubst < /etc/cron.d/crontab | sponge /etc/cron.d/crontab

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
