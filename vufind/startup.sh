#!/bin/bash

# Create symlinks to the shared storage
# Populating the shared storage if empty
mkdir -p /mnt/shared/local/${STACK_NAME}
if [ ! "$(ls -A /mnt/shared/local/${STACK_NAME})" ]; then
    rsync -aiv /usr/local/vufind/local/ /mnt/shared/local/${STACK_NAME}/local/
    rsync -aiv /usr/local/vufind/themes/msul/ /mnt/shared/local/${STACK_NAME}/msul/
    rsync -aiv /usr/local/vufind/module/Catalog/ /mnt/shared/local/${STACK_NAME}/Catalog/
fi
rm -rf /usr/local/vufind/local
ln -sf /mnt/shared/local/${STACK_NAME}/local /usr/local/vufind
rm -rf /usr/local/vufind/themes/msul
ln -sf /mnt/shared/local/${STACK_NAME}/msul /usr/local/vufind/themes
rm -rf /usr/local/vufind/module/Catalog
ln -sf /mnt/shared/local/${STACK_NAME}/Catalog /usr/local/vufind/module

# Ensure SolrCloud is available prior to creating Collections
SOLR_CLUSTER_SIZE=0
while [[ "$SOLR_CLUSTER_SIZE" -lt 1 ]]; do
    echo "No Solr nodes online yet. Waiting..."
    sleep 5
    SOLR_CLUSTER_SIZE=$(curl -s "http://solr:8983/solr/admin/collections?action=clusterstatus" | jq ".cluster.live_nodes | length")
done

# Create Solr collections
COLLS=("authority" "biblio" "reserves" "website")
for COLL in "${COLLS[@]}"
do
    # Create collection
    curl "http://solr:8983/solr/admin/collections?action=CREATE&name=$COLL&numShards=1&replicationFactor=3&wt=xml&collection.configName=$COLL"
    echo "Created Solr collection for $COLL"
done

# Run grunt if a devel/review site
if [[ ! ${SITE_HOSTNAME} = catalog* ]]; then
    grunt watch:less&
fi

# Start Apache
tail -f /var/log/vufind/vufind.log & apachectl -DFOREGROUND
