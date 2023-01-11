#!/bin/bash

# Cron job - Cleanup unsaved searches

export CRON_COMMAND="/usr/bin/php /usr/local/vufind/public/index.php util/expire_searches"
export LATEST_PATH=/mnt/logs/vufind/searches_latest.log
export LOG_PATH=/mnt/logs/vufind/searches_cleanup.log
export EXIT_CODE_PATH=/mnt/logs/vufind/searches_exit_code
export DOCKER_TAG=EXPIRE_SEARCHES
export OUTPUT_LOG=0

cron-common.sh
