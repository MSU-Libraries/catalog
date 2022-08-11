#!/bin/bash

SOLR_URL="http://solr:8983/solr"

# Script help text
runhelp() {
    echo ""
    echo "Usage: Harvest data from FOLIO via OAI-PMH"
    echo "       and import that data into Vufind's Solr."
    echo ""
    echo "Examples: "
    echo "   /harvest-and-import.sh --oai-harvest --full --batch-import"
    echo "     Do a full harvest from scratch and import that data"
    echo "   /harvest-and-import.sh --oai-harvest --batch-import"
    echo "     Do an update harvest with changes made since the"
    echo "     last run, and import that data"
    echo "   /harvest-and-import.sh --batch-import"
    echo "     Run only a full import of data that has already been"
    echo "     harvested and saved to the shared location. Will prompt"
    echo "     before copying data to VuFind unless --yes flag is passed"
    echo "   /harvest-and-import.sh -o"
    echo "     Only run the OAI harvest, but do not proceed to import"
    echo "     the data into VuFind"
    echo ""
    echo "FLAGS:"
    echo "  -o|--oai-harvest"
    echo "      Run an OAI harvest into SHARED_HARVEST_DIR. Will attempt"
    echo "      to resume from last harvest state unless -f flag given"
    echo "  -f|--full"
    echo "      Forces a reset of SHARED_HARVEST_DIR, resulting"
    echo "      in a full harvest. Must be used with --oai-harvest."
    echo "  -b|--batch-import"
    echo "      Run VuFind batch import on files in VUFIND_HARVEST_DIR"
    echo "  -c|--copy-shared"
    echo "      Copy XML from SHARED_HARVEST_DIR back to VUFIND_HARVEST_DIR."
    echo "      Only usable when NOT running a harvest."
    # Currently Unused
    # echo "  -y|--yes"
    # echo "      Assume a 'yes' answer to all prompts and run the"
    # echo "      command non-interactively."
    echo "  -l|--limit FILES_COUNT"
    echo "      Limit the number of files imported during batch import."
    echo "      For copy-shared, this will limit files copied."
    echo "      Without copy-shared, this is done by DELETING any files"
    echo "      exceeding this limit from the VUFIND_HARVEST_DIR."
    echo "  -d|--vufind-harvest-dir DIR"
    echo "      Full path to the vufind harvest directory"
    echo "      Default: /usr/local/vufind/local/harvest/folio"
    echo "  -s|--shared-harvest-dir DIR"
    echo "      Full path to the shared storage location for harvested OAI files"
    echo "      Default: /mnt/shared/oai"
    echo "  -r|--reset-solr"
    echo "      Clear out the biblio Solr collection prior to importing"
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
    ARGS[OAI_HARVEST]=0
    ARGS[FULL]=0
    ARGS[YES]=0
    ARGS[COPY_SHARED]=0
    ARGS[BATCH_IMPORT]=0
    ARGS[LIMIT]=
    ARGS[VUFIND_HARVEST_DIR]=/usr/local/vufind/local/harvest/folio
    ARGS[SHARED_HARVEST_DIR]=/mnt/shared/oai
    ARGS[RESET_SOLR]=0
    ARGS[VERBOSE]=0
}

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
        -o|--oai-harvest)
            ARGS[OAI_HARVEST]=1
            shift;;
        -f|--full)
            ARGS[FULL]=1
            shift;;
        -c|--copy-shared)
            ARGS[COPY_SHARED]=1
            shift;;
        -y|--yes)
            ARGS[YES]=1
            shift;;
        -b|--batch-import)
            ARGS[BATCH_IMPORT]=1
            shift;;
        -l|--limit)
            ARGS[LIMIT]="$2"
            if [[ ! "${ARGS[LIMIT]}" -gt 0 ]]; then
                echo "ERROR: -l|--limit only accept positive integers"
                exit 1
            fi
            shift; shift ;;
        -d|--vufind-harvest-dir)
            ARGS[VUFIND_HARVEST_DIR]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[VUFIND_HARVEST_DIR]}" ]]; then
                echo "ERROR: -d|--vufind-harvest-dir path does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -s|--shared-harvest-dir)
            ARGS[SHARED_HARVEST_DIR]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[SHARED_HARVEST_DIR]}" ]]; then
                echo "ERROR: -s|--shared-harvest-dir path does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -r|--reset-solr)
            ARGS[RESET_SOLR]=1
            shift;;
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

prompt_yes() {
    if [[ "${ARGS[YES]}" -ne 1 ]]; then
        read -r -p "$1 (y/N) " RESP
        case $RESP in
            [Yy])
                return;;
            *)
                echo "Exiting..."
                exit;;
        esac
    fi
}

