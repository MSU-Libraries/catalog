# Technical Overview

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
* **swarm-cron**:  
    **swarm-cronjob**: Runs an image of [swarm-cronjob](https://crazymax.dev/swarm-cronjob/) that will
    kick off cron services as specified in service labels  
    **prune-nodes**: Runs a `docker system prune` with flags on each of the nodes in the swarm  
