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
`./upgrade-helper.sh -r v8.1  -p /tmp/vufind/`

* That same script will also tell you if there are database changes that have been made since the
last release. You will need to apply them to the
[entrypoint SQL](https://github.com/MSU-Libraries/catalog/blob/main/db/entrypoint/setup-database.sql).
Also, you will need to plan to manually run them once the new version of Vufind is deployed to your
environment. TODO: Maybe we should re-write them so they can be re-run and have it be a CI/CD step
that always run on DB deploys to apply the script.

* Update the [vufind Dockerfile](https://github.com/MSU-Libraries/catalog/blob/main/vufind/Dockerfile)
and the [Solr Dockerfile](https://github.com/MSU-Libraries/catalog/blob/main/solr/Dockerfile) to update the
`VUFIND_VERSION` to be the new release you are updating to.

* Create a new branch with these changes named either `review-`* or `devel-`* to trigger a pipeline with
a new environment.

* Once the pipeline completes successfully, verify that the site loads and works correctly.

* In order to test that a database migration will work correctly, take a fresh database dump of an
environment at the older version and load it into the new release environment. Now manually run the
database schema changes that were detected earlier in this process to ensure that the site still works.

* Once thurough testing is complete, take a backup of the database on `main`, merge the branch into `main`,
then repeat the database migration steps once the pipeline completes.

* It is recommended to do a reindex of Solr to apply the latest schema changes. In order to do this, 
you will need to run the [harvest-and-import script](https://github.com/MSU-Libraries/catalog/blob/main/vufind/harvest-and-import.sh)
copying back the last full harvest and doing only an import: `./harvest-and-import.sh -c -b`
