#!/bin/bash

# Cron job - DB backup

LATEST_PATH=/mnt/logs/backups/db_latest.log
LOG_PATH=/mnt/logs/backups/db.log
EXIT_CODE_PATH=/mnt/logs/backups/db_exit_code
DOCKER_TAG=BACKUP_DB

/backup.sh --verbose --db -r 5 >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
    cat $LATEST_PATH
fi
