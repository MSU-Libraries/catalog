#!/bin/bash

mkdir -p /mnt/shared/harvest_folio/
mv /usr/local/vufind/local/harvest/folio/ /tmp/
ln -s /mnt/shared/harvest_folio/ /usr/local/vufind/local/harvest/folio/

cron -f -L 4

