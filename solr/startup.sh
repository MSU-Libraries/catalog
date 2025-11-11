#!/bin/bash

# Java security manager is incompatible with AlphaBrowse handler
export SOLR_SECURITY_MANAGER_ENABLED="false"

COLLEX_CONFIGS=/solr_confs

# Log rotation consistent with logrotate (PC-1530)
# log4j2.xml is copied from /opt/solr/server/resources/log4j2.xml to /var/solr/log4j2.xml
# by Solr's docker-entrypoint.sh the first it is run
# /var/solr is saved in a volume, so it's easier to edit log4j2.xml here than in the Dockerfile
sed -i 's/<DefaultRolloverStrategy max="10"\/>/<DefaultRolloverStrategy max="10" fileIndex="min"\/>/g' /var/solr/log4j2.xml

# Give Zookeeper time to startup
echo "Waiting for Zookeeper to come online..."
sleep 15;

# Perform solr "bootstrap" steps
echo "Checking if Zookeeper needs to bootstrap /solr path (Hosts: $SOLR_ZK_HOSTS)"
if ! solr zk ls /solr -z "$SOLR_ZK_HOSTS"; then
    echo "Bootstrapping creation of /solr root path"
    solr zk mkroot /solr -z "$SOLR_ZK_HOSTS"
else
    echo "Found /solr already in Zookeeper."
fi

echo "Creating required VuFind Solr collections..."
for COLL_DIR in "${COLLEX_CONFIGS}/"*; do
    COLL=$(basename "$COLL_DIR")
    if [[ -d "$COLL_DIR" && "$COLL" != "jars" ]]; then
        echo "Attempting to upload Solr config for $COLL collection..."
        # Add configuration files
        solr zk upconfig --conf-name "$COLL" --conf-dir "$COLL_DIR/conf" -z "$ZK_HOST"
        echo "Created config set for $COLL with files from $COLL_DIR/conf"
    fi
done

# Call base image CMD
echo "Running Solr start script: $*"
exec "$@"
