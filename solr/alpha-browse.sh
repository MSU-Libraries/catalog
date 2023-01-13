#!/bin/bash

DEFAULT_BUILD_PATH=/tmp/alpha-browse/build
DEFAULT_SHARED_PATH=/mnt/shared/alpha-browse
DEFAULT_MAX_AGE_HOURS=6
if [[ -n "${STACK_NAME}" ]]; then
    DEFAULT_SHARED_PATH="${DEFAULT_SHARED_PATH}/${STACK_NAME}"
fi

# Script help text
runhelp() {
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
    runhelp
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

# Call the rebuild script to generate new database files
rebuild_databases() {
    verbose "Running database rebuild script..."
    RCODE=0

    # Prepare build directory
    mkdir -p "${ARGS[BUILD_PATH]}/alphabetical_browse"

    # Create required symlinks if they don't already exist
    if [[ ! -h ${ARGS[BUILD_PATH]}/../vendor ]]; then
        ln -s /opt/bitnami/solr ${ARGS[BUILD_PATH]}/../vendor
    fi
    if [[ ! -h ${ARGS[BUILD_PATH]}/jars ]]; then
        ln -s /bitnami/solr/server/solr/jars ${ARGS[BUILD_PATH]}/jars
    fi
    if [[ ! -h ${ARGS[BUILD_PATH]}/biblio ]]; then
        ln -s /bitnami/solr/server/solr/biblio ${ARGS[BUILD_PATH]}/biblio
    fi
    if [[ ! -h ${ARGS[BUILD_PATH]}/authority ]]; then
        ln -s /bitnami/solr/server/solr/authority ${ARGS[BUILD_PATH]}/authority
    fi

    if ! JAVA_HOME=/opt/bitnami/java SOLR_HOME=${ARGS[BUILD_PATH]} /solr_confs/index-alphabetic-browse.sh; then
        verbose "Error occured while running index-alphabetic-browse.sh script!"
        RCODE=1
    else
        verbose "Rebuild complete"
    fi

    return $RCODE
}

update_shared() {
    # Remove all files from the shared storage alphabetical browse folder
    verbose "Cleaning up old db files from ${ARGS[SHARED_PATH]} (with age > ${ARGS[MAX_AGE_HOURS]} hour(s))."
    find ${ARGS[SHARED_PATH]} -type f -mmin +$(( ARGS[MAX_AGE_HOURS] * 60 )) -name "*.db*" ! -name "*lock" -delete

    # Copy all database files to the shared storage
    verbose "Copying database files from: ${ARGS[BUILD_PATH]}/alphabetical_browse/*db-* to ${ARGS[SHARED_PATH]}"
    cp -p ${ARGS[BUILD_PATH]}/alphabetical_browse/*db-* ${ARGS[SHARED_PATH]}/
}

lock_state() {
    MAX_SLEEP=$(( 6 * 60 * 60 ))
    CUR_SLEEP=0
    while ! /lock-state.sh $@; do
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
        cp -p "${ARGS[SHARED_PATH]}/"* /bitnami/solr/server/solr/alphabetical_browse/ && \
        chown 1001 /bitnami/solr/server/solr/alphabetical_browse/*
        RCODE=$?
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

    # All nodes will acquire building lock before checking if they need to perform a build.
    # If a build is necessary, the build will happen here before releasing lock.
    # This includes cleaning up old db files and copying new files to shared location.
    build_browse

    # All nodes will acquire copying lock before checking for new DB files in the shared location.
    # If new files exist, the copy will happen here before releasing the lock.
    copy_to_solr

    verbose "All processing complete!"
}

# Parse and start running
default_args
parse_args "$@"
main
