#!/bin/bash

# Run a cron command and save output and exit code
# Takes these parameters:
#   CRON_COMMAND    : Required, command to run
#   LATEST_PATH     : Required, where to latest log
#   LOG_PATH        : Required, the log file
#   EXIT_CODE_PATH  : Required, the path to the exit code file
#   DOCKER_TAG      : Required, the tag to send to the logger
#   OUTPUT_LOG      : Optional, 0 = no output, 1 = out to stdout (default)
#   NICENESS        : Optional, default of 0
# (all the vufind cron scripts are assumed to be on the PATH)
# NOTE: solr/cron-alphabrowse.sh duplicates this code

# Clear out previous exit code while we run. This helps to
# reduce additional Nagios alerts for long running cronjobs.
# Keep a copy of the previous exit code for monitoring.
cp --preserve=timestamps "$EXIT_CODE_PATH" "${EXIT_CODE_PATH}_previous"
true > "$EXIT_CODE_PATH"

OUTPUT_LOG=${OUTPUT_LOG:-1}
NICENESS=${NICENESS:-0}
TIMESTAMP=$( date +%Y%m%d%H%M%S )
LATEST_PATH_WITH_TS="${LATEST_PATH}.${TIMESTAMP}"
# shellcheck disable=SC2086
nice -n "$NICENESS" $CRON_COMMAND > "${LATEST_PATH_WITH_TS}" 2>&1
EXIT_CODE=$?

if [[ $EXIT_CODE -eq 255 ]]; then
    echo "Could not obtain file lock." > "${LATEST_PATH_WITH_TS}"
fi

cat "${LATEST_PATH_WITH_TS}" >> "$LOG_PATH"

echo $EXIT_CODE > "$EXIT_CODE_PATH"
rm -f "${EXIT_CODE_PATH}_previous"

if [[ $EXIT_CODE -ne 0 ]]; then
    logger -t "$DOCKER_TAG" -f "${LATEST_PATH_WITH_TS}"
    if [[ $OUTPUT_LOG -eq 1 ]]; then
        cat "${LATEST_PATH_WITH_TS}"
    fi
fi

rm -f "${LATEST_PATH_WITH_TS}"
