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

echo "If there are no aliases create them"
if ! ALIASES=$(curl "http://${STACK_NAME}-solr_solr:8983/solr/admin/collections?action=LISTALIASES&wt=json" -s); then
    echo "Failed to query to the collection alaises in Solr. Exiting"
    exit 1
fi
if ! [[ "${ALISES}" =~ .*"biblio".* ]]; then
    if ! OUTPUT=$(curl "http://${STACK_NAME}-solr_solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio&collections=biblio1"); then
        echo "Failed to create biblio alias pointing to biblio1. Exiting. ${OUTPUT}"
        exit 1
    fi
    if ! OUTPUT=$(curl "http://${STACK_NAME}-solr_solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio-build&collections=biblio2");then
        echo "Failed to create biblio-build alias pointing to biblio2. Exiting. ${OUTPUT}"
        exit 1
    fi
fi

# Call base image CMD
echo "Running Solr start script: $@"
exec "$@"
