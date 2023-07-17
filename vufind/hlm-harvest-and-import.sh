#!/bin/bash

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[HARVEST]=0
    ARGS[FULL]=0
    ARGS[COPY_SHARED]=0
    ARGS[IMPORT]=0
    ARGS[LIMIT]=
    ARGS[LIMIT_BY_DELETE]=
    ARGS[VUFIND_HARVEST_DIR]=/usr/local/vufind/local/harvest/hlm
    ARGS[FTP_SERVER]=atozftp.ebsco.com
    ARGS[FTP_DIR]=s8364774/vufind/
    ARGS[FTP_USER]=${FTP_USER}
    ARGS[FTP_PASSWORD]=${FTP_PASSWORD}
    ARGS[SHARED_DIR]=/mnt/hlm
    ARGS[VERBOSE]=0
}
default_args

# Script help text
runhelp() {
    echo ""
    echo "Usage: Harvest HLM records from EBSCO via their FTP server"
    echo "       and import that data into VuFind's Solr."
    echo ""
    echo "Examples: "
    echo "   /hlm-harvest-and-import.sh --harvest --full --import"
    echo "     Do a full harvest from scratch and import that data"
    echo "   /hlm-harvest-and-import.sh --harvest --import"
    echo "     Do an update harvest with changes made since the"
    echo "     last run, and import that data"
    echo "   /hlm-harvest-and-import.sh --import"
    echo "     Run only a import of data that has already been"
    echo "     harvested and saved to the shared location."
    echo "   /hlm-harvest-and-import.sh --harvest"
    echo "     Only run the harvest, but do not proceed to import"
    echo "     the data into VuFind"
    echo ""
    echo "FLAGS:"
    echo "  -t|--harvest"
    echo "      Run an harvest into VUFIND_HARVEST_DIR. Will attempt"
    echo "      to resume from last harvest state unless -f flag given."
    echo "      On success, sync a copy of harvest files to SHARED_DIR/current/."
    echo "  -f|--full"
    echo "      Forces a reset of VUFIND_HARVEST_DIR, resulting"
    echo "      in a full harvest. Must be used with --harvest."
    echo "  -i|--import"
    echo "      Run VuFind batch import on files within VUFIND_HARVEST_DIR."
    echo "  -c|--copy-shared"
    echo "      Copy MARC file(s) from SHARED_DIR back to VUFIND_HARVEST_DIR."
    echo "      Only usable when NOT running a harvest (see also --limit)."
    echo "  -l|--limit COPY_COUNT"
    echo "      Usable with --copy-shared only. This will limit the number"
    echo "      of files copied from SHARED_DIR to VUFIND_HARVEST_DIR."
    echo "  -X|--limit-by-delete IMPORT_COUNT"
    echo "      Usable with --batch-import only. This will limit the number"
    echo "      of files imported from VUFIND_HARVEST_DIR by deleting MARC"
    echo "      import files exceeding the given count prior to importing."
    echo "  -d|--vufind-harvest-dir VUFIND_HARVEST_DIR"
    echo "      Full path to the VuFind harvest directory."
    echo "      Default: ${ARGS[VUFIND_HARVEST_DIR]}"
    echo "  -s|--shared-harvest-dir SHARED_DIR"
    echo "      Full path to the shared storage location for HLM files."
    echo "      Default: ${ARGS[SHARED_DIR]}"
    echo "  -F|--ftp-server FTP_SERVER"
    echo "      FTP server that contains the HLM records from EBSCO"
    echo "      Default: ${ARGS[FTP_SERVER]}"
    echo "  -D|--ftp-dir FTP_DIR"
    echo "      Directory on the FTP server that contains the HLM records "
    echo "      from EBSCO"
    echo "      Default: ${ARGS[FTP_DIR]}"
    echo "  -U|--ftp-user FTP_USER"
    echo "      User for connecting to the FTP server"
    echo "      Default: Stored in the environment variable \$FTP_USER"
    echo "  -P|--ftp-password FTP_PASSWORD"
    echo "      Password for connecting to the FTP server"
    echo "      Default: Stored in the environment variable \$FTP_PASSWORD"
    echo "  -v|--verbose"
    echo "      Show verbose output."
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
        -t|--harvest)
            ARGS[HARVEST]=1
            shift;;
        -f|--full)
            ARGS[FULL]=1
            shift;;
        -c|--copy-shared)
            ARGS[COPY_SHARED]=1
            shift;;
        -i|--import)
            ARGS[IMPORT]=1
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
        -F|--ftp-server)
            ARGS[FTP_SERVER]="$2"
            shift; shift ;;
        -D|--ftp-dir)
            ARGS[FTP_DIR]="$2"
            shift; shift ;;
        -U|--ftp-user)
            ARGS[FTP_USER]="$2"
            shift; shift ;;
        -P|--ftp-password)
            ARGS[FTP_PASSWORD]="$2"
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
    if [[ "${ARGS[HARVEST]}" -eq 1 && "${ARGS[COPY_SHARED]}" -eq 1 ]]; then
        echo "ERROR: It is invalid to set both --harvest and --copy-shared flags."
        exit 1
    fi
    if [[ -n "${ARGS[LIMIT]}" && "${ARGS[COPY_SHARED]}" -ne 1 ]]; then
        echo "ERROR: The --limit flag is only valid when --copy-shared is also set."
        exit 1
    fi
    if [[ -n "${ARGS[LIMIT_BY_DELETE]}" && "${ARGS[IMPORT]}" -ne 1 ]]; then
        echo "ERROR: The --limit-by-delete flag is only valid when --import is also set."
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

