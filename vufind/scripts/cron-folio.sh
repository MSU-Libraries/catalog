#!/bin/bash

# Cron job - FOLIO harvest

LATEST_PATH=/mnt/logs/harvests/folio_latest.log
LOG_PATH=/mnt/logs/harvests/folio.log
EXIT_CODE_PATH=/mnt/logs/harvests/folio_exit_code
DOCKER_TAG=FOLIO_HARVEST

/usr/bin/flock -n /tmp/harvest.lock /harvest-and-import.sh --verbose --oai-harvest --batch-import >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
    cat $LATEST_PATH
fi
