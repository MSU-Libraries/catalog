#!/bin/bash

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[OAI_HARVEST]=0
    ARGS[FULL]=0
    ARGS[COPY_SHARED]=0
    ARGS[BATCH_IMPORT]=0
    ARGS[LIMIT]=
    ARGS[LIMIT_BY_DELETE]=
    ARGS[VUFIND_HARVEST_DIR]=/usr/local/vufind/local/harvest/folio
    ARGS[SHARED_DIR]=/mnt/oai
    ARGS[SOLR_URL]="http://solr:8983/solr"
    ARGS[RESET_SOLR]=0
    ARGS[VERBOSE]=0
    ARGS[TEST_HARVEST]=
}
default_args

# Script help text
runhelp() {
    echo ""
    echo "Usage: Harvest data from FOLIO via OAI-PMH"
    echo "       and import that data into VuFind's Solr."
    echo ""
    echo "Examples: "
    echo "   /harvest-and-import.sh --oai-harvest --full --batch-import"
    echo "     Do a full harvest from scratch and import that data"
    echo "   /harvest-and-import.sh --oai-harvest --batch-import"
    echo "     Do an update harvest with changes made since the"
    echo "     last run, and import that data"
    echo "   /harvest-and-import.sh --batch-import"
    echo "     Run only a full import of data that has already been"
    echo "     harvested and saved to the shared location."
    echo "   /harvest-and-import.sh -o"
    echo "     Only run the OAI harvest, but do not proceed to import"
    echo "     the data into VuFind"
    echo ""
    echo "Note: Harvests can be disabled by adding a file named 'disabled'"
    echo "(case insensitive) at the top of the SHARED_DIR path."
    echo ""
    echo "FLAGS:"
    echo "  -o|--oai-harvest"
    echo "      Run an OAI harvest into VUFIND_HARVEST_DIR. Will attempt"
    echo "      to resume from last harvest state unless -f flag given."
    echo "      On success, sync copy of harvest files to SHARED_DIR/current/."
    echo "  -f|--full"
    echo "      Forces a reset of VUFIND_HARVEST_DIR, resulting"
    echo "      in a full harvest. Must be used with --oai-harvest."
    echo "  -b|--batch-import"
    echo "      Run VuFind batch import on files within VUFIND_HARVEST_DIR."
    echo "  -c|--copy-shared"
    echo "      Copy XML file(s) from SHARED_DIR back to VUFIND_HARVEST_DIR."
    echo "      Only usable when NOT running a harvest (see also --limit)."
    echo "  -l|--limit COPY_COUNT"
    echo "      Usable with --copy-shared only. This will limit the number"
    echo "      of files copied from SHARED_DIR to VUFIND_HARVEST_DIR."
    echo "  -X|--limit-by-delete IMPORT_COUNT"
    echo "      Usable with --batch-import only. This will limit the number"
    echo "      of files imported from VUFIND_HARVEST_DIR by deleting XML"
    echo "      import files exceeding the given count prior to importing."
    echo "  -d|--vufind-harvest-dir VUFIND_HARVEST_DIR"
    echo "      Full path to the VuFind harvest directory."
    echo "      Default: ${ARGS[VUFIND_HARVEST_DIR]}"
    echo "  -s|--shared-harvest-dir SHARED_DIR"
    echo "      Full path to the shared storage location for OAI files."
    echo "      Default: ${ARGS[SHARED_DIR]}"
    echo "  -S|--solr SOLR_URL"
    echo "      Base URL for accessing Solr (only used for --reset-solr)."
    echo "      Default: ${ARGS[SOLR_URL]}"
    echo "  -r|--reset-solr"
    echo "      Clear out the biblio Solr collection prior to importing."
    echo "  -v|--verbose"
    echo "      Show verbose output."
    echo "  -T|--test-harvest HARVEST_TGZ"
    echo "      Instead of calling VuFind's harvest script, instead extract"
    echo "      this gzip'd tar file into the VUFIND_HARVEST_DIR. This flag"
    echo "      is used to test this script (only used with --oai-harvest)."
    echo ""
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
        -o|--oai-harvest)
            ARGS[OAI_HARVEST]=1
            shift;;
        -f|--full)
            ARGS[FULL]=1
            shift;;
        -c|--copy-shared)
            ARGS[COPY_SHARED]=1
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
        -X|--limit-by-delete)
            ARGS[LIMIT_BY_DELETE]="$2"
            if [[ ! "${ARGS[LIMIT_BY_DELETE]}" -gt 0 ]]; then
                echo "ERROR: -X|--limit-by-delete only accept positive integers"
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
            ARGS[SHARED_DIR]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[SHARED_DIR]}" ]]; then
                echo "ERROR: -s|--shared-harvest-dir path does not exist: $2"
                exit 1
            fi
            shift; shift ;;
        -S|--solr)
            ARGS[SOLR_URL]="$2"
            shift; shift ;;
        -r|--reset-solr)
            ARGS[RESET_SOLR]=1
            shift;;
        -v|--verbose)
            ARGS[VERBOSE]=1
            shift;;
        -T|--test-harvest)
            ARGS[TEST_HARVEST]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -f "${ARGS[TEST_HARVEST]}" ]]; then
                echo "ERROR: -T|--test-harvest must be a file: $2"
                exit 1
            fi
            TEST_FILES=$( tar -tzf "${ARGS[TEST_HARVEST]}" )
            RC=$?
            if [[ "$RC" -ne 0 || -z "${TEST_FILES}" ]]; then
                echo "ERROR: -T|--test-harvest must be a non-empty gzip tarball: $2"
                exit 1
            fi
            shift; shift ;;
        *)
            echo "ERROR: Unknown flag: $1"
            exit 1
        esac
    done
}

