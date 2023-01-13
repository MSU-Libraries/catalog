#!/bin/bash

# Cron job - HLM harvest

export CRON_COMMAND="/usr/bin/flock -n /tmp/hlm-harvest.lock /hlm-harvest-and-import.sh --verbose --harvest --import"
export LATEST_PATH=/mnt/logs/harvests/hlm_latest.log
export LOG_PATH=/mnt/logs/harvests/hlm.log
export EXIT_CODE_PATH=/mnt/logs/harvests/hlm_exit_code
export DOCKER_TAG=HLM_IMPORT
export OUTPUT_LOG=1

cron-common.sh