# Print the last modified time as epoch seconds, or 0 if not a valid/accessible file
last_modified() {
    if [[ ! -f "$1" ]]; then
        echo "0"
    else
        stat --format=%Y "$1"
    fi
}

archive_shared_xml() {
    verbose "Checking for previous harvest files"
    if ! ls "${ARGS[SHARED_HARVEST_DIR]}"/combined_*.xml >/dev/null 2>&1 && [[ ! -f "${ARGS[SHARED_HARVEST_DIR]}/last_harvest.txt" ]]; then
        verbose "No previous harvest files found"
        return
    fi
    ARCHIVE_TS=$(date +%Y%m%d_%H%M%S)
    mkdir -p "${ARGS[SHARED_HARVEST_DIR]}/archives"
    ARCHIVE_FILE="${ARGS[SHARED_HARVEST_DIR]}/archives/archive_${ARCHIVE_TS}.tar.gz"
    pushd "${ARGS[SHARED_HARVEST_DIR]}/" > /dev/null 2>&1 || exit 1
    declare -a ARCHIVE_LIST
    while read -r FILE; do
        ARCHIVE_LIST+=("$FILE")
    done < <(find ./ -mindepth 1 -maxdepth 1 -name 'combined_*.xml' -o -name 'last_harvest.txt' -o -name 'harvest.log')
    # Archive all combined xml files and the last_harvest file, if it exists
    if [[ "${#ARCHIVE_LIST[@]}" -gt 0 ]]; then
        verbose "Archiving previous harvest files"
        if tar -czvf "$ARCHIVE_FILE" "${ARCHIVE_LIST[@]}"; then
            # remove archived files
            rm "${ARCHIVE_LIST[@]}"
        else
            echo "ERROR: Could not archive previous harvest files into ${ARCHIVE_FILE}"
            exit 1
        fi
    fi
    popd > /dev/null 2>&1 || exit 1
}

oai_harvest_combiner() {
    if [[ "${#COMBINE_FILES[@]}" -eq 0 ]]; then
        return
    fi
    COMBINE_TS=$(date +%Y%m%d_%H%M%S)
    COMBINE_TARGET="combined_${COMBINE_TS}.xml"
    verbose "Combining ${#COMBINE_FILES[@]} into ${COMBINE_TARGET}"
    xml_grep --wrap collection --cond "marc:record" "${COMBINE_FILES[@]}" > "${ARGS[VUFIND_HARVEST_DIR]}/${COMBINE_TARGET}"
    verbose "Done combining ${COMBINE_TARGET}; removing pre-combined files..."
    rm -v "${COMBINE_FILES[@]}"
    verbose "Done removing ${#COMBINE_FILES[@]} files."
    COMBINE_FILES=()
}

# Reset the biblio Solr collection by clearing all records
reset_solr() {
    if [[ "${ARGS[RESET_SOLR]}" -eq 0 ]]; then
        return
    fi
    verbose "Clearing the biblio Solr index"
    curl ${SOLR_URL}/biblio/update -H "Content-type: text/xml" --data-binary '<delete><query>*:*</query></delete>'
    curl ${SOLR_URL}/biblio/update -H "Content-type: text/xml" --data-binary '<commit />'
    verbose "Done clearing the Solr index"
}

