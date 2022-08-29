#!/bin/bash

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
