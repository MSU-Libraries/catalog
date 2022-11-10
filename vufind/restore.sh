#!/bin/bash

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[SHARED_DIR]=/mnt/shared/backups
    ARGS[AUTHORITY]=
    ARGS[BIBLIO]=
    ARGS[DB]=
    ARGS[VERBOSE]=0
}
default_args

# Script help text
runhelp() {
    echo ""
    echo "Usage: Runs a restore of the Solr and/or the database from"
    echo "       data backups."
    echo ""
    echo "Examples:"
    echo "  ./restore.sh --biblio /path/to/biblio/snapshot.1.tar.gz"
    echo "     Restore the biblio Solr index using data from snapshot.1.tar.gz"
    echo "  ./restore.sh --db /path/to/vufind.tar"
    echo "     Restore the database using vufind.tar backup"
    echo "  ./restore.sh --authority /path/to/authority/snapshot.1.tar.gz"
    echo "     Restore the authority Solr index using data from snapshot.1.tar.gz"
    echo ""
    echo "Flags:"
    echo "  -a/--authority"
    echo "     Full path to the authority Solr index backup to restore to"
    echo "  -b/--biblio"
    echo "     Full path to the biblio Solr index backup to restore to"
    echo "  -d/--db"
    echo "     Full path to the database backup to restore to"
    echo "  -s|--shared-dir SHARED_DIR"
    echo "      Full path to the shared storage location for backups to be stored."
    echo "      Default: ${ARGS[SHARED_DIR]}"
    echo "  -v/--verbose"
    echo "     Show verbose output"
}

if [[ -z "$1" || $1 == "-h" || $1 == "--help" || $1 == "help" ]]; then
    runhelp
    exit 0
fi

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
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
    if [[ -z "${ARGS[AUTHORITY]}" && -z "${ARGS[DB]}" && -z "${ARGS[BIBLIO]}" ]]; then
        echo "ERROR: Neither --authority, --biblio or --db flag is set. Please selet one or more to use this tool."
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
    COLL="$1"
    BACKUP_PATH="$2"
    BACKUP_FILE="$(basename "${BACKUP_PATH}")"
    BACKUP_FILE=${BACKUP_FILE//.tar.gz}

    mkdir -p ${ARGS[SHARED_DIR]}/solr_dropbox/"${COLL}"/"${BACKUP_FILE}"

    verbose "Extracting the backup ${BACKUP_PATH} to ${ARGS[SHARED_DIR]}/solr_dropbox/${COLL}"
    if ! tar -xzf "${BACKUP_PATH}" -C "${ARGS[SHARED_DIR]}"/solr_dropbox/"${COLL}"; then
        verbose "ERROR: could not extract ${BACKUP_PATH} to ${ARGS[SHARED_DIR]}/solr_dropbox/${COLL}" 1
        exit 1
    fi

    chmod -R 777 ${ARGS[SHARED_DIR]}/solr_dropbox/
    chown -R 1001:1001 ${ARGS[SHARED_DIR]}/solr_dropbox

    # Trigger the backup in Solr
    verbose "Starting restore of '${COLL}' index"
    if ! curl "http://solr2:8983/solr/${COLL}/replication?command=restore&location=/mnt/solr_backups/${COLL}&name=${BACKUP_FILE//snapshot.}" > /dev/null 2>&1; then
        verbose "ERROR: Failed to trigger a restore of the '${COLL}' collection in Solr!" 1
        exit 1
    fi

    # Wait until restore is complete
    sleep 3
    MAX_WAITS=50
    CUR_WAIT=1
    URL="http://solr2:8983/solr/${COLL}/replication?command=restorestatus&wt=json"
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

restore_db() {
    mkdir -p /tmp/restore

    verbose "Extracting the backup"
    if ! tar -xf ${ARGS[DB]} -C /tmp/restore; then
        verbose "ERROR: could not extract ${ARGS[DB]} to /tmp/restore" 1
        exit 1
    fi
    BACKUP="$(find /tmp/restore -type f -name '*.sql')"

    verbose "Temporarily setting Galera node to desychronized state"
    if ! mysql -h galera2 -u root -p12345 -e "SET GLOBAL wsrep_desync = ON" 2>/dev/null; then
        verbose "ERROR: Failed to set node to desychronized state. Unsafe to continue backup." 1
        exit 1
    fi

    verbose "Starting restore of database using ${BACKUP}"
    if ! mysql -h galera2 -u root -p12345 vufind < "${BACKUP}" 2>/dev/null; then
        verbose "ERROR: Failed to successfully restore the database" 1
        exit 1
    fi

    verbose "Re-enabling Galera node to sychronized state"
    if ! mysql -h galera2 -u root -p12345 -e "SET GLOBAL wsrep_desync = OFF" 2>/dev/null; then
        verbose "ERROR: Failed to re-set node to synchronized state after restore was complete." 1
        exit 1
    fi

    verbose "Removing temporary uncompressed backup"
    if ! rm -rf /tmp/restore; then
        verbose "ERROR: could not remove temporary restore location /tmp/restore" 1 # won't exit
    fi

    verbose "Completed restore of database"
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

    verbose "All processing complete"
}

# Parse and start running
parse_args "$@"
catch_invalid_args
main
