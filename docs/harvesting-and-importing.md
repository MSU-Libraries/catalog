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


## Full Data Harvests
This section describes the steps needed to re-harvest all of the data from each source.

### FOLIO
1. Ensure that your OAI settings on the FOLIO tennant are what you want them to be for this particular
harvest. For example, if you wish to include storage and inventory records (i.e. the records without a MARC source)
then you will need to modify the "Record Source" field in the OAI Settings.

2. Next you will need to clear out the contents of the `harvest_folio` directory before the next cron job will run.
Assuming you want to preserve the last harvest for the time being, you can simply move those directories somewhere
else and rename them. Below is an example, but certainly not the only option. The only goal is that the `harvest_folio`
directory has no files in it, but can have the `log` and `processed` directories within it as long as they are empty
(they technically can have files in them, you just will not want them to have files since they will get mixed in with
your new harvest).
```
cd /mnt/shared/oai/[STACK_NAME]/harvest_folio/
mv processed processed_old
mv log log_old
mv last_state.txt last_state.txt.old
mv harvest.log harvest.log.old
```

3. Monitor progress after it starts via the cron job in the monitoring app or in the log file on the container or volume
(`/mnt/logs/harvests/`).

### HLM
This can just be done with the script's `--full` `--harvest` flags in a one off run, but if you prefer to have
it run via the cron job use it's normal flags, here are the steps you would need to do in order to prepare
the environment.

1. Remove all files from the `/mnt/shared/hlm/[STACK_NAME]/current/` directory and remove all files from the
container's `local/harvest/hlm`. You can also just move them somewhere else if you want to preserve a copy
of them.
```
find /mnt/shared/hlm/[STACK_NAME]/current/ -maxdepth 1 -name '*.marc' -print0 | tar -czf archive_[SOME_DATE].tar.gz --null -T -
find /mnt/shared/hlm/[STACK_NAME]/current/ -maxdepth 1 -name '*.marc' -delete

# exec in to the container and run
find /usr/local/vufind/local/harvest/hlm -mindepth 1 -maxdepth 1 -name '*.marc' -delete
rm /usr/local/vufind/local/harvest/hlm/processed/*
```

2. Monitor progress after it starts via the cron job in the monitoring app or in the log file on the container or volume
(`/mnt/logs/harvests/`).

### Backstage (Authority records)
This can just be done with the script's `--full` `--harvest` flags in a one off run, but if you prefer to have
it run via the cron job use it's normal flags, here are the steps you would need to do in order to prepare
the environment.

1. Remove all files from the `/mnt/shared/authority/[STACK_NAME]/current/` directory and remove all files from the
container's `local/authority/hlm`. You can also just move them somewhere else if you want to preserve a copy
of them.
```
tar -czf archive_[SOME_DATE].tar.gz /mnt/shared/authority/[STACK_NAME]/current/
rm /mnt/shared/authority/[STACK_NAME]/current/processed/*
rm /mnt/shared/authority/[STACK_NAME]/current/*

# exec in to the container and run
rm /usr/local/vufind/local/harvest/authority/*
rm /usr/local/vufind/local/harvest/authority/processed/*
```

2. Monitor progress after it starts via the cron job in the monitoring app or in the log file on the container or volume
(`/mnt/logs/harvests/`).

## Full Data Imports
This section will describe the process needed to run a full re-import of the data since 
that is frequently required to update the Solr index with new field updates. If other tasks are required (such as full
harvests or incremental) refer to the `--help` flags on the appropriate script.