assert_ebsco_ftp_readable() {
    if ! curl ftp://${ARGS[FTP_SERVER]}/${ARGS[FTP_DIR]} --user ${ARGS[FTP_USER]}:${ARGS[FTP_PASSWORD]} > /dev/null 2>&1; then
        echo "ERROR: EBSCO HLM harvest location is not readable: ${ARGS[FTP_SERVER]}"
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
    done < <( find ./ -mindepth 1 -maxdepth 1 -name '*.marc' )

    # Archive all marc files and the last_harvest file, if it exists
    if [[ "${#ARCHIVE_LIST[@]}" -gt 0 ]]; then
        verbose "Archiving ${#ARCHIVE_LIST[@]} harvest files."
        countdown 5
        if ! tar -czvf "$ARCHIVE_FILE" "${ARCHIVE_LIST[@]}"; then
            verbose "ERROR: Could not archive harvest files into ${ARCHIVE_FILE}" 1
            exit 1
        fi
    fi
    popd > /dev/null 2>&1 || exit 1
}

clear_harvest_files() {
    countdown 5
    find "${1}" -mindepth 1 -maxdepth 1 -name '*.marc' -delete
}

# Perform an harvest of HLM Records
harvest() {
    assert_vufind_harvest_dir_writable
    assert_shared_dir_writable
    assert_ebsco_ftp_readable

    if [[ "${ARGS[FULL]}" -eq 1 ]]; then
        verbose "Clearing VuFind harvest directory for new full harvest."
        clear_harvest_files "${ARGS[VUFIND_HARVEST_DIR]}/"
        
        archive_current_harvest

        verbose "Clearing shared current directory before we sync the new full harvest."
        clear_harvest_files "${ARGS[SHARED_DIR]}/current/"
    fi

    # Check FTP server to compare files for ones we don't have
    OLD_PWD=$(pwd)
    cd ${ARGS[VUFIND_HARVEST_DIR]}
    while IFS= read -r HLM_FILE
    do
        # If it is not in the shared storage and it is an MARC file, then get it
        if [ ! -f "${ARGS[SHARED_DIR]}/current/${HLM_FILE}" ] && [[ ${HLM_FILE} == *.marc ]]; then
            if ! wget --ftp-user=${ARGS[FTP_USER]} --ftp-password=${ARGS[FTP_PASSWORD]} ftp://${ARGS[FTP_SERVER]}/${ARGS[FTP_DIR]}/${HLM_FILE} > /dev/null 2>&1; then
                verbose "ERROR: Failed to retrieve ${HLM_FILE} from FTP server" 1
                exit 1
            fi
        fi
    done < <(curl ftp://${ARGS[FTP_SERVER]}/${ARGS[FTP_DIR]} --user ${ARGS[FTP_USER]}:${ARGS[FTP_PASSWORD]} -l -s)
    cd ${OLD_PWD}

    verbose "Syncing harvest files to shared storage."
    if ! rsync -ai --exclude "processed" --exclude "log" --exclude ".gitkeep" "${ARGS[VUFIND_HARVEST_DIR]}"/ "${ARGS[SHARED_DIR]}/current/" > /dev/null 2>&1; then
        verbose "ERROR: Failed to sync harvest files to shared storage from ${ARGS[VUFIND_HARVEST_DIR]}" 1
        exit 1
    fi
}

# Copy MARC files back from shared dir to VuFind dir
copyback_from_shared() {
    assert_vufind_harvest_dir_writable
    verbose "Replacing any VuFind MARC with files from shared directory."
    countdown 5

    # Clear out any exising marc files before copying back from shared storage
    clear_harvest_files "${ARGS[VUFIND_HARVEST_DIR]}/"

    COPIED_COUNT=0
    while read -r FILE; do
        cp --preserve=timestamps "${FILE}" "${ARGS[VUFIND_HARVEST_DIR]}/"
        (( COPIED_COUNT += 1 ))
        if [[ -n "${ARGS[LIMIT]}" && "${COPIED_COUNT}" -ge "${ARGS[LIMIT]}" ]]; then
            # If limit is set, only copy the provided limit of marc files over to the VUFIND_HARVEST_DIR
            break
        fi
    done < <(find "${ARGS[SHARED_DIR]}/current/" -mindepth 1 -maxdepth 1 -name '*.marc')

}

# Perform VuFind batch import of HLM records
import() {
    assert_vufind_harvest_dir_writable

    verbose "Starting import processing..."
    if [[ -n "${ARGS[LIMIT_BY_DELETE]}" ]]; then
        verbose "Will only import ${ARGS[LIMIT_BY_DELETE]} MARC files; others will be deleted."
        countdown 5
        # Delete excess files beyond the provided limit from the VUFIND_HARVEST_DIR prior to import
        FOUND_COUNT=0
        while read -r FILE; do
            (( FOUND_COUNT += 1 ))
            if [[ "${FOUND_COUNT}" -gt "${ARGS[LIMIT_BY_DELETE]}" ]]; then
                rm "$FILE"
            fi
        done < <(find "${ARGS[VUFIND_HARVEST_DIR]}/" -mindepth 1 -maxdepth 1 -name '*.marc')
    else
        countdown 5
    fi
    # TODO -- can remove once https://github.com/vufind-org/vufind/pull/2623 is included
    # in a release (remember to also update the while loop find below too to)
    verbose "Pre-processing import files to rename from .marc to .mrc"
    while read -r FILE; do
        mv ${FILE} ${FILE%.marc}.mrc
    done < <(find "${ARGS[VUFIND_HARVEST_DIR]}/" -mindepth 1 -maxdepth 1 -name '*.marc')

    verbose "Pre-processing deletion files to extract IDs from EBSCO MARC records"
    mkdir -p ${ARGS[VUFIND_HARVEST_DIR]}/processed # needed in case it doesn't already exist
    while read -r FILE; do
        DEL_FILE=${ARGS[VUFIND_HARVEST_DIR]}/$(basename ${FILE})
        marc2xml ${FILE} > ${DEL_FILE%.mrc}.xml
        xmllint --xpath "//*[@tag = '001']/text()" ${DEL_FILE%.mrc}.xml > ${DEL_FILE%.mrc}.delete
        sed -i 's/^/hlm./' ${DEL_FILE%.mrc}.delete
        # Cleanup the other files that the batch-delete.sh script won't move to the processed dir
        rm ${DEL_FILE%.mrc}.xml
        mv ${FILE} ${ARGS[VUFIND_HARVEST_DIR]}/processed
    done < <(find "${ARGS[VUFIND_HARVEST_DIR]}/" -mindepth 1 -maxdepth 1 -name '*-Del-*.mrc')

    verbose "Running batch import"
    if [[ "${ARGS[VERBOSE]}" -eq 1 ]]; then
        if ! /usr/local/vufind/harvest/batch-import-marc.sh hlm/ | tee "$LOG_FILE"; then
            verbose "ERROR: Batch import failed with code: $?" 1
            exit 1
        fi
    else
        if ! /usr/local/vufind/harvest/batch-import-marc.sh hlm/ >> "$LOG_FILE"; then
            verbose "ERROR: Batch import failed with code: $?" 1
            exit 1
        fi
    fi
    verbose "Completed batch import"

    verbose "Processing delete records from harvest."
    countdown 5
    if [[ "${ARGS[VERBOSE]}" -eq 1 ]]; then
        if ! /usr/local/vufind/harvest/batch-delete.sh hlm | tee "$LOG_FILE"; then
            verbose "ERROR: Batch delete script failed with code: $?" 1
            exit 1
        fi
    else
        if ! /usr/local/vufind/harvest/batch-delete.sh hlm >> "$LOG_FILE"; then
            verbose "ERROR: Batch delete script failed with code: $?" 1
            exit 1
        fi
    fi
    verbose "Completed processing records to be deleted."
}

# Main logic for the script
main() {
    declare -g LOG_FILE
    LOG_FILE=$(mktemp)
    verbose "Logging to ${LOG_FILE}"
    verbose "Using VUFIND_HOME of ${VUFIND_HOME}"
    pushd "${VUFIND_HOME}" 2> /dev/null
    verbose "Starting processing"

    if [[ "${ARGS[HARVEST]}" -eq 1 ]]; then
        harvest
    elif [[ "${ARGS[COPY_SHARED]}" -eq 1 ]]; then
        copyback_from_shared
    fi
    if [[ "${ARGS[IMPORT]}" -eq 1 ]]; then
        import
    fi

    popd 2> /dev/null
    verbose "All processing complete!"
}

# Parse and start running
parse_args "$@"
catch_invalid_args
main
