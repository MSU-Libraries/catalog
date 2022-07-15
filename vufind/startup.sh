#!/bin/bash

# Create Solr collections
COLLS=("authority" "biblio" "reserves" "website")
for COLL in "${COLLS[@]}"
do
    # Create collection
    curl "http://solr:8983/solr/admin/collections?action=CREATE&name=$COLL&numShards=1&replicationFactor=3&wt=xml&collection.configName=$COLL"
    echo "Created Solr collection for $COLL"
done

# Start Apache
apachectl -DFOREGROUND
