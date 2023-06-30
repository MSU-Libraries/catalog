#!/bin/bash

# Replace regular cron.d with croncache.d
mv /etc/cron.d/ /etc/defaultcron.d/
mv /etc/croncache.d/ /etc/cron.d/
chmod -R g-w,o-w /etc/cron.d/

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
