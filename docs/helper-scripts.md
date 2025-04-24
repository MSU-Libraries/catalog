# Helper Scripts

As part of our [infrastructure repository](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure)
(soon to be open source), we have a set of helper scripts to help with common
tasks that have long or hard to remember commands. The following documentation
serves as a quick reference to know which scripts are available.

Each script has its own `--help` flag to get more detailed information.
Each script also offers tab completion for ease-of-use.

## Deploy Helper ([pc-deploy](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure/-/blob/main/configure-playbook/roles/deploy-helper-scripts/files/pc-deploy?ref_type=heads))

Deploys stacks for a given environment and docker compose. This is useful
because it does the step of sourcing the `.env` file for the environment
directory used and calling `envsubstr` on the compose file before deploying
the stack.

Make sure you run it as the `deploy` user so that the proper Docker
container registry credentials are passed. While running as `root`
would also work since the script will detect that and switch users,
this documentation will list using the `deploy` user to help
indicate that is the user with the proper credentials to the
container registry.

```bash
# Deploy the catalog stack for the catalog-prod environment
sudo -Hu deploy pc-deploy catalog-prod catalog

# Do a dry-run of the traefik stack, which is a core-stack
sudo -Hu deploy pc-deploy core-stacks traefik -n

# Deploy the solr bootstrap compose file for the devel-test stack
sudo -Hu deploy pc-deploy devel-test solr-bootstrap
# or
sudo -Hu deploy pc-deploy devel-test docker-compose.solr-bootstrap.yml
```

## OAI File Locator ([pc-locate-oai](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure/-/blob/main/configure-playbook/roles/deploy-helper-scripts/files/pc-locate-oai?ref_type=heads))

Locates the OAI harvest file that contains the given FOLIO instance ID,
which can be then used for importing a specific record into your stack
(or re-importing it). Additionally, it has the option to extract the single
record from an OAI file and put it in a temporary file. The script is
available on the host machines as well as within the `catalog`, `cron` and
`build` containers in the `catalog` stack.

```bash
# Locate the file that contains data for in01234 in catalog-prod's OAI files
# that have previously been imported to that environment
pc-locate-oai in01234
# or
pc-locate-oai in01234

# Give verbose output to show you the grep command being run
pc-locate-oai in01234 --debug

# Locate the file that contains data for in01234 in catalog-beta's OAI files
pc-locate-oai in01234 catalog-beta

# Locate the files that contain data for in00005342798 and in00001442723
# then extract the data for those specific records into a temp file
pc-locate-oai in00005342798,in00001442723 --extract
```

## Record manipulation ([pc-record](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure/-/blob/main/configure-playbook/roles/deploy-helper-scripts/files/pc-record?ref_type=heads))

Helper to manipulate records.

Currently available:

* **delete:** delete records from provided files and or inline ids. The script
  is available on the host machines as well as within the `catalog`, `cron` and
  `build` containers in the `catalog` stack.

```bash
# delete the record with id hlm.in01234 on catalog-beta
pc-record delete catalog-beta hlm.in01234

# delete records with ids in input file on devel-robby being verbose
pc-record delete devel-robby --input file_containing_ids.txt --debug

# show the command to delete the record with id in01234
# (folio.in01234 with prefix) on catalog-beta being verbose 
pc-record delete catalog-beta in01234 --dry-run --vvv --prefix folio
```

## Connect to container ([pc-connect](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure/-/blob/main/configure-playbook/roles/deploy-helper-scripts/files/pc-connect?ref_type=heads))

Helper to connect to a container for a given service (and optionally, on a
particular node). Also has the option to override the `bash` command with
anything else, or with helpers to run the `mysql` or `zh-shell` commands.

```bash
# Connect to the catalog instance with verbose logging
pc-connect catalog-prod-catalog_cron -v

# Connect to the database on node 3
pc-connect catalog-prod-mariadb_galera 3

# Connect to zk-shell
pc-connect catalog-prod-solr_solr --zk

# Dry-run to locate an instance
pc-connect devel-test-catalog_catalog -n
```

## Run full import ([pc-full-import](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure/-/blob/main/configure-playbook/roles/deploy-helper-scripts/files/pc-full-import?ref_type=heads))

Helper to run a full import of data into an environment using the folio and
hlm data in their `processed` or `current` directories. This should be run
in a `screen` since it will likely run for a day and needs to be run with
`sudo` on the host.

```bash
screen # run in a screen, this isn't required, but highly recommended

# List all of the steps the script will run
sudo pc-full-import catalog-prod --list

# Run a full import with debug output saving to a file
sudo pc-full-import catalog-prod --debug 2>&1 | tee catalog-prod-import_$(date -I).log

# Run only a few steps from script bypassing user confirmation
# (if that step asks for it)
sudo pc-full-import catalog-prod --first-step 3 --last-step 5 --debug --yes

# Do a dry run of the full import to show what steps it would perform
sudo pc-full-import catalog-prod --dry-run
```
