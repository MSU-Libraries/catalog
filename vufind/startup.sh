#!/bin/bash

echo "Startup script..."

SHARED_STORAGE="/mnt/shared/local"
TIMESTAMP=$( date +%Y%m%d%H%M%S )

if [[ "${STACK_NAME}" != catalog-prod ]]; then
    echo "Replacing robots.txt file with disallow contents"
    echo "User-agent: *" > ${VUFIND_HOME}/public/robots.txt
    echo "Disallow: /" >> ${VUFIND_HOME}/public/robots.txt
fi

# Create symlinks to the shared storage for non-production environments
# Populating the shared storage if empty
if [[ "${STACK_NAME}" == devel-* ]]; then
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
        chown msuldevs -R "${SHARED_STORAGE}/${STACK_NAME}"/repo/vufind/module/
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
    # Make sure permissions haven't gotten changed on the share along the way
    # (This can happen no matter what on devel container startup)
    chown msuldevs:msuldevs -R "${SHARED_STORAGE}/${STACK_NAME}"/repo/*
    chown www-data -R "${SHARED_STORAGE}/${STACK_NAME}"/repo/vufind/themes/msul/
    chown msuldevs:msuldevs -R "${SHARED_STORAGE}/${STACK_NAME}"/local-confs/*
    rsync -aip --chmod=D2775,F664 --exclude "*.sh" --exclude "cicd" --exclude "*scripts*" "${SHARED_STORAGE}/${STACK_NAME}"/ "${SHARED_STORAGE}/${STACK_NAME}"/
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
let SLEEP_TIME=${NODE}*4
sleep $SLEEP_TIME

# Create Solr collections
# biblio needs to be done first in order to initialize the ICUTokenizerFactory
COLLS=("biblio1" "biblio2" "authority" "reserves" "website")
for COLL in "${COLLS[@]}"
do
    # See if the collection already exists in Solr
    echo "Existing collections:"
    curl -s "${CLUSTER_STATUS_URL}" | jq ".cluster.collections | keys[]"

    MATCHED_COLL=$(curl -s "${CLUSTER_STATUS_URL}" | jq ".cluster.collections | keys[]" | grep "${COLL}")
    while [[ -z "${MATCHED_COLL}" ]]; do
        echo "Existing collections:"
        curl -s "${CLUSTER_STATUS_URL}" | jq ".cluster.collections | keys[]"

        # Create collection
        OUTPUT=$(curl -s "http://solr:8983/solr/admin/collections?action=CREATE&name=${COLL}&numShards=1&replicationFactor=3&wt=xml&collection.configName=${COLL}")
        HAS_ERROR=$(echo ${OUTPUT} | grep "SolrException" | wc -l)

        if [[ ${HAS_ERROR} -gt 0 ]]; then
            echo "Failed to create Solr collection ${COLL}. ${OUTPUT}"
            sleep 5
        else
            echo "Created Solr collection for ${COLL}."
            # Not breaking here so we can do a final curl call to verify it is showing in the cluster status properly
        fi
        MATCHED_COLL=$(curl -s "${CLUSTER_STATUS_URL}" | jq ".cluster.collections | keys[]" | grep "${COLL}")
    done
    echo "Verified that Solr collection ${COLL} exists."
done

echo "If there are no aliases create them"
if ! ALIASES=$(curl "http://solr:8983/solr/admin/collections?action=LISTALIASES&wt=json" -s); then
    echo "Failed to query to the collection alaises in Solr. Exiting. ${ALIASES}"
    exit 1
fi
if ! [[ "${ALIASES}" =~ .*"biblio".* ]]; then
    if ! OUTPUT=$(curl -s "http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio&collections=biblio1"); then
        echo "Failed to create biblio alias pointing to biblio1. Exiting. ${OUTPUT}"
        exit 1
    fi
    if ! OUTPUT=$(curl -s "http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio-build&collections=biblio2");then
        echo "Failed to create biblio-build alias pointing to biblio2. Exiting. ${OUTPUT}"
        exit 1
    fi
fi

# Run grunt if a devel/review site
if [[ ! ${SITE_HOSTNAME} = catalog* ]]; then
    echo "Starting grunt to auto-compile theme changes..."
    grunt watch:less&
fi

# Unset environment variables that are no longer necessary before starting Apache
unset DEPLOY_KEY

# Start Apache
tail -f /var/log/vufind/vufind.log & apachectl -DFOREGROUND
