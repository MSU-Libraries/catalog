#!/bin/bash

# Cron job - Alphabrowse backup

export CRON_COMMAND="/backup.sh --verbose --alpha"
export LATEST_PATH=/mnt/logs/backups/alpha_latest.log
export LOG_PATH=/mnt/logs/backups/alpha.log
export EXIT_CODE_PATH=/mnt/logs/backups/alpha_exit_code
export DOCKER_TAG=BACKUP_ALPHA
export OUTPUT_LOG=1

cron-common.sh
