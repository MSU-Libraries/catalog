#!/bin/bash

# Solr Cloud Cluster Health Status Check
#
# This script will verify the following things and either exit with 1 or 0:
#  * All of the nodes are online
#  * All 4 of the collections exist
#  * All collections have a health of "GREEN"
#  * All shards have a status of "active"
#  * All replicas have a status of "active"

EXIT_CODE=0

# Get cluster status
CLUSTER_STATUS=$(curl -s --fail "http://localhost:8983/solr/admin/collections?action=clusterstatus")
EC=$?
if [ "${EC}" != "0" ]; then
   echo "ERROR: Failed to query for cluster status! Response: ${CLUSTER_STATUS}"
   exit 1
fi

# Validate all nodes are online
CLUSTER_SIZE=$( echo "${CLUSTER_STATUS}" | jq ".cluster.live_nodes | length")
NUM_NODES=$(echo "${CLUSTER_STATUS}" | grep node_name | sort | uniq | wc -l )
if [ "${CLUSTER_SIZE}" != "${NUM_NODES}" ]; then
    echo "ERROR: Expect ${NUM_NODES} nodes, found ${COLLECTION_COUNT}!"
    EXIT_CODE=1
fi

# Validate all collections exist
COLLECTION_COUNT=$( echo "${CLUSTER_STATUS}" | jq ".cluster.collections | length")
if [ "${COLLECTION_COUNT}" != "4" ]; then
    echo "ERROR: Expect 4 collections, found ${COLLECTION_COUNT}!"
    EXIT_CODE=1
fi

COLLECTIONS=$( echo "${CLUSTER_STATUS}" | jq -c -r ".cluster.collections | keys | .[]")
for COLLECTION in $COLLECTIONS; do
    # Validate collection health
    HEALTH=$( echo "${CLUSTER_STATUS}" | jq -r ".cluster.collections.${COLLECTION}.health")
    if [ "${HEALTH}" != "GREEN" ]; then
        echo "ERROR: Collection '${COLLECTION}' has a health of ${HEALTH} (expected: GREEN)"
        EXIT_CODE=1
    fi

    SHARDS=$( echo "${CLUSTER_STATUS}" | jq -r -c ".cluster.collections.${COLLECTION}.shards | keys | .[]")
    for SHARD in $SHARDS; do
        # Validate shard state
        STATE=$( echo "${CLUSTER_STATUS}" | jq -r ".cluster.collections.${COLLECTION}.shards.${SHARD}.state")
        if [ "${STATE}" != "active" ]; then
            echo "ERROR: Collection '${COLLECTION}', Shard '${SHARD}' has a state of ${STATE} (expected: active)"
            EXIT_CODE=1
        fi

        REPLICAS=$( echo "${CLUSTER_STATUS}" | jq -r -c ".cluster.collections.${COLLECTION}.shards.${SHARD}.replicas | keys | .[]")
        for REPLICA in $REPLICAS; do
            # Validate replica state
            STATE=$( echo "${CLUSTER_STATUS}" | jq -r ".cluster.collections.${COLLECTION}.shards.${SHARD}.replicas.${REPLICA}.state")
            if [ "${STATE}" != "active" ]; then
                echo "ERROR: Collection '${COLLECTION}', Shard '${SHARD}', Replica '${REPLICA}' has a state of ${STATE} (expected: active)"
                EXIT_CODE=1
            fi
        done

    done
done

# Exit with set code
exit ${EXIT_CODE}
