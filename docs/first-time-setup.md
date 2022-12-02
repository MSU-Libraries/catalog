# First Time Setup

## Building the Vufind image
Before bringing up the application stack, you need to build the custom
vufind image. Below is a sample command, and further down will describe
the build arguments in more detail.

```bash
docker build ./vufind/ -t catalog:latest \
    --build-arg VUFIND_VERSION=8.1.0
    --build-arg FOLIO_URL=https://okapi-url.example.edu \
    --build-arg FOLIO_USER=vufind \
    --build-arg FOLIO_PASS=vufind_pass \
    --build-arg FOLIO_TENANT=1234567890 \
    --build-arg FOLIO_REC_ID=1 \ # This is the default in vufind when checking ILS status
    --build-arg FOLIO_CANCEL_ID=75187e8d-e25a-47a7-89ad-23ba612338de \ # This is the default in vufind
    --build-arg OAI_URL=https://okapi-url.example.edu/oai/records \
    --build-arg EMAIL=some_email@example.edu \
    --build-arg MAIL_HOST=localhost \
    --build-arg MAIL_PORT=25 \
    --build-arg MAIL_USERNAME= \
    --build-arg MAIL_PASSWORD= \
    --build-arg SOLR_URL=http://solr:8983/solr \
    --build-arg EDS_USER=eds \
    --build-arg EDS_PASS=123 \
    --build-arg EDS_PROFILE=profile.edu.id \
    --build-arg EDS_ORG=eds.org \
    --build-arg FEEDBACK_EMAIL=another_email@example.edu \
    --build-arg RECAPTCHA_SITE_KEY=mysitekey \
    --build-arg RECAPTCHA_SECRET_KEY=mysecretkey \
    --build-arg SIMPLESAMLPHP_VERSION=1.19.6
    --build-arg SIMPLESAMLPHP_SALT=abcXYZ
    --build-arg SIMPLESAMLPHP_ADMIN_PW=mySecretPass
    --build-arg DEPLOY_KEY=readonlygitlabdeploykey
```

* `VUFIND_VERSION`: The version of VuFind to install 
* `FOLIO_URL`: The URL of the OKAPI endpoint for your FOLIO instance
* `FOLIO_USER`: The username in FOLIO that vufind can use to connect with
* `FOLIO_PASS`: The password for the provided `FOLIO_USER`
* `FOLIO_TENANT`: The tenant ID for FOLIO. Can be found in the 'Software versions' settings page on FOLIO
* `FOLIO_REC_ID`: The FOLIO ID to check for when validating the the service is available. Vufind
uses `1` by default
* `FOLIO_CANCEL_ID`: The FOLIO cancelation ID to use when canceling an order. Vufind uses
`75187e8d-e25a-47a7-89ad-23ba612338de` by default
* `OAI_URL`: The URL of the OAI-PMH endpoint for FOLIO
* `EMAIL`: Email address to use in vufind that users will be shown when errors occur
* `MAIL_HOST`: Host to use for sending email. The recommended default is `localhost`
* `MAIL_PORT`: Port to use for email. Typically `25` is standard
* `MAIL_USERNAME`: Username to use when authenticating to the `MAIL_HOST` if not `localhost`
* `MAIL_PASSWORD`: Password for the `MAIL_USERNAME`
* `FEEDBACK_EMAIL`: Email address to send feedback form response to
* `SOLR_URL`: The URL that Vufind's Solr instance is accessible on. This setup will use `solr`
* `EDS_USER`: Username for the EBSCO Discovery Service API
* `EDS_PASS`: Password for the `EDS_USER` account
* `EDS_PROFILE`: Profile ID for the EDS API
* `EDS_ORG`: Organization ID to use with the EDS API
* `RECAPTCHA_SITE_KEY`: Site key for reCaptcha form validation
* `RECAPTCHA_SECRET_KEY`: Secret key for reCaptcha form validation
* `SIMPLESAMLPHP_VERSION`: The version of SimpleSAMLphp to install
* `SIMPLESAMLPHP_SALT`: Random salt for SimpleSAMLphp
* `SIMPLESAMLPHP_ADMIN_PW`: Password to the admin interface of SimpleSAMLphp
* `DEPLOY_KEY`: GitLab read-only deploy key base64 encoded

## To start the application stack
During the first time you are bring up the stack, you will need
to run these first to bootstrap Solr and MariaDB:
```bash
docker stack deploy -c docker-compose.solr-cloud-bootstrap.yml solr
docker stack deploy -c docker-compose.mariadb-cloud-bootstrap.yml mariadb
```

Subsequently you will run these commands (during the inital deploy
and whenever you need to deploy updates):
```bash
# Traefik stack to handle networking
docker stack deploy -c docker-compose.traefik.yml traefik

# Internal network for galera cluster
docker stack deploy -c docker-compose.internal.yml internal

# The rest of the MariaDB galera cluster
docker stack deploy -c docker-compose.mariadb-cloud.yml mariadb

# The rest of the Solr cloud stack
docker stack deploy -c docker-compose.solr-cloud.yml solr

# The vufind stack
docker stack deploy -c docker-compose.yml catalog

# Deploy the swarm cron stack
docker stack deploy -c docker-comoose.swarm-cron.yml swarm-cron
```

## Creating a FOLIO user
In order for Vufind to connect to FOLIO to make API calls, it
requires a generic user to be created, called `vufind`.

The users credentials are provided as build arguments to the vufind image:
`FOLIO_USER` and `FOLIO_PASS`.

The `vufind` application user (set in `local/confing/vufind/folio.ini`) requires the
following permissions within FOLIO. They need to be created as a permission set with the FOLIO API, with a `POST` request to `/perms/permissions`.

* `inventory.instances.item.get`
* `inventory-storage.holdings.collection.get`
* `inventory-storage.holdings.item.get`
* `inventory-storage.instances.collection.get`
* `inventory-storage.items.collection.get`
* `inventory-storage.items.item.get`
* `inventory-storage.locations.collection.get`
* `inventory-storage.locations.item.get`
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
modify them directly inside the containers (ideally scale the `${STACK_NAME}_catalog_catalog`
down to 1 beforehand to not confuse yourself), or you can mount the shared storage and make
changes there. Changes to the live storage are symboliclly linked to the containers and will
appear real time in the environment -- very handy for theme development!

Within the shared storage there will be a sub-directory for each branch name. This documentation
assumes that the share has been set up and configured already on the hosts. The sub-directory
will contain a clone of this repository which can be easily used to track changes between
subsequent deploys to the same branch.

Note that subsequent deploys only do a `git fetch` to avoid overwriting local changes. You are
responsible for doing a `git pull` to apply new changes.
