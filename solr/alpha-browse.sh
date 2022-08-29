#!/bin/bash

# Script help text
runhelp() {
    echo ""
    echo "Usage: Update the alphabetical browse databases used by Solr."
    echo ""
    echo "Examples: "
    echo "   /alpha-browse.sh"
    echo "     Checks the shared storage for updated database files. If any exist and are less"
    echo "     than 2 hours old, it will copy those, remove the originals, and stop processing."
    echo "     Otherwise it will run the command to generate new database files and make a copy"
    echo "     in the shared location."
    echo "   /alpha-browse.sh --shared-path /shared/path --force"
    echo "     Will rebuild the database files regardless of age and uses a non-default shared path."
    echo "   /alpha-browse.sh --max-age-hours 12"
    echo "     Will rebuild the database files only if they are more than 12 hours old."
    echo ""
    echo "FLAGS:"
    echo "  -p|--shared-path PATH"
    echo "     Path to the already cloned Vufind repository. Default: /mnt/shared/alpha-browse"
    echo "  -a|--max-age-hours"
    echo "      Max age (difference between current timestamp and created timestamp) in hours"
    echo "      of the database files to determine if it will use the existing files or build"
    echo "      new ones. Default: 2"
    echo "  -f|--force"
    echo "      Rebuild the database files regardless of age"
    echo "  -v|--verbose"
    echo "      Show verbose output"
    echo ""
}

if [[ -z "$1" || $1 == "-h" || $1 == "--help" || $1 == "help" ]]; then
    runhelp
    exit 0
fi

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[SHARED_PATH]=/mnt/shared/oai
    ARGS[MAX_AGE_HOURS]=2
    ARGS[FORCE]=0
    ARGS[VERBOSE]=0
}

# Parse command arguments
parse_args() {
    # Check environment variable
    if [[ -z $SOLR_HOME ]]; then
        echo "ERROR: SOLR_HOME not set in the environment"
        exit 1
    fi

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
        -p|--shared-path)
            ARGS[SHARED_PATH]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[SHARED_PATH]}" ]]; then
                echo "ERROR: -p|--shared-path path does not exist: $2"
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

rebuild_databases() {
    verbose "Running database rebuild script..."

    if ! SOLR_HOME=/bitnami/solr/server/solr /solr_confs/index-alphabetic-browse.sh; then
        verbose "Error occured while running index-alphabetic-browse.sh script!"
    fi

    verbose "Rebuild complete"
}

copy_to_shared() {
    # TODO check the names of the files we want to copy. was it the *updated or *ready?
    # or the ones that match neither of those?
    verbose "Copying database files from: /bitnami/solr/server/solr/alphabetical_browse/*-updated to ${ARGS[SHARED_PATH]}"

    cp /bitnami/solr/server/solr/alphabetical_browse/*-updated ${ARGS[SHARED_PATH]}/
}

# Remove all files from the shared storage alphabetical browse folder
remove_from_shared() {
    verbose "Removing all db files from ${ARGS[SHARED_PATH]}"
    rm ${ARGS[SHARED_PATH]}/*.db*
}

copy_from_shared() {
    verbose "Determining if there are files we can/should copy from the shared location"

    # Convert hours to minutes
    mins=$(( ARGS[MAX_AGE_HOURS] * 60 ))

    # Check if files exists with a age within the max
    if [[ "${ARGS[FORCE]}" -eq 1 || -z $(find ${ARGS[SHARED_PATH]}/ -type f -mmin ${mins}) ]]; then
        verbose "No files found within the shared path that are within a max age of ${ARGS[MAX_AGE_HOURS]} hour(s).\
        or the force flag was provided to bypass this check."
        rebuild_databases
    else
        # Otherwise, we can use those files
        # TODO we shouldn't remove the existing db files first right? Solr will just replace the
        # "in use" files with the updated one on-acceess as needed?
        cp ${ARGS[SHARED_PATH]}/* /bitnami/solr/server/solr/alphabetical_browse/
    fi
}

main() {
    declare -g LOG_FILE
    LOG_FILE=$(mktemp)
    verbose "Logging to ${LOG_FILE}"
    verbose "Starting processing..."

    if [[ "${ARGS[FORCE]}" -eq 1 ]]; then
        rebuild_databases
    else
        copy_from_shared
    fi

    copy_to_shared

    verbose "All processing complete!"
}

# Parse and start running
default_args
parse_args "$@"
main
