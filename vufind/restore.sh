#!/bin/bash

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[SHARED_DIR]=/mnt/shared/backups
    ARGS[ALPHA_DIR]=/mnt/alpha-browse
    ARGS[AUTHORITY]=
    ARGS[BIBLIO]=
    ARGS[DB]=
    ARGS[NODE]=
    ARGS[VERBOSE]=0
}
default_args

# Script help text
run_help() {
    echo ""
    echo "Usage: Runs a restore of the Solr, the database, and/or the alphabrowse"
    echo "       files from backups."
    echo ""
    echo "Examples:"
    echo "  ./restore.sh --biblio /path/to/biblio/snapshot.1.tar.gz"
    echo "     Restore the biblio Solr index using data from snapshot.1.tar.gz"
    echo "  ./restore.sh --alpha /path/to/alpha/20220101.tar"
    echo "     Restore the alphabrowse databases from 20220101.tar"
    echo "  ./restore.sh --db /path/to/20220101.tar"
    echo "     Restore the database using 20220101.tar backup"
    echo "  ./restore.sh --authority /path/to/authority/snapshot.1.tar.gz"
    echo "     Restore the authority Solr index using data from snapshot.1.tar.gz"
    echo ""
    echo "Flags:"
    echo "  -a/--authority"
    echo "     Full path to the authority Solr index backup to restore to"
    echo "  -b/--biblio"
    echo "     Full path to the biblio Solr index backup to restore to"
    echo "  -l|--alpha"
    echo "     Back up the Solr alphabrowse database files"
    echo "  -d/--db"
    echo "     Full path to the database backup to restore to"
    echo "  -n/--node"
    echo "     Node number to restore the database backup from"
    echo "     Default: 1"
    echo "  -p|--alpha-dir ALPHA_DIR"
    echo "      Full path to the alphabrowse database storage location."
    echo "      Default: ${ARGS[ALPHA_DIR]}"
    echo "  -s|--shared-dir SHARED_DIR"
    echo "      Full path to the shared storage location for backups to be stored."
    echo "      Default: ${ARGS[SHARED_DIR]}"
    echo "  -v/--verbose"
    echo "     Show verbose output"
}

if [[ -z "$1" || $1 == "-h" || $1 == "--help" || $1 == "help" ]]; then
    run_help
    exit 0
fi

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
        -l|--alpha)
            ARGS[ALPHA]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -f "${ARGS[ALPHA]}" ]]; then
                echo "ERROR: -l|--alpha file does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -a|--authority)
            ARGS[AUTHORITY]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -f "${ARGS[AUTHORITY]}" ]]; then
                echo "ERROR: -a|--authority file does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -b|--biblio)
            ARGS[BIBLIO]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -f "${ARGS[BIBLIO]}" ]]; then
                echo "ERROR: -b|--biblio file does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -d|--db)
            ARGS[DB]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -f "${ARGS[DB]}" ]]; then
                echo "ERROR: -d|--db file does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -s|--shared-dir)
            ARGS[SHARED_DIR]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[SHARED_DIR]}" ]]; then
                echo "ERROR: -s|--shared-dir path does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -p|--alpha-dir)
            ARGS[ALPHA_DIR]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[ALPHA_DIR]}" ]]; then
                echo "ERROR: -p|--alpha-dir path does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -n|--node)
            ARGS[NODE]="$2"
            if [[ ! "${ARGS[NODE]}" -gt 0 ]]; then
                echo "ERROR: -n|--node only accept positive integers"
                exit 1
            fi
            shift; shift ;;
        -v|--verbose)
            ARGS[VERBOSE]=1
            shift;;
        *)
            echo "ERROR: Unknown flag: $1"
            exit 1
        esac
    done
}

catch_invalid_args() {
    if [[ -z "${ARGS[AUTHORITY]}" && -z "${ARGS[DB]}" && -z "${ARGS[BIBLIO]}" && -z "${ARGS[ALPHA]}" ]]; then
        echo "ERROR: Neither --authority, --biblio, --alpha, or --db flag is set. Please select one or more to use this tool."
        exit 1
    fi

    if [[ -z "${ARGS[DB]}" && -n "${ARGS[NODE]}" ]]; then
        echo "ERROR: --node cannot be used without --db. Please see the --help message for more information."
        exit 1
    fi
}

