#!/bin/bash

VUFIND_HARVEST_DIR=/usr/local/vufind/local/harvest/folio
OAI_HARVEST_DIR=${}

# Script help text
runhelp() {
    echo ""
    echo "Usage: Harvest data from FOLIO via OAI-PMH"
    echo "       and import that data into Vufind's Solr."
    echo ""
    echo "Examples: "
    echo "   /harvest-and-import.sh"
    echo "     Do an update harvest with changes made since"
    echo "     the last run, copy it to the shared location,"
    echo "     and import that data"
    echo "   /harvest-and-import.sh -i -f"
    echo "     Run only a full import of data that has already been"
    echo "     harvested and saved to the shared location"
    echo "   /harvest-and-import.sh -h"
    echo "     Run only an update harvest with changes made"
    echo "     since the last run and copy it to the shared location"
    echo ""
    echo "FLAGS:"
    echo "  -o|--harvest-oai"
    echo "      Run the OAI harvest into OAI_HARVEST_DIR"
    echo "  -i|--import"
    echo "      Run VuFind batch import on files in VUFIND_HARVEST_DIR"
    echo "  -c|--copy-harvest"
    echo "      Copy OAI files from OAI_HARVEST_DIR to"
    echo "      the VUFIND_HARVEST_DIR."
    echo "  -t|--full-harvest"
    echo "      When running the OAI harvest, do a complete"
    echo "      harvest instead of just an update harvest,"
    echo "      which would normally only pull changes since"
    echo "      the last run"
    echo "  -v|--verbose"
    echo "      Show verbose output"
    echo ""
    echo "Exptected Environment variables:"
    echo "  SHARED_HARVEST_DIR"
    echo "      Full path to the location of the harvested XML files"
}

if [[ -z "$1" || $1 == "-h" || $1 == "--help" || $1 == "help" ]]; then
    runhelp
    exit 0
fi

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[VERBOSE]=0
}

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
        -v|--verbose)
            ARGS[VERBOSE]=1
            shift;;
        *)
            echo "ERROR: Unknown flag: $1"
            exit 1
        esac
    done


# Print message if verbose is enabled
verbose() {
    MSG="$1"
    if [[ "${ARGS[VERBOSE]}" -eq 1 ]]; then
        echo "${MSG}"
    fi
}

# Main logic for the script
main() {

}

# Parse and start running
default_args
parse_args "$@"
main
