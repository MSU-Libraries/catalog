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
