#!/bin/bash

# Java security manager is incompatible with AlphaBrowse handler
export SOLR_SECURITY_MANAGER_ENABLED="false"

COLLEX_CONFIGS=/solr_confs

# Give Zookeeper time to startup
echo "Waiting for Zookeeper to come online..."
sleep 15;

# Perform solr "bootstrap" steps
echo "Checking if Zookeeper needs to bootstrap /solr path (Hosts: $SOLR_ZK_HOSTS)"
if ! solr zk ls /solr -z $SOLR_ZK_HOSTS; then
    echo "Bootstrapping creation of /solr root path"
    solr zk mkroot /solr -z $SOLR_ZK_HOSTS
else
    echo "Found /solr already in Zookeeper."
fi

# Modify `biblio` config as we are using `biblio09112023` which uses an alias of `biblio`
if [[ "${STACK_NAME}" == "catalog-"* ]]; then
    cp -r /solr_confs/biblio /solr_confs/biblio09112023
    sed -i "s/\\bbiblio\\b/biblio9/" "${COLLEX_CONFIGS}/biblio09112023/conf/solrconfig.xml"
fi

echo "Creating required VuFind Solr collections..."
for COLL_DIR in "${COLLEX_CONFIGS}/"*; do
    COLL=$(basename $COLL_DIR)
    if [[ -d "$COLL_DIR" && "$COLL" != "jars" ]]; then
        echo "Attempting to upload Solr config for $COLL collection..."
        # Add configuration files
        solr zk upconfig -confname $COLL -confdir $COLL_DIR/conf -z $SOLR_ZK_HOSTS/solr
        echo "Created config set for $COLL with files from $COLL_DIR/conf"
    fi
done

# Call base image CMD
echo "Running Solr start script: $@"
exec "$@"
