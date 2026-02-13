# Upgrading

## VuFind

For general documentation on how to upgrade VuFind, see the
[official documentation](https://vufind.org/wiki/installation:migration_notes#vufind_migration_notes).
This documentation will focus on how to upgrade specific to
this environment setup.

* Clone the [VuFind repository](https://github.com/vufind-org/vufind)
  locally as well as [this repository](https://github.com/MSU-Libraries/catalog)
  and check out the VuFind repository to the release tag that your environment
  is currently on.

* Run the [upgrade-helper.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/upgrade-helper.sh)
  script to determine what config files in the local directory need to be
  updated with changes made to core files and apply them manually to
  [the catalog repository](https://github.com/MSU-Libraries/catalog)
  in the `/vufind/local` directory. An example of running the command might be:
  `~/catalog/vufind/upgrade-helper.sh --target-release v10.1.1
  --current-release v10.0.1 --core-vf-path ~/vufind --msul-vf-path
  ~/catalog/vufind -v`.

* Update the [CI/CD Config](https://github.com/MSU-Libraries/catalog/blob/main/.gitlab-ci.yml)
  to update the `VUFIND_VERSION` variable to be the new release you
  are updating to.

* If updating SimpleSAMLphp, also update `SIMPLESAMLPHP_VERSION` in the
  same file.

* Create a new branch with these changes named either `review-`\* or `devel-`\*
  to trigger a pipeline with a new environment.

* Make sure the `--data` parameters contains the right field in
  `solr_cache_warmup` in `pc-import-folio`

* Once the pipeline completes successfully, verify that the site loads
  and works correctly.

* In order to test that a database migration will work correctly, take
  a fresh database dump of an environment at the older version and load
  it into the new release environment. Now connect to the catalog
  container on your development environment and run:

<!-- markdownlint-disable MD013 -->
```bash
php public/index.php upgrade/database --interactive -vvv
```
<!-- markdownlint-enable MD013 -->

* Once thorough testing is complete, take a backup of the database on
  `main`, merge the branch into `main`, then repeat the database migration
  steps once the pipeline completes.

* It is recommended to do a re-index of Solr to apply the latest schema
  changes, if the helper script detected any. In order to do this, you will
  need to run the [pc-import-folio script](https://github.com/MSU-Libraries/catalog/blob/main/vufind/scripts/pc-import-folio)
  copying back the last full harvest and doing only an import:

<!-- markdownlint-disable MD013 -->
```bash
sudo screen
pc-full-import catalog-prod --yes --debug 2>&1 | tee /mnt/shared/logs/catalog-prod-import_$(date -I).log
```
<!-- markdownlint-enable MD013 -->

### Verification

This section includes some things that should be checked after doing
a VuFind upgrade.

* Inside the container, execute `run-tests` to make sure all tests and linting
  checks are passing.

* Import course reserves (via the optional CI job) to make sure that searching
  and display continues to work.

* Verify that the "bound with" relationships are still working as expected.
  Here are some sample records from our environment:

```txt
https://catalog.lib.msu.edu/Record/folio.in00000717323
https://catalog.lib.msu.edu/Record/folio.in00000336743
https://catalog.lib.msu.edu/Record/folio.in00000280877
```

* Verify that the status displayed on the record page and search results
page matches what is in the "Get This" button status.

* Check the login

* Check the feedback form submits (you can check result at /mail/ on devel instances)

* Check holdings status (Available, Checked out, ...)

* Check import scripts

* Check placing and canceling a request. Specifically check some "bound with"
  records that have previously had problems:

```txt
https://catalog.lib.msu.edu/Record/folio.in00005032730
```

* Confirm there are no errors/warnings we should address in the VuFind
  or Apache logs

## Solr Config & Schema

If it is just the Solr version that is being upgraded, then updates to
the Docker image will handle the update. But if the schema or solr config
are being updated you may need to follow these steps depending on how
significant the change. For example if the schema version changes you will
need to an empty index, but for just adding or removing a few fields, it is
fine to continue from an existing index.
You can either clear out your current index,
but this will result in downtime for your site where there
will be no results returned for a period of time, or follow the below
steps to index in the alternate collection and then
swap over to once it has completed. Just note that we currently only
have an alternate collection for `biblio` and *not* for `authority` or
`reserves`, so those will need to be cleared and have a short amount of
time when they are empty.

* Manually run the pipeline of with the updated VuFind version and Solr version
  up to the Build stage, canceling all of the jobs in the Deploy stage.

* Manually update the `.env` file in `/home/deploy/${STACK_NAME}` on the first node
  in the cluster setting the `CI_COMMIT_SHORT_SHA` to the commit SHA in the pipeline
  that ran above.

* Deploy the new build container.

<!-- markdownlint-disable MD013 -->
```bash
# This sample is doing it for catprod-prod
sudo -Hu deploy bash -c 'docker stack deploy --with-registry-auth --detach=false -c <(source "/home/deploy/catprod-prod/.env"; envsubst < "/home/deploy/catprod-prod/docker-compose.build.yml") "catprod-prod-catalog"'
```
<!-- markdownlint-enable MD013 -->

* Clear the `biblio-build` collection.

<!-- markdownlint-disable MD013 -->
```bash
pc-connect ${STACK_NAME}-solr_solr
curl 'http://solr1:8983/solr/biblio-build/update' --data '<delete><query>*:*</query></delete>' -H 'Content-type:text/xml; charset=utf-8'
curl 'http://solr1:8983/solr/biblio-build/update' --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
```
<!-- markdownlint-enable MD013 -->

* Update the Solr config files on the `biblio-build` collection.

<!-- markdownlint-disable MD013 -->
```bash
CI_COMMIT_SHORT_SHA=[ENTER THE COMMIT_SHA USED IN THE .ENV FILE]
STACK_NAME=catprod-prod

docker pull registry.gitlab.msu.edu/msu-libraries/catalog/catalog/solr:${CI_COMMIT_SHORT_SHA}
id=$(docker create registry.gitlab.msu.edu/msu-libraries/catalog/catalog/solr:${CI_COMMIT_SHORT_SHA})
docker cp $id:/solr_confs/biblio1/conf/schema.xml /tmp/
docker cp $id:/solr_confs/biblio1/conf/solrconfig.xml /tmp/
docker rm $id

docker cp /tmp/solrconfig.xml $(docker ps -q -f name=${STACK_NAME}-solr_solr):/tmp/
docker cp /tmp/schema.xml $(docker ps -q -f name=${STACK_NAME}-solr_solr):/tmp/
docker exec -it $(docker ps -q -f name=${STACK_NAME}-solr_solr) bash

# LOOK UP THE CORRECT biblio BASED ON THE ALIAS!!!! (Solr Admin -> Cloud -> Tree -> aliases.json)
## EXAMPLE IF biblio-build = biblio2
solr zk cp /tmp/solrconfig.xml zk:/solr/configs/biblio2/solrconfig.xml -z zk1:2181
solr zk cp /tmp/schema.xml zk:/solr/configs/biblio2/schema.xml -z zk1:2181
curl "http://solr1:8983/solr/admin/collections?action=RELOAD&name=biblio2"
```
<!-- markdownlint-enable MD013 -->

* Disable incremental FOLIO harvests, since we don't want to have to worry about
  the last harvest time getting out of sync between the two collections once we
  swap back over.

```bash
mv /mnt/shared/oai/${STACK_NAME}/enabled /mnt/shared/oai/${STACK_NAME}/disabled
```

* Re-index all the FOLIO records into biblio-build.

<!-- markdownlint-disable MD013 -->
```bash
screen
rm -rf /mnt/shared/oai/${STACK_NAME}/harvest_folio_build/*
cp /mnt/shared/oai/${STACK_NAME}/harvest_folio/processed/* /mnt/shared/oai/${STACK_NAME}/harvest_folio_build/
pc-connect ${STACK_NAME}-catalog_build
# Note: In another ssh window, you will need to temporarily re-enable the harvests for this
# command to work. Disable it again as soon as it starts though.
# mv /mnt/shared/oai/${STACK_NAME}/disabled /mnt/shared/oai/${STACK_NAME}/enabled
# mv /mnt/shared/oai/${STACK_NAME}/enabled /mnt/shared/oai/${STACK_NAME}/disabled
pc-import-folio -b -q -v
rm -rf /mnt/shared/oai/${STACK_NAME}/harvest_folio_build/*
```
<!-- markdownlint-enable MD013 -->

* Re-index all the HLM records into biblio-build.

<!-- markdownlint-disable MD013 -->
```bash
screen
rm -rf /mnt/shared/hlm/${STACK_NAME}/harvest_hlm_build/*
cp /mnt/shared/hlm/${STACK_NAME}/harvest_hlm/processed/*.marc /mnt/shared/hlm/${STACK_NAME}/harvest_hlm_build/
cp /mnt/shared/hlm/${STACK_NAME}/harvest_hlm/processed/*.delete /mnt/shared/hlm/${STACK_NAME}/harvest_hlm_build/
pc-connect ${STACK_NAME}-catalog_build
pc-import-hlm -i -v -q
rm -rf /mnt/shared/hlm/${STACK_NAME}/harvest_hlm_build/*
```
<!-- markdownlint-enable MD013 -->

* Rebuild the spellchecking indices.

<!-- markdownlint-disable MD013 -->
```bash
screen
pc-connect ${STACK_NAME}-catalog_build
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

* Swap the Solr collections.

<!-- markdownlint-disable MD013 -->
```bash
pc-connect ${STACK_NAME}-solr_solr
curl -s "http://solr:8983/solr/admin/collections?action=LISTALIASES" | grep biblio
# This EXAMPLE sets biblio-build to biblio2, and biblio to biblio1
# biblio-build => biblio2
# biblio       => biblio1
curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio-build&collections=biblio2'
curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio&collections=biblio1'
# This EXAMPLE sets biblio-build to biblio1, and biblio to biblio2
# biblio-build => biblio1
# biblio       => biblio2
curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio-build&collections=biblio1'
curl 'http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio&collections=biblio2'
```
<!-- markdownlint-enable MD013 -->

* Clear out the `biblio-build` collection again to save disk space.

<!-- markdownlint-disable MD013 -->
```bash
pc-connect ${STACK_NAME}-solr_solr
curl 'http://solr1:8983/solr/biblio-build/update' --data '<delete><query>*:*</query></delete>' -H 'Content-type:text/xml; charset=utf-8'
curl 'http://solr1:8983/solr/biblio-build/update' --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
```
<!-- markdownlint-enable MD013 -->

* Clear out `authority` and `reserves` in Solr.

<!-- markdownlint-disable MD013 -->
```bash
pc-connect ${STACK_NAME}-solr_solr
curl 'http://solr1:8983/solr/biblio-build/update' --data '<delete><query>*:*</query></delete>' -H 'Content-type:text/xml; charset=utf-8'
curl 'http://solr1:8983/solr/biblio-build/update' --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
```
<!-- markdownlint-enable MD013 -->

* Now run all the pipeline steps in Deploy stage of the pipeline.

* Run any DB migrations.

```bash
pc connect ${STACK_NAME}-catalog_catalog
php public/index.php upgrade/database -vvv
```

* Manually run the course reserves import.

```bash
pc-connect ${STACK_NAME}-catalog_cron
vim /etc/cron.d/crontab
# Update the run time for the cron-reserves.sh to right away
# then after that time has passed, revert the change.
# Logs can be viewed in /monitoring
```

* Manually run the authority import.

<!-- markdownlint-disable MD013 -->
```bash
screen
pc-connect ${STACK_NAME}-catalog_cron
cd local/harvest/authority && mv processed/FULL_AUTH_D250808_0* . && pc-import-authority -v -q -i -B && for pattern in EEM2508N EEM2509N EEM2510N EEM2511N EEM2512N EEM2601N; do mv processed/${pattern}_*.[0-9][0-9][0-9].xml . && pc-import-authority -v -q -i -B; done
```
<!-- markdownlint-enable MD013 -->

* Rebuild the alphabetic browse databases (starting from the host that is set to
  build them and not do the copy
  (prod: node 1, beta: node 2, preview: node 3).

<!-- markdownlint-disable MD013 -->
```bash
docker exec -it $(docker ps -q -f name=${STACK_NAME}-solr_cron) /alpha-browse.sh -v -f
```
<!-- markdownlint-enable MD013 -->

* Run the full import script for the `biblio` collection again (which will
  re-enable incremental harvests at the appropriate time). This needs to be
  done again in case there were any database
  migrations that would result in DB record updates to get them in sync again.

<!-- markdownlint-disable MD013 -->
```bash
screen
sudo -s
pc-full-import ${STACK_NAME} --email LIB.DL.pubcat@msu.edu --yes --debug 2>&1 | tee /mnt/shared/logs/${STACK_NAME}-import_$(date -I).log
```
<!-- markdownlint-enable MD013 -->
