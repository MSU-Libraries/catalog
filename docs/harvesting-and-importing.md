# Harvesting & Importing

!!! note
    All of these commands should be run within the `catalog` or `cron`
    Docker container

## Script Summary

* [pc-import-folio](https://github.com/MSU-Libraries/catalog/blob/main/vufind/scripts/pc-import-folio):
  Used to harvest and import FOLIO MARC data into the `biblio` collection
  of Solr.
* [pc-import-hlm](https://github.com/MSU-Libraries/catalog/blob/main/vufind/scripts/pc-import-hlm):
  Used to harvest and import EBSCO MARC data into the `biblio` collection
  of Solr from the FTP location given access to by EBSCO. The records
  contain the HLM dataset that is missing from FOLIO's database.
* [pc-import-authority](https://github.com/MSU-Libraries/catalog/blob/main/vufind/scripts/pc-import-authority):
  Used to harvest and import MARC data from Backstage into the `authority`
  collection in Solr from the FTP location provided by Backstage.
* [cron-reserves.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/scripts/cron-reserves.sh):
* [pc-full-import](https://gitlab.msu.edu/msu-libraries/catalog/catalog-infrastructure/-/blob/main/configure-playbook/roles/deploy-helper-scripts/files/pc-full-import?ref_type=heads):
  Wrapper script for the `pc-import-folio` and `pc-import-hlm` scripts to
  perform a full import of data.

## HLM Data

One of our sources is HLM (Holdings Link Management) which is files from
EBSCO's FTP server with electronic resources our library has access to
which are not in FOLIO. To automate the retrieval and import of these
files we have a wrapper script, `pc-import-hlm`.

Since occasionally EBSCO sends us full sets of data, we want to be able
to exclude all previous sets from them, so to do this we have an ignore
files `/mnt/shared/oai/ignore_patterns.txt` with will check to see if the file
name contains any of those substrings and ignore them if they match.

Also, we will only harvest files that match the pattern `*.m*c` and `*.zip`.
`*.m*c` files will be imported with the exception of `-del*.m*c` (case
insensitive) or `.delete` files which will instead be tagged as deletion files.

If you simply want to see what files are on the FTP server currently, you can
run our helper script, `pc-list-hlm-remote`, to list all the files. If you
want to download a specific file, run `pc-get-hlm-remote [NAME OF FILE]`.

## Full Data Harvests

This section describes the steps needed to re-harvest all the data from
each source.

### FOLIO

<!-- markdownlint-disable MD031 -->
1. Ensure that your OAI settings on the FOLIO tenant are what you want
   them to be for this particular harvest. For example, if you wish to
   include storage and inventory records (i.e. the records without a
   MARC source) then you will need to modify the "Record Source" field
   in the OAI Settings in FOLIO.

2. Next you will need to clear out the contents of the `harvest_folio`
   directory before the next cron job will run. Assuming you want to
   preserve the last harvest for the time being, you can simply move
   those directories somewhere else and rename them. Below is an example,
   but certainly not the only option. The only goal is that the
   `harvest_folio` directory has no files in it, but can have the `log`
   and `processed` directories within it as long as they are empty
   (they technically can have files in them, you just will not want
   them to have files since they will get mixed in with your new harvest).
   ```bash
   cd /mnt/shared/oai/[STACK_NAME]/harvest_folio/
   mv processed processed_old
   mv log log_old
   mv last_state.txt last_state.txt.old
   mv harvest.log harvest.log.old
   ```

3. Monitor progress after it starts via the cron job in the monitoring app
   or in the log file on the container or volume (`/mnt/logs/harvests/`).
<!-- markdownlint-enable MD031 -->

### HLM

This can just be done with the script's `--full` `--harvest` flags in a
one-off run, but if you prefer to have it run via the cron job use it's
normal flags, here are the steps you would need to do in order to prepare
the environment.

<!-- markdownlint-disable MD013 MD031 -->
1. Remove all files from the `/mnt/shared/hlm/[STACK_NAME]/harvest_hlm/`
   directory. You can also just move them somewhere else if you want to preserve a copy of them.
   ```bash
   cd /mnt/shared/hlm/[STACK_NAME]/harvest_hlm/
   mv processed processed_old
   mv log log_old
   ```

2. Monitor progress after it starts via the cron job in the monitoring app or
   in the log file on the container or volume (`/mnt/logs/harvests/`).
<!-- markdownlint-enable MD013 MD031 -->

### Backstage (Authority records)

This can just be done with the script's `--full` `--harvest` flags in a
one-off run, but if you prefer to have it run via the cron job use it's normal
flags, here are the steps you would need to do in order to prepare
the environment.

<!-- markdownlint-disable MD031 -->
1. Remove all files from the `/mnt/shared/authority/[STACK_NAME]/current/`
   directory and remove all files from the container's `local/authority/hlm`.
   You can also just move them somewhere else if you want to preserve a copy
   of them.
   ```bash
   tar -czf archive_[SOME_DATE].tar.gz /mnt/shared/authority/[STACK_NAME]/current/
   rm /mnt/shared/authority/[STACK_NAME]/current/processed/*
   rm /mnt/shared/authority/[STACK_NAME]/current/*
   
   # exec in to the container and run
   rm /usr/local/vufind/local/harvest/authority/*
   rm /usr/local/vufind/local/harvest/authority/processed/*
   ```

2. Monitor progress after it starts via the cron job in the monitoring app or
in the log file on the container or volume (`/mnt/logs/harvests/`).
<!-- markdownlint-enable MD031 -->

## Full Data Imports

### `biblio` Index

<!-- markdownlint-disable MD013 MD046 -->
!!! tip "Helper script for full import"

    There is now a helper script to run *all* of the below steps in the proper
    order to do a full re-import of data in the `biblio` index. See the full
    documentation for the [pc-full-import](helper-scripts.md#run-full-import-pc-full-import)
    and the below for a simple example.

    ```bash
    sudo screen
    # Prompting for confirmation
    pc-full-import catalog-prod --debug 2>&1 | tee /mnt/shared/logs/catalog-prod-import_$(date -I).log
    # Bypassing user confirmation
    pc-full-import catalog-prod --yes --debug 2>&1 | tee /mnt/shared/logs/catalog-prod-import_$(date -I).log
    ```

    Should you choose to do the steps manually, this section will describe
    the process needed to run a full re-import of the data since that is
    frequently required to update the Solr index with new field updates.
    If other tasks are required (such as full harvests or incremental) refer
    to the `--help` flags on the appropriate script.
<!-- markdownlint-enable MD013 MD046 -->

Full imports for the `biblio` collection can be done

* directly in the `cron` container for prod/beta/preview,
* in the `catalog` container for dev environments,
* using the `biblio` collection alias in the `build` container,
to avoid serving incomplete collections in prod (see
[How to Use the Collection Aliases to Rebuild and Swap](#how-to-use-the-collection-aliases-to-rebuild-and-swap)
below).

#### Importing FOLIO records using the cron container

Connect to one of the catalog server nodes and move the following files up
a level out of the processed directory in the shared storage. This will allow
them to be picked up by the next cron job and re-started automatically should
the container get stopped due to deployments. Progress can be monitored
by checking then number of files remaining in the directory and the log file
in `/mnt/logs/harvests/folio_latest.log`.

```bash
# Can be done inside or outside the container
mv /mnt/shared/oai/[STACK_NAME]/harvest_folio/processed/* /mnt/shared/oai/[STACK_NAME]/harvest_folio/
```

#### Importing FOLIO records in dev environments

This will import the tests records. In the `catalog` container:

```bash
./pc-import-folio -c /mnt/shared/oai/devel-batch -l 1 -b -v -r
```

#### Importing HLM records using the cron container

Assuming HLM records also need to be updated in the `biblio` index as
well, you will need to copy those files from the shared directory into
the container prior to starting the script. Then start a `screen` session
and connect to the container again and run the command to import the files.
Note that this will get terminated and not be recoverable if the container
stops due to a deploy like the previous command was. Process can be monitored
by seeing the remaining files in the `/usr/local/harvest/hlm/` directory
and by re-attaching to the `screen` (by using `screen -r`) to see if the command
has completed.

```bash
# Done inside the catalog_cron container
cp /mnt/shared/hlm/[STACK_NAME]/current/* /usr/local/vufind/local/harvest/hlm/

# You will want to kick off this command in a screen session,
# since it can take many hours to run
/usr/local/bin/pc-import-hlm -i -v
```

#### How to Use the Collection Aliases to Rebuild and Swap

As mentioned in the [Solr documentation](solr.md#collection-structure), `biblio`
uses aliases to manage directing VuFind to the collection in Solr that have the
"live" biblio data that should be used for searching: `biblio1` or `biblio2`.
This means we will on occasion need to swap them. This occasion being when
we rebuild the index, such as when we're adding new data fields or doing a
VuFind version upgrade (...which typically add new data fields).

* Start the manual task "Deploy VuFind Build Env" in GitLab. It will update
  the `catalog_build` container. This is not done automatically so that other
  updates to the main branch can be deployed while a full import is running.

* Identify what collection each alias is pointing to currently
  (i.e. is `biblio` pointing to `biblio1` or `biblio2`) and confirm
  the **other** collection is what `biblio-build` is pointing to.
  To get the list of aliases, from a container:

```bash
curl -s "http://solr:8983/solr/admin/collections?action=LISTALIASES" | grep biblio
```

* Rebuild the index on `biblio-build` using the `catalog_build` container.
  This has everything that the `catalog_cron` containers have access to, but
  do not run `cron` jobs since rebuilds do not happen at regular or frequent
  intervals. In fact, all this container does is sleep! It is recommended to
  run these commands in a `screen`.

!!! warning
    If you run a deploy pipeline while this is running, you will not want to
    run the manual job that deploys the updates to the build container (since
    not all the import scripts are configured to resume where they left off
    yet).

<!-- markdownlint-disable MD013 -->
```bash
# On Host
screen
docker exec -it $(docker ps -q -f name=catalog-prod-catalog_build) bash

# Inside container
rm local/harvest/folio/processed/*
cp /mnt/shared/oai/${STACK_NAME}/harvest_folio/processed/* local/harvest/folio/
/usr/local/bin/pc-import-folio --verbose --reset-solr --collection biblio-build --batch-import | tee /mnt/shared/logs/folio_import_${STACK_NAME}_$(date -I).log
[Ctrl-a d]

# On Host
screen
docker exec -it $(docker ps -q -f name=catalog-prod-catalog_build) bash

# Inside container
cp /mnt/shared/hlm/${STACK_NAME}/current/* local/harvest/hlm/
/usr/local/bin/pc-import-hlm --import --verbose | tee /mnt/shared/logs/hlm_import_${STACK_NAME}_$(date -I).log
[Ctrl-a d]
```
<!-- markdownlint-enable MD013 -->

* Verify the counts are what you expect on the `biblio-build` collection
  using the following command

<!-- markdownlint-disable MD013 -->
```bash
curl 'http://solr:8983/solr/admin/metrics?nodes=solr1:8983_solr,solr2:8983_solr,solr3:8983_solr&prefix=SEARCHER.searcher.numDocs,SEARCHER.searcher.deletedDocs&wt=json'
```
<!-- markdownlint-enable MD013 -->

* Build the spellchecking indices

Building these indices is only necessary for a full import.

<!-- markdownlint-disable MD013 -->
```bash
curl 'http://solr1:8983/solr/biblio-build/select?q=*:*&spellcheck=true&spellcheck.build=true' &
curl 'http://solr2:8983/solr/biblio-build/select?q=*:*&spellcheck=true&spellcheck.build=true' &
curl 'http://solr3:8983/solr/biblio-build/select?q=*:*&spellcheck.true&spellcheck.build=true' &
wait
curl 'http://solr1:8983/solr/biblio-build/select?q=*:*&spellcheck.dictionary=basicSpell&spellcheck=true&spellcheck.build=true' &
curl 'http://solr2:8983/solr/biblio-build/select?q=*:*&spellcheck.dictionary=basicSpell&spellcheck=true&spellcheck.build=true' &
curl 'http://solr3:8983/solr/biblio-build/select?q=*:*&spellcheck.dictionary=basicSpell&spellcheck=true&spellcheck.build=true' &
wait
```
<!-- markdownlint-enable MD013 -->

`/bitnami/solr/server/solr/biblioN/spellShingle` and
`/bitnami/solr/server/solr/biblioN/spellchecker` should have a significant
size afterward in the solr container (replace `biblioN` by the
`biblio-build` collection)

* Your Solr instance will likely require more memory than it typically needs
  to do the collection alias swap. Be sure to increase and deploy the stack
  with additional `SOLR_JAVA_MEM` as required to  ensure no downtime during
  this step. Currently, 6G (which we use in prod) is enough for the swap.
  Alternatively (for beta and preview), let it crash after these commands
  and restart the pipeline to help Solr cloud fix itself.

```bash
# Open the solr-cloud compose file for your environment
vim docker-compose.solr-cloud.yml

# Modify the memory line to:
SOLR_JAVA_MEM: -Xms8192m -Xmx8192m

# Now on the host, run the deploy helper script
sudo pc-deploy [ENV_NAME] solr-cloud
```

* Once you are confident in the new data, you are ready to do the swap!
  **BE SURE TO SWAP THE NAME AND COLLECTION IN THE BELOW COMMAND EXAMPLE**

<!-- markdownlint-disable MD046 -->
!!! warning
    ```bash
    # Command to check the aliases (repeated from above)
    curl -s "http://solr:8983/solr/admin/collections?action=LISTALIASES" | grep biblio
    ```

    ```bash
    # This EXAMPLE sets biblio-build to biblio2, and biblio to biblio1
    # biblio-build => biblio2
    # biblio       => biblio1
    curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio-build&collections=biblio2'
    curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio&collections=biblio1'
    ```

    ```bash
    # This EXAMPLE sets biblio-build to biblio1, and biblio to biblio2
    # biblio-build => biblio1
    # biblio       => biblio2
    curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio-build&collections=biblio1'
    curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio&collections=biblio2'
    ```
<!-- markdownlint-enable MD046 -->

* If needed, back-date the timestamp on your `last_harvest.txt` re-harvest
  some of the OAI changes since you started the import

* Clear out the collection that `biblio-build` is pointing to, to avoid
  having two large indexing stored for a long period of time (only
  after you are confident in the new index's data)

```bash
/usr/local/bin/pc-import-folio --verbose --reset-solr --collection biblio-build
```

* If `SOLR_JAVA_MEM` was increased, lower it to its previous amount.

* Kick off a manual alpha browse re-index if you don't want it to be outdated
  until the next scheduled run.

```bash
# Run this on all of the host's
docker exec -it \
  $(docker ps -q -f name=${STACK_NAME}-solr_cron) \
  /alpha-browse.sh -v -f
```

### `authority` Index

Similar to the process for HLM records, copy the files from the shared
directory into the containers import location prior to starting the script.
Then start a `screen` session and connect to the container again and run
the command to import the files. Note that this will get terminated and not
be in a recoverable state if the container is stopped due to a deploy.
Process can be monitored by seeing the remaining files in the
`/usr/local/harvest/authority` directory and by re-attaching to the
`screen` (by using `screen -r`) to see if the command has completed.

```bash
# Done inside the container
cp /mnt/shared/authority/[STACK_NAME]/current/processed/*.xml /usr/local/vufind/local/harvest/authority/

# You will want to kick off this command in a screen session,
# since it can take many hours to run
/usr/local/bin/pc-import-authority -i -B
```

### `reserves` Index

The course reserves data is refreshed by a Cron job on a nightly basis,
so likely you will not need to run this manually if you can just wait for the
regular run. But if needed, here is the command to run it off-schedule.

```bash
# Done inside the container (ideally within a screen since it will
# take hours to run)
php /usr/local/vufind/util/index_reserves.php
```

Alternatively, you can also modify the cron entry (or add a temporary
additional cron entry) in the cron container for the `cron-reserves.sh`
command to run at an earlier time. The benefit of this would be it would
save logs to `/mnt/logs/vufind/reserves_latest.log` and track them in the
Monitoring site.

### Adding generated call numbers

This should be done after each full import (FOLIO + HLM) when data was reset,
in the `solr_solr` container:

```bash
python3 ./add_generated_call_numbers.py
```

Partial call numbers are added to `callnumber-label` for records that didn't
have any when the `call_numbers.csv` file was generated.

Note that the call numbers in `/mnt/shared/call-numbers/call_numbers.csv` are
meant for beta/preview/prod. There is another file at
`/mnt/shared/call-numbers/test_call_numbers.csv` that can be used for testing
in dev.

## Ignoring certain HLM files

If your EBSCO FTP server is set up in a way where it contains all of the sets
ever generated for you, then you'll likely want a way to have the `pc-import-hlm`
script ignore the past sets assuming you get new full sets periodically. This can
be done by adding a new substring pattern to ignore to the top level of the `hlm`
directory in the shared storage (`/mnt/shared/hlm/ignore_patterns.txt`). This
file is used automatically and created on the cron containers startup if it
doesn't exist. You can override the file path by using the `-p|--ignore-file`
flag.

## Using VuFind Utilities

The preferred method is to use the included wrapper script with this repository.
The [pc-import-folio](https://github.com/MSU-Libraries/catalog/blob/main/vufind/scripts/pc-import-folio)
script can run either, or both, the harvest and import of data from FOLIO to
VuFind. Use the `--help` flag to get information on how to run that script.

But should you choose to run the commands included directly with VuFind, below
is documentation on how to do that.

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

### Importing into VuFind

```bash
cd /usr/local/vufind/harvest
./batch-import-marc.sh folio
```
