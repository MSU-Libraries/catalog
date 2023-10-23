#!/bin/bash

# Replace environment variables in template ini files
envsubst < local/config/vufind/config.ini | sponge local/config/vufind/config.ini
envsubst < local/config/vufind/contentsecuritypolicy.ini | sponge local/config/vufind/contentsecuritypolicy.ini
envsubst < local/config/vufind/folio.ini | sponge local/config/vufind/folio.ini
envsubst < local/config/vufind/EDS.ini | sponge local/config/vufind/EDS.ini
envsubst < local/config/vufind/BrowZine.ini | sponge local/config/vufind/BrowZine.ini
envsubst < local/harvest/oai.ini | sponge local/harvest/oai.ini
envsubst < /etc/aliases | sponge /etc/aliases

# Set required environment variables so cron jobs have access to them
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

# Unset env variables that are just used in config files and don't need to be in the environment after this
unset FOLIO_URL FOLIO_USER FOLIO_PASS FOLIO_TENANT FOLIO_REC_ID FOLIO_CANCEL_ID OAI_URL MAIL_HOST MAIL_PORT \
    MAIL_USERNAME MAIL_PASSWORD FEEDBACK_EMAIL EDS_USER EDS_PASS EDS_PROFILE EDS_ORG RECAPTCHA_SITE_KEY \
    RECAPTCHA_SECRET_KEY MATOMO_URL MATOMO_SITE_ID MATOMO_SEARCHBACKEND_DIMENSION

exec "$@"
