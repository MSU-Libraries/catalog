# CI/CD
This page describes the GitLab CI/CD pipeline that is used by
the MSU Libraries team to deploy the Docker services as multiple stacks
in a multi-node Docker swarm cluster.

The `catalog-preview` branch represents the staging environment for
changes before they will be deployed to the production environments.

The `main` branch represents the stable production environment. Branches
with the prefix of `review-` or `devel-` will create separate stacks on
the cluster, auto-provisioning DNS CNAMES as part of the pipeline.

The workflow for developers will be to make code changes on `devel-`
environments, then merge them in to the `catalog-preview` branch,
and once a semester (approximately), we will merge that branch
into the `main` branch to deploy to production. Occasionally there
may be changes that need to go to production sooner, in that case they
will be merged to from the `devel-` branches to *BOTH* the `catalog-preview` and
the `main` branch.

## Environment based on branch name

* branch: `main`,  
  stack prefix: `catalog-beta` (`catalog-beta-catalog`, `catalog-beta-solr`, `catalog-beta-mariadb`, etc.),  
  url: https://catalog-beta.lib.msu.edu (local DNS C-Record to catalog.aws.lib.msu.edu)  
  stack prefix: `catalog-prod` (`catalog-prod-catalog`, `catalog-prod-solr`, `catalog-prod-mariadb`, etc.),  
  url: https://catalog.lib.msu.edu (local DNS C-Record to catalog.aws.lib.msu.edu)

