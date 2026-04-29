#!/bin/bash

# Cron job - DR (Digital Repository) harvest

export CRON_COMMAND="/usr/bin/flock -n -E 255 /tmp/dr-harvest.lock /usr/local/bin/pc-import-dr --verbose --oai-harvest --batch-import"
export LATEST_PATH=/mnt/logs/harvests/dr_latest.log
export LOG_PATH=/mnt/logs/harvests/dr.log
export EXIT_CODE_PATH=/mnt/logs/harvests/dr_exit_code
export DOCKER_TAG=DR_HARVEST
export OUTPUT_LOG=1
export NICENESS=19

cron-common.sh
