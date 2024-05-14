#!/bin/bash

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[SHARED_DIR]=/mnt/backups
    ARGS[ALPHA_DIR]=/mnt/alpha-browse
    ARGS[SOLR]=0
    ARGS[ALPHA]=0
    ARGS[DB]=0
    ARGS[ROTATIONS]=3
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
    echo "  ./backup.sh --alpha"
    echo "     Back up the Solr alphabrowse database files"
    echo "  ./backup.sh --db"
    echo "     Back up the database"
    echo "  ./backup.sh --db --rotations 5"
    echo "     Back up the database saving the last 5 backups"
    echo ""
    echo "Flags:"
    echo "  -s|--solr"
    echo "     Back up the Solr index"
    echo "  -a|--alpha"
    echo "     Back up the Solr alphabrowse database files"
    echo "  -d|--db"
    echo "     Back up the MariaDB database"
    echo "  -b|--shared-dir SHARED_DIR"
    echo "      Full path to the shared storage location for backups to be stored."
    echo "      Default: ${ARGS[SHARED_DIR]}"
    echo "  -p|--alpha-dir ALPHA_DIR"
    echo "      Full path to the alphabrowse database storage location."
    echo "      Default: ${ARGS[ALPHA_DIR]}"
    echo "  -r|--rotations ROTATIONS"
    echo "      Number of most recent backups to save"
    echo "      Default: ${ARGS[ROTATIONS]}"
    echo "  -v|--verbose"
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
        -a|--alpha)
            ARGS[ALPHA]=1
            shift;;
        -s|--solr)
            ARGS[SOLR]=1
            shift;;
        -d|--db)
            ARGS[DB]=1
            shift;;
        -b|--shared-dir)
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
        -r|--rotations)
            ARGS[ROTATIONS]="$2"
            if [[ ! "${ARGS[ROTATIONS]}" -gt 0 ]]; then
                echo "ERROR: -r|--rotations only accept positive integers"
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
    if [[ "${ARGS[SOLR]}" -eq 0 && "${ARGS[DB]}" -eq 0 && "${ARGS[ALPHA]}" -eq 0 ]]; then
        echo "ERROR: Neither --solr, --alpha, or --db flag is set. Please selet at least one to use this tool."
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

