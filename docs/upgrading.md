# Upgrading

## Vufind
For general documentation on how to upgrade Vufind, see the
[official documentation](https://vufind.org/wiki/installation:migration_notes#vufind_migration_notes).
This documentation will focus on how to upgrade specific to this environment setup.

* Clone the [vufind repository](https://github.com/vufind-org/vufind) locally as well as
[this repository](https://github.com/MSU-Libraries/catalog) and check out the Vufind repository
to the release tag that your environment is currently on.

* Run the [upgrade-helper.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/upgrade-helper.sh)
script to determine what config files in the local directory need to be updated with changes made to
core files and apply them manually to [the catalog repository](https://github.com/MSU-Libraries/catalog)
in the `/vufind/local` directory. An example of running the command might be:
`./upgrade-helper.sh -r v8.1 -p /tmp/vufind/`.

* Update the [CI/CD Config](https://github.com/MSU-Libraries/catalog/blob/main/.gitlab-ci.yml)
to update the `VUFIND_VERSION` variable to be the new release you are updating to.

  * If updating SimpleSAMLphp, also update `SIMPLESAMLPHP_VERSION` in the same file.

* Create a new branch with these changes named either `review-`* or `devel-`* to trigger a pipeline with
a new environment.

* Once the pipeline completes successfully, verify that the site loads and works correctly.

* In order to test that a database migration will work correctly, take a fresh database dump of an
environment at the older version and load it into the new release environment. Now connect to the `catalog`
container and modify the `config.ini` file to set the `autoConfigure` value to `true` temporarily.
This will enable the [URL]/Update/Home url to be accessible to run the database migration manually.
It will likely prompt for the database credentials, which can be found in the
[docker-compose.mariadb-cloud.yml](https://github.com/MSU-Libraries/catalog/blob/main/docker-compose.mariadb-cloud.yml)
file within the environment variables. **Remember to disable the `autoConfigure` once complete**.
Then ensure that everything still works post-upgrade and that data is preserved.

* Once thurough testing is complete, take a backup of the database on `main`, merge the branch into `main`,
then repeat the database migration steps once the pipeline completes.

* It is recommended to do a reindex of Solr to apply the latest schema changes, if the helper script
detected any. In order to do this, you will need to run the
[harvest-and-import script](https://github.com/MSU-Libraries/catalog/blob/main/vufind/harvest-and-import.sh)
copying back the last full harvest and doing only an import: `./harvest-and-import.sh -c -b`.


## Solr
If it is just the Solr version that is being upgraded, then updates to the Docker image will handle
the update. But if the schema or solr config are being updated you'll likely need to follow these
steps. Potentially all you may need is to
[upload the new configs](https://msu-libraries.github.io/catalog/solr/#updating-the-solr-configuration-files)
, but there is the chance then when you attempt to index new items into Solr it will not want to overwrite
the existing index. In that case you will need to need to start with an empty index. You can either clear out
your current index (using the `--reset-solr` flag in the `harvest-and-import.sh` script), but this will result
in downtime for your site where there will be no results returned for
a period of time, or follow the below steps to build a temporary alternate collection to index in and then
swap over to once it has completed. All of these commands should be run from within the Solr containers of
the stack you are wanting to upgrade the Solr schema/config on.

* Get the latest config files from GitHub for the version tag you are upgrading to and `wget` them on
the solr container to a temp directory. For example:
```
mkdir /tmp/biblio9
cd /tmp/biblio9
cp -r /solr_confs/biblio/conf/* /tmp/biblio9/
rm /tmp/biblio9/s*.xml
wget https://raw.githubusercontent.com/vufind-org/vufind/v9.0.2/solr/vufind/biblio/conf/solrconfig.xml
wget https://raw.githubusercontent.com/vufind-org/vufind/v9.0.2/solr/vufind/biblio/conf/schema.xml
```

* Update the location the index data will save to in the `solrconfig.xml`:
```
<dataDir>${solr.solr.home:./solr}/biblio</dataDir>
```


* Upload the config to the Zookeepers:
```
solr zk upconfig -confname biblio9 -confdir /tmp/biblio9 -z $SOLR_ZK_HOSTS/solr
```

* Create the new collection:
```
curl "http://solr:8983/solr/admin/collections?action=CREATE&name=biblio9&numShards=1&replicationFactor=3&wt=xml&collection.configName=biblio9"
```

* Update the location in the local `import.properties` as well on the `catalog_cron` container, modifying
the `local/import/import.properties` file replacing the `biblio` collection references to the 
new collection (i.e. `biblio9` for example). The references should be in the `solr.core.name` and the `solr.hosturl`.

* Index data into the new collection from the cron container where you modified the `import.properties` file in the
previous step. Be sure to prepare the data you wish to import as descirbed in the
[full import documentaion](https://msu-libraries.github.io/catalog/harvesting-and-importing/#full-data-imports).
```
/harvest-and-import.sh --verbose --collection biblio9 --batch-import
/hlm-harvest-and-import.sh -i
```

* Then verfy the index in Solr and in VuFind by manually updating
the `config.ini` to point to the new collection name temporarily. Once satisfied with the output, move
on to the next step.

* Create a new alias with the name `biblio` pointing to the new collection which will direct all
queries to the new collection instead of the original one
```
curl "http://solr:8983/solr/admin/collections?action=CREATEALIAS&name=biblio&collections=biblio9"
```

* Once you are confident in the new index, remove the original index
```
curl "http://solr:8983/solr/admin/collections?action=DELETE&name=biblio"
```
