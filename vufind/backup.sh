#!/bin/bash

ROTATIONS=3

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[SHARED_DIR]=/mnt/shared/backups
    ARGS[SOLR]=0
    ARGS[DB]=0
    ARGS[VERBOSE]=0
}
default_args

# Script help text
runhelp() {
    echo ""
    echo "Usage: Runs a backup of the Solr and/or the database and"
    echo "       saves it to the shared storage path."
    echo ""
    echo "Examples:"
    echo "  ./backup.sh --solr --db" 
    echo "     Back up both the Solr index and database"
    echo "  ./backup.sh --solr" 
    echo "     Back up the Solr index"
    echo "  ./backup.sh --db" 
    echo "     Back up the database"
    echo ""
    echo "Flags:"
    echo "  -s/--solr"
    echo "     Back up the Solr index"
    echo "  -d/--db"
    echo "     Back up the MariaDB database"
    echo "  -b|--shared-dir SHARED_DIR"
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
        -s|--solr)
            ARGS[SOLR]=1
            shift;;
        -d|--db)
            ARGS[DB]=1
            shift;;
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
    if [[ "${ARGS[SOLR]}" -eq 0 && "${ARGS[DB]}" -eq 0 ]]; then
        echo "ERROR: Neither --solr or --db flag is set. Please selet one or both to use this tool."
        exit 1
    fi
}

# Print message if verbose is enabled
verbose() {
    LOG_TS=$(date +%Y-%m-%d\ %H:%M:%S)
    MSG="[${LOG_TS}] $1"
    if [[ "${ARGS[VERBOSE]}" -eq 1 ]]; then
        echo "${MSG}"
    fi
    echo "${MSG}" >> "$LOG_FILE"
}

backup_solr() {
    backup_collection "biblio"

    backup_collection "authority"
}

backup_collection() {
    COLL="$1"

    mkdir -p ${ARGS[SHARED_DIR]}/solr/${COLL}
    chmod -R 777 ${ARGS[SHARED_DIR]}/solr/${COLL}

    # Trigger the backup in Solr
    verbose "Starting backup of '${COLL}' index"
    if ! curl "http://solr2:8983/solr/${COLL}/replication?command=backup&location=/mnt/solr_backups/${COLL}&numberToKeep=${ROTATIONS}" > /dev/null 2>&1; then
        verbose "ERROR: Failed to trigger a backup of the '${COLL}' collection in Solr!"
        exit 1
    fi

    # Verify that the backup successfully completed
    MAX_WAITS=50
    CUR_WAIT=1
    sleep 10 # give it time to clear out old status from last backup
    while [[ "$(curl -s "http://solr2:8983/solr/${COLL}/replication?command=details&wt=json" | jq ".details.backup[5]")" != *"success"* ]]; do
        if [ "$CUR_WAIT" -gt "$MAX_WAITS" ]; then
            echo "ERROR: Backup never completed for '${COLL}' index!"
            exit 1
        fi
        verbose "DEBUG: Backup not yet complete for '${COLL}' index."
        sleep 5
    done

    verbose "Backup completed for '${COLL}' index."
}

backup_db() {
    mkdir -p ${ARGS[SHARED_DIR]}/db

    verbose "Temporarily setting Galera node to desychronized state"
    if ! mysql -h galera2 -u root -p12345 -e "SET GLOBAL wsrep_desync = ON" 2>/dev/null; then
        echo "ERROR: Failed to set node to desychronized state. Unsafe to continue backup."
        exit 1
    fi

    remove_old_db_backups
    
    verbose "Starting backup of database"
    TIMESTAMP=$( date +%Y%m%d%H%M%S )
    if ! mysqldump -h galera2 -u root -p12345 --triggers --routines --column-statistics=0 vufind > ${ARGS[SHARED_DIR]}/db/vufind-"${TIMESTAMP}".sql 2>/dev/null; then
        echo "ERROR: Failed to successfully backup the database"
        exit 1
    fi

    verbose "Re-enabling Galera node to sychronized state"
    if ! mysql -h galera2 -u root -p12345 -e "SET GLOBAL wsrep_desync = OFF" 2>/dev/null; then
        echo "ERROR: Failed to re-set node to synchronized state after dump was complete."
        exit 1
    fi

    verbose "Completed backup of database"
}

remove_old_db_backups() {
    if [ "$(ls -1f ${ARGS[SHARED_DIR]}/db | grep "\.sql" -c)" -ge "${ROTATIONS}" ]; then
        verbose "Removing old backups"
        CUR_BACKUP=1
        find ${ARGS[SHARED_DIR]}/db -type f -name "*.sql" -print0 | xargs -0 ls -t | while read -r DB_BACKUP; do
            # Remove backups when we have more than the max number of rotations that should be saved
            # starting with the oldest backup
            if [ "${CUR_BACKUP}" -ge "${ROTATIONS}" ]; then
                if ! rm ${DB_BACKUP}; then
                    verbose "ERROR: Could not remove old backup file: ${DB_BACKUP}"
                fi
            fi
            CUR_BACKUP=$((CUR_BACKUP+1))
        done
    fi
}

main() {
    declare -g LOG_FILE
    LOG_FILE=$(mktemp)
    verbose "Logging to ${LOG_FILE}"
    verbose "Starting processing"
    
    if [[ "${ARGS[SOLR]}" -eq 1 ]]; then
        backup_solr
    fi
    if [[ "${ARGS[DB]}" -eq 1 ]]; then
        backup_db
    fi

    verbose "All processing complete"
}

# Parse and start running
parse_args "$@"
catch_invalid_args
main