backup_alpha() {
    mkdir -p ${ARGS[SHARED_DIR]}/alpha
    remove_old_backups ${ARGS[SHARED_DIR]}/alpha

    verbose "Taking a backup of the alphabrowse databases"
    TIMESTAMP=$( date +%Y%m%d%H%M%S )
    mkdir -p ${ARGS[SHARED_DIR]}/alpha/"${TIMESTAMP}"

    if ! OUTPUT=$(cp ${ARGS[ALPHA_DIR]}/* ${ARGS[SHARED_DIR]}/alpha/"${TIMESTAMP}"/); then
        verbose "ERROR: failed to make a backup of the alphabrowse databases. $OUTPUT" 1
        exit 1
    fi

    verbose "Compressing the backup"
    if ! tar -cf ${ARGS[SHARED_DIR]}/alpha/"${TIMESTAMP}".tar -C ${ARGS[SHARED_DIR]}/alpha/"${TIMESTAMP}" --remove-files ./; then
        verbose "ERROR: Failed to compress the alphabrowse databases." 1
        exit 1
    fi

    verbose "Completed backup of alphabrowse database"
}

backup_solr() {
    backup_collection "biblio"

    backup_collection "authority"

    backup_collection "reserves"
}

backup_collection() {
    # Select one Solr node to perform backups on
    SOLR_NODES=(solr1 solr2 solr3)
    SOLR_IDX="$(( RANDOM % ${#SOLR_NODES[@]} ))"
    SOLR_NODE="${SOLR_NODES[$SOLR_IDX]}"
    COLL="$1"

    mkdir -p ${ARGS[SHARED_DIR]}/solr/"${COLL}"
    mkdir -p ${ARGS[SHARED_DIR]}/solr_dropbox/"${COLL}"
    chmod -R 777 ${ARGS[SHARED_DIR]}/solr_dropbox/

    # Trigger the backup in Solr
    verbose "Starting backup of '${COLL}' index (using node $SOLR_NODE)"
    SNAPSHOT="$(date +%Y%m%d%H%M%S)"
    if ! OUTPUT=$(curl -sS "http://$SOLR_NODE:8983/solr/${COLL}/replication?command=backup&location=/mnt/solr_backups/${COLL}&name=${SNAPSHOT}"); then
        verbose "ERROR: Failed to trigger a backup of the '${COLL}' collection in Solr. Exit code: $?. ${OUTPUT}" 1
        exit 1
    fi

    # Verify that the backup started
    sleep 10
    SNAPSHOT="snapshot.${SNAPSHOT}"
    if [ ! -d "${ARGS[SHARED_DIR]}/solr_dropbox/${COLL}/${SNAPSHOT}" ]; then
        verbose "ERROR: Failed to start backup for the '${COLL}' collection in Solr!" 1
        exit 1
    fi

    # Verify that the backup successfully completed
    MAX_WAITS=900
    CUR_WAIT=1
    EXPECTED=""
    ACTUAL="0"
    while [[ "${EXPECTED}" != "${ACTUAL}" ]]; do
        if [ "$CUR_WAIT" -gt "$MAX_WAITS" ]; then
            verbose "ERROR: Backup never completed for '${COLL}' index! (${ACTUAL}/${EXPECTED} files copied)" 1
            exit 1
        fi
        if [[ "${EXPECTED}" != "" ]]; then
            verbose "Backup still in progress (${ACTUAL}/${EXPECTED} files copied)"
        fi
        sleep 3
        EXPECTED="$(curl -sS "http://$SOLR_NODE:8983/solr/${COLL}/replication?command=details&wt=json" | jq '.details.commits[0][5]|length')"
        ACTUAL="$(find ${ARGS[SHARED_DIR]}/solr_dropbox/"${COLL}"/"${SNAPSHOT}" -type f 2>/dev/null | wc -l)"
        CUR_WAIT=$((CUR_WAIT+1))
    done

    # Move the backups from the dropbox, remove Solr's access, and compress
    mv ${ARGS[SHARED_DIR]}/solr_dropbox/"${COLL}"/"${SNAPSHOT}" ${ARGS[SHARED_DIR]}/solr/"${COLL}"/
    chown -R root:root ${ARGS[SHARED_DIR]}/solr/"${COLL}"
    chmod -R 660 ${ARGS[SHARED_DIR]}/solr/"${COLL}"

    verbose "Compressing the backup"
    PREV_CWD="$(pwd)"
    if ! cd ${ARGS[SHARED_DIR]}/solr/"${COLL}"; then
        verbose "ERROR: Could not navigate into index backup directory (${ARGS[SHARED_DIR]}/solr/${COLL})" 1
        exit 1
    fi
    if ! tar -c --use-compress-program="pigz -k -p3 " -f "${SNAPSHOT}".tar.gz "${SNAPSHOT}"; then
        verbose "ERROR: Failed to compress the backup for the '${COLL}' index." 1
        exit 1
    else
        if ! rm -rf "${SNAPSHOT}"; then
            verbose "ERROR: Failed to remove uncompressed backup for the '${COLL}' index." 1
            exit 1
        fi
    fi
    if ! cd "${PREV_CWD}"; then
        verbose "ERROR: Could not navigate back out of backup directory to previous working dir (${PREV_CWD})" 1
        exit 1
    fi

    verbose "Backup completed for '${COLL}' index."

    remove_old_backups ${ARGS[SHARED_DIR]}/solr/"${COLL}"
}

# Return database back to normal
reset_db() {
    # DB_NODE is declared in backup_db() function
    verbose "Re-enabling Galera node to sychronized state"
    # TODO Here MARIADB_ROOT_PASSWORD_FILE seems to come from vufind container
    if ! OUTPUT=$(mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" -e "SET GLOBAL wsrep_desync = OFF" 2>&1); then
        # Check if it was a false negative and the state was actually set
        if ! mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" -e "SHOW GLOBAL STATUS LIKE 'wsrep_desync_count'" 2>/dev/null \
          | grep 0 > /dev/null 2>&1; then
            verbose "ERROR: Failed to re-set node to synchronized state after dump was complete. ${OUTPUT}" 1
            exit 1
        fi
    fi
}

backup_db() {
    DBS=( galera1 galera2 galera3 )
    DB_IDX="$(( RANDOM % ${#DBS[@]} ))"
    declare -g DB_NODE="${DBS[$DB_IDX]}"
    mkdir -p ${ARGS[SHARED_DIR]}/db

    verbose "Removing leftover uncompressed sql files"
    rm ${ARGS[SHARED_DIR]}/db/*.sql 2>/dev/null

    # If interrupted, we'll try to reset the database before exiting
    trap reset_db SIGTERM SIGINT EXIT

    verbose "Temporarily setting Galera node to desychronized state (using node $DB_NODE)"
    # TODO Here MARIADB_ROOT_PASSWORD_FILE seems to come from vufind container
    if ! OUTPUT=$(mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" -e "SET GLOBAL wsrep_desync = ON" 2>&1); then
        # Check if it was a false negative and the state was actually set
        if ! mysql -h "$DB_NODE" -u root -p"$MARIADB_ROOT_PASSWORD" -e "SHOW GLOBAL STATUS LIKE 'wsrep_desync_count'" 2>/dev/null \
          | grep 1 > /dev/null 2>&1; then
            verbose "ERROR: Failed to set node to desychronized state. Unsafe to continue backup. ${OUTPUT}" 1
            exit 1
        fi
    fi

    remove_old_backups ${ARGS[SHARED_DIR]}/db

    verbose "Starting backup of database"
    TIMESTAMP=$( date +%Y%m%d%H%M%S )
    for DB in "${DBS[@]}"; do
        # TODO Here MARIADB_ROOT_PASSWORD_FILE seems to come from vufind container
        if ! OUTPUT=$(mysqldump -h "${DB}" -u root -p"$MARIADB_ROOT_PASSWORD" --triggers --routines --single-transaction --skip-lock-tables --column-statistics=0 --no-data  vufind 2>&1 > >(gzip > ${ARGS[SHARED_DIR]}/db/"${DB}"-"${TIMESTAMP}".sql.gz )); then
            verbose "ERROR: Failed to successfully backup the database structure from ${DB}. ${OUTPUT}" 1
            exit 1
        fi
        if ! OUTPUT=$(mysqldump -h "${DB}" -u root -p"$MARIADB_ROOT_PASSWORD" --quick --single-transaction --skip-lock-tables --column-statistics=0 --no-create-info --ignore-table=vufind.session --ignore-table=vufind.SimpleSAMLphp_kvstore --ignore-table=vufind.SimpleSAMLphp_saml_LogoutStore vufind 2>&1 > >(gzip >> ${ARGS[SHARED_DIR]}/db/"${DB}"-"${TIMESTAMP}".sql.gz )); then

            verbose "ERROR: Failed to successfully backup the database from ${DB}. ${OUTPUT}" 1
            exit 1
        fi
    done

    # Reset the database and unset our trap
    reset_db
    trap - SIGTERM SIGINT EXIT

    verbose "Compressing the backup"
    PREV_CWD="$(pwd)"
    if ! cd ${ARGS[SHARED_DIR]}/db; then
        verbose "ERROR: Could not navigate into database backup directory (${ARGS[SHARED_DIR]}/db)" 1
        exit 1
    fi
    if ! tar -cf "${TIMESTAMP}".tar ./*"${TIMESTAMP}".sql.gz; then
        verbose "ERROR: Failed to compress the database dumps." 1
        exit 1
    else
        if ! rm ./*"${TIMESTAMP}.sql.gz"; then
            verbose "ERROR: Failed to remove the uncompressed database dumps" 1 # Not exiting
        fi
    fi
    if ! cd "${PREV_CWD}"; then
        verbose "ERROR: Could not navigate back out of backup directory to previous working dir (${PREV_CWD})" 1
        exit 1
    fi

    verbose "Completed backup of database"
}

remove_old_backups() {
    BACKUP_DIR="$1"

    if [ "$(find "${BACKUP_DIR}" -type f \( -name "*.gz" -o -name "*.tar" \) | wc -l)" -ge "${ARGS[ROTATIONS]}" ]; then
        verbose "Removing old backups"
        CUR_BACKUP=1
        find "${BACKUP_DIR}" -type f \( -name "*.gz" -o -name "*.tar" \) -print0 | xargs -0 ls -t | while read -r BACKUP; do
            # Remove backups when we have more than the max number of rotations that should be saved
            # starting with the oldest backup
            if [ "${CUR_BACKUP}" -ge "${ARGS[ROTATIONS]}" ]; then
                if [ -f "${BACKUP}" ] && ! rm "${BACKUP}"; then
                    verbose "ERROR: Could not remove old backup file: ${BACKUP}" 1
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
    verbose "Starting backup of ${STACK_NAME}"

    if [[ "${ARGS[SOLR]}" -eq 1 ]]; then
        backup_solr
    fi
    if [[ "${ARGS[DB]}" -eq 1 ]]; then
        backup_db
    fi
    if [[ "${ARGS[ALPHA]}" -eq 1 ]]; then
        backup_alpha
    fi

    verbose "All processing complete"
}

# Parse and start running
parse_args "$@"
catch_invalid_args
main
