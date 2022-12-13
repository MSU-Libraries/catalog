#!/bin/bash

# Cron job - Cleanup unsaved searches

LATEST_PATH=/mnt/logs/vufind/searches_latest.log
LOG_PATH=/mnt/logs/vufind/searches_cleanup.log
EXIT_CODE_PATH=/mnt/logs/vufind/searches_exit_code
DOCKER_TAG=EXPIRE_SEARCHES

/usr/bin/php /usr/local/vufind/public/index.php util/expire_searches >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
fi
