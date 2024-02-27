# Helper Scripts
As part of our [infrastructure repository](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure)
(soon to be open source), we have a set of helper scripts to help with common tasks that have long or
hard to remember commands.

The best place to get more detailed information on running these is from the `--help` flag
on each of these, but this will serve as a quick reference to know which scripts are
available.

Each script also offers tab completion for ease-of-use.

## Deploy Helper ([pc-deploy](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure/-/blob/main/configure-playbook/roles/deploy-helper-scripts/files/pc-deploy?ref_type=heads))
Deploys stacks for a given environment and docker compose. This is useful because it does
the step of sourcing the `.env` file for the environment directory used and calling
`envsubstr` on the compose file before deploying the stack.

```bash
# Deploy the catalog stack for the catalog-prod environment
pc-deploy catalog-prod catalog

# Do a dry-run of the traefik stack, which is a core-stack
pc-deploy core-stacks traefik -n

# Deploy the solr bootstrap compose file for the devel-test stack
pc-deploy devel-test solr-bootstrap
# or
pc-deploy devel-test docker-compose.solr-bootstrap.yml
```

## OAI File Locator ([pc-locate-oai](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure/-/blob/main/configure-playbook/roles/deploy-helper-scripts/files/pc-locate-oai?ref_type=heads))
Locates the OAI harvest file that contains the given FOLIO instance ID, which can be then used for
importing a specific record into your stack (or re-importing it). Additionally, it has the option to
extract the single record from an OAI file and put it in a temporary file. The script is available on the host
machines as well as within the `catalog`, `cron` and `build` containers in the `catalog` stack.

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
