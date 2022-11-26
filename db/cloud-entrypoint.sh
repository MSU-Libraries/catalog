#!/bin/bash

# Setup logs as root to have the needed permissions
# Remove logs redirect to stdout
rm -rf /opt/bitnami/mariadb/logs
# Add symlink to /mnt/logs for monitoring
mkdir -p /mnt/logs/mariadb
chown 1001 /mnt/logs/mariadb
ln -sf /mnt/logs/mariadb /opt/bitnami/mariadb/logs

# Continue as user 1001
su - 1001

# shellcheck disable=SC1091

set -o errexit
set -o nounset
set -o pipefail
# set -o xtrace # Uncomment this line for debugging purposes

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
    if [[ -z "${PRE_GRA}" && "$MARIADB_GALERA_CLUSTER_BOOTSTRAP" != "yes" ]]; then
        info "First boot (and without bootstrap); reset grastate safe_to_bootstrap to 0"
        sed -i 's/^safe_to_bootstrap:.*$/safe_to_bootstrap: 0/' "$GALERA_STATE_FILE"
    fi
fi

echo ""
exec "$@"
