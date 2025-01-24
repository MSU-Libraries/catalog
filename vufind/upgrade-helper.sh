#!/bin/bash

# Script help text
run_help() {
    echo ""
    echo "Usage: Detect changes in files in the local VuFind customization with"
    echo "       changes made to the given release."
    echo ""
    echo "Examples: "
    echo "   /upgrade-helper.sh --target-release v10.1.1 --current-release v10.0.1 --core-vf-path ~/vufind --msul-vf-path ~/catalog/vufind"
    echo "     Detect differences between the 10.0.1 and 10.1.1 release in ~/vufind with our code in ~/catalog/vufind"
    echo ""
    echo "FLAGS:"
    echo "  -t|--target-release RELEASE"
    echo "     (Required) The release to use for upgrading the local files to"
    echo "  -c|--current-release RELEASE"
    echo "     (Required) The release you are currently on"
    echo "  -p|--core-vf-path PATH"
    echo "     Path to the already cloned Vufind repository."
    echo "  -l|--msul-vf-path PATH"
    echo "     Path to the VuFind code in an already cloned Catalog repository."
    echo "  -v|--verbose"
    echo "      Show verbose output"
    echo ""
}

if [[ -z "$1" || $1 == "-h" || $1 == "--help" || $1 == "help" ]]; then
    run_help
    exit 0
fi

# Set defaults
default_args() {
    declare -g -A ARGS
    declare -g -a SUMMARY
    declare -g -a IGNORE_PATHS
    ARGS[VERBOSE]=0
    ARGS[CORE_VF_PATH]=
    ARGS[MSUL_VF_PATH]=
    ARGS[TARGET_RELEASE]=
    ARGS[CURRENT_RELEASE]=
    # Ignore changes in these partial paths since we don't override them in our local code
    IGNORE_PATHS=( "themes/bootstrap5" "themes/bootprint3" "themes/local_mixin_example" "themes/local_theme_example" "themes/sandal" "themes/sandal5" )
}

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
        -t|--target-release)
            ARGS[TARGET_RELEASE]="$2"
            shift; shift ;;
        -c|--current-release)
            ARGS[CURRENT_RELEASE]="$2"
            shift; shift ;;
        -p|--core-vf-path)
            ARGS[CORE_VF_PATH]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[CORE_VF_PATH]}" ]]; then
                echo "ERROR: -p|--core-vf-path path does not exist: $2"
                exit 1
            fi
            if ! git -C "${ARGS[CORE_VF_PATH]}" rev-parse 2>/dev/null; then
                echo "ERROR: -p|--core-vf-path must be a vufind git repository clone"
                exit 1
            fi
            shift; shift ;;
        -l|--msul-vf-path)
            ARGS[MSUL_VF_PATH]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[MSUL_VF_PATH]}" ]]; then
                echo "ERROR: -l|--msul-vf-path path does not exist: $2"
                exit 1
            fi
            if ! git -C "${ARGS[MSUL_VF_PATH]}" rev-parse 2>/dev/null; then
                echo "ERROR: -l|--msul-vf-path must be a vufind git repository clone"
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

required_flags() {
    REQUIRED=( TARGET_RELEASE CURRENT_RELEASE CORE_VF_PATH MSUL_VF_PATH )
    for REQ in "${REQUIRED[@]}"; do
        if [[ -z "${ARGS[$REQ]}" ]]; then
            echo "FAILURE: Missing required flag --${REQ,,}"
            exit 1
        fi
    done
}

# Print message if verbose is enabled
verbose() {
    LOG_TS=$(date +%Y-%m-%d\ %H:%M:%S)
    MSG="[${LOG_TS}] $1"
    if [[ "${ARGS[VERBOSE]}" -eq 1 ]]; then
        echo "${MSG}"
    fi
}

