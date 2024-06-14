# First Time Setup

## Building the VuFind image
Before bringing up the application stack, you need to build the custom
VuFind images. There are helper scripts in this repository to build one
for each service, such as VuFind and Solr.

The [CICD](CICD.md) will do this for you automatically, but if you want to
do it manually you can still use the scripts.

The more complex one, VuFind, has its
[own script](https://github.com/MSU-Libraries/catalog/blob/main/cicd/build-vufind)
which includes the documentation on which environment variables it expects
to be defined when calling the script. For example:

```
# Build vufind:new using vufind:latest's build cache with the 9.0.1 VuFind version
# NOTE: this is missing many other required environment variables to be a running VuFind
# environment and is just meant to be an example to get you started!
LATEST=vufind:latest CURR=vufind:new VUFIND_VERSION=9.0.1 cicd/build-vufind
```

For the rest of the components, they share a
[single script](https://github.com/MSU-Libraries/catalog/blob/main/cicd/build-general)
to build their images and can be run like this:

```
# Build the Solr image as vufind:new using solr:latst's build cache using data from
# VuFind 9.0.1 (in this case the Solr schema and solrconfig)
LATEST=solr:latest CURR=solr:new COMPONENT=solr VUFIND_VERSION=9.0.1 cicd/build-general

# Now do the rest!
LATEST=zk:latest CURR=zk:new COMPONENT=zk VUFIND_VERSION=9.0.1 cicd/build-general
LATEST=db:latest CURR=db:new COMPONENT=db VUFIND_VERSION=9.0.1 cicd/build-general
LATEST=ansible:latest CURR=ansible:new COMPONENT=ansible VUFIND_VERSION=9.0.1 cicd/build-general
LATEST=monitoring:latest CURR=monitoring:new COMPONENT=monitoring VUFIND_VERSION=9.0.1 cicd/build-general
LATEST=legacylinks:latest CURR=legacylinks:new COMPONENT=legacylinks VUFIND_VERSION=9.0.1 cicd/build-general
```

## To start the application stack
During the first time you are bringing up the stack, you will need
to run these first to bootstrap Solr and MariaDB:
```bash
docker stack deploy -c <(source .env; envsubst <docker-compose.solr-cloud-bootstrap.yml) solr
docker stack deploy -c <(source .env; envsubst <docker-compose.mariadb-cloud-bootstrap.yml) mariadb
```

Subsequently, you will run these commands (during the initial deploy
and whenever you need to deploy updates):
```bash
# Public network
docker stack deploy -c <(source .env; envsubst <docker-compose.public.yml) public

# Traefik stack to handle networking
docker stack deploy -c <(source .env; envsubst <docker-compose.traefik.yml) traefik

# Internal network for galera cluster
docker stack deploy -c <(source .env; envsubst <docker-compose.internal.yml) internal

# The rest of the MariaDB galera cluster
docker stack deploy -c <(source .env; envsubst <docker-compose.mariadb-cloud.yml) mariadb

# The rest of the Solr cloud stack
docker stack deploy -c <(source .env; envsubst <docker-compose.solr-cloud.yml) solr

# The VuFind stack
docker stack deploy -c <(source .env; envsubst <docker-compose.catalog.yml) catalog

# Deploy the swarm cleanup stack
docker stack deploy -c <(source .env; envsubst <docker-compose.swarm-cleanup.yml) swarm-cleanup

# Deploy the monitoring stack
docker stack deploy -c <(source .env; envsubst <docker-compose.monitoring.yml) monitoring
```

## Creating a FOLIO user
In order for VuFind to connect to FOLIO to make API calls, it
requires a generic user to be created, called `vufind`.

The users credentials are provided as build arguments to the VuFind image:
`FOLIO_USER` and `FOLIO_PASS`.

The `vufind` application user (set in `local/confing/vufind/folio.ini`) requires the
following permissions within FOLIO. They need to be created as a permission set with the FOLIO API,
with a `POST` request to `/perms/permissions`.

* `inventory.instances.item.get`
* `inventory-storage.bound-with-parts.collection.get`
* `inventory-storage.holdings.collection.get`
* `inventory-storage.holdings.item.get`
* `inventory-storage.instances.collection.get`
* `inventory-storage.items.collection.get`
* `inventory-storage.items.item.get`
* `inventory-storage.locations.collection.get`
* `inventory-storage.locations.item.get`
* `inventory-storage.loan-types.collection.get` (For MSUL customization to display loan type)
* `inventory-storage.service-points.collection.get`
* `circulation.loans.collection.get`
* `circulation.requests.item.post`
* `circulation.requests.item.get`
* `circulation.requests.item.put`
* `circulation.renew-by-id.post`
* `circulation-storage.requests.collection.get`
* `users.collection.get`
* `accounts.collection.get`
* `course-reserves-storage.courselistings.collection.get`
* `course-reserves-storage.courselistings.courses.collection.get`
* `course-reserves-storage.courselistings.instructors.collection.get`
* `course-reserves-storage.courses.collection.get`
* `course-reserves-storage.departments.collection.get`
* `course-reserves-storage.reserves.collection.get`
* `oai-pmh.records.collection.get`
* `kb-ebsco.packages.collection.get` (For MSUL customization to add license agreement information to record pages)
* `proxiesfor.collection.get`

## For GitLab users
### Creating a CI/CD Token
Create a new [access token](https://gitlab.msu.edu/help/user/project/settings/project_access_tokens)
that has `read_registry` privileges to the repository and create a new CI/CD variable with the
resulting key value (`REGISTRY_ACCESS_TOKEN`).

### Create CI/CD Variables
There are a number of variables that are required for the CI/CD pipeline to run. Refer to the
[CI/CD variables section](CICD.md#variables) for details.

## For local development
To make changes to the theme, custom module, or files stored within `local` you can either
modify them directly inside the containers, or you can mount the shared storage and make
changes there. Changes to the live storage are symbolically linked to the containers and will
appear real time in the environment -- very handy for theme development!
This is only available for development environment starting with devel-*.

Within the shared storage there will be a subdirectory for each branch name. This documentation assumes that the share has been set up and configured already on the hosts. The subdirectory
will contain a clone of this repository which can be easily used to track changes between
subsequent deploys to the same branch.

Note that subsequent deploys only do a `git fetch` to avoid overwriting local changes. You are
responsible for doing a `git pull` to apply new changes.
