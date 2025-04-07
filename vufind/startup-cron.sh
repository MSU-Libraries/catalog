#!/bin/bash

# Map harvest directories onto the shared storage
# FOLIO
mkdir -p "/mnt/shared/oai/${STACK_NAME}/harvest_folio/" "/mnt/shared/oai/${STACK_NAME}/current/" "/mnt/shared/oai/${STACK_NAME}/archives/"
mv /usr/local/vufind/local/harvest/folio/ /tmp/
ln -s "/mnt/shared/oai/${STACK_NAME}/harvest_folio/" /usr/local/vufind/local/harvest/folio
ln -s "/mnt/shared/oai/${STACK_NAME}"/ /mnt/oai
# HLM
mkdir -p "/mnt/shared/hlm/${STACK_NAME}/current/" "/mnt/shared/hlm/${STACK_NAME}/archives/"
ln -s "/mnt/shared/hlm/${STACK_NAME}/" /mnt/hlm
# AUTHORITY
mkdir -p "/mnt/shared/authority/${STACK_NAME}/current/" "/mnt/shared/authority/${STACK_NAME}/archives/"
ln -s "/mnt/shared/authority/${STACK_NAME}/" /mnt/authority

# Save the logs in the logs docker volume
mkdir -p /mnt/logs/vufind /mnt/logs/harvests
ln -sf /mnt/logs/vufind /var/log/vufind
touch /mnt/logs/vufind/vufind.log

# Create backups dir symlink
ln -s "/mnt/shared/backups/${STACK_NAME}/" /mnt/backups

# Set custom cron minute offsets for OAI harvesting
FOLIO_CRON_MINS="0,15,30,45"  # catalog-prod
HARV_CRON_MINS="30"
RESRV_CRON_MINS="10"
if [[ "${STACK_NAME}" == "catalog-beta" ]]; then
    FOLIO_CRON_MINS="15"
    HARV_CRON_MINS="15"
    RESRV_CRON_MINS="20"
elif [[ "${STACK_NAME}" == "catalog-preview" ]]; then
    FOLIO_CRON_MINS="45"
    HARV_CRON_MINS="45"
    RESRV_CRON_MINS="50"
fi
export FOLIO_CRON_MINS
export HARV_CRON_MINS
export RESRV_CRON_MINS

envsubst < /etc/cron.d/crontab | sponge /etc/cron.d/crontab

# Put the database password used for backups in the environment file
echo "MARIADB_ROOT_PASSWORD=\"${MARIADB_ROOT_PASSWORD}\"" >> /etc/environment

# Change to using file sessions
sed -i 's/type\s*=\s*Database/type=File/' /usr/local/vufind/local/config/vufind/config.ini

# If not catalog-prod remove the backup jobs
if [[ "${STACK_NAME}" != catalog-prod ]]; then
    rm /etc/cron.d/backups
fi

# Create the HLM ignore substring file if it doesn't exist
touch /mnt/shared/hlm/ignore_patterns.txt

# Start up syslog (required for cron)
rsyslogd

cron -f -L 4
