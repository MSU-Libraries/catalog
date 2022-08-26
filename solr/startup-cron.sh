#!/bin/bash

# Create symlink from local solr location to bitnami volume and set SOLR_HOME
ln -s /opt/bitnami/solr /bitnami/solr/server/vendor
export SOLR_HOME=/bitnami/solr/server/solr

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4

