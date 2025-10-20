#!/bin/bash

verbose() {
    LOG_TS=$(date +%Y-%m-%d\ %H:%M:%S)
    echo "[${LOG_TS}] $1"
}

verbose "Startup script..."

SHARED_STORAGE="/mnt/shared/local"

if [[ "${STACK_NAME}" != catalog-prod ]]; then
    verbose "Replacing robots.txt file with disallow contents"
    echo "User-agent: *" > "${VUFIND_HOME}/public/robots.txt"
    echo "Disallow: /" >> "${VUFIND_HOME}/public/robots.txt"
fi

# Create symlinks to the shared storage for non-production environments
# Populating the shared storage if empty
if [[ "${STACK_NAME}" == devel-* ]]; then
    verbose "Setting up links for module/Catalog, and themes/msul directories to ${SHARED_STORAGE}"
    # Set up deploy key
    install -d -m 700 ~/.ssh/
    base64 -d "$DEPLOY_KEY_FILE" > ~/.ssh/id_ed25519
    ( umask 022; touch ~/.ssh/known_hosts )
    chmod 600 ~/.ssh/id_ed25519
    ssh-keyscan gitlab.msu.edu >> ~/.ssh/known_hosts
    git config --system --add safe.directory \*
    # Update the repo (repo is initially cloned during first CI run for branch)
    (umask 0002; git -C "${SHARED_STORAGE}/${STACK_NAME}"/repo fetch)

    # Set up the symlink to be able to access code from host machine
    if [[ ${VUFIND_CORE_INSTALLATION} == 1 ]]; then
        rm -r /usr/local/vufind/module/Catalog /usr/local/vufind/themes/msul
        # Changing theme in config
        sed -i -r 's/^(theme\s+= )msul/\1bootstrap5/' /usr/local/vufind/local/config/vufind/config.ini
    fi

    # Enable detailed error reporting for devel
    sed -i -E 's#^(file\s+= /var/log/vufind/vufind.log:).*$#\1alert-5,error-5,notice-5,debug-1#' /usr/local/vufind/local/config/vufind/config.ini

    # Make sure permissions haven't gotten changed on the share along the way
    # (This can happen no matter what on devel container startup)
    chown 1000:1000 -R "${SHARED_STORAGE}/${STACK_NAME}"/repo/
    chown www-data -R "${SHARED_STORAGE}/${STACK_NAME}"/repo/vufind/themes/msul/
fi

# Save the logs in the logs docker volume
mkdir -p /mnt/logs/apache /mnt/logs/vufind /mnt/logs/simplesamlphp /mnt/logs/harvests /mnt/logs/backups
chown www-data:www-data /mnt/logs/simplesamlphp
rm -rf /var/log/apache2
ln -sf /mnt/logs/apache /var/log/apache2
ln -sf /mnt/logs/vufind /var/log/vufind
ln -sf /mnt/logs/simplesamlphp /var/log/simplesamlphp
touch /mnt/logs/vufind/vufind.log
touch /var/log/simplesamlphp/simplesamlphp.log
chown www-data:www-data /mnt/logs/vufind/vufind.log /var/log/simplesamlphp/simplesamlphp.log

# Link to shared BannerNotices.yaml
ln -f -s /mnt/shared/config/BannerNotices.yaml /usr/local/vufind/local/config/vufind/BannerNotices.yaml
ln -f -s /mnt/shared/config/LocationNotices.yaml /usr/local/vufind/local/config/vufind/LocationNotices.yaml
ln -f -s /mnt/shared/config/RequestNotices.yaml /usr/local/vufind/local/config/vufind/RequestNotices.yaml

# Prepare cache cli dir (volume only exists after start)
clear-vufind-cache

verbose "Running Solr query with healthcheck ID to confirm Solr readiness"
curl --max-time 5 -o /dev/null -s "http://solr:8983/solr/biblio/select?fl=%2A&wt=json&json.nl=arrarr&q=id%3A%22folio.${FOLIO_REC_ID}%22"
EXIT_STATUS=$?
while [[ "$EXIT_STATUS" -eq 28 ]]; do # exit code 28 is curl timeout
    verbose "Solr not ready yet (timed out). Waiting..."
    sleep 5
    curl --max-time 5 -o /dev/null -s "http://solr:8983/solr/biblio/select?fl=%2A&wt=json&json.nl=arrarr&q=id%3A%22folio.${FOLIO_REC_ID}%22"
    EXIT_STATUS=$?
done
verbose "Solr ready!"

# Run npm if a devel/review site
if [[ ! ${SITE_HOSTNAME} = catalog* ]]; then
    verbose "Starting npm to auto-compile theme changes..."
    npm run watch:scss&
else
    verbose "Running npm to compile theme changes..."
    npm run build:scss
fi


# Unset environment variables that are no longer necessary before starting Apache
unset DEPLOY_KEY_FILE VUFIND_CORE_INSTALLATION

# Start Apache
verbose "Starting Apache..."
# shellcheck disable=SC1091
source /etc/apache2/envvars
tail -f /var/log/vufind/vufind.log & /usr/sbin/apache2 -DFOREGROUND "$@"
