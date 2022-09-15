# Troubleshooting

## Vufind
[Vufind's troubleshooting page](https://vufind.org/wiki/development:troubleshooting)
is a good reference for debugging options, but we'll highlight a few 
specifics we've found helpful.

* The first spot to check for errors is the Docker service logs which
contain both Apache error and access logs (these logs can help identify
critical issues such as the innability to connect to the database) as well
as Vufind's application logs.
To view service logs: `docker service logs -f [STACK_NAME]-catalog_catalog`.

* To enable debug messages to be printed to the service logs,
update the `file` setting under the `[Logging]` section
in the `local/config/vufind/config.ini` file to include `debug`.
For example: `file = /var/log/vufind/vufind.log:alert,error,notice,debug`

!!! warning "Warning with `debug=true`"
    You can set `debug=true` in the main `config.ini` file to have debug messages
    to be printed to the page instead of the service logs, but be warned that sometime
    too much information is printed to the page
    when `debug` is set to `true`, such as passwords used for ILS calls 
    (hopefully this can be fixed to remove that from debug messages entirely).

* To enable higher verbosity in the Apache logging, you can update the
`LogLevel` in the `/etc/apache2/sites-enabled/000-default.conf` file and
then run `apachectl graceful` to apply the changes.

* To enable stack traces to be shown on the page when ever any 
exception is thrown, edit the `local/httpd-vufind.conf` file and
uncomment the line that says: `SetEnv VUFIND_ENV development`. Write
the changes to the file and then run `apachectl graceful` to apply
the change.

## Traefik

* Your first line of defense when debugging issues with Traefik is
navigating to the Traefik dashboard at https://your-site/dashboard/
where you can see all the routers and services that have been defined.
This is helpful when the issue is a configuration issue either in the
Traefik command or labels.

* When you have basic authentication enabled, ensure that the
password hash has a low enough settings (we found 8 to work well)
otherwise Traefik will use a significant amount of CPU load and
cause pages to load extremely slow.

* To debug performance issues in Traefik, you can enable debug
mode by adding to the traefik service: `--api.debug=true`.
This enables all of the [debug endpoints](https://doc.traefik.io/traefik/operations/api/#debug).

```bash
curl -u user:passwd https://your-site/debug/pprof/heap -o heap.pprof
curl -u user:passwd https://your-site/debug/pprof/profile -o profile.pprof
curl -u user:passwd https://your-site/debug/pprof/block -o block.pprof
curl -u user:passwd https://your-site/debug/pprof/mutex -o mutex.pprof
curl -u user:passwd https://your-site/debug/pprof/goroutine -o goroutine.pprof

# Install Go
apt install golang
# Install pprof
go install github.com/google/pprof@latest

go tool pprof -top heap.pprof
go tool pprof -top profile.pprof
go tool pprof -top block.pprof
go tool pprof -top mutex.pprof
go tool pprof -top goroutine.pprof
```

## Solr

* Solr can be accessed via https://your-site/solr which is helpful
for verifying the state that the cloud is in (Cloud -> Nodes) as well as the ZooKeeper
containers (Cloud -> ZK Status). Additionally you can check the status of the collections
(Cloud -> Graph) to make sure they are marked as Active. It may also be helpful to use the
web interface for testing queries in too.

* A helper script is included with all of the nodes, called `clusterhealth.sh`,
that can be run to check all of the replicas and shards across all nodes and report
and issues identified. It can be run by:

```bash
docker exec $(docker ps -q -f name=${STACK_NAME}-solr_solr) /clusterhealth.sh
```

* To view the Docker healthcheck logs from a particular container you can:

```bash
# View from running containers
docker inspect --format '{{ .State.Health.Status }}' $(docker ps -q -f name=${STACK_NAME}-solr_solr)
docker inspect --format '{{ (index .State.Health.Log 0).Output }}' $(docker ps -q -f name=${STACK_NAME}-solr_solr)

# View from stopped containers
docker inspect --format '{{ .State.Health.Status }}' [CONTAINER_ID]
docker inspect --format '{{ (index .State.Health.Log 0).Output }}' [CONTAINER_ID]

# Run healthcheck script manually
docker exec $(docker ps -q -f name=${STACK_NAME}-solr_solr) /healthcheck.sh
```

### Fixing Down Solr Replicas

In the event that you find a Solr replica that appears to be stuck in a "down" state
despite all efforts to bring it back online, it may be easiest to just discard that replica and recreate it.

This can be accomplished via the `DELETEREPLICA` and `ADDREPLICA` Solr API calls. 
See [https://solr.apache.org/guide/8_10/replica-management.html](https://solr.apache.org/guide/8_10/replica-management.html).

For example, if one node in a replica is stuck down, you can simply remove the downed replicas
and then add a new replica to replace it.

```bash
# Identified one down replicas for `biblio` on solr3 to be removed.
# We don't have to specify solr3 here, as we're setting `onlyIfDown`.
curl 'http://solr:8983/solr/admin/collections?action=DELETEREPLICA&collection=biblio&count=1&onlyIfDown=true&shard=shard1'

# Create a new replica for `biblio` on solr3 to replace the one we removed.
curl 'http://solr:8983/solr/admin/collections?action=ADDREPLICA&collection=biblio&shard=shard1&node=solr3:8983_solr'
```

Note, the new replica may take a few minutes to "recover" while it comes up. This is the
process where it gets current collection data from the other replicas.
