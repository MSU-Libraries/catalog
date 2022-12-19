# Public Catalog Monitoring

## Introduction
The Monitoring app helps to monitor the Public Catalog system as a whole. It is a web application displaying a dashboard of the status for the different services and cron processes, doing checks across all the nodes. It also records available memory, disk space and apache requests to display graphs of these variables over time. To make troubleshooting easy, it also provides easy access to the numerous logs for the different services across all the nodes. Finally, it provides links to other admin panels.
The home page is refreshed regularly (with an html `meta`).

## Access
Monitoring is available with the path `/monitoring`, using the same credentials as the Traefik and Solr admin panels. Access is controlled with traefik.

## JSON status for nodes
The monitoring app is running on each node. The status specific to each node can be obtained with the path `/monitoring/node/status`. So for instance within a container using the docker network, one can get node 2's status with `http://monitoring2/monitoring/node/status`.

## Implementation
Implementation is in Python with Flask, in Docker. The starting point is simply `python app/app.py`. It is using a mariadb database called `monitoring` (using galera like the other services).
Here is a summary of what top-level files/directories are for:
- `static`: CSS and Javascript files
- `templates`: Jinja templates
- `app.py`: main file, including all the routes, and starting the scheduler
- `collector.py`: regular task saving the variables in a database; also collects apache requests by looking at the access log.
- `graphs.py`: functions to create graphs
- `home.py`: prepares the home page template using functions in `status.py`.
- `logs.py`: gathers and displays the logs; log files are read from the `${STACK_NAME}_logs` docker volume.
- `status.py`: gathers all status information
- `util.py`: utilities (mainly to do async http requests in parallel with `asyncio` and `aiohttp`)

## Pylint arguments
- `--disable=missing-module-docstring`
- `--disable=missing-class-docstring`
- `--disable=missing-function-docstring`
- `--max-line-length=120`
- `--good-names=i,j,k,x,y,ex`
- `--generated-members=human_readable.*`

## TODO
See JIRA tickets.
Currently it is still running with debug on, but eventually that should be turned off.
