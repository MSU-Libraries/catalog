# catalog

Public custom catalog implementation using Vufind


## Running
First run only requires bootstrapping Solr and MariaDB:
```
docker stack deploy -c docker-compose.solr-cloud-bootstrap.yml solr
docker stack deploy -c docker-compose.mariadb-bootstrap.yml mariadb
```

To run this application using Docker swarm:
```
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

**Notes**:  
* The MariaDB galera cluster will not be able to come back up automatically
if all of it's services are brought down at the same time (because none of
the services will be able to set `safe_to_bootstrap` in their 
`mariadb/data/grastate.dat` file). Because of this, it is important to
bring down the services one at a time when updating them to avoid that sitation.

## FOLIO Application User Requirements
The `vufind` application user (set in `local/confing/vufind/Folio.ini`) requires the
following permissions within FOLIO:  

* Inventory: View instances, holdings, and items
* MSU OAI-PMH
* Requests: View
* Settings (OAI-PMH): Can view
* Settings (OAI-PMH): Can view and edit settings
* Users: Can view fees/fines and loans
* Users: Can view user profile
