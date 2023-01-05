#!/bin/bash

# Cron job - Clear out old sessions from the database

export CRON_COMMAND="/usr/bin/php /usr/local/vufind/public/index.php util/expire_sessions"
export LATEST_PATH=/mnt/logs/vufind/sessions_latest.log
export LOG_PATH=/mnt/logs/vufind/sessions_cleanup.log
export EXIT_CODE_PATH=/mnt/logs/vufind/sessions_exit_code
export DOCKER_TAG=EXPIRE_SESSIONS
export OUPUT_LOG=0

cron-common.sh
