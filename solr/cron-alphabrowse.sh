#!/bin/bash

# Cron job - Update alphabetical browsing

LATEST_PATH=/mnt/logs/solr/alphabrowse_latest.log
LOG_PATH=/mnt/logs/solr/alphabrowse.log
EXIT_CODE_PATH=/mnt/logs/solr/alphabrowse_exit_code
DOCKER_TAG=ALPHA_BROWSE

/alpha-browse.sh -v -p /mnt/shared/alpha-browse/${STACK_NAME} >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
fi
