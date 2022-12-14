#!/bin/bash

# Cron job - Authority records harvest

LATEST_PATH=/mnt/logs/harvests/authority_latest.log
LOG_PATH=/mnt/logs/harvests/authority.log
EXIT_CODE_PATH=/mnt/logs/harvests/authority_exit_code
DOCKER_TAG=AUTHORITY_IMPORT

/usr/bin/flock -n /tmp/authority-harvest.lock /authority-harvest-and-import.sh --verbose --harvest --import >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
    cat $LATEST_PATH
fi
