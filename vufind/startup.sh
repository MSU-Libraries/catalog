#!/bin/bash

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
