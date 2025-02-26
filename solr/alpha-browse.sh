#!/bin/bash

DEFAULT_BUILD_PATH=/tmp/alpha-browse/build
DEFAULT_SHARED_PATH=/mnt/shared/alpha-browse
DEFAULT_MAX_AGE_HOURS=6
if [[ -n "${STACK_NAME}" ]]; then
    DEFAULT_SHARED_PATH="${DEFAULT_SHARED_PATH}/${STACK_NAME}"
fi

# Script help text
run_help() {
    echo ""
    echo "Usage: Update the alphabetical browse databases used by Solr."
    echo ""
    echo "Examples: "
    echo "   /alpha-browse.sh"
    echo "     Checks the shared storage for updated database files. If any exist and are less"
    echo "     than 4 hours old, it will copy those, remove the originals, and stop processing."
    echo "     Otherwise it will run the command to generate new database files and make a copy"
    echo "     in the shared location."
    echo "   /alpha-browse.sh --shared-path /shared/path --force"
    echo "     Will rebuild the database files regardless of age and uses a non-default shared path."
    echo "   /alpha-browse.sh --max-age-hours 12"
    echo "     Will rebuild the database files only if they are more than 12 hours old."
    echo ""
    echo "FLAGS:"
    echo "  -b|--build-path PATH"
    echo "     Working directory to build new databases in. Default: ${DEFAULT_BUILD_PATH}"
    echo "  -p|--shared-path PATH"
    echo "     Path to the already cloned VuFind repository. Default: ${DEFAULT_SHARED_PATH}"
    echo "  -a|--max-age-hours"
    echo "      Max age (difference between current timestamp and created timestamp) in hours"
    echo "      of the database files to determine if it will use the existing files or build"
    echo "      new ones. Default: ${DEFAULT_MAX_AGE_HOURS}"
    echo "  -f|--force"
    echo "      Rebuild the database files regardless of age"
    echo "  -v|--verbose"
    echo "      Show verbose output"
    echo ""
}

if [[ $1 == "-h" || $1 == "--help" || $1 == "help" ]]; then
    run_help
    exit 0
fi

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[BUILD_PATH]="${DEFAULT_BUILD_PATH}"
    ARGS[SHARED_PATH]="${DEFAULT_SHARED_PATH}"
    ARGS[MAX_AGE_HOURS]="${DEFAULT_MAX_AGE_HOURS}"
    ARGS[FORCE]=0
    ARGS[VERBOSE]=0
}

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
        -f|--force)
            ARGS[FORCE]=1
            shift;;
        -a|--max-age-hours)
            ARGS[MAX_AGE_HOURS]="$2"
            if [[ ! "${ARGS[MAX_AGE_HOURS]}" -gt 0 ]]; then
                echo "ERROR: -a|--max-age-hours only accept positive integers"
                exit 1
            fi
            shift; shift ;;
        -b|--build-path)
            ARGS[BUILD_PATH]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 ]]; then
                echo "ERROR: -b|--build-path path is not valid: $2"
                exit 1
            fi
            shift; shift ;;
        -p|--shared-path)
            ARGS[SHARED_PATH]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 ]]; then
                echo "ERROR: -p|--shared-path path is not valid: $2"
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

# Print message if verbose is enabled
verbose() {
    LOG_TS=$(date +%Y-%m-%d\ %H:%M:%S)
    MSG="[${LOG_TS}] $1"
    if [[ "${ARGS[VERBOSE]}" -eq 1 ]]; then
        echo "${MSG}"
    fi
    echo "${MSG}" >> "$LOG_FILE"
}

check_skip() {
    declare -g SKIP_BUILD
    SKIP_BUILD=0
    # Avoid running alpha-browse on prod and beta on the same node
    if [[ "${STACK_NAME}" == "catalog-prod" && "${NODE}" != "1" ]]; then
        echo "Stack is prod: only building alpha-browse on node 1."
        SKIP_BUILD=1
    fi
    if [[ "${STACK_NAME}" == "catalog-beta" && "${NODE}" != "2" ]]; then
        echo "Stack is beta: only building alpha-browse on node 2."
        SKIP_BUILD=1
    fi
    if [[ "${STACK_NAME}" == "catalog-preview" && "${NODE}" != "3" ]]; then
        echo "Stack is preview: only building alpha-browse on node 3."
        SKIP_BUILD=1
    fi
    if [[ "${SKIP_BUILD}" -eq 1 ]]; then
        # Wait for another node to start building, so we can copy results afterwards
        sleep 10
    fi
}