main() {
    compare_diffs
    compare_db
    compare_solr

    echo ""
    echo "-----------------------------------------------------------------------------------------"
    echo ""
    echo "Current Release: ${ARGS[TARGET_RELEASE]}"
    echo "Target Release: ${ARGS[TARGET_RELEASE]}"
    echo "Core VuFind Path: ${ARGS[CORE_VF_PATH]}"
    echo "MSUL VuFind Path: ${ARGS[MSUL_VF_PATH]}"
    echo ""
    echo "Summary of Changes:"
    for i in "${SUMMARY[@]}"; do echo "${i}"; done
    echo "-----------------------------------------------------------------------------------------"
    echo ""
    echo "Steps:"
    echo "1. Apply updates in the local directory with the changes detected in the core files"
    echo "   where appropriate. Use the git diff command output to see the changes made between"
    echo "   releases and the vimdiff commands to compare with our local changes. Having both outputs"
    echo "   helps point out what changes are MSUL customizations vs updates due to core VuFind changes."
    echo "2. Apply updates to the theme with changes detected in the core theme"
    echo "   where appropriate."
    echo "3. The prior output will tell you if database changes were detected. If they were,"
    echo "   you will want to ensure you have a backup of the database before performing the"
    echo "   upgrade. You will also need to manually run the DB update steps from the documentation"
    echo "   after the deploy. "
    echo "4. The prior output will also identify if Solr changes were detected. Depending on the"
    echo "   change (you can inspect the changes yourself or just always assume to be safe), you"
    echo "   will need to re-index / re-import your data into Solr in order to get the updated index."
    echo "3. Reference https://msu-libraries.github.io/catalog/upgrading/ for further"
    echo "   documentation on how to perform an upgrade."
    echo ""
}

compare_db() {
    verbose "Comparing database changes made from ${ARGS[CURRENT_RELEASE]} -> ${ARGS[TARGET_RELEASE]}"

    SUMMARY+=("-----------------------------------------------------------------------------------------")
    if ! git -C "${ARGS[CORE_VF_PATH]}" diff "${ARGS[CURRENT_RELEASE]}":module/VuFind/sql/mysql.sql --exit-code "${ARGS[TARGET_RELEASE]}":module/VuFind/sql/mysql.sql; then
        SUMMARY+=("Database changes detected in ${ARGS[TARGET_RELEASE]}:module/VuFind/sql/mysql.sql. Will be applied during build and deploy jobs in pipeline.")
    else
        SUMMARY+=("No database changes detected between ${ARGS[CURRENT_RELEASE]} and ${ARGS[TARGET_RELEASE]}")
    fi
}

compare_solr() {
    verbose "Comparing Solr changes made from ${ARGS[CURRENT_RELEASE]} -> ${ARGS[TARGET_RELEASE]}"

    SUMMARY+=("-----------------------------------------------------------------------------------------")
    if ! git -C "${ARGS[CORE_VF_PATH]}" diff --quiet "${ARGS[CURRENT_RELEASE]}":solr/ "${ARGS[TARGET_RELEASE]}":solr/; then
        SUMMARY+=("Solr changes detected in ${ARGS[TARGET_RELEASE]}:solr/. Full re-import detected to apply new index")
    else
        SUMMARY+=("No Solr changes detected between ${ARGS[CURRENT_RELEASE]} and ${ARGS[TARGET_RELEASE]}")
    fi
}


compare_diffs(){
    verbose "Looking for changes in customized files from ${ARGS[CURRENT_RELEASE]} -> ${ARGS[TARGET_RELEASE]}"

    SUMMARY+=("-----------------------------------------------------------------------------------------")
    for f in $(git -C "${ARGS[CORE_VF_PATH]}" diff tags/${ARGS[CURRENT_RELEASE]} tags/${ARGS[TARGET_RELEASE]} --name-only); do
        skip=0
        for substring in "${IGNORE_PATHS[@]}"; do
            if [[ "$f" == *"$substring"* ]]; then
                skip=1
                break
            fi
        done
        if [[ $skip == 1 ]]; then continue; fi
        match=$(find "${ARGS[MSUL_VF_PATH]}" -name "$(basename "$f")")
        if [[ -n $match ]]; then
            SUMMARY+=("Source File Updated: $f:")
            SUMMARY+=("  Possible MSUL Match(es): $match")
            SUMMARY+=("  See changes between ${ARGS[CURRENT_RELEASE]} and ${ARGS[TARGET_RELEASE]}:")
            SUMMARY+=("    git -C ${ARGS[CORE_VF_PATH]} diff ${ARGS[CURRENT_RELEASE]}:$f ${ARGS[TARGET_RELEASE]}:$f")
            SUMMARY+=("  See changes between ${ARGS[TARGET_RELEASE]} and MSUL:")
            for result in $match; do
                SUMMARY+=("    vimdiff ${ARGS[CORE_VF_PATH]}/$f $result")
            done
        fi
    done
}


# Parse and start running
default_args
parse_args "$@"
required_flags
main
