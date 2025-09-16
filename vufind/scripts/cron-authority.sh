#!/bin/bash

# Cron job - Authority records harvest

export CRON_COMMAND="/usr/bin/flock -n -E 255 /tmp/authority-harvest.lock /usr/local/bin/pc-import-authority --verbose --harvest --import"
export LATEST_PATH=/mnt/logs/harvests/authority_latest.log
export LOG_PATH=/mnt/logs/harvests/authority.log
export EXIT_CODE_PATH=/mnt/logs/harvests/authority_exit_code
export DOCKER_TAG=AUTHORITY_IMPORT
export OUTPUT_LOG=1
export NICENESS=19

cron-common.sh
