#!/bin/bash

echo "Startup script..."

SHARED_STORAGE="/mnt/shared/local"

if [[ "${STACK_NAME}" != catalog-prod ]]; then
    echo "Replacing robots.txt file with disallow contents"
    echo "User-agent: *" > "${VUFIND_HOME}/public/robots.txt"
    echo "Disallow: /" >> "${VUFIND_HOME}/public/robots.txt"
fi

# Create symlinks to the shared storage for non-production environments
# Populating the shared storage if empty
if [[ "${STACK_NAME}" == devel-* ]]; then
    echo "Setting up links for module/Catalog, and themes/msul directories to ${SHARED_STORAGE}"
    # Set up deploy key
    install -d -m 700 ~/.ssh/
    base64 -d "$DEPLOY_KEY_FILE" > ~/.ssh/id_ed25519
    ( umask 022; touch ~/.ssh/known_hosts )
    chmod 600 ~/.ssh/id_ed25519
    ssh-keyscan gitlab.msu.edu >> ~/.ssh/known_hosts
    git config --system --add safe.directory \*
    # Update the repo (repo is initially cloned during first CI run for branch)
    git -C "${SHARED_STORAGE}/${STACK_NAME}"/repo fetch

    # Set up the symlink to be able to access code from host machine
    if [[ ${VUFIND_CORE_INSTALLATION} == 1 ]]; then
        rm -r /usr/local/vufind/module/Catalog /usr/local/vufind/themes/msul
        # Commenting the setEnv directive
        sed -i -r 's/^\s+SetEnv VUFIND_LOCAL_MODULES Catalog/#&/' /usr/local/vufind/local/httpd-vufind.conf
        # Changing theme in config
        sed -i -r 's/^(theme\s+= )msul/\1bootstrap3/' /usr/local/vufind/local/config/vufind/config.ini
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
SLEEP_TIME=$(( NODE*4 ))
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
        HAS_ERROR=$(echo "${OUTPUT}" | grep -c "SolrException")

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
    echo "Failed to query to the collection aliases in Solr. Exiting. ${ALIASES}"
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
    # TODO vufind10 use scss
    grunt watch:less&
fi

# Unset environment variables that are no longer necessary before starting Apache
unset DEPLOY_KEY_FILE VUFIND_CORE_INSTALLATION

# Start Apache
tail -f /var/log/vufind/vufind.log & apachectl -DFOREGROUND
