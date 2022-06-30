#!/bin/bash

COLLEX_CONFIGS=/solr_confs/

# Give Zookeeper time to startup
sleep 15;
for COLL_DIR in ${COLLEX_CONFIGS}*
do
    COLL=$(basename $COLL_DIR)
    if [[ -d "$COLL_DIR" && "$COLL" != "jars" ]]; then
        # Add configuration files
        solr zk upconfig -confname $COLL -confdir $COLL_DIR/conf -z $SOLR_ZK_HOSTS/solr
        echo "Created config set for $COLL with files from $COLL_DIR/conf"
    fi;
done

# Call base image CMD
/opt/bitnami/scripts/solr/run.sh
