#!/bin/bash

SHARED_STORAGE="/mnt/shared/local"

# Create symlinks to the shared storage for non-production environments
# Populating the shared storage if empty
if [ "${STACK_NAME}" != "catalog-beta" ]; then
    echo "Linking the local, module/Catalog, and themes/msul directories to ${SHARED_STORAGE}"
    mkdir -p ${SHARED_STORAGE}/${STACK_NAME}
    if [ ! "$(ls -A ${SHARED_STORAGE}/${STACK_NAME})" ]; then
        rsync -aiv /usr/local/vufind/local/ ${SHARED_STORAGE}/${STACK_NAME}/local/
        rsync -aiv /usr/local/vufind/themes/msul/ ${SHARED_STORAGE}/${STACK_NAME}/msul/
        rsync -aiv /usr/local/vufind/module/Catalog/ ${SHARED_STORAGE}/${STACK_NAME}/Catalog/
    fi
    rm -rf /usr/local/vufind/local
    ln -sf ${SHARED_STORAGE}/${STACK_NAME}/local /usr/local/vufind
    rm -rf /usr/local/vufind/themes/msul
    ln -sf ${SHARED_STORAGE}/${STACK_NAME}/msul /usr/local/vufind/themes
    rm -rf /usr/local/vufind/module/Catalog
    ln -sf ${SHARED_STORAGE}/${STACK_NAME}/Catalog /usr/local/vufind/module
fi;

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
