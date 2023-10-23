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

# Set required environment variables so cron jobs have access to them
if ! grep -q STACK_NAME /etc/environment; then
    echo JAVA_HOME="$JAVA_HOME" >> /etc/environment
    echo VUFIND_HOME="$VUFIND_HOME"  >> /etc/environment
    echo VUFIND_LOCAL_DIR="$VUFIND_LOCAL_DIR" >> /etc/environment
    echo VUFIND_CACHE_DIR="$VUFIND_CACHE_DIR" >> /etc/environment
    echo VUFIND_LOCAL_MODULES="Catalog" >> /etc/environment
    echo FTP_USER="$FTP_USER" >> /etc/environment
    echo FTP_PASSWORD="$FTP_PASSWORD" >> /etc/environment
    echo AUTH_FTP_USER="$AUTH_FTP_USER" >> /etc/environment
    echo AUTH_FTP_PASSWORD="$AUTH_FTP_PASSWORD" >> /etc/environment
    echo DEPLOY_KEY="$DEPLOY_KEY" >> /etc/environment
    echo BROWZINE_LIBRARY="$BROWZINE_LIBRARY" >> /etc/environment
    echo BROWZINE_TOKEN="$BROWZINE_TOKEN" >> /etc/environment
    echo STACK_NAME="$STACK_NAME" >> /etc/environment
fi

# Finish SimpleSAMLphp config setup
envsubst '${SIMPLESAMLPHP_SALT} ${SIMPLESAMLPHP_ADMIN_PW}' < ${SIMPLESAMLPHP_CONFIG_DIR}/config.php | \
    sponge ${SIMPLESAMLPHP_CONFIG_DIR}/config.php

exec "$@"
