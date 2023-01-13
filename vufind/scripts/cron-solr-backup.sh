#!/bin/bash

# Cron job - Solr backup

export CRON_COMMAND="/backup.sh --verbose --solr"
export LATEST_PATH=/mnt/logs/backups/solr_latest.log
export LOG_PATH=/mnt/logs/backups/solr.log
export EXIT_CODE_PATH=/mnt/logs/backups/solr_exit_code
export DOCKER_TAG=BACKUP_SOLR
export OUTPUT_LOG=1

cron-common.sh
