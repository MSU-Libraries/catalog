# Technical Overview

## High Level Overview
If you’re looking for the answer for the quickest answer to “how do we deploy our site”, then you’re reading
the right section! We use two separate code repositories, one for infrastructure and one for our application
code (this repository). The infrastructure repository is internal and uses GitLab CI/CD to kickoff Ansible
playbooks that run terraform commands to manage the infrastructure in AWS for us. This repository however is
public, and contains all our customization on top of VuFind (like our own custom module and theme) but much
more beyond that – it has our own Docker swarm setup that runs all the services VuFind depends on. Just like our
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
    * GitLab CI also has 1 click cleanup and removal of environments (except production, to prevent mistakes); 
  running the cleanup will free the resources on the server, and will not remove the source code files, 
  nor delete the git branch.
* Environments are completely disposable. We typically make one for a ticket, then destroy it once we
close it. The only ones we leave up are the production deployments (currently, our "beta", "prod" and "preview" sites).

### Equality Between Development and Production Environments
Anything the production environment has, the development environments have as well. Full redundant
services, TLS Certificates from Let's Encrypt, email support, and so on. The only thing we decided
to limit was the automated data import. That was restricted to 10,000 records just because
importing the full catalog would slow things down too much (approx 9 million bib records +
3 million authority records).

### Idempotency for both Infrastructure and Environment Deployment
We run the same CI pipelines for both the creation & modification of our infrastructure.
Likewise for our deployment environments, one unified pipeline that can run to create or update things.

### Developer Friendly Interfaces & Logs
* Services with management or monitoring interfaces are automatically enabled with
permissions granted so developers can access them.
    * Solr Admin Web Interface
    * Traefik Dashboard
    * Locally developed service monitoring dashboard
* Logs for services are set up to be output to Docker for ease of accessing.

## Technologies Used  
* **Docker**: Used to create images for the various services (VuFind, Solr, etc.) that
containers are created from  
* **Docker Swarm**: Manages the server "nodes" and creates containers based on our
server definitions  
* **Terraform**: Handles the provisioning and state management of the AWS cloud services
used, such as EC2, IAM, Route53, and EFS (some of which are stored in an internal
repository)  
* **Ansible Playbooks**: Automates the provisioning and configuration of the setup of
the EC2 nodes and the Docker Swarm stacks and provision DNS names as needed for
development and review environments (some of which are stored in an internal repository)  
* **AWS Cloud Services**: AWS self-service resources are used to allow for full
infrastructure-as-code and automation of the provisioning 
* **VuFind**: What we're all here for! VuFind is the core application all of this infrastructure
is all built trying to serve  
* **SolrCloud**: A more fault-tolerant and highly available search and indexing service than
just traditional Solr, distributing index data across multiple nodes  
* **ZooKeeper**: Used by SolrCloud to manage configuration files for the collections  
* **MariaDB Galera**:  A synchronous database cluster providing higher availability and more
fault tolerance  
* **Traefik**: Use to route traffic externally to the appropriate VuFind container; and
also used for an internal network of the MariaDB service  
* **Nginx**: Handles proxying requests to `/solr` to the Solr container. Allowing us to keep
the Solr containers only on the internal network but still being able to access the Solr interface
via the web  
* **LetsEncrypt**: Provides automatically provisioned SSL certificates based on settings in
our Docker swarm configuration file  
* **GitLab CI/CD**:  The key tool our tool belt that allows us to define highly customized
pipelines to provision and configure our application stack  
* **GitLab Container Registry**: Stores the Docker images built, which our EC2 instances have
a read-only access key to the registry to pull from  
* **MkDocs**: The static site generator used to generate this documentation  
* **GitHub Pages**: The hosting service used for this documentation  
* **[marc-utils](https://github.com/banerjek/marc-utils/tree/main)**  

## Docker Swarm Stacks and Services
* **catalog**:  
    **catalog**: Runs VuFind with Apache in the foreground  
    **cron**: A single replica service using the `catalog` service's image to run automated jobs
    such as the periodic harvest and import of data from FOLIO  
    **legacylinks**: Redirects legacy Sierra formated URLS to VuFind record pages  
    **croncache**: Only on development environments, clearing local cache files that are created  
* **solr**:  
    **solr**: Runs SolrCloud  
    **zk**: ZooKeeper in the foreground  
    **cron**: Runs automated jobs using the SolrCloud image (updating the alphabetical browse databases)  
    **proxysolr**:  Runs Nginx to proxy requests from the public network to the Solr container  
* **mariadb**:  
    **galera**: Runs MariaDB Galera  
* **internal**:  
    **health**: Creates only the internal network used by the `galera` service  
* **traefik**:  
    **traefik**:  Runs Traefik and handles external traffic and routes it to the appropriate `catalog` service
depending on the host name of the request (since multiple environments run in separate stacks on the same Docker
swarm)  
* **public**:  
    **health**: Creates only the public network used by the `catalog` service and the `proxy`* services  
* **swarm-cleanup**:  
    **prune-nodes**: Runs a `docker system prune` with flags on each of the nodes in the swarm  
* **monitoring**:  
    **monitoring**: Runs the locally developed Flask application in the foreground to monitor the other stacks  
    **proxymon**: Exposes the `monitoring` service publicly since that service is only on the internal network  
