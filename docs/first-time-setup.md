# First Time Setup

## Building the Vufind image
Before bringing up the application stack, you need to build the custom
vufind image. Below is a sample command, and further down will describe
the build arguments in more detail.

```bash
docker build ./vufind/ -t catalog:latest \
    --build-arg DEBUG=true \
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
    --build-arg SOLR_URL=http://solr:8983/solr
```

* `DEBUG`: Enable debug messages to be displayed on the page. Should be set to `true` or `false`
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
* `SOLR_URL`: The URL that Vufind's Solr instance is accessible on. This setup will use `solr`

## To start the application stack
During the first time you are bring up the stack, you will need
to run these first to bootstrap Solr and MariaDB:
```bash
docker stack deploy -c docker-compose.solr-cloud-bootstrap.yml solr
docker stack deploy -c docker-compose.mariadb-bootstrap.yml mariadb
```

Subsequently you will run these commands (during the inital deploy
and whenever you need to deploy updates):
```bash
# Traefik stack to handle internal and public networking
docker stack deploy -c docker-compose.traefik-public.yml traefik-public
docker stack deploy -c docker-compose.traefik-internal.yml traefik-internal

# The rest of the MariaDB galera cluster
docker stack deploy -c docker-compose.mariadb-galera2.yml mariadb
docker stack deploy -c docker-compose.mariadb-galera3.yml mariadb
docker stack deploy -c docker-compose.mariadb-galera1.yml mariadb

# The rest of the Solr cloud stack
docker stack deploy -c docker-compose.solr-cloud.yml solr

# The vufind stack
docker stack deploy -c docker-compose.yml catalog
```

!!! warning "Warning"
    The MariaDB galera cluster will not be able to come back up automatically
    if all of it's services are brought down at the same time (because none of
    the services will be able to set `safe_to_bootstrap` in their
    `mariadb/data/grastate.dat` file). Because of this, it is important to
    bring down the services one at a time when updating them to avoid that sitation.

## Creating a FOLIO user
In order for Vufind to connect to FOLIO to make API calls, it
requires a generic user to be created, called `vufind`.

The users credentials are provided as build arguments to the vufind image:
`FOLIO_USER` and `FOLIO_PASS`.

The `vufind` application user (set in `local/confing/vufind/Folio.ini`) requires the
following permissions within FOLIO:

* Inventory: View instances, holdings, and items
* MSU OAI-PMH
* Requests: View
* Settings (OAI-PMH): Can view
* Settings (OAI-PMH): Can view and edit settings
* Users: Can view fees/fines and loans
* Users: Can view user profile

## For GitLab Users: Creating a CI/CD Token
Create a new [access token](https://gitlab.msu.edu/help/user/project/settings/project_access_tokens)
that has `read_registry` privileges to the repository and create a new CI/CD variable with the
resulting key value.