catch_invalid_args() {
    if [[ "${ARGS[OAI_HARVEST]}" -eq 1 && "${ARGS[COPY_SHARED]}" -eq 1 ]]; then
        echo "ERROR: It is invalid to set both --oai-harvest and --copy-shared flags."
        exit 1
    fi
    if [[ -n "${ARGS[LIMIT]}" && "${ARGS[COPY_SHARED]}" -ne 1 ]]; then
        echo "ERROR: The --limit flag is only valid when --copy-shared is also set."
        exit 1
    fi
    if [[ -n "${ARGS[LIMIT_BY_DELETE]}" && "${ARGS[BATCH_IMPORT]}" -ne 1 ]]; then
        echo "ERROR: The --limit-by-delete flag is only valid when --batch-import is also set."
        exit 1
    fi
    if [[ -n "${ARGS[TEST_HARVEST]}" && "${ARGS[OAI_HARVEST]}" -ne 1 ]]; then
        echo "ERROR: The --test-harvest flag is only valid when --oai-harvest is also set."
        exit 1
    fi
}

assert_shared_dir_writable() {
    if ! [ -w "${ARGS[SHARED_DIR]}" ]; then
        echo "ERROR: Shared storage location is not writable: ${ARGS[SHARED_DIR]}"
        exit 1
    fi
    mkdir -p "${ARGS[SHARED_DIR]}/current/"
    mkdir -p "${ARGS[SHARED_DIR]}/archives/"
}

