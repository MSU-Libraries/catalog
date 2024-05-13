#!/bin/bash

# Script help text
runhelp() {
    echo ""
    echo "Usage: Detect changes in files in the local directory with"
    echo "       changes made to the given release."
    echo ""
    echo "Examples: "
    echo "   /upgrade-helper.sh --release v8.1"
    echo "     Updates the local directories to have new data from the v8.1 release tag"
    echo "   /upgrade-helper.sh --release v8.1 --repo-path /some/path"
    echo "     Updates the local directories to have new data from the v8.1 release tag"
    echo "     and will use the already cloned Vufind repository at /some/path"
    echo ""
    echo "FLAGS:"
    echo "  -r|--release RELEASE"
    echo "     (Required) The release to use for upgrading the local files to"
    echo "  -p|--repo-path PATH"
    echo "     Path to the already cloned Vufind repository. Default: ${VUFIND_HOME}"
    echo "  -c|--current-release RELEASE"
    echo "     (Required) The release you are currently on"
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
    declare -g -a SUMMARY
    ARGS[VERBOSE]=0
    ARGS[REPO_PATH]=${VUFIND_HOME}
    ARGS[RELEASE]=
    ARGS[CURRENT_RELEASE]=
}

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
        -r|--release)
            ARGS[RELEASE]="$2"
            shift; shift ;;
        -c|--current-release)
            ARGS[CURRENT_RELEASE]="$2"
            shift; shift ;;
        -p|--repo-path)
            ARGS[REPO_PATH]=$( readlink -f "$2" )
            RC=$?
            if [[ "$RC" -ne 0 || ! -d "${ARGS[REPO_PATH]}" ]]; then
                echo "ERROR: -p|--repo-path path does not exist: $2"
                exit 1
            fi
            if ! git -C "${ARGS[REPO_PATH]}" rev-parse 2>/dev/null; then
                echo "ERROR: -p|--repo-path must be a vufind git repository clone"
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
    REQUIRED=( RELEASE CURRENT_RELEASE )
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
    compare_theme
    compare_local
    compare_diffs
    compare_db
    compare_solr

    echo ""
    echo "-----------------------------------------------------------------------------------------"
    echo ""
    echo "Target Release: ${ARGS[RELEASE]}"
    echo "Source Release Repo Path: ${ARGS[REPO_PATH]}"
    echo ""
    echo "Summary of Changes:"
    for i in "${SUMMARY[@]}"; do echo "${i}"; done
    echo ""
    echo "Steps:"
    echo "1. Apply updates in the local directory with the changes detected in the core files"
    echo "   where appropriate."
    echo "2. Apply updates to the theme with changes detected in the core bootstrap 3 theme"
    echo "   where appropriate."
    echo "3. The prior output will tell you if database changes were detected. If they were,"
    echo "   you will want to ensure you have a backup of the database before performing the"
    echo "   upgrade."
    echo "4. The prior output will also identify if Solr changes were detected. Depending on the"
    echo "   change (you can inspect the changes yourself or just always assume to be safe), you"
    echo "   will need to re-index / re-import your data into Solr in order to get the updated index."
    echo "3. Reference https://msu-libraries.github.io/catalog/upgrading/ for further"
    echo "   documentation on how to perform an upgrade."
    echo ""
}

compare_db() {
    verbose "Comparing database changes made in release ${ARGS[RELEASE]}"

    if ! git -C "${ARGS[REPO_PATH]}" diff ${ARGS[CURRENT_RELEASE]}:module/VuFind/sql/mysql.sql --exit-code "${ARGS[RELEASE]}":module/VuFind/sql/mysql.sql; then
        SUMMARY+=("Database changes detected in ${ARGS[RELEASE]}:module/VuFind/sql/mysql.sql. Will be applied during build and deploy jobs in pipeline.")
    fi
}

compare_solr() {
    verbose "Comparing Solr changes made in release ${ARGS[RELEASE]}"

    if ! git -C "${ARGS[REPO_PATH]}" diff --quiet ${ARGS[CURRENT_RELEASE]}:solr/ "${ARGS[RELEASE]}":solr/; then
        SUMMARY+=("Solr changes detected in ${ARGS[RELEASE]}:solr/. Full re-import detected to apply new index")
    fi
}

compare_theme(){
    verbose "Checking for changes to bootstrap3 theme in ${ARGS[RELEASE]}"

    while read -r file; do
        # See if we overrode that file in our theme and will need to apply the updates to it
        if [ -f themes/msul/"$file" ]; then
            SUMMARY+=("${file} --> ${ARGS[RELEASE]}:themes/bootstrap3/$file")
            # Don't print the diff for any compiled files since those are not helpful at all
            if [[ ! "$file" =~ "compiled" ]]; then
                git -C "${ARGS[REPO_PATH]}" diff ${ARGS[CURRENT_RELEASE]}:themes/bootstrap3/$file "${ARGS[RELEASE]}":themes/bootstrap3/$file
            fi
        fi
    done < <(git -C "${ARGS[REPO_PATH]}" diff --name-only ${ARGS[CURRENT_RELEASE]}:themes/bootstrap3 "${ARGS[RELEASE]}":themes/bootstrap3)
}

compare_diffs(){
    verbose "Looking for equivilent files in changes"

    while read -r file; do
        SUMMARY+=("${file} May have had it's core file updated this release")
    done < <(git -C ../vufind diff --name-only ${ARGS[CURRENT_RELEASE]} ${ARGS[RELEASE]} | xargs -n1 basename | xargs -n1 find . -name)
}

compare_local() {
    verbose "Comparing changes made in local directory: /${1#/} to release ${ARGS[RELEASE]}"

    for current in local/"${1}"/*
    do
        orig="${current}"
        current="${current/_local/}"
        local coreEquivalent=${ARGS[REPO_PATH]}/${current#local/}
        verbose "Comparing ${current/\/\//\/} to ${coreEquivalent/\/\//\/}"
        if [ -d "$current" ]
        then
          compare_local "${current#local/}"
        else
          if [ -f "$coreEquivalent" ]
          then
            local repoPath="${current#local/}"
            repoPath=${repoPath:1}
            SUMMARY+=("${orig/\/\//\/} -->  ${ARGS[RELEASE]}:${repoPath}")
            git -C "${ARGS[REPO_PATH]}" diff ${ARGS[CURRENT_RELEASE]}:"$repoPath" "${ARGS[RELEASE]}":"$repoPath"
          fi
        fi
    done
}

# Parse and start running
default_args
parse_args "$@"
required_flags
main
