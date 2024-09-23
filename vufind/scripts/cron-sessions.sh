#!/bin/bash

# Cron job - Clear out old sessions from the database

EXPIRE_IF_OLDER_THAN_AGE_IN_DAYS="0.5"
export CRON_COMMAND="/usr/bin/php /usr/local/vufind/public/index.php util/expire_sessions --batch 1000 $EXPIRE_IF_OLDER_THAN_AGE_IN_DAYS"
export LATEST_PATH=/mnt/logs/vufind/sessions_cleanup_latest.log
export LOG_PATH=/mnt/logs/vufind/sessions_cleanup.log
export EXIT_CODE_PATH=/mnt/logs/vufind/sessions_exit_code
export DOCKER_TAG=EXPIRE_SESSIONS
export OUTPUT_LOG=0

cron-common.sh
