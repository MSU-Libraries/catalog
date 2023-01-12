#!/bin/bash

# Cron job - Update alphabetical browsing

# Get the stack name from the parameters passed in
STACK_NAME=${1}

CRON_COMMAND="/alpha-browse.sh -v -p /mnt/shared/alpha-browse/${STACK_NAME}"
LATEST_PATH=/mnt/logs/solr/alphabrowse_latest.log
LOG_PATH=/mnt/logs/solr/alphabrowse.log
EXIT_CODE_PATH=/mnt/logs/solr/alphabrowse_exit_code
DOCKER_TAG=ALPHA_BROWSE
OUTPUT_LOG=0

rm -f "$LATEST_PATH" "$EXIT_CODE_PATH"
$CRON_COMMAND >$LATEST_PATH 2>&1
EXIT_CODE=$?

cat $LATEST_PATH >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    cat $LATEST_PATH | logger -t $DOCKER_TAG
    if [[ $OUTPUT_LOG -eq 1 ]]; then
        cat $LATEST_PATH
    fi
fi