# Perform an OAI harvest
oai_harvest() {
    verbose "Checking harvest state"

    # Copy last_harvest from SHARED_HARVEST_DIR if it exists and is newer (except if --full)
    SHARED_LAST_HARVEST="${ARGS[SHARED_HARVEST_DIR]}/last_harvest.txt"
    VUFIND_LAST_HARVEST="${ARGS[VUFIND_HARVEST_DIR]}/last_harvest.txt"
    SHARED_LAST_MODIFIED=$( last_modified "$SHARED_LAST_HARVEST" )
    VUFIND_LAST_MODIFIED=$( last_modified "$VUFIND_LAST_HARVEST" )
    if [[ "$SHARED_LAST_MODIFIED" -gt "$VUFIND_LAST_MODIFIED" && "${ARGS[FULL]}" -ne 1 ]]; then
        verbose "Restoring $SHARED_LAST_HARVEST"
        cp --preserve=timestamps "$SHARED_LAST_HARVEST" "$VUFIND_LAST_HARVEST"
    fi

    # Validate the the shared storage location is writable
    if ! [ -w "${ARGS[SHARED_HARVEST_DIR]}" ] ; then
        echo "ERROR: Shared storage location is not writable: ${ARGS[SHARED_HARVEST_DIR]}"
        exit 1
    fi

    # If this is a full harvest, archive the previous XML files in the shared location
    if [[ "${ARGS[FULL]}" -eq 1 ]]; then
        archive_shared_xml
        rm -f "${ARGS[VUFIND_HARVEST_DIR]}/last_harvest.txt"
        rm -f "${ARGS[VUFIND_HARVEST_DIR]}/harvest.log"
        rm -f "${ARGS[VUFIND_HARVEST_DIR]}/last_state.txt"
    fi

    verbose "Starting OAI harvest"

    php /usr/local/vufind/harvest/harvest_oai.php

    verbose "Completed OAI harvest"

    # Combine XML files for faster import
    verbose "Combining harvested XML files"
    declare -g -a COMBINE_FILES=()
    while read -r FILE; do
        COMBINE_FILES+=("$FILE")
        if [[ "${#COMBINE_FILES[@]}" -ge 100 ]]; then
            oai_harvest_combiner
        fi
    done < <(find "${ARGS[VUFIND_HARVEST_DIR]}/" -mindepth 1 -maxdepth 1 -name '*_oai_*.xml')
    oai_harvest_combiner

    verbose "Copying combined XML to shared dir"
    cp --preserve=timestamps "${ARGS[VUFIND_HARVEST_DIR]}"/combined_*.xml "${ARGS[SHARED_HARVEST_DIR]}/"

    HARVEST_LOG="${ARGS[VUFIND_HARVEST_DIR]}/harvest.log"
    if [[ -f "${HARVEST_LOG}" ]]; then
        verbose "Copying harvest.log to shared dir"
        cp --preserve=timestamps "${HARVEST_LOG}" "${ARGS[SHARED_HARVEST_DIR]}/"
    fi
    LAST_HARVEST="${ARGS[VUFIND_HARVEST_DIR]}/last_harvest.txt"
    if [[ -f "${LAST_HARVEST}" ]]; then
        verbose "Copying last_harvest.txt to shared dir"
        cp --preserve=timestamps "${LAST_HARVEST}" "${ARGS[SHARED_HARVEST_DIR]}/"
    fi
}

# Copy XML files back from shared dir to VuFind dir
copyback_from_shared() {
    #prompt_yes "Proceed to copy XML and last_harvest.txt from ${ARGS[SHARED_HARVEST_DIR]} to ${ARGS[VUFIND_HARVEST_DIR]}?"

    # Clear out any exising xml files before copying back from shared storage
    rm -f "${ARGS[VUFIND_HARVEST_DIR]}/combined_"*.xml

    verbose "Copying combined XML from shared dir to VuFind"
    COPIED_COUNT=0
    while read -r FILE; do
        cp --preserve=timestamps "${FILE}" "${ARGS[VUFIND_HARVEST_DIR]}/"
        (( COPIED_COUNT += 1 ))
        if [[ -n "${ARGS[LIMIT]}" && "${COPIED_COUNT}" -ge "${ARGS[LIMIT]}" ]]; then
            # If limit is set, only copy the provided limit of xml files over to the VUFIND_HARVEST_DIR
            break
        fi
    done < <(find "${ARGS[SHARED_HARVEST_DIR]}" -mindepth 1 -maxdepth 1 -name 'combined_*.xml')

    verbose "Copying last_harvest.txt from shared dir to VuFind"
    cp --preserve=timestamps "${ARGS[SHARED_HARVEST_DIR]}"/last_harvest.txt "${ARGS[VUFIND_HARVEST_DIR]}/"
}

# Perform VuFind batch import of OAI records
batch_import() {
    verbose "Starting batch import"

    if [[ -n "${ARGS[LIMIT]}" ]]; then
        # Delete excess files beyond the provided limit from the VUFIND_HARVEST_DIR prior to import
        FOUND_COUNT=0
        while read -r FILE; do
            (( FOUND_COUNT += 1 ))
            if [[ "${FOUND_COUNT}" -gt "${ARGS[LIMIT]}" ]]; then
                rm "$FILE"
            fi
        done < <(find "${ARGS[VUFIND_HARVEST_DIR]}/" -mindepth 1 -maxdepth 1 -name 'combined_*.xml')
    fi
    
    /usr/local/vufind/harvest/batch-import-marc.sh folio

    verbose "Completed batch import"
}

# Main logic for the script
main() {
    declare -g LOG_FILE
    LOG_FILE=$(mktemp)
    verbose "Logging to ${LOG_FILE}"
    verbose "Starting processing..."

    if [[ "${ARGS[OAI_HARVEST]}" -eq 1 ]]; then
        oai_harvest
    elif [[ "${ARGS[COPY_SHARED]}" -eq 1 ]]; then
        copyback_from_shared
    fi
    if [[ "${ARGS[RESET_SOLR]}" -eq 1 ]]; then
        reset_solr
    fi
    if [[ "${ARGS[BATCH_IMPORT]}" -eq 1 ]]; then
        batch_import
    fi

    verbose "All processing complete!"
}

# Parse and start running
default_args
parse_args "$@"
main