# Call the rebuild script to generate new database files
rebuild_databases() {
    verbose "Running database rebuild script..."
    RCODE=0

    # Prepare build directory
    mkdir -p "${ARGS[BUILD_PATH]}/alphabetical_browse"

    # Create required symlink if it doesn't already exist
    if [[ ! -h "${ARGS[BUILD_PATH]}/jars" ]]; then
        ln -s /solr_confs/jars "${ARGS[BUILD_PATH]}/jars"
    fi

    # Always recreate the biblio link (collections might have changed)
    rm -f "${ARGS[BUILD_PATH]}/biblio"
    # Get the biblio collection path for the biblio alias
    if ! ALIASES=$(curl -s "http://solr:8983/solr/admin/collections?action=LISTALIASES&wt=xml"); then
        echo "Failed to query the collection aliases in Solr. Output: ${ALIASES}"
        return 1
    fi
    BIBLIO_COLLECTION_NAME=$(echo "$ALIASES" | grep '"biblio"' | sed -e 's/.*>\([^<]*\)<.*/\1/')
    BIBLIO_COLLECTION_PATH="/bitnami/solr/server/solr/${BIBLIO_COLLECTION_NAME}"
    if [ ! -d "$BIBLIO_COLLECTION_PATH" ]; then
        echo "Could not find the collection directory at $BIBLIO_COLLECTION_PATH"
        return 1
    fi
    ln -s "$BIBLIO_COLLECTION_PATH" "${ARGS[BUILD_PATH]}/biblio"

    if [[ ! -h ${ARGS[BUILD_PATH]}/authority ]]; then
        ln -s /bitnami/solr/server/solr/authority "${ARGS[BUILD_PATH]}/authority"
    fi

    if ! JAVA_HOME=/opt/bitnami/java SOLR_HOME=${ARGS[BUILD_PATH]} SOLR_JAR_PATH=/opt/bitnami/solr VUFIND_HOME=/solr_confs /solr_confs/index-alphabetic-browse.sh; then
        verbose "Error occurred while running index-alphabetic-browse.sh script!"
        RCODE=1
    else
        verbose "Rebuild complete"
    fi

    # Change ownership so it is correct before we copy to shared
    chown -f 1001 "${ARGS[BUILD_PATH]}"/alphabetical_browse/*

    return $RCODE
}

update_shared() {
    # Remove all files from the shared storage alphabetical browse folder
    verbose "Cleaning up old db files from ${ARGS[SHARED_PATH]} (with age > ${ARGS[MAX_AGE_HOURS]} hour(s))."
    find "${ARGS[SHARED_PATH]}" -type f -mmin +$(( ARGS[MAX_AGE_HOURS] * 60 )) -name "*.db*" ! -name "*lock" -delete

    # Copy all database files to the shared storage
    verbose "Copying database files from: ${ARGS[BUILD_PATH]}/alphabetical_browse/*db-* to ${ARGS[SHARED_PATH]}"
    cp -p "${ARGS[BUILD_PATH]}"/alphabetical_browse/*db-* "${ARGS[SHARED_PATH]}/"
}

lock_state() {
    MAX_SLEEP=$(( 6 * 60 * 60 ))
    CUR_SLEEP=0
    while ! /lock-state.sh "$@"; do
        sleep 5;
        (( CUR_SLEEP += 5 ))
        if [[ "$CUR_SLEEP" -gt "$MAX_SLEEP" ]]; then
            verbose "Could not acquire lock for building (timeout after $MAX_SLEEP seconds)"
            exit 1
        fi
    done
}

build_browse() {
    verbose "Acquiring build lock."
    lock_state -b -l "${ARGS[SHARED_PATH]}/rebuild_lock"

    # Check if a rebuild is needed
    RCODE=0
    verbose "Determining if a fresh build is needed."
    # Check if any files exist with age within the max
    if [[ "${ARGS[FORCE]}" -eq 1 || -z $(find "${ARGS[SHARED_PATH]}/" -type f -mmin -$(( ARGS[MAX_AGE_HOURS] * 60 )) ! -name "*lock" ) ]]; then
        if [[ "${ARGS[FORCE]}" -eq 1 ]]; then
            verbose "Age check bypassed due to force flag being set."
        else
            verbose "No files within the shared path are less than the max age of ${ARGS[MAX_AGE_HOURS]} hour(s) old."
        fi
        verbose "Rebuild started..."
        rebuild_databases
        RCODE=$?

        if [[ "$RCODE" -eq 0 ]]; then
            verbose "Rebuild succeeded. Updating shared storage with new databases."
            update_shared
        else
            verbose "Rebuild failed!"
        fi
    fi

    verbose "Releasing build lock."
    lock_state -u -l "${ARGS[SHARED_PATH]}/rebuild_lock"

    return $RCODE
}

copy_to_solr() {
    RCODE=0
    verbose "Acquiring copy lock."
    lock_state -c -l "${ARGS[SHARED_PATH]}/rebuild_lock"

    # TODO optimize so that we only copy files if the timestamp is newer

    if [[ -n $(find "${ARGS[SHARED_PATH]}/" -type f -mmin -$(( ARGS[MAX_AGE_HOURS] * 60 )) ! -name "*lock" ) ]]; then
        verbose "Identified existing database files that can be used; starting copy."
        # First remove any remaining db-ready files so updates are not triggered before we copy the databases
        rm -f /bitnami/solr/server/solr/alphabetical_browse/*db-ready
        # Copy database files first, then the "-ready" files indicating they are ready to be used
        cp -p "${ARGS[SHARED_PATH]}/"*db-updated /bitnami/solr/server/solr/alphabetical_browse/
        RCODE=$?
        if [[ "$RCODE" -eq 0 ]]; then
            cp -p "${ARGS[SHARED_PATH]}/"*db-ready /bitnami/solr/server/solr/alphabetical_browse/
            RCODE=$?
        fi
    fi

    verbose "Releasing copy lock."
    lock_state -u -l "${ARGS[SHARED_PATH]}/rebuild_lock"

    return $RCODE
}

main() {
    declare -g LOG_FILE
    LOG_FILE=$(mktemp)
    verbose "Logging to ${LOG_FILE}"
    verbose "Starting processing..."

    # Ensure the directory exists on the volume
    mkdir -p /bitnami/solr/server/solr/alphabetical_browse
    chown 1001 /bitnami/solr/server/solr/alphabetical_browse

    # Ensure the directory exists on the shared path
    mkdir -p "${ARGS[SHARED_PATH]}"

    check_skip

    # All nodes will acquire building lock before checking if they need to perform a build.
    # If a build is necessary, the build will happen here before releasing lock.
    # This includes cleaning up old db files and copying new files to shared location.
    if [[ "${SKIP_BUILD}" -eq 0 ]]; then
        build_browse
        RCODE=$?
        if [[ "$RCODE" -ne 0 ]]; then
            verbose "Rebuild failed. Exiting without copying to Solr."
            exit $RCODE
        fi
        # Cleanup /tmp/alpha-browse if we were using it as a build path and if there was no error
        if [[ "${ARGS[BUILD_PATH]}" =~ ^/tmp/alpha-browse/ ]]; then
            verbose "Clearing ${ARGS[BUILD_PATH]}"
            rm -rf "${ARGS[BUILD_PATH]}"
        fi
    fi

    # All nodes will acquire copying lock before checking for new DB files in the shared location.
    # If new files exist, the copy will happen here before releasing the lock.
    copy_to_solr
    RCODE=$?
    if [[ "$RCODE" -ne 0 ]]; then
        verbose "Copy to Solr failed."
        exit $RCODE
    fi

    verbose "All processing complete!"
}

# Parse and start running
default_args
parse_args "$@"
main