assert_vufind_harvest_dir_writable() {
    if ! [ -w "${ARGS[VUFIND_HARVEST_DIR]}" ]; then
        echo "ERROR: VuFind harvest location is not writable: ${ARGS[VUFIND_HARVEST_DIR]}"
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

verbose_inline() {
    MSG="$1"
    if [[ "${ARGS[VERBOSE]}" -eq 1 ]]; then
        echo -n -e "${MSG}"
    fi
    echo -n -e "${MSG}" >> "$LOG_FILE"
}

#####
# Start a timed countdown (allowing user to cancel)
#  $1 => (Optional) String message to display before countdown; default: "Proceeding in:"
#  $2 => (Optional) Integer number of seconds to countdown from; default: 5
countdown() {
    CD_CNT="${1:-5}"
    CD_MSG="${2:-Proceeding in:}"
    verbose_inline "${CD_MSG}"
    while [[ "$CD_CNT" -gt 0 ]]; do
        verbose_inline " ${CD_CNT}";
        sleep 1.1
        (( CD_CNT -= 1 ))
    done
    verbose_inline "\n"
}

# Print the last modified time as epoch seconds, or 0 if not a valid/accessible file
last_modified() {
    if [[ ! -f "$1" ]]; then
        echo "0"
    else
        stat --format=%Y "$1"
    fi
}

archive_current_harvest() {
    assert_shared_dir_writable
    verbose "Creating archive of latest harvest"

    ARCHIVE_TS=$(date +%Y%m%d_%H%M%S)
    ARCHIVE_FILE="${ARGS[SHARED_DIR]}/archives/archive_${ARCHIVE_TS}.tar.gz"
    pushd "${ARGS[VUFIND_HARVEST_DIR]}/" > /dev/null 2>&1 || exit 1
    declare -a ARCHIVE_LIST
    while read -r FILE; do
        ARCHIVE_LIST+=("$FILE")
    done < <( find ./ -mindepth 1 -maxdepth 1 \
        -name 'combined_*.xml' -o \
        -name 'combined_*.delete' -o \
        -name 'last_harvest.txt' -o \
        -name 'harvest.log'
    )

    # Archive all combined xml files and the last_harvest file, if it exists
    if [[ "${#ARCHIVE_LIST[@]}" -gt 0 ]]; then
        verbose "Archiving ${#ARCHIVE_LIST[@]} harvest files."
        countdown 5
        if ! tar -czvf "$ARCHIVE_FILE" "${ARCHIVE_LIST[@]}"; then
            echo "ERROR: Could not archive harvest files into ${ARCHIVE_FILE}"
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
    verbose "Combining ${#COMBINE_FILES[@]} xml files into ${COMBINE_TARGET}"
    xml_grep --wrap collection --cond "marc:record" "${COMBINE_FILES[@]}" > "${ARGS[VUFIND_HARVEST_DIR]}/${COMBINE_TARGET}"
    verbose_inline "Done combining ${COMBINE_TARGET}; now removing pre-combined files "
    while read -r LINE; do verbose_inline "."
    done < <( rm -v "${COMBINE_FILES[@]}" )
    verbose_inline "\n"
    verbose "Done removing ${#COMBINE_FILES[@]} files."
    COMBINE_FILES=()
}

append_hrid_given_uuid(){
    UUID="$1"
    APPEND_FILE="$2"
    if [[ -z "$UUID" ]]; then return; fi
    if [[ -z "$APPEND_FILE" ]]; then
        echo "ERROR: No append file given when converting a delete UUID: $UUID"
        exit 1
    fi
    HRID=$( curl -s "${ARGS[SOLR_URL]}/biblio/select?q=uuid_str:${UUID}&wt=json" | jq -r '.response.docs[0].id' )
    JQ_EC="$?"
    if [[ "$JQ_EC" -ne 0 || -z "$HRID" || "$HRID" == "null" ]]; then
        verbose "MISSING: The UUID $UUID was not found in Solr; ignoring it."
        return
    fi
    echo "$HRID" >> "${ARGS[VUFIND_HARVEST_DIR]}/${APPEND_FILE}"
}

oai_delete_combiner() {
    if [[ "${#DELETE_FILES[@]}" -eq 0 ]]; then
        return
    fi
    COMBINE_TS=$(date +%Y%m%d_%H%M%S)
    COMBINE_TARGET="combined_${COMBINE_TS}.delete"
    verbose "Combining and converting ${#DELETE_FILES[@]} delete files into ${COMBINE_TARGET}"
    for DFILE in "${DELETE_FILES[@]}"; do
        # Ensure file ends in newline
        sed -i '$a\' "$DFILE"
        while read -r UUID; do
            append_hrid_given_uuid "$UUID" "$COMBINE_TARGET"
        done < "$DFILE"
    done
    verbose_inline "Done combining ${COMBINE_TARGET}; now removing pre-combined files "
    while read -r LINE; do verbose_inline "."
    done < <( rm -v "${DELETE_FILES[@]}" )
    verbose_inline "\n"
    verbose "Done removing ${#DELETE_FILES[@]} files."
    DELETE_FILES=()
}

# Reset the biblio Solr collection by clearing all records
reset_solr() {
    if [[ "${ARGS[RESET_SOLR]}" -eq 0 ]]; then
        return
    fi
    verbose "Clearing the biblio Solr index."
    countdown 5
    curl "${ARGS[SOLR_URL]}/biblio/update" -H "Content-type: text/xml" --data-binary '<delete><query>*:*</query></delete>'
    curl "${ARGS[SOLR_URL]}/biblio/update" -H "Content-type: text/xml" --data-binary '<commit />'
    verbose "Done clearing the Solr index."
}

clear_harvest_files() {
    countdown 5
    find "${1}" -mindepth 1 -maxdepth 1 \
      \( \
        -name '*.xml' -o \
        -name '*.delete' -o \
        -name 'last_harvest.txt' -o \
        -name 'last_state.txt' -o \
        -name 'harvest.log' \
      \) -delete
}

# Perform an OAI harvest
oai_harvest() {
    assert_vufind_harvest_dir_writable
    assert_shared_dir_writable

    if [[ "${ARGS[FULL]}" -eq 1 ]]; then
        verbose "Clearing VuFind harvest directory for new full harvest."
        clear_harvest_files "${ARGS[VUFIND_HARVEST_DIR]}/"
    fi


    if [[ -n "${ARGS[TEST_HARVEST]}" ]]; then
        verbose "TESTING: Extracting OAI harvest from file: ${ARGS[TEST_HARVEST]}"
        countdown 5
        tar -C "${ARGS[VUFIND_HARVEST_DIR]}" -xzvf "${ARGS[TEST_HARVEST]}"
        verbose "TESTING: Harvest extraction completed."
    else
        verbose "Starting OAI harvest."
        countdown 5
        MAX_FAILURES=10
        CUR_FAILURES=0
        while ! php /usr/local/vufind/harvest/harvest_oai.php && [[ "$CUR_FAILURES" -lt "$MAX_FAILURES" ]]; do
            (( CUR_FAILURES += 1))
            verbose "Failure from harvest_oai.php (#${CUR_FAILURES})."
            if [[ "$CUR_FAILURES" -lt "$MAX_FAILURES" ]]; then
                verbose "Waiting before trying to continue harvest..."
                sleep 30
            else
                verbose "Too many failures while attempting to harvest! Exiting."
                exit 1
            fi
        done
        verbose "Completed OAI harvest."
    fi

    # Combine XML files for faster import
    verbose "Combining harvested XML files."
    countdown 5
    declare -g -a COMBINE_FILES=()
    while read -r FILE; do
        COMBINE_FILES+=("$FILE")
        if [[ "${#COMBINE_FILES[@]}" -ge 100 ]]; then
            oai_harvest_combiner
        fi
    done < <(find "${ARGS[VUFIND_HARVEST_DIR]}/" -mindepth 1 -maxdepth 1 -name '*_*_*_*.xml')
    oai_harvest_combiner

    declare -g -a DELETE_FILES=()
    while read -r FILE; do
        DELETE_FILES+=("$FILE")
        if [[ "${#DELETE_FILES[@]}" -ge 100 ]]; then
            oai_delete_combiner
        fi
    done < <(find "${ARGS[VUFIND_HARVEST_DIR]}/" -mindepth 1 -maxdepth 1 -name '*_*_*_*.delete')
    oai_delete_combiner

    if [[ "${ARGS[FULL]}" -eq 1 ]]; then
        archive_current_harvest

        verbose "Clearing shared current directory before we sync the new full harvest."
        clear_harvest_files "${ARGS[SHARED_DIR]}/current/"
    fi

    verbose "Syncing combined harvest files to shared storage."
    rsync -vt "${ARGS[VUFIND_HARVEST_DIR]}"/* "${ARGS[SHARED_DIR]}/current/"
}

# Copy XML files back from shared dir to VuFind dir
copyback_from_shared() {
    assert_vufind_harvest_dir_writable
    verbose "Replacing any VuFind combined XML with files from shared directory."
    countdown 5

    # Clear out any exising xml files before copying back from shared storage
    rm -f "${ARGS[VUFIND_HARVEST_DIR]}/combined_"*.xml

    COPIED_COUNT=0
    while read -r FILE; do
        cp --preserve=timestamps "${FILE}" "${ARGS[VUFIND_HARVEST_DIR]}/"
        (( COPIED_COUNT += 1 ))
        if [[ -n "${ARGS[LIMIT]}" && "${COPIED_COUNT}" -ge "${ARGS[LIMIT]}" ]]; then
            # If limit is set, only copy the provided limit of xml files over to the VUFIND_HARVEST_DIR
            break
        fi
    done < <(find "${ARGS[SHARED_DIR]}/current/" -mindepth 1 -maxdepth 1 -name 'combined_*.xml')

    verbose "Copying last_harvest.txt from shared dir to VuFind."
    cp --preserve=timestamps "${ARGS[SHARED_DIR]}"/current/last_harvest.txt "${ARGS[VUFIND_HARVEST_DIR]}/"
}

# Perform VuFind batch import of OAI records
batch_import() {
    assert_vufind_harvest_dir_writable

    verbose "Starting batch import..."
    if [[ -n "${ARGS[LIMIT_BY_DELETE]}" ]]; then
        verbose "Will only import ${ARGS[LIMIT_BY_DELETE]} XML files; others will be deleted."
        countdown 5
        # Delete excess files beyond the provided limit from the VUFIND_HARVEST_DIR prior to import
        FOUND_COUNT=0
        while read -r FILE; do
            (( FOUND_COUNT += 1 ))
            if [[ "${FOUND_COUNT}" -gt "${ARGS[LIMIT_BY_DELETE]}" ]]; then
                rm "$FILE"
            fi
        done < <(find "${ARGS[VUFIND_HARVEST_DIR]}/" -mindepth 1 -maxdepth 1 -name 'combined_*.xml')
    else
        countdown 5
    fi

    if ! /usr/local/vufind/harvest/batch-import-marc.sh folio/; then
        echo "ERROR: Batch import failed with code: $?"
        exit 1
    fi
    verbose "Completed batch import"

    verbose "Processing delete records from harvest."
    countdown 5
    if ! /usr/local/vufind/harvest/batch-delete.sh folio; then
        echo "ERROR: Batch delete script failed."
        exit 1
    fi
    verbose "Completed processing records to be deleted."
}

check_harvest_disabled() {
    DISABLED=$( find -L "${ARGS[SHARED_DIR]}" -mindepth 1 -maxdepth 1 -type f -iname 'disabled' | wc -l )
    if [[ "$DISABLED" -gt 0 ]]; then
        verbose "Not starting OAI harvest - detected file named 'disabled' in ${ARGS[SHARED_DIR]}"
        exit 0
    fi
}

# Main logic for the script
main() {
    declare -g LOG_FILE
    LOG_FILE=$(mktemp)
    verbose "Logging to ${LOG_FILE}"
    verbose "Using VUFIND_HOME of ${VUFIND_HOME}"
    check_harvest_disabled
    pushd "${VUFIND_HOME}" 2> /dev/null
    verbose "Starting processing"

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

    popd 2> /dev/null
    verbose "All processing complete!"
}

# Parse and start running
parse_args "$@"
catch_invalid_args
main
