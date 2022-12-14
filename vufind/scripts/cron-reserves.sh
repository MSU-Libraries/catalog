#!/bin/bash

# Cron job - Course reserves update

LATEST_PATH=/mnt/logs/vufind/reserves_latest.log
LOG_PATH=/mnt/logs/vufind/reserves_update.log
EXIT_CODE_PATH=/mnt/logs/vufind/reserves_exit_code
DOCKER_TAG=INDEX_RESERVES

/usr/bin/php /usr/local/vufind/util/index_reserves.php >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
fi
