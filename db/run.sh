#!/bin/bash

# shellcheck disable=SC1091

set -o errexit
set -o nounset
set -o pipefail
set -o xtrace # Uncomment this line for debugging purposes

echo "0 MARIADB_GALERA_CLUSTER_ADDRESS: $MARIADB_GALERA_CLUSTER_ADDRESS"

# Load libraries
. /opt/bitnami/scripts/libos.sh
. /opt/bitnami/scripts/libldapclient.sh
. /opt/bitnami/scripts/libmariadbgalera.sh
echo "1 MARIADB_GALERA_CLUSTER_ADDRESS: $MARIADB_GALERA_CLUSTER_ADDRESS"

# Load MariaDB environment variables
. /opt/bitnami/scripts/mariadb-env.sh
echo "2 MARIADB_GALERA_CLUSTER_ADDRESS: $MARIADB_GALERA_CLUSTER_ADDRESS"

# Load LDAP environment variables
eval "$(ldap_env)"
echo "3 MARIADB_GALERA_CLUSTER_ADDRESS: $MARIADB_GALERA_CLUSTER_ADDRESS"

# mysqld_safe does not allow logging to stdout/stderr, so we stick with mysqld
EXEC="${DB_SBIN_DIR}/mysqld"

flags=("--defaults-file=${DB_CONF_DIR}/my.cnf" "--basedir=${DB_BASE_DIR}" "--datadir=${DB_DATA_DIR}" "--socket=${DB_SOCKET_FILE}")
[[ -z "${DB_PID_FILE:-}" ]] || flags+=("--pid-file=${DB_PID_FILE}")

# Add flags specified via the 'DB_EXTRA_FLAGS' environment variable
read -r -a db_extra_flags <<< "$(mysql_extra_flags)"
[[ "${#db_extra_flags[@]}" -gt 0 ]] && flags+=("${db_extra_flags[@]}")

# Add flags passed to this script
flags+=("$@")

# Fix for MDEV-16183 - mysqld_safe already does this, but we are using mysqld
LD_PRELOAD="$(find_jemalloc_lib)${LD_PRELOAD:+ "$LD_PRELOAD"}"
export LD_PRELOAD

echo "4 MARIADB_GALERA_CLUSTER_ADDRESS: $MARIADB_GALERA_CLUSTER_ADDRESS"
is_boolean_yes "$DB_ENABLE_LDAP" && ldap_start_nslcd_bg

info "** Starting MariaDB **"

set_previous_boot
echo "5 MARIADB_GALERA_CLUSTER_ADDRESS: $MARIADB_GALERA_CLUSTER_ADDRESS"
echo "5 DB_GALERA_CLUSTER_ADDRESS: $DB_GALERA_CLUSTER_ADDRESS"

if am_i_root; then
    exec gosu "$DB_DAEMON_USER" "$EXEC" "${flags[@]}"
else
    exec "$EXEC" "${flags[@]}"
fi
