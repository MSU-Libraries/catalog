# CI/CD
This page describes the GitLab CI/CD pipeline that is used by
the MSU Libraries team to deploy the Docker services as multiple stacks
in a multi-node Docker swarm cluster.

The `main` branch represents the stable production environment. Branches
with the prefix of `review-` or `devel-` will create separate stacks on
the cluster, auto-provisioning DNS CNAMES as part of the pipeline.

## Pipeline

* branch: `main`,  
  stack prefix: `catalog-beta` (`catalog-beta-catalog`, `catalog-beta-internal`, `catalog-beta-solr`, `catalog-beta-mariadb`),  
  url: https://catalog-beta.aws.lib.msu.edu (local DNS https://catalog-beta.lib.msu.edu)
  stack prefix: `catalog-prod` (`catalog-prod-catalog`, `catalog-prod-internal`, `catalog-prod-solr`, `catalog-prod-mariadb`),  
  url: https://catalog-prod.aws.lib.msu.edu (local DNS https://catalog-prod.lib.msu.edu)

* branch: `review-some-feature`,  
  stack prefix: `review-some-feature` (`review-some-feature-catalog`, `review-some-feature-internal`, `review-some-feature-solr`, `review-some-feature-mariadb`),  
  url: https://review-some-feature.aws.lib.msu.edu 

* branch: `devel-some-feature`,  
  stack prefix: `devel-some-feature` (`devel-some-feature-catalog`, `devel-some-feature-internal`, `devel-some-feature-solr`, `devel-some-feature-mariadb`),  
  url: https://devel-some-feature.aws.lib.msu.edu 

* branch: `nothing-special`  
  stack prefix: None, no environment created  
  url: None, no environment created  

!!! note

    The `traefik` stack is shared by all of the stacks because it
    controls routing public requests to the relavent stack based on the host name.
    branch: wip-code, no url

The `devel` and `review` environments will have the following extra characteristics:
* An extra job in the pipeline that can be manually run to cleanup the environment
when you are done with it
* Only a subset of the harvest records will be imported

## Pipeline Stages & Jobs

## test
**branches**: `main`, `devel-`*, and `review-`*  
* Runs templates included with GitLab CI/CD to scan for secrets used in committed code
* Runs `shellcheck` on all bash scripts in the repository

### Build
**branches**: `main`, `devel-`*, and `review-`*  
* Builds all of the images in this repository, tagging them with `latest` only if it the `main` branch

### Deploy
**branches**: `main`, `devel-`*, and `review-`*  
* Will set the `STACK_NAME` variable that is used throughout the pipeline, which is essentially
the branch name unless the branch does not start with `devel-`, `review-` or is `main`
* Will make updates to the docker compose files and copy them to the AWS servers. The updates include
changing the image tag from `:latest` to the current commit sha and modifying services based on
the `STACK_NAME`
* Will call the playbook that creates a DNS record for devel and review environments if necessary
* Deploy both the traefik (which handles routing of public traffic to the different enviornments
hosted on the swarm) and the internal network used by the MariaDB Galera services
within the indivudual environment)
* Will bootstrap the `solr` and `mariadb` stacks if they have not already been (i.e. this is the first time
running this job for this branch)
* Deploys the `catalog`, `solr`, `swarm-cron`, and `mariadb` stacks. If this is a devel or review environment, it will
import a single marc file into the vufind instance as test data
* Runs VuFind version upgrades, if applicable
* If it is a `devel-` or `review-` branch, it will populate the environment with sample data
* Evaluate the health of the services on all nodes

### Cleanup
**branches**: `devel-`* and `review-`*  
* Removes the stacks and runs the playbook to remove the DNS record created for the environment

### Release 
**branches**: `main`  
* Creates a release tag for the current commit
* Pushes the latest changes to Github and publishes the Github Pages onces the Github Action
job should have completed that compiles the docs

## Variables
At this time, the following variables need to be defined in the
project's CI/CD settings to be available to the pipeline. While it is ok for variables to be
marked as `masked`, they can not be marked as `protected`; otherwise they will not be
available in the `devel-` and `review-` pipelines.

* `AUTH_FTP_USER`: User name for the authority marc file FTP server
* `AUTH_FTP_PASSWORD`: Password for `AUTH_FTP_USER`
* `AWS_KEY`: The AWS access key to use when provisioning the DNS CNAME records
* `AWS_SECRET`: The AWS secret for the `AWS_KEY` uses when provisioning the DNS CNAME records
* `BASICAUTH_FOR_RESOURCES`: Bcrypt password hash[^1] for basic authentication to internal
resources such as Solr and the Traefik dashboard
* `DEPLOY_PRIVATE_KEY`: The `base64` encoded private ssh key to the deploy server
* `EDS_ORG`: Organization ID for the EDS API
* `EDS_PASS`: Password for the `EDS_USER` username
* `EDS_PROFILE`: Profile name for EDS
* `EDS_USER`: Username for the EDS API
* `EMAIL`: Email address set in Vufind's configs 
* `FEEDBACK_EMAIL`: Email address for sending feedback form submissions to
* `FOLIO_CANCEL_ID`: The FOLIO cancelation ID to use when canceling an order. Vufind uses
* `FOLIO_PASS`: Password for the `FOLIO_USER` application user used by Vufind
* `FOLIO_REC_ID`: Record ID in FOLIO to search for to verify the tenant is available
* `FOLIO_TENANT`: Tenant ID 
* `FOLIO_URL`: Okapi URL for FOLIO used by Vufind 
* `FOLIO_USER`: Application user used by Vufind for ILS calls 
* `FTP_USER`: User name for the EBSCO FTP server
* `FTP_PASSWORD`: Password for `FTP_USER`
* `GITHUB_USER_TOKEN`: Token used to publish releases to GitHub repository 
* `OAI_URL`: URL for making OAI calls to FOLIO when harvesting (can include API Token) 
* `RECAPTCHA_SITE_KEY`: Site key for reCaptcha form validation
* `RECAPTCHA_SECRET_KEY`: Secret key for reCaptcha form validation
* `REGISTRY_ACCESS_TOKEN`: Read-only registry access token used by deploy user
* `RW_CICD_TOKEN`: Read-Write access token to this repository used to create release tags 

## Deploy Freezes
If you want to prevent deployments to the production environment during certain times, you
can make use of GitLab's [Deploy Freeze](https://gitlab.msu.edu/help/ci/environments/deployment_safety.md#prevent-deployments-during-deploy-freeze-windows)
feature. Simply enter in a timeframe using cron-style syntax in the "Deploy Freeze" section
of the CI/CD Settings of the repository.

[^1]: 
    There are many ways to generate this password hash, such as online generators or command
    line tools (like `htpasswd` in the `apache-utils` package). For Traefik performance
    reasons, we recommend you use a brcypt cost value of 8.
