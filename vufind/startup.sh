#!/bin/bash

SHARED_STORAGE="/mnt/shared/local"
TIMESTAMP=$( date +%Y%m%d%H%M%S )

# Create symlinks to the shared storage for non-production environments
# Populating the shared storage if empty
if [[ "${STACK_NAME}" != catalog-* ]]; then
    echo "Setting up links for module/Catalog, and themes/msul directories to ${SHARED_STORAGE}"
    mkdir -p ${SHARED_STORAGE}/${STACK_NAME}/local-confs
    mkdir -p ${SHARED_STORAGE}/${STACK_NAME}/repo
    chmod g+ws ${SHARED_STORAGE}/${STACK_NAME}/local-confs
    chmod g+ws ${SHARED_STORAGE}/${STACK_NAME}/repo
    # Set up deploy key
    install -d -m 700 ~/.ssh/
    echo "$DEPLOY_KEY" | base64 -d > ~/.ssh/id_ed25519
    ( umask 022; touch ~/.ssh/known_hosts )
    chmod 600 ~/.ssh/id_ed25519
    ssh-keyscan gitlab.msu.edu >> ~/.ssh/known_hosts
    # Set up the "repo" dir
    if [ ! -d "${SHARED_STORAGE}/${STACK_NAME}/repo/.git" ]; then
        # Clone repository
        git clone -b ${STACK_NAME} git@gitlab.msu.edu:msu-libraries/devops/catalog.git ${SHARED_STORAGE}/${STACK_NAME}/repo
        # Set up the repository for group editting
        git config --system --add safe.directory \*
        git -C "${SHARED_STORAGE}/${STACK_NAME}"/repo config core.sharedRepository group
        chgrp -R msuldevs "${SHARED_STORAGE}/${STACK_NAME}"/repo
        chmod -R g+rw "${SHARED_STORAGE}/${STACK_NAME}"/repo
        chmod g-w "${SHARED_STORAGE}/${STACK_NAME}"/repo/.git/objects/pack/*
        find "${SHARED_STORAGE}/${STACK_NAME}" -type d -exec chmod g+s {} \;
        chown www-data -R "${SHARED_STORAGE}/${STACK_NAME}"/repo/vufind/themes/
        chown www-data -R "${SHARED_STORAGE}/${STACK_NAME}"/repo/vufind/module/
    fi
    git -C "${SHARED_STORAGE}/${STACK_NAME}"/repo fetch
    # Setting up "local" sync dir
    if [[ $( ls -1 ${SHARED_STORAGE}/${STACK_NAME}/local-confs/* | wc -l ) -gt 0 ]]; then
        # archive the last pipeline's configs
        mkdir -p ${SHARED_STORAGE}/${STACK_NAME}/.archive/${TIMESTAMP}
        mv ${SHARED_STORAGE}/${STACK_NAME}/local-confs/* ${SHARED_STORAGE}/${STACK_NAME}/.archive/${TIMESTAMP}
    fi
    # Sync over the current pipeline's configs
    rsync -ai /usr/local/vufind/local/ ${SHARED_STORAGE}/${STACK_NAME}/local-confs/
    # Set up the symlink
    rm -rf /usr/local/vufind/local
    ln -sf ${SHARED_STORAGE}/${STACK_NAME}/local-confs /usr/local/vufind/local
    rm -rf /usr/local/vufind/themes/msul
    ln -sf ${SHARED_STORAGE}/${STACK_NAME}/repo/vufind/themes/msul /usr/local/vufind/themes
    rm -rf /usr/local/vufind/module/Catalog
    ln -sf ${SHARED_STORAGE}/${STACK_NAME}/repo/vufind/module/Catalog /usr/local/vufind/module
fi

# Save the logs in the logs docker volume
mkdir -p /mnt/logs/apache /mnt/logs/vufind /mnt/logs/simplesamlphp /mnt/logs/harvests /mnt/logs/backups
rm -rf /var/log/apache2
ln -sf /mnt/logs/apache /var/log/apache2
ln -sf /mnt/logs/vufind /var/log/vufind
ln -sf /mnt/logs/simplesamlphp /var/log/simplesamlphp
touch /mnt/logs/vufind/vufind.log
touch /var/log/simplesamlphp/simplesamlphp.log
chown www-data:www-data /mnt/logs/vufind/vufind.log /var/log/simplesamlphp/simplesamlphp.log

# Prepare cache cli dir (volume only exists after start)
clear-vufind-cache

# Ensure SolrCloud is available prior to creating Collections
CLUSTER_STATUS_URL="http://solr:8983/solr/admin/collections?action=clusterstatus"
SOLR_CLUSTER_SIZE=0
while [[ "$SOLR_CLUSTER_SIZE" -lt 1 ]]; do
    echo "No Solr nodes online yet. Waiting..."
    sleep 5
    SOLR_CLUSTER_SIZE=$(curl -s "${CLUSTER_STATUS_URL}" | jq ".cluster.live_nodes | length")
done

# Sleep before creating collections so all
# nodes don't try at the same time
let SLEEP_TIME=${NODE}*2
sleep $SLEEP_TIME

# Create Solr collections
COLLS=("authority" "biblio" "reserves" "website")
for COLL in "${COLLS[@]}"
do
    # See if the collection already exists in Solr
    MATCHED_COLL=$(curl -s "${CLUSTER_STATUS_URL}" | jq ".cluster.collections | keys[]" | grep "${COLL}")
    if [ -z "${MATCHED_COLL}" ]; then
        # Create collection
        curl "http://solr:8983/solr/admin/collections?action=CREATE&name=$COLL&numShards=1&replicationFactor=3&wt=xml&collection.configName=$COLL"
        echo "Created Solr collection for $COLL."
    else
        echo "Verified that Solr collection $COLL exists."
    fi
done

# Run grunt if a devel/review site
if [[ ! ${SITE_HOSTNAME} = catalog* ]]; then
    echo "Starting grunt to auto-compile theme changes..."
    grunt watch:less&
fi

# Start Apache
tail -f /var/log/vufind/vufind.log & apachectl -DFOREGROUND
