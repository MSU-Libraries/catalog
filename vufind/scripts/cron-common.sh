#!/bin/bash

# Run a cron command and save output and exit code
# Takes these parameters: CRON_COMMAND, LATEST_PATH, LOG_PATH, EXIT_CODE_PATH, DOCKER_TAG, OUTPUT_LOG
# (all the vufind cron scripts are assumed to be on the PATH)
# NOTE: solr/cron-alphabrowse.sh duplicates this code

TIMESTAMP=$( date +%Y%m%d%H%M%S )
LATEST_PATH_WITH_TS="${LATEST_PATH}.${TIMESTAMP}"
$CRON_COMMAND > "${LATEST_PATH_WITH_TS}" 2>&1
EXIT_CODE=$?

if [[ $EXIT_CODE -eq 255 ]]; then
    echo "Could not obtain file lock." > "${LATEST_PATH_WITH_TS}"
fi

cat "${LATEST_PATH_WITH_TS}" >> "$LOG_PATH"

echo $EXIT_CODE > "$EXIT_CODE_PATH"

if [[ $EXIT_CODE -ne 0 ]]; then
    logger -t "$DOCKER_TAG" -f "${LATEST_PATH_WITH_TS}"
    if [[ $OUTPUT_LOG -eq 1 ]]; then
        cat "${LATEST_PATH_WITH_TS}"
    fi
fi

rm -f "${LATEST_PATH_WITH_TS}"
