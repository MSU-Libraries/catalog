#!/bin/bash

# Replace regular cron.d with cachecron.d
mv /etc/cron.d/ /etc/defaultcron.d/
mv /etc/cachecron.d/ /etc/cron.d/
chmod -R g-w,o-w /etc/cron.d/

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
