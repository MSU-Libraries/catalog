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

ip_from_hostname() {
    PAIR=$( getent hosts "$1")
    RC=$?
    echo "$PAIR" | awk '{ print $1 }'
    return "$RC"
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
        verbose "Checking if safe to start..."
        if any_galera_node_online; then
            verbose "Found another node already online."
            break
        elif will_bootstrap; then
            verbose "No nodes online, but I am set to bootstrap."
            break
        else
            verbose "No nodes online and I cannot bootstrap. Another node must do the bootstrap."
            sleep 4
        fi
    done

    # Remove logs redirect to stdout
    rm -rf /opt/bitnami/mariadb/logs
    # Add symlink to /mnt/logs for monitoring
    mkdir -p /mnt/logs/mariadb
    touch /mnt/logs/mariadb/mysqld.log
    chown 1001 /mnt/logs/mariadb/mysqld.log
    ln -sf /mnt/logs/mariadb /opt/bitnami/mariadb/logs
    # Output the log for docker
    tail -f /opt/bitnami/mariadb/logs/mysqld.log &

    # Start Galera as a background process so we can listen for the shutdown signal
    if [[ "$MARIADB_GALERA_CLUSTER_BOOTSTRAP" == "yes" ]]; then
        verbose "Starting service as a bootstrap node..."
        MARIADB_GALERA_CLUSTER_ADDRESS="gcomm://" /opt/bitnami/scripts/mariadb-galera/run.sh --wsrep-new-cluster &
    else
        verbose "Starting service as a joining node..."
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

    verbose "Scanning to see if other nodes are online..."
    # Scan other nodes to see if they are up
    NODES_ONLINE=($(scan_for_online_nodes))
    verbose "Lowest online node number: ${NODES_ONLINE[0]}"

    # Wait to give other node scans time to complete
    sleep 5

    # If self is the lowest numbered node that is up, we wait to let other nodes stop first
    SELF_NUMBER=$(node_number "$GALERA_HOST")
    verbose "I am node number: ${SELF_NUMBER}"
    if [[ "$SELF_NUMBER" -eq "${NODES_ONLINE[0]}" ]]; then
        if [[ "${#NODES_ONLINE[@]}" -gt 1 ]]; then
            verbose "I am lowest online node; resting to allow other nodes to shutdown first."
            wait_for_grastate_safe_to_bootstrap 75
        else
            verbose "I am the ONLY node, so I'm safe to shutdown immediately."
        fi
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
