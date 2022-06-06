# catalog

Public custom catalog implementation using Vufind


## Running
To run this application using Docker swarm:

```
docker stack deploy -c docker-compose.traefik.yml traefik
docker stack deploy -c docker-compose.yml catalog
```

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
