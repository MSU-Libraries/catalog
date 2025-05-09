#!/bin/bash

echo "Entrypoint script..."

# Replace environment variables in template ini files
envsubst < local/config/vufind/config.ini | sponge local/config/vufind/config.ini
envsubst < local/config/vufind/contentsecuritypolicy.ini | sponge local/config/vufind/contentsecuritypolicy.ini
envsubst < local/config/vufind/folio.ini | sponge local/config/vufind/folio.ini
envsubst < local/config/vufind/FeedbackForms.yaml | sponge local/config/vufind/FeedbackForms.yaml
envsubst < local/config/vufind/EDS.ini | sponge local/config/vufind/EDS.ini
envsubst < local/config/vufind/EPF.ini | sponge local/config/vufind/EPF.ini
envsubst < local/config/vufind/BrowZine.ini | sponge local/config/vufind/BrowZine.ini
envsubst < local/harvest/oai.ini | sponge local/harvest/oai.ini
envsubst < /etc/aliases | sponge /etc/aliases

# Finish SimpleSAMLphp config setup
# shellcheck disable=SC2016
envsubst '${SIMPLESAMLPHP_SALT} ${SIMPLESAMLPHP_ADMIN_PW_FILE} ${SIMPLESAMLPHP_CUSTOM_DIR} ${MARIADB_VUFIND_PASSWORD_FILE}' < "${SIMPLESAMLPHP_CONFIG_DIR}/config.php" | \
    sponge "${SIMPLESAMLPHP_CONFIG_DIR}/config.php"

# Add in only the password file to the crontab for now
# shellcheck disable=SC2016
envsubst '${MARIADB_VUFIND_PASSWORD_FILE}' < /etc/cron.d/crontab | sponge /etc/cron.d/crontab

# Unset env variables that are just used in config files and don't need to be in the environment after this.
# SIMPLESAMLPHP_HOME, BROWZINE_LIBRARY and BROWZINE_TOKEN
# cannot be unset yet because our custom PHP code uses the environment variables instead of the configs.
unset FOLIO_URL FOLIO_USER FOLIO_PASS FOLIO_TENANT FOLIO_REC_ID FOLIO_CANCEL_ID OAI_URL MAIL_HOST MAIL_PORT \
    FEEDBACK_EMAIL FEEDBACK_PUBLIC_EMAIL EDS_USER EDS_PASS EDS_PROFILE EDS_ORG \
    RECAPTCHA_SITE_KEY RECAPTCHA_SECRET_KEY MATOMO_URL MATOMO_SITE_ID MATOMO_SEARCHBACKEND_DIMENSION \
    SESSION_BOT_SALT SIMPLESAMLPHP_SALT SIMPLESAMLPHP_ADMIN_PW_FILE SIMPLESAMLPHP_VERSION SIMPLESAMLPHP_CUSTOM_DIR \
    MARIADB_VUFIND_PASSWORD MARIADB_VUFIND_PASSWORD_FILE SYNDETICS_ID

if [[ "$1" == "/startup-cron.sh" ]]; then
    if ! grep -q STACK_NAME /etc/environment; then
        # Set required environment variables so cron jobs have access to them
        {
            echo JAVA_HOME="$JAVA_HOME"
            echo VUFIND_HOME="$VUFIND_HOME"
            echo VUFIND_LOCAL_DIR="$VUFIND_LOCAL_DIR"
            echo VUFIND_CACHE_DIR="$VUFIND_CACHE_DIR"
            echo VUFIND_LOCAL_MODULES="$VUFIND_LOCAL_MODULES"
            echo HLM_FTP_USER="$HLM_FTP_USER"
            echo HLM_FTP_PASSWORD_FILE="$HLM_FTP_PASSWORD_FILE"
            echo AUTH_FTP_USER="$AUTH_FTP_USER"
            echo AUTH_FTP_PASSWORD="$AUTH_FTP_PASSWORD"
            echo STACK_NAME="$STACK_NAME"
        } >> /etc/environment
    fi
else
    # Unset variables that are only useful for cron jobs.
    # We are still passing them to the catalog container in the docker-compose, because devel environments
    # don't have the cron container and we might need them in the catalog container when doing a docker exec.
    unset HLM_FTP_USER HLM_FTP_PASSWORD_FILE AUTH_FTP_USER AUTH_FTP_PASSWORD SOLR_URL
fi

exec "$@"
