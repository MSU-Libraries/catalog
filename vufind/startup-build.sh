#!/bin/bash

# Map harvest directories onto the shared storage
# FOLIO
mkdir -p /mnt/shared/oai/${STACK_NAME}/harvest_folio/ /mnt/shared/oai/${STACK_NAME}/current/ /mnt/shared/oai/${STACK_NAME}/archives/
mv /usr/local/vufind/local/harvest/folio/ /tmp/
ln -s /mnt/shared/oai/${STACK_NAME}/harvest_folio/ /usr/local/vufind/local/harvest/folio
ln -s /mnt/shared/oai/${STACK_NAME}/ /mnt/oai
# HLM
mkdir -p /mnt/shared/hlm/${STACK_NAME}/current/ /mnt/shared/hlm/${STACK_NAME}/archives/
ln -s /mnt/shared/hlm/${STACK_NAME}/ /mnt/hlm
# AUTHORITY
mkdir -p /mnt/shared/authority/${STACK_NAME}/current/ /mnt/shared/authority/${STACK_NAME}/archives/
ln -s /mnt/shared/authority/${STACK_NAME}/ /mnt/authority

# Save the logs in the logs docker volume
mkdir -p /mnt/logs/vufind /mnt/logs/harvests
ln -sf /mnt/logs/vufind /var/log/vufind
touch /mnt/logs/vufind/vufind.log

# Change to using file sessions
sed -i 's/type\s*=\s*Database/type=File/' /usr/local/vufind/local/config/vufind/config.ini

# Update this container to index to the biblio-build collection alias
if ! OUTPUT=$(sed -i "s/\\bbiblio\\b/biblio-build/" /usr/local/vufind/local/import/import.properties); then
    echo "Failed to change the indexing collection from biblio to biblio-build. Exiting container. ${OUTPUT}"
    exit 1
fi

# Now do nothing until we need it to!
sleep inf
