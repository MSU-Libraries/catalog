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
environment at the older version and load it into the new release environment. Now re-run the Upgrade Vufind
job in the pipline and check the site again to ensure that everything still works post-upgrade with your
production database.

* Once thurough testing is complete, take a backup of the database on `main`, merge the branch into `main`,
then repeat the database migration steps once the pipeline completes.

* It is recommended to do a reindex of Solr to apply the latest schema changes, if the helper script
detected any. In order to do this, you will need to run the
[harvest-and-import script](https://github.com/MSU-Libraries/catalog/blob/main/vufind/harvest-and-import.sh)
copying back the last full harvest and doing only an import: `./harvest-and-import.sh -c -b`.
