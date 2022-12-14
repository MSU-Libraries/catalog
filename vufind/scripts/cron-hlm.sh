#!/bin/bash

# Cron job - HLM harvest

LATEST_PATH=/mnt/logs/harvests/hlm_latest.log
LOG_PATH=/mnt/logs/harvests/hlm.log
EXIT_CODE_PATH=/mnt/logs/harvests/hlm_exit_code
DOCKER_TAG=HLM_IMPORT

/usr/bin/flock -n /tmp/hlm-harvest.lock /hlm-harvest-and-import.sh --verbose --harvest --import >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
    cat $LATEST_PATH
fi
