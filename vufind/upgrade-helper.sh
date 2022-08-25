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
    echo "   /upgrade-helper.sh --release v8.1 --vufind-repo-path /some/path"
    echo "     Updates the local directories to have new data from the v8.1 release tag"
    echo "     and will use the already cloned Vufind repository at /some/path"
    echo ""
    echo "FLAGS:"
    echo "  -r|--release RELEASE"
    echo "     (Required) The release to use for upgrading the local files to"
    echo "  -p|--repo-path PATH"
    echo "     Path to the already cloned Vufind repository. Default: ${VUFIND_HOME}"
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
}

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
        -r|--release)
            ARGS[RELEASE]="$2"
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
    REQUIRED=( RELEASE )
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
    compare_local
    compare_db

    echo "--- Summary of Changes ---"
    echo "Files changes in release ${ARGS[RELEASE]}"
    for i in "${SUMMARY[@]}"; do echo "${i}"; done
    echo "--- Done ---"
}

compare_db() {
    verbose "Comparing database changes made in release ${ARGS[RELEASE]}"

    if ! git -C "${ARGS[REPO_PATH]}" diff HEAD:module/VuFind/sql/mysql.sql "${ARGS[RELEASE]}":module/VuFind/sql/mysql.sql; then
        SUMMARY+=("catalog/db/entrypoint/setup-database.sql -->  ${ARGS[RELEASE]}:module/VuFind/sql/mysql.sql")
    fi

    if git -C "${ARGS[REPO_PATH]}" show "${ARGS[RELEASE]}":module/VuFind/sql/migrations/pgsql/${ARGS[RELEASE]#v} 2> /dev/null; then
        SUMMARY+=("Database changes found in: ${ARGS[RELEASE]}:module/VuFind/sql/migrations/pgsql/${ARGS[RELEASE]#v}")
    fi
}

compare_solr() {
    verbose "Comparing Solr changes made in release ${ARGS[RELEASE]}"
    
    if git -C "${ARGS[REPO_PATH]}" diff HEAD:solr/ "${ARGS[RELEASE]}":solr/ 2> /dev/null; then
        SUMMARY+=("Solr changes detected in solr/. Full re-import detected to apply new index")
    fi
}
compare_local() {
    verbose "Comparing changes made in ${ARGS[REPO_PATH]}/local/$1 in release ${ARGS[RELEASE]}"

    for current in "${ARGS[REPO_PATH]}"/local"${1}"/*
    do
        orig="${current}"
        current="${current/.template/}"
        current="${current/_local/}"
        local coreEquivalent=${ARGS[REPO_PATH]}${current#${ARGS[REPO_PATH]}/local}
        verbose "Comparing ${current} to ${coreEquivalent}"
        if [ -d "$current" ]
        then
          verbose "Calling function again for $current"
          compare_local "${current#${ARGS[REPO_PATH]}/local}"
        else
          if [ -f "$coreEquivalent" ]
          then
            local repoPath="${current#${ARGS[REPO_PATH]}/local}"
            repoPath=${repoPath:1}
            SUMMARY+=("${orig} -->  ${ARGS[RELEASE]}:${repoPath}")
            git -C "${ARGS[REPO_PATH]}" diff HEAD:"$repoPath" "${ARGS[RELEASE]}":"$repoPath"
          fi
        fi
    done
}

# Parse and start running
default_args
parse_args "$@"
required_flags
main
