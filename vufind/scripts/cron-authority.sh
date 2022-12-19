#!/bin/bash

# Cron job - Authority records harvest

export CRON_COMMAND="/usr/bin/flock -n /tmp/authority-harvest.lock /authority-harvest-and-import.sh --verbose --harvest --import"
export LATEST_PATH=/mnt/logs/harvests/authority_latest.log
export LOG_PATH=/mnt/logs/harvests/authority.log
export EXIT_CODE_PATH=/mnt/logs/harvests/authority_exit_code
export DOCKER_TAG=AUTHORITY_IMPORT
export OUPUT_LOG=1

cron-common.sh