### `biblio` Index
Full imports for the `biblio` collection can be done
- directly in the `cron` container for prod/beta/preview,
- in the `catalog` container for dev environments,
- using the `biblio` collection alias in the `build` container,
to avoid serving incomplete collections in prod (see
[How to Use the Collection Aliases to Rebuild and Swap](#how-to-use-the-collection-aliases-to-rebuild-and-swap) below).

#### Importing FOLIO records using the cron container
Connect to one of the catalog server nodes and move the following files up a level out of the
processed directory in the shared storage. This will allow them to be picked up by the next cron job
and re-started automatically should the container get stopped due to deployments. Progress can be monitored
by checking then number of files remaining in the directory and the log file in `/mnt/logs/harvests/folio_latest.log`.
```
# Can be done inside or outside the container
mv /mnt/shared/oai/[STACK_NAME]/harvest_folio/processed/* /mnt/shared/oai/[STACK_NAME]/harvest_folio/
```

#### Importing FOLIO records in dev environments
This will import the tests records. In the `catalog` container:
```
./harvest-and-import.sh -c -l 1 -b -v -r -s /mnt/shared/oai/devel-batch
```

#### Importing HLM records using the cron container
Assuming HLM records also need to be updated in the `biblio` index as well, you will need to copy those files from
the shared directory into the container prior to starting the script. Then start a `screen` session and connect to the
container again and run the command to import the files. Note that this will get terminated and not be recoverable if
the container stops due to a deploy like the previous command was. Process can be monitored by seeing the remaining
files in the `/usr/local/harvest/hlm/` directoy and by re-attaching to the `screen` (by using `screen -r`)
to see if the command has completed.
```
# Done inside the container
cp /mnt/shared/hlm/[STACK_NAME]/current/* /usr/local/vufind/local/harvest/hlm/

# You will want to kick off this command in a screen session, since it can take many hours to run
/hlm-harvest-and-import.sh -i
```

#### How to Use the Collection Aliases to Rebuild and Swap
As mentioned in the [Solr documentation](solr.md#collection-structure), `biblio` uses aliases to manage directing
VuFind to the collection in Solr that have the "live" biblio data that should be used for searching: `biblio1` or `biblio2`.
This means we will on occassion need to swap them. This occassion being when we rebuild the index,
such as when we're adding new data fields or doing a VuFind version upgrade (...which typically
add new data fields).

* Identify what collection each alias is pointing to currently (i.e. is `biblio` pointing
to `biblio1` or `biblio2`) and confirm the **other** collection is what `biblio-build` is
pointing to

* Clear out the collection that `biblio-build` is pointing to
```bash
curl 'http://solr1:8983/solr/biblio-build/update' --data '<delete><query>id:*</query></delete>' -H 'Content-type:text/xml; charset=utf-8'
curl 'http://solr1:8983/solr/biblio-build/update' --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
```

* Rebuild you index on `biblio-build` using the `catalog_build` container. This has
everything that the `catalog_cron` containers have access to, but do not run `cron`
jobs since rebuilds do not happen at regular or frequent intervals. In fact, all this container
does is sleep! It is recommended to run these commands in a `screen`.  
!!! warning
    If you run a deploy pipeline while this is running, you will not want to run
    the manual job that deploys the updates to the build container (since not all of the
    import scripts are configured to resume where they left off yet).  
```bash
user@catalog-1$ screen
user@catalog-1$ docker exec -it catalog-prod-catalog_build.12345 bash
root@vufind:/usr/local/vufind# cp /mnt/shared/oai/${STACK_NAME}/harvest_folio/processed/* local/harvest/folio/
root@vufind:/usr/local/vufind# /harvest-and-import.sh --verbose --collection biblio-build --batch-import | tee /mnt/shared/logs/folio_import.log
[Ctrl-a d]
user@catalog-1$ screen
user@catalog-1$ docker exec -it catalog-prod-catalog_build.12345 bash
root@vufind:/usr/local/vufind# cp /mnt/shared/hlm/${STACK_NAME}/current/* local/harvest/hlm/
root@vufind:/usr/local/vufind# /hlm-harvest-and-import.sh --import --verbose | tee /mnt/shared/logs/hlm_import.log
[Ctrl-a d]
```

* Verify the counts are what you expect on the `biblio-build` collection using the following command
```bash
curl 'http://solr:8983/solr/admin/metrics?nodes=solr1:8983_solr,solr2:8983_solr,solr3:8983_solr&prefix=SEARCHER.searcher.numDocs,SEARCHER.searcher.deletedDocs&wt=json'
```

* Once you are confident in the new data, you are ready to do the swap! **BE SURE TO SWAP THE NAME AND COLLECTION IN THE BELOW COMMAND EXAMPLE**  
!!! warning
    Your Solr instance may require more memory than it typically needs to do the collection alias swap.
    Be sure to increase and deploy the stack with additional `SOLR_JAVA_MEM` as required to  ensure no
    downtime during this step.
```bash
# This EXAMPLE sets biblio-build to biblio2, and biblio to biblio1
curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio-build&collections=biblio2'
curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio&collections=biblio1'
```

* If needed, back-date the timestamp on your `last_harvest.txt` re-harvest some of the OAI changes since you started the import

* Clear out the collection that `biblio-build` is pointing to, to avoid having two large indexing stored for a long period of time
(only after you are confident in the new index's data)
```bash
curl 'http://solr1:8983/solr/biblio-build/update' --data '<delete><query>id:*</query></delete>' -H 'Content-type:text/xml; charset=utf-8'
curl 'http://solr1:8983/solr/biblio-build/update' --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
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
cp /mnt/shared/authority/[STACK_NAME]/current/processed/*.xml /usr/local/vufind/local/harvest/authority/

# You will want to kick off this command in a screen session, since it can take many hours to run
/authority-harvest-and-import.sh -i -B
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
