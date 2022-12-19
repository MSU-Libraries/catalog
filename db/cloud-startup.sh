#!/bin/bash

## Reference variables ##
# Get array of Galera nodes from env var
# MARIADB_GALERA_CLUSTER_ADDRESS=gcomm://galera1,galera2,galera3
# Bootstap variable; tells Galera to attempt to bootstrap the cluster
#MARIADB_GALERA_CLUSTER_BOOTSTRAP=yes
# This var tells Galera to force bootstap even when grastate.dat has "safe_to_bootstrap: 0"
#MARIADB_GALERA_FORCE_SAFETOBOOTSTRAP=yes
# Node's own own node name
#GALERA_HOST

GALERA_STATE_FILE=/bitnami/mariadb/data/grastate.dat
NODES_STR="${MARIADB_GALERA_CLUSTER_ADDRESS##gcomm://}"
NODES_ARR=(${NODES_STR//,/ })
declare -g -A NODE_IPS
declare -g GALERA_PID

verbose() {
    1>&2 echo "::${GALERA_HOST}: $1"
}

node_number() {
    echo "${1##galera}"
}

###############################
## Check if array contains a given value
##  $1 -> Name of array to search
##  $2 -> Value to find
## Returns 0 if an element matches the value to find
array_contains() {
    local ARRNAME="$1[@]"
    local HAYSTACK=( ${!ARRNAME} )
    local NEEDLE="$2"
    for VAL in "${HAYSTACK[@]}"; do
        if [[ "$NEEDLE" == "$VAL" ]]; then
            return 0
        fi
    done
    return 1
}

ip_from_hostname() {
    PAIR=$( getent hosts "$1")
    RC=$?
    echo "$PAIR" | awk '{ print $1 }'
    return "$RC"
}

hostname_from_ip() {
    IP="$1"
    for HOST in "${!NODE_IPS[@]}"; do
        if [[ "${NODE_IPS[$HOST]}" == "$IP" ]]; then
            echo "$HOST"
            return 0
        fi
    done
    return 1
}

#####################
# Given either an IP or hostname, return the hostname
to_hostname() {
    ARG="$1"
    HOST=$( hostname_from_ip "$ARG" )
    if [[ "$?" -ne 0 ]]; then
        HOST="$ARG" # Assuming ARG isn't some other random value; we could check NODES_ARR here
    fi
    echo "$HOST"
}

#####################
# Given either an IP or hostname, return the IP
to_ip() {
    ARG="$1"
    IP="$ARG"
    if ! hostname_from_ip "$ARG"; then
        # Check failed, thus ARG should be hostname
        IP="${NODE_IPS[$ARG]}"
    fi
    echo "$IP"
}

update_node_ips() {
    for NODE in "${NODES_ARR[@]}"; do
        NEW_IP=$(ip_from_hostname "$NODE")
        RC="$?"
        if [[ "$RC" -eq 0 ]]; then
            NODE_IPS["$NODE"]=$(ip_from_hostname "$NEW_IP")
        fi
    done
}

galera_node_online() {
    nc -zw1 "$1" 3306 2> /dev/null
    RC=$?
    verbose "Scanned ${1} - return code: $RC"
    return $RC
}

any_galera_node_online() {
    ONLINE=1
    for NODE in "${NODES_ARR[@]}"; do
        if galera_node_online "$NODE"; then
            ONLINE=0
        fi
    done
    return $ONLINE
}

galera_node_query() {
    NODE="$1"
    QUERY="$2"
    declare -g ROW_CNT=0
    declare -g -a ROW_$ROW_CNT=
    while read -r -a ROW_$ROW_CNT; do
        (( ROW_CNT+=1 ))
        declare -g -a ROW_$ROW_CNT
    done < <( timeout 2 mysql -h "$NODE" -u root -p"$MARIADB_ROOT_PASSWORD" --silent -e "$QUERY" )
    return $ROW_CNT
}

galera_node_is_primary_synced() {
    NODE_IP=$( to_ip "$1" )
    NODE_NAME=$( to_hostname "$1" )
    # Row indices => 0:Node_Index,1:Node_Status,2:Cluster_Status,3:Cluster_Size
    if galera_node_query "$NODE_IP" "SHOW WSREP_STATUS"; then  # return is number of rows, so 0 is failure
        verbose "No response to SHOW WSREP_STATUS on $NODE_NAME"
        return 1
    fi
    if [[ "${ROW_0[2]}" != "primary" ]]; then
        verbose "Warning: WSREP_STATUS $NODE_NAME Cluster Status = ${ROW_0[0]}"
        return 1
    fi
    # Possible statuses: Initialized, Connnected, Joining, Waiting on SST, Joined, Synced, Donor, Error, Disconnecting, Disconnected
    if [[ "${ROW_0[1]}" != "synced" ]]; then
        verbose "Warning: WSREP_STATUS $NODE_NAME Node Status = ${ROW_0[0]}"
        return 1
    fi
    return 0
}

any_galera_node_is_primary_synced() {
    ONLINE=1
    for NODE in "${NODES_ARR[@]}"; do
        if galera_node_is_primary_synced "$NODE"; then
            ONLINE=0
        fi
    done
    return $ONLINE
}

another_galera_node_is_primary_synced() {
    ONLINE=1
    for NODE in "${NODES_ARR[@]}"; do
        if [[ "$NODE" == "$GALERA_HOST" ]]; then
            continue
        fi
        if galera_node_is_primary_synced "$NODE"; then
            ONLINE=0
        fi
    done
    return $ONLINE
}

galera_node_is_donor() {
    NODE_IP=$( to_ip "$1" )
    NODE_NAME=$( to_hostname "$1" )
    # Row indices => 0:Node_Index,1:Node_Status,2:Cluster_Status,3:Cluster_Size
    if galera_node_query "$NODE_IP" "SHOW WSREP_STATUS"; then  # return is number of rows, so 0 is failure
        verbose "No response to SHOW WSREP_STATUS on $NODE_NAME"
        return 2
    fi
    if [[ "${ROW_0[1]}" == "donor" ]]; then
        return 0
    fi
    return 1
}

galera_cluster_membership_is_okay() {
    # Row indices => 0:Index,1:Uuid,2:Name,3:Address
    GALERA_HOST_IP=$( to_ip "$GALERA_HOST" )
    if galera_node_query "$GALERA_HOST_IP" "SHOW WSREP_MEMBERSHIP"; then  # return is number of rows, so 0 is failure
        verbose "No response to SHOW WSREP_MEMBERSHIP on $GALERA_HOST"
        return 1
    fi
    ROWS=$?

    # Find any nodes are missing from the cluster and
    # ensure there no duplicates of expected node found
    for NODE_NAME in "${NODES_ARR[@]}"; do
        NFOUND=0
        for ((IDX=0; IDX<ROWS; IDX++)); do
            NNVAR="ROW_$IDX[2]"
            if [[ "$NODE_NAME" == "${!NNVAR}" ]]; then
                (( NFOUND += 1 ))
            fi
        done
        if [[ "$NFOUND" -gt 1 ]]; then
            verbose "Duplicate $NODE_NAME nodes (found ${NFOUND}) in cluster"
            return 1
        elif [[ "$NFOUND" -lt 1 ]]; then
            verbose "Cluster membership is missing node $NODE_NAME"
            return 1
        fi
    done

    # Check that no unexpected node names found
    #TODO
    #if ! array_contains NODES_ARR "$NODE"; then
    #    verbose "Cluster contains unexpeted node: $NODE (expected: ${NODES_STR}}"
    #    return 1
    #fi

    # Cluster size is correct (this check _should_ be redunant after earlier checks)
    if [[ "$ROWS" -ne "${#NODES_ARR}" ]]; then
        verbose "Cluster size is $ROWS (should be ${#NODES_ARR})"
        return 1
    fi
    return 0
}

grastate_safe_to_bootstrap() {
    SAFE=1
    if [[ -f "$GALERA_STATE_FILE" ]]; then
        verbose "grastate.dat already exists"
        grep -q "safe_to_bootstrap: 1" "$GALERA_STATE_FILE"
        SAFE=$?
    else
        verbose "There is no grastate.dat file"
    fi
    return $SAFE
}

will_bootstrap() {
    grastate_safe_to_bootstrap
    WILL_BOOTSTRAP=$?
    if [[ "$WILL_BOOTSTRAP" -eq 0 ]]; then
        verbose "Detected safe_to_bootstrap: 1 (setting MARIADB_GALERA_CLUSTER_BOOTSTRAP=yes)"
        export MARIADB_GALERA_CLUSTER_BOOTSTRAP=yes
    fi
    if [[ "$MARIADB_GALERA_CLUSTER_BOOTSTRAP" == "yes" ]]; then
        verbose "Detected new bootstrap: MARIADB_GALERA_CLUSTER_BOOTSTRAP=yes"
        WILL_BOOTSTRAP=0
    fi
    if [[ "$MARIADB_GALERA_FORCE_SAFETOBOOTSTRAP" == "yes" ]]; then
        verbose "Detected forced bootsrap: MARIADB_GALERA_FORCE_SAFETOBOOTSTRAP=yes"
        WILL_BOOTSTRAP=0
    fi
    return $WILL_BOOTSTRAP
}

###############################
## Wait for local safe_to_bootstrap: 1 in grastate.dat up to a limit
##  $1 -> The maximum time to wait in seconds (should be divisible by 5)
wait_for_grastate_safe_to_bootstrap() {
    local MAX_SLEEP="$1"
    local CUR_SLEEP=0
    while ! grastate_safe_to_bootstrap && [[ "$CUR_SLEEP" -le "$MAX_SLEEP" ]]; do
        sleep 5
        (( CUR_SLEEP += 5 ))
        verbose "Waiting for safe_to_bootstrap: 1"
    done
    verbose "Wait limit exceeded (${MAX_SLEEP} secs); proceeding while not safe to bootstrap."
}

wait_for_other_node_synced_or_safe_to_bootstrap() {
    sleep 5 # pre-sleep to give other nodes an extra head start
    local MAX_SLEEP="$1"
    local CUR_SLEEP=5
    while [[ "$CUR_SLEEP" -le "$MAX_SLEEP" ]]; do
        sleep 5
        (( CUR_SLEEP += 5 ))
        if ! grastate_safe_to_bootstrap; then
            verbose "I am not safe_to_bootstrap: 1"
        elif galera_node_is_donor "$GALERA_HOST"; then
            verbose "I am currently a donor for another node's syncing"
        elif ! another_galera_node_is_primary_synced; then
            verbose "I cannot leave when no other nodes are synced to the primary cluster"
        fi
    done

    if [[ "$CUR_SLEEP" -gt "$MAX_SLEEP" ]]; then
        verbose "Wait limit exceeded (${MAX_SLEEP} secs); proceeding while not safe to bootstrap."
        verbose "CLUSTER WILL REQUIRE FORCE BOOTSTRAP."
        touch /bitnami/node_shutdown_unsafely
    fi
}

scan_for_online_nodes() {
    NODES_UP=()
    for NODE in "${NODES_ARR[@]}"; do
        # Using IPs as Docker Swarm has removed hostnames by this point of shutdown
        NODE_IP="${NODE_IPS[$NODE]}"
        if galera_node_online "$NODE_IP"; then
            NODES_UP+=($(node_number "$NODE"))
        fi
    done
    # Sort numerically
    readarray -t NODES_SORTED < <(printf '%s\n' "${NODES_UP[@]}" | sort -g)
    echo "${NODES_SORTED[@]}"
}

galera_slow_startup() {
    # Proceed to start Galera if:
    # - Another galera node is online
    # - No other nodes online, and grastate.dat file contains "safe_to_bootstrap: 1"
    # Otherwise not okay to proceed; sleep, rescan nodes, then check again
    while true; do
        verbose "Checking if safe to start."
        update_node_ips
        if another_galera_node_is_primary_synced; then
            verbose "Found another node already online and synced."
            break
        elif will_bootstrap; then
            verbose "No nodes online, but I am set to bootstrap."
            break
        else
            verbose "No nodes online and I cannot bootstrap. Another node must do the bootstrap."
            sleep 4
        fi
    done

    # Remove unsafe shutdown flag file; if we got this far, things look to be okay
    rm -f /bitnami/node_shutdown_unsafely
    # Remove logs redirect to stdout
    rm -f /opt/bitnami/mariadb/logs/mysqld.log
    # Add symlink to log file for monitoring
    touch /mnt/logs/mariadb/mysqld.log
    ln -sf /mnt/logs/mariadb/mysqld.log /opt/bitnami/mariadb/logs/mysqld.log
    # Output the log for docker
    tail -f /mnt/logs/mariadb/mysqld.log &

    # Start Galera as a background process so we can listen for the shutdown signal
    if [[ "$MARIADB_GALERA_CLUSTER_BOOTSTRAP" == "yes" ]]; then
        verbose "Starting service as a bootstrap node."
        MARIADB_GALERA_CLUSTER_ADDRESS="gcomm://" /opt/bitnami/scripts/mariadb-galera/run.sh --wsrep-new-cluster &
    else
        verbose "Starting service as a joining node."
        /opt/bitnami/scripts/mariadb-galera/run.sh &
    fi
    GALERA_PID=$!

    while true; do
        update_node_ips
        sleep 2
        # Check if Galera is still running in the background
        if ! kill -s 0 -- "$GALERA_PID" 2>/dev/null; then
            verbose "Galera process ended unexpectedly; exiting."
            exit 1
        fi
    done
}

galera_slow_shutdown() {
    verbose "Got request to shutdown."

    verbose "Scanning to see if other nodes are online."
    # Scan other nodes to see if they are up
    NODES_ONLINE=($(scan_for_online_nodes))
    verbose "Lowest online node number: ${NODES_ONLINE[0]:-None}"

    # Wait to give other node scans time to complete
    sleep 5

    # If self is the lowest numbered node that is up, we wait to let other nodes stop first
    SELF_NUMBER=$(node_number "$GALERA_HOST")
    verbose "I am node number: ${SELF_NUMBER}"
    if [[ "$SELF_NUMBER" -eq "${NODES_ONLINE[0]}" ]]; then
        verbose "I am lowest online node and may need to let others sync from me."
        wait_for_other_node_synced_or_safe_to_bootstrap 150
    elif galera_node_is_primary_synced "${NODES_ONLINE[0]}"; then
        verbose "Node number ${NODES_ONLINE[0]} is online and synced. I'm safe to shutdown."
    elif [[ "${#NODES_ONLINE[@]}" -eq 0 ]]; then
        verbose "Shutting down, but detected no nodes were online at the time."
    else
        verbose "The lowest node is ${NODES_ONLINE[0]}, but it is not synched! Waiting for things to improve."
        wait_for_other_node_synced_or_safe_to_bootstrap 150
    fi

    # As this might NOT be a global stop for all nodes, there is no need to re-scan to confirm.
    # We must continue to shutdown after our delay, even if other nodes remain up.
    # Send a TERM signal to self, which will also be sent through to the Galera run.sh as well
    verbose "Shutting down now!"
    kill -s SIGTERM "$GALERA_PID"
    wait
    verbose "Node is shutdown."
    exit 0
}

catch_sig() {
    galera_slow_shutdown
}

# Using WINCH as USR1 and USR2 may be used by mysqld
trap catch_sig SIGWINCH

galera_slow_startup
