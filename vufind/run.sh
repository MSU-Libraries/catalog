#!/bin/bash

# Start local Solr
/usr/local/vufind/solr.sh -force start

# Start Apache
apachectl -DFOREGROUND
