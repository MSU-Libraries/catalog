#!/bin/bash

# Cron job - Clear out old sessions from the database

export CRON_COMMAND="/usr/local/bin/pc-clear-sessions -v --batch-size 1000 --expiration-days 1 --max-attempts 5"
export LATEST_PATH=/mnt/logs/vufind/sessions_cleanup_latest.log
export LOG_PATH=/mnt/logs/vufind/sessions_cleanup.log
export EXIT_CODE_PATH=/mnt/logs/vufind/sessions_exit_code
export DOCKER_TAG=EXPIRE_SESSIONS
export OUTPUT_LOG=0

cron-common.sh
