# Technical Overview

## High Level Overview
If you’re looking for the answer for the quickest answer to “how do we deploy our site”, then you’re reading
the right section! We use two separate code repositories, one for infrastructure and one for our application
code (this repository). The infrastructure repository is internal and uses GitLab CI/CD to kickoff Ansible
playbooks that run terraform commands to manage the infrastructure in AWS for us. This repository however is
public, and contains all our customization on top of Vufind (like our own custom module and theme) but much
more beyond that – it has our own Docker swarm setup that runs all the services Vufind depends on. Just like our
infrastructure repository, our application repository uses GitLab CI/CD to run jobs that deploy the code to our
AWS EC2 instances. Based on the Git branch name, it will decide if it will spin up a new development environment,
update an existing one, or update our production environment. To get a little more into the details, the CI/CD triggers
a Docker build passing `--build-arg` parameters to the build command with multiple CI/CD variables stored in
GitLab. Those variables are then used in the Dockerfile throughout the build (for example: the `config.ini` file
is populated with data in the CI/CD variables with the use of the `envsubst` command in the Dockerfile after the
`ARG` variables have been copied into `ENV` variables).

## Detailed Overview
### Infrastructure as Code
* No reliance on manually provisioning hardware or waiting for humans to accomplish any task.
From nothing to a full production ready environment can be accomplished via the CI tasks.
* Using Terraform to provision entire stack:
    * Virtual Networking & Firewalling
    * Shared services like mounted storage and email relay service
    * Virtual Machines with Network Interfaces, IPs, & Local Block Storage
    * Initial user setup
* Once provisioning is completed, all users and core services are ready for use.

### Fully Redundant
* Current infrastructure spans 3 availability zones (i.e. different data centers)
and could expand to allow for additional nodes.
* Each service is clustered with active load balancing; this includes MariaDB, Solr,
and VuFind (along with their supporting services, like cron jobs). The public access point
(Traefik) is a lightweight single instance which Docker Swarm can redeploy onto another node
in seconds, should its current node go down.

### Automated Environment Creation (and Destruction)
* With an appropriately named git branch, a fully functional environment will be created for use or testing.
    * Creating a branch starting with `devel-` or `review-` will automatically trigger CI stages to
    create a new deployment site.
    * GitLab CI also has 1 click cleanup and removal of environments (except production, to prevent mistakes)
* Environments are completely disposable. We typically make one for a ticket, then destroy it once we
close it. The only ones we leave up are the production deployments (currently, our "beta" and "prod" sites).

### Equality Between Development and Production Environments
Anything the production environment has, the development environments have as well. Full redundant
services, TLS Certificates from Let's Encrypt, email support, and so on. The only thing we decided
to limit was the automated data import. That was restricted to 10,000 records just because
importing the full catalog would slow things down too much (approx 7 million bib records +
3 million authority records).

### Idempotency for both Infrastructure and Environment Deployment
We run the same CI pipelines for both the creation & modification of our infrastructure.
Likewise for our deployment environments, one unified pipeline that can run to create or update things.

### Developer Friendly Interfaces & Logs
* Services with management or monitoring interfaces are automatically enabled with
permissions granted so developers can access them.
    * Solr Admin Web Interface
    * Traefik Dashboard
* Logs for services are set up to be output to Docker for ease of accessing.

## Technologies Used  
* **Docker**: Used to create images for the various services (Vufind, Solr, etc.) that
containers are created from  
* **Docker Swarm**: Manages the server "nodes" and creates containers based on our
server definitions  
* **Terraform**: Handles the provisioning and state management of the AWS cloud services
used, such as EC2, IAM, Route53, and EFS (some of which are stored in an internal
repository)  
* **Ansible Playbooks**: Automates the provisioning and configuration of the setup of
the EC2 nodes and the Docker Swarm stacks and provision DNS names as needed for
development and review enviromnets (some of which are stored in an internal repository)  
* **AWS Cloud Services**: AWS self-service resources are used to allow for full
infrastructure-as-code and automation of the provisioning 
* **Vufind**: What we're all here for! Vufind is the core application all of this infrastructure
is all built trying to serve  
* **SolrCloud**: A more fault tolerant and highly available search and indexing service than
just traditional Solr, distributing index data accross multiple nodes  
* **ZooKeeper**: Used by SolrCloud to manage configuration files for the collections  
* **MariaDB Galera**:  A synchronous database cluster providing higher availability and more
fault tolerance  
* **Traefik**: Use to route traffic externally to the appropriate vufind container; and
also used for an internal network of the MariaDB service
* **LetsEncypt**: Provides automatically provissioned SSL certificates based on settings in
our Docker swarm configuration file  
* **GitLab CI/CD**:  The key tool our tool belt that allows us to define highly customized
pipelines to provision and configure our application stack  
* **GitLab Container Registry**: Stores the Docker images built, which our EC2 instances have
a read-only access key to the registry to pull from  
* **MkDocs**: The static site generator used to generate this documentation  
* **GitHub Pages**: The hosting service used for this documentation  

## Docker Swarm Stacks and Services
* **catalog**:  
    **catalog**: Runs Vufind with Apache in the foreground  
    **cron**: A single replica service using the `catalog` service's image to run automated jobs
    such as the periodic harvest and import of data from FOLIO  
* **solr**:  
    **solr**: Has 3 replicas, one on each node in the cluster, running SolrCloud  
    **zk**: Runs 3 ZooKeeper replicas, one on each node of the cluster  
    **cron**: A 3-replica service that runs automated jobs using the SolrCloud image on each node in the cluster.
    Currently the only job being run is to update the alphabetical browse Solr databases.  
* **mariadb**:  
    **galera**: Runs MariaDB Galera with 3 replicas, one on each node on in the cluster
* **internal**:  
    **health**: Creates only the internal network used by the `galera` service  
* **traefik**:  
    **traefik**:  Runs Traefik and handles external traffic and routes it to the appropriate `catalog` service
depending on the host name of the request (since multiple environments run in separate stacks on the same Docker
swarm)  
* **swarm-cleanup**:  
    **prune-nodes**: Runs a `docker system prune` with flags on each of the nodes in the swarm  