# Print message if verbose is enabled
verbose() {
    FORCE=$2
    LOG_TS=$(date +%Y-%m-%d\ %H:%M:%S)
    MSG="[${LOG_TS}] $1"
    if [[ "${ARGS[VERBOSE]}" -eq 1 ]] || [[ "$FORCE" -eq 1 ]]; then
        echo "${MSG}"
    fi
    echo "${MSG}" >> "$LOG_FILE"
}

restore_collection() {
    # Select one Solr node to perform backups on
    SOLR_NODES=(solr1 solr2 solr3)
    SOLR_IDX="$(( RANDOM % ${#SOLR_NODES[@]} ))"
    SOLR_NODE="${SOLR_NODES[$SOLR_IDX]}"
    COLL="$1"
    BACKUP_PATH="$2"
    BACKUP_FILE="$(basename "${BACKUP_PATH}")"
    BACKUP_FILE=${BACKUP_FILE//.tar.gz}

    mkdir -p "${ARGS[SHARED_DIR]}/solr_dropbox/${COLL}/${BACKUP_FILE}"

    verbose "Extracting the backup ${BACKUP_PATH} to ${ARGS[SHARED_DIR]}/solr_dropbox/${COLL}"
    if ! tar -xzf "${BACKUP_PATH}" -C "${ARGS[SHARED_DIR]}"/solr_dropbox/"${COLL}"; then
        verbose "ERROR: could not extract ${BACKUP_PATH} to ${ARGS[SHARED_DIR]}/solr_dropbox/${COLL}" 1
        exit 1
    fi

    chmod -R 777 "${ARGS[SHARED_DIR]}/solr_dropbox/"
    chown -R 8983:8983 "${ARGS[SHARED_DIR]}/solr_dropbox"

    # Trigger the backup in Solr
    verbose "Starting restore of '${COLL}' index"
    if ! curl "http://${SOLR_NODE}:8983/solr/${COLL}/replication?command=restore&location=/mnt/solr_backups/${COLL}&name=${BACKUP_FILE//snapshot.}" > /dev/null 2>&1; then
        verbose "ERROR: Failed to trigger a restore of the '${COLL}' collection in Solr!" 1
        exit 1
    fi

    # Wait until restore is complete
    sleep 3
    MAX_WAITS=500
    CUR_WAIT=1
    URL="http://${SOLR_NODE}:8983/solr/${COLL}/replication?command=restorestatus&wt=json"
    STAT=""
    ACTUAL=""
    verbose "Waiting until restore is complete of ${BACKUP_FILE}"
    while [[ "${STAT}" != *"success"* && "${BACKUP_FILE}" != "${ACTUAL}" ]]; do
        if [ "$CUR_WAIT" -gt "$MAX_WAITS" ]; then
            verbose "ERROR: Restore never completed for '${COLL}' index!" 1
            exit 1
        fi
	STAT="$(curl -s "${URL}" | jq '.restorestatus.status')"
	ACTUAL="$(curl -s "${URL}" | jq '.restorestatus.snapshotName' 2>/dev/null)"
	if [ "$CUR_WAIT" -ne 1 ]; then
	    verbose "Restore not yet complete. Status: ${STAT}"
	fi
	sleep 2
        CUR_WAIT=$((CUR_WAIT+1))
    done

    verbose "Removing temporary uncompressed backup"
    if ! rm -rf "${ARGS[SHARED_DIR]}"/solr_dropbox/"${COLL}"/"${BACKUP_FILE}"; then
        verbose "ERROR: could not remove temporary restore location ${ARGS[SHARED_DIR]}/solr_dropbox/${COLL}/${BACKUP_FILE}" 1 # won't exit
    fi
}

cleanup() {
    if ! rm -rf /tmp/restore; then
        verbose "ERROR: could not remove temporary restore location /tmp/restore" 1 # won't exit
    fi
}

restore_db() {
    DBS=( galera1 galera2 galera3 )
    DB_IDX="$(( RANDOM % ${#DBS[@]} ))"
    declare -g DB_NODE="${DBS[$DB_IDX]}"
    mkdir -p /tmp/restore

    # If interrupted, we'll try to clean up temp files
    trap cleanup SIGTERM SIGINT EXIT

    verbose "Extracting the backup"
    if ! tar -xf "${ARGS[DB]}" -C /tmp/restore; then
        verbose "ERROR: could not extract ${ARGS[DB]} to /tmp/restore" 1
        exit 1
    fi
    BACKUP="$(find /tmp/restore -type f -name "galera${NODE}-*.sql.gz")"

    verbose "Temporarily setting Galera node to desynchronized state"
    if ! OUTPUT=$(mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" -e "SET GLOBAL wsrep_desync = ON" 2>&1); then
        # Check if it was a false negative and the state was actually set
        if ! mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" -e "SHOW GLOBAL STATUS LIKE 'wsrep_desync_count'" 2>/dev/null \
          | grep 1 > /dev/null 2>&1; then
            verbose "ERROR: Failed to set node to desynchronized state. Unsafe to continue restore. ${OUTPUT}" 1
            exit 1
        fi
    fi

    verbose "Starting restore of database using ${BACKUP}"
    if ! OUTPUT=$(gunzip < "${BACKUP}" | mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" vufind 2>&1); then
        verbose "ERROR: Failed to successfully restore the database. ${OUTPUT}" 1
        exit 1
    fi

    verbose "Re-enabling Galera node to sychronized state"
    if ! OUTPUT=$(mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" -e "SET GLOBAL wsrep_desync = OFF" 2>&1); then
        # Check if it was a false negative and the state was actually set
        if ! mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" -e "SHOW GLOBAL STATUS LIKE 'wsrep_desync_count'" 2>/dev/null \
          | grep 0 > /dev/null 2>&1; then
            verbose "ERROR: Failed to re-set node to synchronized state after restore was complete. ${OUTPUT}" 1
            exit 1
        fi
    fi

    verbose "Removing temporary uncompressed backup"
    # Reset the database and unset our trap
    cleanup
    trap - SIGTERM SIGINT EXIT

    verbose "Completed restore of database"
}

restore_alpha() {
    verbose "Extracting the backup"

    if ! OUTPUT=$(mkdir -p /tmp/restore); then
        verbose "ERROR: could not make temp restore directory. ${OUTPUT}" 1
        exit 1
    fi

    if ! OUTPUT=$(tar -xf "${ARGS[ALPHA]}" -C /tmp/restore); then
        verbose "ERROR: could not extract ${ARGS[ALPHA]} to /tmp/restore. ${OUTPUT}" 1
        exit 1
    fi

    verbose "Starting restore."
    if ! OUTPUT=$(find /tmp/restore/ -type f -exec cp {} "${ARGS[ALPHA_DIR]}/" \;); then
        verbose "ERROR: failed to restore ${ARGS[ALPHA]} to ${ARGS[ALPHA_DIR]}. ${OUTPUT}" 1
        exit 1
    fi

    verbose "Touching files to update timestamp"
    verbose "(so that the alpha-browse script recognizes them as the most up-to-date files to use)"
    if ! OUTPUT=$(touch "${ARGS[ALPHA_DIR]}"/*); then
        verbose "ERROR: failed to update the timestamp of the files in ${ARGS[ALPHA_DIR]}. ${OUTPUT}" 1
        exit 1
    fi

    verbose "Cleaning up temp files"
    if ! OUTPUT=$(rm -rf /tmp/restore); then
        verbose "ERROR: failed to cleanup /tmp/restore directory. ${OUTPUT}" 1
        exit 1
    fi

    verbose "Restore complete." 1
    verbose "IMPORTANT: remaining steps: " 1
    verbose "--" 1
    verbose "On each node, connect to the Solr cron container and run the alpha-browse.sh script" 1
    verbose "to copy back the files into the running Solr instance." 1
    verbose "Command:" 1
    verbose "docker exec \$(docker ps -q -f ${STACK_NAME}-solr_cron) /alpha-browse.sh -v" 1
    verbose "--" 1
}

main() {
    declare -g LOG_FILE
    LOG_FILE=$(mktemp)
    verbose "Logging to ${LOG_FILE}"
    verbose "Starting processing"

    if [[ -n "${ARGS[BIBLIO]}" ]]; then
        restore_collection "biblio" "${ARGS[BIBLIO]}"
    fi
    if [[ -n "${ARGS[AUTHORITY]}" ]]; then
        restore_collection "authority" "${ARGS[AUTHORITY]}"
    fi
    if [[ -n "${ARGS[DB]}" ]]; then
        restore_db
    fi
    if [[ -n "${ARGS[ALPHA]}" ]]; then
        restore_alpha
    fi

    verbose "All processing complete"
}

# Parse and start running
parse_args "$@"
catch_invalid_args
main
