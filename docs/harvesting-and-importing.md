# Harvesting & Importing
!!! note 
    All of these commands should be run within the `catalog` or `cron` Docker container

## Script Summary
* [harvest-and-import.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/harvest-and-import.sh): 
Used to harvest and import FOLIO MARC data into the `biblio` collection of Solr.  
* [hlm-harvest-and-import.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/hlm-harvest-and-import.sh):
Used to harvest and import EBSCO MARC data into the `biblio` collection of Solr from the FTP location given
access to by EBSCO. The records contain the HLM dataset that is missing from FOLIO's database.  
* [authority-harvest-and-import.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/authority-harvest-and-import.sh):
Used to harvest and import MARC data from Backstage into the `authority` collection in Solr from the FTP location
provided by Backstage.  
* [cron-reserves.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/scripts/cron-reserves.sh):
Used to update the refresh the `reserves` index in Solr with data from FOLIO's API, replacing it entirely each run.  


## Full Data Imports
This section will describe the process needed to run a full re-import of the data since 
that is frequently required to update the Solr index with new field updates. If other tasks are required (such as full
harvests or incremental) refer to the `--help` flags on the appropriate script.

### `biblio` Index
1. Connect to one of the catalog server nodes and move the following files up a level out of the
processed directory in the shared storage. This will allow them to be picked up by the next cron job
and re-started automatically should the container get stopped due to deployments. Progress can be monitored
by checking then number of files remaining in the directory and the log file in `/mnt/logs/harvests/folio_latest.log`.
```
# Can be done inside or outside the container
mv /mnt/shared/oai/[STACK_NAME]/harvest_folio/processed/* /mnt/shared/oai/[STACK_NAME]/harvest_folio/
```

2. Assuming HLM records also need to be updated in the `biblio` index as well, you will need to copy those files from
the shared directory into the container prior to starting the script. Then start a `screen` session and connect to the
container again and run the command to import the files. Note that this will get terminated and not be recoverable if
the container stops due to a deploy like the previous command was. Process can be monitored by seeing the remaining
files in the `/usr/local/harvest/hlm/` directoy and by re-attaching to the `screen` (by using `screen -r`)
to see if the command has completed.
```
# Done inside the container
cp /mnt/shared/hlm/[STACK_NAME]/current/* /usr/local/harvest/hlm/

# You will want to kick off this command in a screen session, since it can take many hours to run
/hlm-harvest-and-import.sh -i
```

### `authority` Index
Similar to the process for HLM records, copy the files from the shared directory into the containers import
location prior to starting the script. Then start a `screen` session and connect to the container again and run
the command to import the files. Note that this will get terminated and not be in a recoverable state if the
container is stopped due to a deploy. Process can be monitored by seeing the remaining files in the
`/usr/local/harvest/authority` directoy and by re-attaching to the `screen` (by using `screen -r`) to see if the
command has completed.
```
# Done inside the container
cp /mnt/shared/authority/[STACK_NAME]/current/processed/*.xml /usr/local/harvest/authority/

# You will want to kick off this command in a screen session, since it can take many hours to run
/authority-harvest-and-import.sh -i
```

### `reserves` Index
The course reserves data is refreshed by a Cron job on a nightly basis, so likely you will not need
to run this manually if you can just wait for the regular run. But if needed, here is the command to run it
off-schedule.
```
# Done inside the container (ideally within a screen since it will take hours to run)
php /usr/local/vufind/util/index_reserves.php
```

Alternatively, you can also modify the cron entry (or add a temporary additional cron entry) in the cron
container for the `cron-reserves.sh` command to run at an earlier time. The benefit of this would be it would
save logs to `/mnt/logs/vufind/reserves_latest.log` and track them in the Monitoring site.

## Using VuFind Utilities
The preferred method is to use the included wrapper script with this repository.
The [harvest-and-import.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/harvest-and-import.sh)
script can run either, or both, the harvest and import of data from FOLIO to Vufind. Use the `--help` flag
to get information on how to run that script.

But should you choose to run the commands included directly with Vufind, below is documentation on how to
do that.

### Harvesting from Folio
```bash
cd /usr/local/vufind/harvest
php harvest_oai.php

## This step is optional, it will combine the xml files into a single file
## to improve the speed of the next import step.
find *.xml | xargs xml_grep --wrap collection --cond "marc:record" > combined.xml
mkdir unmerged
mv *oai*.xml unmerged/
```

### Importing into Vufind
```bash
cd /usr/local/vufind/harvest
./batch-import-marc.sh folio
```


