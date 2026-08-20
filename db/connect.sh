#!/bin/bash

PASS=$(find /run/secrets -name "*MARIADB_ROOT_PASSWORD" -exec cat {} +)
mariadb --skip-ssl -u root -p"${PASS}"
