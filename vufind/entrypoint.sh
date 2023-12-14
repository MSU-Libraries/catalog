#!/bin/bash

echo "Entrypoint script..."

# Replace environment variables in template ini files
envsubst < local/config/vufind/config.ini | sponge local/config/vufind/config.ini
envsubst < local/config/vufind/contentsecuritypolicy.ini | sponge local/config/vufind/contentsecuritypolicy.ini
envsubst < local/config/vufind/folio.ini | sponge local/config/vufind/folio.ini
envsubst < local/config/vufind/EDS.ini | sponge local/config/vufind/EDS.ini
envsubst < local/config/vufind/BrowZine.ini | sponge local/config/vufind/BrowZine.ini
envsubst < local/harvest/oai.ini | sponge local/harvest/oai.ini
envsubst < /etc/aliases | sponge /etc/aliases

# Disable file cache for devel environments
if [[ "${STACK_NAME}" == devel-* ]]; then
  sed -i '/^\[Cache\]$/,/^\[/ s/^;disabled = true/disabled = true/' local/config/vufind/config.ini
  sed -i '/^coverimagesCache = true/coverimagesCache = false/' local/config/vufind/config.ini
fi

# Finish SimpleSAMLphp config setup
envsubst '${SIMPLESAMLPHP_SALT} ${SIMPLESAMLPHP_ADMIN_PW} ${SIMPLESAMLPHP_CUSTOM_DIR}' < ${SIMPLESAMLPHP_CONFIG_DIR}/config.php | \
    sponge ${SIMPLESAMLPHP_CONFIG_DIR}/config.php

# Unset env variables that are just used in config files and don't need to be in the environment after this.
# MARIADB_VUFIND_PASSWORD, SIMPLESAMLPHP_HOME, BROWZINE_LIBRARY and BROWZINE_TOKEN
# cannot be unset yet because our custom PHP code uses the environment variables instead of the configs.
unset FOLIO_URL FOLIO_USER FOLIO_PASS FOLIO_TENANT FOLIO_REC_ID FOLIO_CANCEL_ID OAI_URL MAIL_HOST MAIL_PORT \
    MAIL_USERNAME MAIL_PASSWORD FEEDBACK_EMAIL EDS_USER EDS_PASS EDS_PROFILE EDS_ORG RECAPTCHA_SITE_KEY \
    RECAPTCHA_SECRET_KEY MATOMO_URL MATOMO_SITE_ID MATOMO_SEARCHBACKEND_DIMENSION SIMPLESAMLPHP_SALT \
    SIMPLESAMLPHP_ADMIN_PW SIMPLESAMLPHP_VERSION SIMPLESAMLPHP_CUSTOM_DIR

if [[ "$1" == "/startup-cron.sh" ]]; then
    if ! grep -q STACK_NAME /etc/environment; then
        # Set required environment variables so cron jobs have access to them
        echo JAVA_HOME="$JAVA_HOME" >> /etc/environment
        echo VUFIND_HOME="$VUFIND_HOME"  >> /etc/environment
        echo VUFIND_LOCAL_DIR="$VUFIND_LOCAL_DIR" >> /etc/environment
        echo VUFIND_LOCAL_MODULES="Catalog" >> /etc/environment
        echo FTP_USER="$FTP_USER" >> /etc/environment
        echo FTP_PASSWORD="$FTP_PASSWORD" >> /etc/environment
        echo AUTH_FTP_USER="$AUTH_FTP_USER" >> /etc/environment
        echo AUTH_FTP_PASSWORD="$AUTH_FTP_PASSWORD" >> /etc/environment
        echo STACK_NAME="$STACK_NAME" >> /etc/environment
    fi
else
    # Unset variables that are only useful for cron jobs.
    # We are still passing them to the catalog container in the docker-compose, because devel environments
    # don't have the cron container and we might need them in the catalog container when doing a docker exec.
    unset FTP_USER FTP_PASSWORD AUTH_FTP_USER AUTH_FTP_PASSWORD SOLR_URL
fi

exec "$@"
