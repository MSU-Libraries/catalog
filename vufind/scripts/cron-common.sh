#!/bin/bash

# Run a cron command and save output and exit code
# Takes these parameters: CRON_COMMAND, LATEST_PATH, LOG_PATH, EXIT_CODE_PATH, DOCKER_TAG, OUTPUT_LOG
# (all the vufind cron scripts are assumed to be on the PATH)
# NOTE: solr/cron-alphabrowse.sh duplicates this code

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
