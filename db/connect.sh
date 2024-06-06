#!/bin/bash

PASS=$(find /run/secrets -name "*MARIADB_ROOT_PASSWORD" -exec cat {} +)
mysql -u root -p"${PASS}"
