#!/bin/bash

# Cron job - Update alphabetical browsing

CRON_COMMAND="/alpha-browse.sh -v -p /mnt/shared/alpha-browse/${STACK_NAME}"
LATEST_PATH=/mnt/logs/alphabrowse/alphabrowse_latest.log
LOG_PATH=/mnt/logs/alphabrowse/alphabrowse.log
EXIT_CODE_PATH=/mnt/logs/alphabrowse/alphabrowse_exit_code
DOCKER_TAG=ALPHA_BROWSE
OUTPUT_LOG=0
NICENESS=19

TIMESTAMP=$( date +%Y%m%d%H%M%S )
LATEST_PATH_WITH_TS="${LATEST_PATH}.${TIMESTAMP}"
# shellcheck disable=SC2086
nice -n $NICENESS $CRON_COMMAND > "${LATEST_PATH_WITH_TS}" 2>&1
EXIT_CODE=$?

cat "${LATEST_PATH_WITH_TS}" >>$LOG_PATH

echo $EXIT_CODE >$EXIT_CODE_PATH

if [[ $EXIT_CODE -ne 0 ]]; then
    logger -t "$DOCKER_TAG" -f "${LATEST_PATH_WITH_TS}"
    if [[ $OUTPUT_LOG -eq 1 ]]; then
        cat "${LATEST_PATH_WITH_TS}"
    fi
fi

rm -f "${LATEST_PATH_WITH_TS}"
