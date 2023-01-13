#!/bin/bash

# Cron job - DB backup

export CRON_COMMAND="/backup.sh --verbose --db -r 5"
export LATEST_PATH=/mnt/logs/backups/db_latest.log
export LOG_PATH=/mnt/logs/backups/db.log
export EXIT_CODE_PATH=/mnt/logs/backups/db_exit_code
export DOCKER_TAG=BACKUP_DB
export OUTPUT_LOG=1

cron-common.sh
