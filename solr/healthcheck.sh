#!/bin/bash

# Solr Cloud Healthcheck
#
# This script will verify the following things and either exit with 1 or 0:
#  * The node is online
#  * All 4 of the collections exist
#  * All collections have a health of "GREEN"
#  * Verify the state of the shards for the node
#  * Verify the state of the replicas for the node

EXIT_CODE=0
FAIL_STATES=("down" "recovery failed")

# Get cluster status
CLUSTER_STATUS=$(curl -s --fail "http://localhost:8983/solr/admin/collections?action=clusterstatus")
EC=$?
if [ "${EC}" != "0" ]; then
   echo "ERROR: Failed to query for cluster status! Response: ${CLUSTER_STATUS}"
   exit 1
fi

# Validate the node is online
MATCHED=$( echo "${CLUSTER_STATUS}" | jq ".cluster.live_nodes[]" | grep ${HOSTNAME})
if [[ ${MATCHED} = ${HOSTNAME}* ]]; then
    echo "ERROR: ${HOSTNAME} not found in list of live nodes in cluster!"
    EXIT_CODE=1
fi

# Validate all collections exist
COLLECTION_COUNT=$( echo "${CLUSTER_STATUS}" | jq ".cluster.collections | length")
if [ "${COLLECTION_COUNT}" != "4" ]; then
    echo "ERROR: (${HOSTNAME}) Expect 4 collections, found ${COLLECTION_COUNT}!"
    EXIT_CODE=1
fi

COLLECTIONS=$( echo "${CLUSTER_STATUS}" | jq -c -r ".cluster.collections | keys | .[]")
for COLLECTION in $COLLECTIONS; do
    SHARDS=$( echo "${CLUSTER_STATUS}" | jq -r -c ".cluster.collections.${COLLECTION}.shards | keys | .[]")

    for SHARD in $SHARDS; do
        REPLICAS=$( echo "${CLUSTER_STATUS}" | jq -r -c ".cluster.collections.${COLLECTION}.shards.${SHARD}.replicas | keys | .[]")

        for REPLICA in $REPLICAS; do
            STATE=$( echo "${CLUSTER_STATUS}" | jq -r ".cluster.collections.${COLLECTION}.shards.${SHARD}.replicas.${REPLICA}.state")
            NODE=$( echo "${CLUSTER_STATUS}" | jq -r ".cluster.collections.${COLLECTION}.shards.${SHARD}.replicas.${REPLICA}.node_name")

            # Check replicas hosted on the current node
            if [[ ${NODE} = ${HOSTNAME}* ]]; then
                # Validate replica state
                if [[ " ${FAIL_STATES[*]} " =~ " ${STATE} " ]]; then
                    echo "ERROR: (${HOSTNAME}) ${COLLECTION}.${SHARD}.${REPLICA} has a state of ${STATE}!"
                    EXIT_CODE=1
                fi
            fi;
        done
    done
done

# Exit with set code
exit ${EXIT_CODE}
