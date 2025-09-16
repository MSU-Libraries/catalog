#!/bin/bash

# Cron job - Course reserves update

export CRON_COMMAND="/usr/bin/php /usr/local/vufind/util/index_reserves.php"
export LATEST_PATH=/mnt/logs/vufind/reserves_update_latest.log
export LOG_PATH=/mnt/logs/vufind/reserves_update.log
export EXIT_CODE_PATH=/mnt/logs/vufind/reserves_exit_code
export DOCKER_TAG=INDEX_RESERVES
export OUTPUT_LOG=0
export NICENESS=19

cron-common.sh
