# CI/CD
This page describes the GitLab CI/CD pipeline that is used by
the MSU Libraries team to deploy the Docker services as multiple stacks
in a multi-node Docker swarm cluster.

The `main` branch represents the stable production environment. Branches
with the prefix of `review-` or `devel-` will create separate stacks on
the cluster, auto-provisioning DNS CNAMES as part of the pipeline.

## Pipeline

* branch: `main`,  
  stack prefix: `catalog-beta` (`catalog-beta`, `catalog-beta-traefik-internal`, `catalog-beta-solr`, `catalog-beta-mariadb`),  
  url: https://catalog-beta.aws.lib.msu.edu (local DNS https://catalog-beta.lib.msu.edu)

* branch: `review-some-feature`,  
  stack prefix: `review-some-feature` (`review-some-feature`, `review-some-feature-traefik-internal`, `review-some-feature-solr`, `review-some-feature-mariadb`),  
  url: https://review-some-feature.aws.lib.msu.edu 

* branch: `devel-some-feature`,  
  stack prefix: `devel-some-feature` (`devel-some-feature`, `devel-some-feature-traefik-internal`, `devel-some-feature-solr`, `devel-some-feature-mariadb`),  
  url: https://devel-some-feature.aws.lib.msu.edu 

* branch: `nothing-special`  
  stack prefix: None, no environment created  
  url: None, no environment created  

!!! note

    The `traefik-public` stack is shared by all of the stacks because it
    controls routing public requests to the relavent stack based on the host name.
    branch: wip-code, no url

The `devel` and `review` environments will have the following extra characteristics:
* An extra job in the pipeline that can be manually run to cleanup the environment
when you are done with it
* Only a subset of the harvest records will be imported

!!! info
    To allow for development on the containers created by the `devel-`* stacks,
    a volume is mounted on the hosted in `/mnt/shared/vufind/[branch]` and will
    contain the files stored in `/usr/local/vufind/local` on the `vufind` container
    of that stack. Developers can then later commit the changes on the host server
    from the shared mount location if they want to preserve those changes.

## Variables
At this time, the following variables need to be defined in the
project's CI/CD settings to be available to the pipeline.


* `DEPLOY_PRIVATE_KEY`: The `base64` encoded private ssh key to the deploy server
* `EMAIL`: Email address set in Vufind's configs 
* `FOLIO_CANCEL_ID`: The FOLIO cancelation ID to use when canceling an order. Vufind uses
* `FOLIO_PASS`: Password for the `FOLIO_USER` application user used by Vufind
* `FOLIO_REC_ID`: Record ID in FOLIO to search for to verify the tenant is available
* `FOLIO_TENANT`: Tenant ID 
* `FOLIO_URL`: Okapi URL for FOLIO used by Vufind 
* `FOLIO_USER`: Application user used by Vufind for ILS calls 
* `GITHUB_USER_TOKEN`: Token used to publish releases to GitHub repository 
* `OAI_URL`: URL for making OAI calls to FOLIO when harvesting (can include API Token) 
* `REGISTRY_ACCESS_TOKEN`: Read-only registry access token used by deploy user 
