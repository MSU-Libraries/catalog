#!/bin/bash

# Create Solr collections
COLLS=("authority" "biblio" "reserves" "website")
for COLL in "${COLLS[@]}"
do
    # Create collection
    # TODO change replication factor to 3 to match number of AWS nodes once deployed there
    curl "http://solr:8983/solr/admin/collections?action=CREATE&name=$COLL&numShards=1&replicationFactor=1&wt=xml&collection.configName=$COLL"
    echo "Created Solr collection for $COLL"
done

# Start Apache
apachectl -DFOREGROUND
