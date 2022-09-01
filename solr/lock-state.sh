#!/bin/bash

DEFAULT_LOCKFILE=/mnt/shared/alpha-browse/rebuild_lock
LOCKFILE_TIMEOUT_HOURS=12

# Script help text
runhelp() {
    echo ""
    echo "Usage: Get/set the lock file state."
    echo ""
    echo "  Will attempt to set the lock file value, or wait until lock file is available."
    echo ""
    echo "FLAGS:"
    echo "  -l|--lockfile LOCKFILE"
    echo "      The lockfile to use. Default: ${DEFAULT_LOCKFILE}"
    echo "  -w|--wait SECONDS"
    echo "      A maximum time in seconds to wait for the lockfile to become available."
    echo "      Will exit with code 1 if lock could not be acquired. Default will wait forever."
    echo "  -g|--get"
    echo "      Get the current state of the lockfile. Prints to stdout."
    echo "  -u|--unset"
    echo "      Unset the lock; if copying locked, decreases semaphore."
    echo "  -b|--building"
    echo "      Set building lock; prevents all other locks."
    echo "  -c|--copying"
    echo "      Set copying lock; increase copying semaphore if already copying locked."
    echo "  -v|--verbose"
    echo "      Show verbose output"
    echo ""
}

if [[ $1 == "-h" || $1 == "--help" || $1 == "help" || -z "$1" ]]; then
    runhelp
    exit 0
fi

# Set defaults
default_args() {
    declare -g -A ARGS
    ARGS[LOCKFILE]=${DEFAULT_LOCKFILE}
    ARGS[WAIT]=
    ARGS[GET]=0
    ARGS[TARGET_STATE]=
    ARGS[VERBOSE]=0
}

# Parse command arguments
parse_args() {
    # Parse flag arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
        -l|--lockfile)
            ARGS[LOCKFILE]="$2"
            shift; shift;;
        -w|--wait)
            ARGS[WAIT]="$2"
            if [[ ! "${ARGS[WAIT]}" -ge 0 ]]; then
                echo "ERROR: --wait must be an integer >= 0"
                exit 1
            fi
            shift; shift ;;
        -g|--get)
            ARGS[GET]=1
            shift ;;
        -u|--unset)
            ARGS[TARGET_STATE]="unset"
            shift ;;
        -b|--building)
            ARGS[TARGET_STATE]="b"
            shift ;;
        -c|--copying)
            ARGS[TARGET_STATE]="1"
            shift ;;
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
        1>&2 echo "${MSG}"
    fi
}

main() {
    if [[ -n "${ARGS[WAIT]}" ]]; then
        FLOCK_WAIT="-w${ARGS[WAIT]}"
        verbose "Setting flock wait: ${FLOCK_WAIT}"
    fi

    # If lockfile is not empty, yet exceeds timeout hours since last modified, assume lock timeout and allow fresh lock
    flock $FLOCK_WAIT "${ARGS[LOCKFILE]}" bash <<- SCRIPT
    STATE=\$(< /tmp/my.lock)
    if [[ -n "\$STATE" && -n \$( find "${ARGS[LOCKFILE]}" -mmin +\$(( ${LOCKFILE_TIMEOUT_HOURS} * 60 )) ) ]]; then
        echo -n > ${ARGS[LOCKFILE]}
        false
    fi
	SCRIPT
    if [[ $? -ne 0 ]]; then
        verbose "Lockfile state over ${LOCKFILE_TIMEOUT_HOURS} hours old. Assuming expired and resetting state."
    fi

    if [[ "${ARGS[TARGET_STATE]}" == "unset" ]]; then
        verbose "Acquiring lock to unset state..."
        # Decrement copy count if > 1, else clear lockfile contents
        flock $FLOCK_WAIT "${ARGS[LOCKFILE]}" bash <<- SCRIPT
        STATE=\$(< /tmp/my.lock)
        if [[ "\$STATE" =~ ^[0-9]+$ && "\$STATE" -gt 1 ]]; then
            echo -n \$(( STATE - 1 )) > ${ARGS[LOCKFILE]}
        else
            echo -n > ${ARGS[LOCKFILE]}
        fi
		SCRIPT
        if [[ $? -ne 0 ]]; then exit 1; fi
        verbose "Done; lock released."
    elif [[ -n "${ARGS[TARGET_STATE]}" ]]; then
        verbose "Acquiring lock to set new state..."
        # Set lockfile value, increment if copy lock, else return false
        flock $FLOCK_WAIT "${ARGS[LOCKFILE]}" bash <<- SCRIPT
        STATE=\$(< /tmp/my.lock)
        if [[ -z "\$STATE" ]]; then
            echo -n ${ARGS[TARGET_STATE]} > ${ARGS[LOCKFILE]}
        elif [[ "\$STATE" =~ ^[0-9]+$ && "${ARGS[TARGET_STATE]}" == "1" ]]; then
            echo -n \$(( STATE + 1 )) > ${ARGS[LOCKFILE]}
        else
            false
        fi
		SCRIPT
        if [[ $? -ne 0 ]]; then exit 1; fi
        verbose "Done; lock released."
    fi

    if [[ "${ARGS[GET]}" -eq 1 ]]; then
        verbose "Acquiring lock to get current state..."
        flock $FLOCK_WAIT "${ARGS[LOCKFILE]}" cat "${ARGS[LOCKFILE]}" || exit 1
        verbose "Done; lock released."
    fi
}

# Parse and start running
default_args
parse_args "$@"
main
