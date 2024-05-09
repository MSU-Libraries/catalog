#!/bin/bash

# Cron job - FOLIO harvest

export CRON_COMMAND="/usr/bin/flock -n /tmp/harvest.lock /folio-harvest-and-import.sh --verbose --oai-harvest --batch-import"
export LATEST_PATH=/mnt/logs/harvests/folio_latest.log
export LOG_PATH=/mnt/logs/harvests/folio.log
export EXIT_CODE_PATH=/mnt/logs/harvests/folio_exit_code
export DOCKER_TAG=FOLIO_HARVEST
export OUTPUT_LOG=1

cron-common.sh
