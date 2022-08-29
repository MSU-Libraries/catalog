#!/bin/bash

# Create symlink from local solr location to bitnami volume
ln -s /opt/bitnami/solr /bitnami/solr/server/vendor

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