* branch: `catalog-preview`,  
  stack prefix: `catalog-preview (`catalog-preview-catalog`, `catalog-preview-solr`, `catalog-preview-mariadb`, etc.),  
  url: https://catalog-preview.lib.msu.edu (local DNS C-Record to catalog.aws.lib.msu.edu)

* branch: `review-some-feature`,  
  stack prefix: `review-some-feature` (`review-some-feature-catalog`, `review-some-feature-solr`, `review-some-feature-mariadb`, etc.),  
  url: https://review-some-feature.aws.lib.msu.edu

* branch: `devel-some-feature`,  
  stack prefix: `devel-some-feature` (`devel-some-feature-catalog`, `devel-some-feature-solr`, `devel-some-feature-mariadb`, etc.),  
  url: https://devel-some-feature.aws.lib.msu.edu 

* branch: `nothing-special`  
  stack prefix: None, no environment created  
  url: None, no environment created  

!!! note

    The `traefik` stack is shared by all of the stacks because it
    controls routing public requests to the relavent stack based on the host name.

The `devel` and `review` environments will have the following extra characteristics:

* An extra job in the pipeline that can be manually run to clean up the environment
when you are done with it
* Only a subset of the harvest records will be imported

## Pipeline Stages & Jobs

## test
**branches**: `main`, `catalog-preview`, `devel-`*, and `review-`*

* Runs templates included with GitLab CI/CD to scan for secrets used in committed code
* Runs `shellcheck` on all bash scripts in the repository

### Build
**branches**: `main`, `catalog-preview`, `devel-`*, and `review-`*

* Builds all the images in this repository, tagging them with `latest` only if it is the `main` branch
* When building the VuFind image, it will also perform unit testing of the `Catalog` module

### Deploy
**branches**: `main`, `catalog-preview`, `devel-`*, and `review-`*

* Will set the `STACK_NAME` variable that is used throughout the pipeline, which is essentially
the branch name unless the branch does not start with `devel-`, `review-` or is `main`
* Will make updates to the docker compose files and copy them to the AWS servers. The updates include
changing the image tag from `:latest` to the current commit sha and modifying services based on
the `STACK_NAME`
* Will call the playbook that creates a DNS record for devel and review environments if necessary
* Deploy both the traefik (which handles routing of public traffic to the different environments
hosted on the swarm) and the internal network used by the MariaDB Galera services
within the individual environment
* Will bootstrap the `solr` and `mariadb` stacks if they have not already been (i.e. this is the first time
running this job for this branch)
* Deploys the `catalog`, `solr`, `swarm-cleanup`, and `mariadb` stacks. If this is a devel or review environment, it will
import a single marc file into the VuFind instance as test data
* Runs VuFind version upgrades, if applicable
* If on a the `main` branch, it will run functional testing with the tests in the `Catalog` module
* If it is a `devel-` or `review-` branch, it will populate the environment with sample data
* Evaluate the health of the services on all nodes

### Cleanup
**branches**: `devel-`* and `review-`*

* Removes the stacks, their volumes, and runs the playbook to remove the DNS record created for the environment

### Release 
**branches**: `main`

* Creates a release tag for the current commit
* Pushes the latest changes to Github and publishes the Github Pages once the Github Action
job should have completed that compiles the docs

## Variables
At this time, the following variables need to be defined in the
project's CI/CD settings to be available to the pipeline. While it is ok for variables to be
marked as `masked`, they can not be marked as `protected`; otherwise they will not be
available in the `devel-` and `review-` pipelines. You may need to define the same
variable multiple times, but for each environment, so that each site has different values.
For example, your development environments might have different values for `FOLIO_URL` then
the production environment. This is done using the *scope* setting in the variables menu.
And the *scope* value is the branch name you want to match it to followed by a wildcard (`*`).
For example: `devel-mytest*`.

* `AUTH_FTP_PASSWORD`: Password for `AUTH_FTP_USER`
* `AUTH_FTP_USER`: Username for the authority marc file FTP server
* `AWS_KEY`: The AWS access key to use when provisioning the DNS CNAME records
* `AWS_SECRET`: The AWS secret for the `AWS_KEY` uses when provisioning the DNS CNAME records
* `BASICAUTH_FOR_RESOURCES`: Bcrypt password hash[^1] for basic authentication to internal
resources such as Solr and the Traefik dashboard
* `BROWZINE_LIBRARY`: Library ID for BrowZine (LibKey)
* `BROWZINE_TOKEN`: BrowZine API token (LibKey)
* `DEPLOY_KEY`: GitLab read-only deploy key base64 encoded
* `DEPLOY_PRIVATE_KEY`: The `base64` encoded private ssh key to the deploy server
* `EDS_ORG`: Organization ID for the EDS API
* `EDS_PASS`: Password for the `EDS_USER` username
* `EDS_PROFILE`: Profile name for EDS
* `EDS_USER`: Username for the EDS API
* `EMAIL`: Email address set in VuFind's configs 
* `FEEDBACK_EMAIL`: Email address for sending feedback form submissions to (internal and external)
* `FEEDBACK_PUBLIC_EMAIL`: Email address for sending external feedback form submissions to
* `FOLIO_CANCEL_ID`: The FOLIO cancellation ID to use when canceling an order. VuFind uses
`75187e8d-e25a-47a7-89ad-23ba612338de` by default
* `FOLIO_PASS`: Password for the `FOLIO_USER` application user used by VuFind
* `FOLIO_REC_ID`: Record ID in FOLIO to search for to verify the tenant is available
* `FOLIO_TENANT`: Tenant ID 
* `FOLIO_URL`: Okapi URL for FOLIO used by VuFind 
* `FOLIO_USER`: Application user used by VuFind for ILS calls 
* `HLM_FTP_PASSWORD`: Password for `HLM_FTP_USER`
* `HLM_FTP_USER`: Username for the EBSCO FTP server
* `GITHUB_USER_TOKEN`: Token used to publish releases to GitHub repository 
* `MATOMO_SEARCHBACKEND_DIMENSION`: ID for the custom dimension in Matomo to track the search backend used for the request
* `MATOMO_SITE_ID`: Matomo site identifier for the website you want the analytics sent to
* `MATOMO_URL`: Matomo URL to send the analytics to
* `OAI_URL`: URL for making OAI calls to FOLIO when harvesting (can include API Token) 
* `RECAPTCHA_SECRET_KEY`: Secret key for reCaptcha form validation
* `RECAPTCHA_SITE_KEY`: Site key for reCaptcha form validation
* `REGISTRY_ACCESS_TOKEN`: Read-only registry access token used by deploy user
* `RW_CICD_TOKEN`: Read-Write access token to this repository used to create release tags 
* `SESSION_BOT_SALT`: Secure random string used in creating persisting session ids for bots (when bot_agent values are set)
* `SIMPLESAMLPHP_ADMIN_PW`: Password to the admin interface of SimpleSAMLphp
* `SIMPLESAMLPHP_SALT`: Random salt for SimpleSAMLphp

## Scheduled Pipelines
For the Ansible image to build overnight, saving time on regular daily builds, we can set up a scheduled pipeline to
run off-hours in GitLab. This is done in the [Schedules](https://gitlab.msu.edu/msu-libraries/devops/catalog/-/pipeline_schedules)
tab in the CI/CD page of GitLab. You should configure it for the `main` branch and set it to run at whatever time is convenient
for your team.

## Deploy Freezes
If you want to prevent deployments to the production environment during certain times, you
can make use of GitLab's [Deploy Freeze](https://gitlab.msu.edu/help/ci/environments/deployment_safety.md#prevent-deployments-during-deploy-freeze-windows)
feature. Simply enter a timeframe using cron-style syntax in the "Deploy Freeze" section
of the CI/CD Settings of the repository.

[^1]
    There are many ways to generate this password hash, such as online generators or command
    line tools (like `htpasswd` in the `apache-utils` package, for example: `htpasswd -B -C 10 -n [username]`).
    For Traefik performance reasons, we recommend you use a bcrypt cost value of 10.
