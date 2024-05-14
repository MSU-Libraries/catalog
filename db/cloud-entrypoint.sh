#!/bin/bash

# shellcheck disable=SC1091

set -o errexit
set -o nounset
set -o pipefail
# set -o xtrace # Uncomment this line for debugging purposes

secret_env_variable_creation() {
    # Array to store temporary environment variable names
    declare -ga tmp_env_variables

    # Loop through all environment variables
    for var in $(compgen -e); do
        # Check if variable name ends with _FILE
        if [[ ! $var == *_FILE ]]; then
            continue
        fi

        # Get the path stored in the variable
        file_path="${!var}"
        # Check if file exists and is readable
        if [ ! -f "$file_path" ]; then
            echo "Error: File $file_path for variable $var does not exist or is not a regular file." >&2
            continue
        elif [ ! -r "$file_path" ]; then
            echo "Error: File $file_path for variable $var exists but is not readable." >&2
            continue
        fi

        # Extract variable name without _FILE suffix
        new_var_name="${var%_FILE}"
        # Create new environment variable
        export "$new_var_name"="$(<"$file_path")"
        # Add variable name to tmp_env_variables array
        tmp_env_variables+=("$new_var_name")
    done
}

secret_env_variable_deletion() {
    # Unset all variables stored in tmp_env_variables array
    for var_name in "${tmp_env_variables[@]}"; do
        unset "$var_name"
    done
    unset tmp_env_variables
}

secret_env_variable_creation
# Set monitoring db password in sql file
envsubst < /docker-entrypoint-initdb.d/monitoring.sql | sponge /docker-entrypoint-initdb.d/monitoring.sql
secret_env_variable_deletion

# Load libraries
. /opt/bitnami/scripts/libbitnami.sh
. /opt/bitnami/scripts/libmariadbgalera.sh

# Load MariaDB environment variables
. /opt/bitnami/scripts/mariadb-env.sh

print_welcome_page

GALERA_STATE_FILE=/bitnami/mariadb/data/grastate.dat
grastate_stp() {
    GRA=
    if [[ -f "$GALERA_STATE_FILE" ]]; then
        info "Not first boot - grastate already exists"
        if grep -q "safe_to_bootstrap: 1" "$GALERA_STATE_FILE"; then
            GRA=1
        else
            GRA=0
        fi
        info "Previous grastate - safe_to_bootstrap: ${GRA}"
    else
        info "First ever node boot (lacking grastate)."
    fi
    echo "$GRA"
}

if [[ "$1" = "/cloud-startup.sh" ]]; then
    PRE_GRA=$( grastate_stp )
    info "** Starting MariaDB setup **"
    info "MARIADB_GALERA_CLUSTER_BOOTSTRAP=$MARIADB_GALERA_CLUSTER_BOOTSTRAP"
    /opt/bitnami/scripts/mariadb-galera/setup.sh
    info "** MariaDB setup finished! **"
    if [[ -z "${PRE_GRA}" && "$MARIADB_GALERA_CLUSTER_BOOTSTRAP" != "yes" && -f "$GALERA_STATE_FILE" ]]; then
        info "First boot (and without bootstrap); reset grastate safe_to_bootstrap to 0"
        sed -i 's/^safe_to_bootstrap:.*$/safe_to_bootstrap: 0/' "$GALERA_STATE_FILE"
    fi
fi

echo ""
exec "$@"
