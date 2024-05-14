#!/bin/bash

galera_node_query() {
    NODE="$1"
    QUERY="$2"
    declare -g ROW_CNT=0
    declare -g -a ROW_$ROW_CNT=
    while read -r -a ROW_$ROW_CNT; do
        (( ROW_CNT+=1 ))
        declare -g -a ROW_$ROW_CNT
    done < <( mysql -h "$NODE" -u root -p"$(cat "${MARIADB_ROOT_PASSWORD_FILE}")" --silent -e "$QUERY" )
    return $ROW_CNT
}

# Check if a sync is in progress, and always return healthy
if [[ -f "/bitnami/mariadb/data/sst_in_progress" ]]; then
    echo "Sync in progress"
    exit 0
fi

if ! mysqladmin -u root -p"$(cat "${MARIADB_ROOT_PASSWORD_FILE}")" ping; then
    echo "Failed mysqladmin ping."
    exit 1
fi

if ! mysqladmin -u root -p"$(cat "${MARIADB_ROOT_PASSWORD_FILE}")" status; then
    echo "Failed mysqladmin status."
    exit 1
fi

galera_node_query "localhost" "SHOW WSREP_STATUS"
ROW_CNT="$?"
# Row indices => 0:Node_Index,1:Node_Status,2:Cluster_Status,3:Cluster_Size
if [[ "$ROW_CNT" -ne 1 ]]; then
    echo "WSREP_STATUS did not return one row."
    exit 1
fi
echo "Node is ${ROW_0[2]} ${ROW_0[1]} (cluster size: ${ROW_0[3]})"
if [[ "${ROW_0[2]}" != "primary" ]]; then
    echo "Node not part of primary cluster."
    exit 1
fi
if [[ "${ROW_0[1]}" != "synced" && "${ROW_0[1]}" != "donor" ]]; then
    echo "Node status not synced/donor. ${ROW_0[1]}"
    exit 1
fi

exit 0
