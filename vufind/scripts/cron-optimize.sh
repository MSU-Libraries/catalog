#!/bin/bash

# Cron job - Optimize DB tables (well, starting with session for now)

export CRON_COMMAND="/usr/local/bin/pc-optimize -t session"
export LATEST_PATH=/mnt/logs/vufind/optimize_latest.log
export LOG_PATH=/mnt/logs/vufind/optimize_cleanup.log
export EXIT_CODE_PATH=/mnt/logs/vufind/optimize_exit_code
export DOCKER_TAG=OPTIMIZE_DB
export OUTPUT_LOG=0

cron-common.sh
