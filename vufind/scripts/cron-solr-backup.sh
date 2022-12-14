#!/bin/bash

# Cron job - Solr backup

LATEST_PATH=/mnt/logs/backups/solr_latest.log
LOG_PATH=/mnt/logs/backups/solr.log
EXIT_CODE_PATH=/mnt/logs/backups/solr_exit_code
DOCKER_TAG=BACKUP_SOLR

/backup.sh --verbose --solr >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
    cat $LATEST_PATH
fi
