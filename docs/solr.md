# Solr

## Helpers

To quick-connect to the [zk-shell](https://pypi.org/project/zk-shell/#usage)
within the container (without having to pass the ZK hosts)
simply use the `solr-zk-shell` command.

```bash
docker exec -it $(docker ps -q -f name=catalog-prod-solr_solr) solr-zk-shell

```

## Re-deploying

If you ever need to re-deploy the stack, you can use the
[pc-deploy](helper-scripts.md#deploy-helper-pc-deploy) script.

Make sure you run it as the deploy user so that the proper Docker
container registry credentials are passed.

```bash
sudo -Hu deploy pc-deploy catalog-prod solr
```

## Collection Structure

Most of the collections do not have an alias associated with them. But,
`biblio` is special! For that collection we are using Solr aliases because
we want to be able to clear out the index completely and rebuild it without
causing downtime for search on our site. The `biblio` alias will point to the
"live" biblio collection and the `biblio-build` alias will be used for
rebuilding the index on.

``` mermaid
graph LR
    biblio --> biblio1;
    biblio-build --> biblio2;
    subgraph Aliases;
    biblio;
    biblio-build;
    end;
    subgraph Collections;
    biblio1;
    biblio2;
    end;
```

Can be swapped to be:

``` mermaid
graph LR
    biblio --> biblio2;
    biblio-build --> biblio1;
    subgraph Aliases;
    biblio;
    biblio-build;
    end;
    subgraph Collections;
    biblio1;
    biblio2;
    end;
```

Ideally, when you complete the swap over to the `biblio` alias, you would
clear out the contents of the collection that is now pointing to the
`biblio-build` alias to avoid extra disk usage.

## Updating the Solr Configuration Files

You should not need to do this in a new environment (schema changes should
be built into the Docker image) but for development, sometimes you may want
to test changes to the Solr schema. Also, during upgrades you may need to
apply updates to config files to existing environments (as you likely won't
want to just delete your entire volume and start over). For simplicity, we've
documented how to make those updates to them here since it has to be done via
one of the Solr containers and running the `zk` commands.

```bash
# Copy the file(s) you need from Zookeeper
solr zk cp zk:/solr/configs/biblio/solrconfig.xml /tmp/solrconfig.xml -z zk1:2181

# Now make your updates at the location you updated them

# Finally, copy those updated files back onto Zookeeper
solr zk cp /tmp/solrconfig.xml zk:/solr/configs/biblio/solrconfig.xml -z zk1:2181
```

## Deleting documents from the Solr index

In the event that you need to manually remove items from one of the Solr
collections, you can connect to one of the containers in your stack that
have `curl` installed and are within the `internal` network (such as one
of the VuFind containers) and run the following command.

<!-- markdownlint-disable MD013 -->
```bash
# Deletes the document with the id of folio.in00006795294 from the biblio collection
curl 'http://solr1:8983/solr/biblio/update' --data '<delete><query>id:folio.in00006795294</query></delete>' -H 'Content-type:text/xml; charset=utf-8'
curl 'http://solr1:8983/solr/biblio/update' --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
```
<!-- markdownlint-enable MD013 -->

## Troubleshooting

* Solr can be accessed via [https://your-site/solr](https://your-site/solr)
  which is helpful for verifying the state that the cloud is in
  (Cloud -> Nodes) as well as the ZooKeeper containers (Cloud -> ZK Status).
  Additionally, you can check the status of the collections (Cloud -> Graph)
  to make sure they are marked as Active. It may also be helpful to use the
  web interface for testing queries.

* A helper script is included with all the nodes, called `clusterhealth.sh`,
  that can be run to check all the replicas and shards across all nodes
  and report and issues identified. It can be run by:

```bash
docker exec $(docker ps -q -f name=${STACK_NAME}-solr_solr) /clusterhealth.sh
```

* Using the NCPA checks deployed via the catalog-infrastructure repository
  will also run many of these health checks.

```bash
sudo /usr/local/ncpa/plugins/check_galera.sh catalog-prod
```

* To view the Docker healthcheck logs from a particular container you can:

<!-- markdownlint-disable MD013 -->
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
<!-- markdownlint-enable MD013 -->

## Fixing Down Solr Replicas

In the event that you find a Solr replica that appears to be stuck in
a "down" state despite all efforts to bring it back online, it may be
easiest to just discard that replica and recreate it.

This can be accomplished via the `DELETEREPLICA` and `ADDREPLICA` Solr
API calls. See [https://solr.apache.org/guide/8_10/replica-management.html](https://solr.apache.org/guide/8_10/replica-management.html).

For example, if one replica in a shard is stuck down, you can simply
remove the downed replicas and then add a new replica to replace it.

```bash
# Identified one down replicas for `biblio` on solr3 to be removed.
# We don't have to specify solr3 here, as we're setting `onlyIfDown`.
curl 'http://solr:8983/solr/admin/collections?action=DELETEREPLICA&collection=biblio&count=1&onlyIfDown=true&shard=shard1'

# Create a new replica for `biblio` on solr3 to replace the one we removed.
curl 'http://solr:8983/solr/admin/collections?action=ADDREPLICA&collection=biblio&shard=shard1&node=solr3:8983_solr'
```

Note, the new replica may take a few minutes to "recover" while it comes
up. This is the process where it gets current collection data from the other replicas.

## Fixing Count Differences

Occasionally the replicas can get out of sync and have slightly different counts
between the replicas. If the leader is the one with the off-number and the other
replicas are in-sync, then the solution is just to stop the container
on the leader node to force the leadership to change to one of the other nodes.
This will trigger the out-of-sync replica to re-sync with the new leader.

You can verify current leadership status via the Solr Admin interface
in Cloud -> Graph.

You can verify counts on each replica by doing a curl call (or viewing in
your browser, replacing with your site's URL):

```bash
curl 'http://solr:8983/solr/admin/metrics?nodes=solr1:8983_solr,solr2:8983_solr,solr3:8983_solr&prefix=SEARCHER.searcher.numDocs,SEARCHER.searcher.deletedDocs&wt=json'
```

If every node is out of sync, then you will want to look at the volume file
timestamps to determine the most recently modified as well as determining
which has the highest index count. Then, to force that node to become leader,
you will need to pause the other docker nodes (the ones you do NOT want to be
the new leader) from accepting containers, stop the ones you don't want to be
leader one at a time, so the container remaining becomes leader. Once complete,
un-pause the docker nodes, so they can accept new containers again. You can watch
the service logs to ensure they are recovering from the leader node and the
counts sync back up over a period of time.

```bash
docker node ls
# Do this for both nodes you need to pause
docker node update [ID or hostname] --availability pause
# Verify their state
docker node ls
# Re-enable them after you bring your containers back up
docker node update [ID or hostname] --availability active
```

## Running the alphabetical indexing manually

Each node in `solr_cron` runs a script to build the alpha browse databases.
These are offset to not run at the same time on each node (starting with node
3 for beta, node 1 for prod). The first node to run will generate the new
database files and put them into the shared storage space, the other nodes
will detect the recently built files and just copy them.

```bash
/alpha-browse.sh -v -p /mnt/shared/alpha-browse/$STACK_NAME
```

## Directory structure and files in the Solr image

We use VuFind configurations for alphabetical indexing and browsing.
Browsing is started by Solr in the `solr_solr` container, and configuration
is done in the Solr config file for the biblio collection `solrconfig.xml`.
Indexing is started with VuFind's `index-alphabetic-browse.sh`, which
we start with the `alphabrowse.sh` script with cron in the `solr_cron`
container. VuFind includes its own bundled version of Solr, so its
configurations have paths reflecting that, and it doesn't
translate well when using Solr in separate containers.

The constraints are:

* `index-alphabetic-browse.sh` assumes there is an `import` directory in
  the same directory as itself.
* The `CLASSPATH` in `index-alphabetic-browse.sh` implies `browse-indexing.jar`
  and `solrmarc_core*.jar` are in `${VUFIND_HOME}/import`, `marc4j*.jar` is in
  `${VUFIND_HOME}/import/lib/` and other jars are in `${SOLR_HOME}/jars`.
* The `solrconfig.xml` file for the `biblio` collection includes relative
  paths to jar libraries.

The Solr `Dockerfile` puts VuFind `/solr/vufind` files into `/solr_confs` and
customizes them. It also edits the lib paths in `solrconfig.xml`.

To run `index-alphabetic-browse.sh`, `alphabrowse.sh` sets
`SOLR_HOME=/tmp/alpha-browse/build` and `VUFIND_HOME=/solr_confs`
and adds a symlink from `/tmp/alpha-browse/build/jars` to
`/bitnami/solr/server/solr/jars/`.

One thing to keep in mind when changing the structure is that the `/bitnami`
directory is stored in the `solr_bitnami` Docker volume. So any change to the
files in the image copied to `/bitnami` during initialization is not
necessarily reflected in the volume after deployment, and it might require
manual updates. We used to copy jar files into
`/opt/bitnami/solr/server/solr/jars` and
`/opt/bitnami/solr/server/solr-webapp/webapp/WEB-INF/lib/` and these files
were copied on startup to `/bitnami/solr/...`, but these were not getting
updated automatically after a change. Now that we keep files in `/solr_confs`
and change the paths in `solrconfig.xml`, updates to the jars are applied
automatically.

## Restarting Solr

With restarts on Solr, the important part is to make sure each node is back
online and joined/synced before restarting the next one.

Restarting is just `docker stop <container>` on each node, as Docker Swarm
will quickly restart it as a new container. Then keeping an eye on the `/solr/`
cloud nodes and graph. Note, this can be done programmatically, as we
have done in our `check_solr.sh` NCPA plugin script. (TODO link to
catalog-infrastructure script)

In the `/solr/` web page, under the "Cloud" tab on the left there is a
"Nodes" and "Graph" section. The nodes section shows if nodes are up and
happy, the graph section reports on if the replicas are up and ready.

**If the logs mention being unable to elect a leader**, scale to 3 nodes
(so they see each other), then scale down to 1 node (last node sees other
nodes leave) and then wait for a leader election timeout. The remaining
node will eventually (logs will periodically report a timeout period
remaining) become the leader once the timeout happens, and it stops waiting
for its peers to return. Then scale back up to 2, and then 3. You can tell
when they are ready to be scaled back up once they show as green in
/solr -> Cloud -> Graph. The command to scale the nodes is:
`docker service scale catalog-prod-solr_solr=3`, changing the stack name
and replica count as appropriate.

For example if `solr2` complains after all 3 nodes are restarted that
it couldn't connect to `solr1`, restarting `solr1` again fixes it.

<!-- markdownlint-disable MD013 -->
```log
ERROR (recoveryExecutor-10-thread-3-processing-solr2:8983_solr biblio9_shard1_replica_n1 biblio9 shard1 core_node3) [c:biblio9 s:shard1 r:core_node3 x:biblio9_shard1_replica_n1] o.a.s.c.RecoveryStrategy Failed to connect leader http://solr1:8983/solr on recovery, try again
```
<!-- markdownlint-enable MD013 -->

If a replica doesn't come online and there is no indication that is
recovering in the logs, it could be a stuck leader. In which case changing
who is leading that collection's replicas can allow the downed replica to
come back online. In extreme cases where the replica just refuses to come
back online, remove the replica and then re-add it (manually via
[Solr API](https://solr.apache.org/guide/solr/latest/deployment-guide/replica-management.html)
calls using curl). This last case might be due to a split brain, so
unless we are certain that isn't the case (i.e. we know no Solr
updates have occurred), starting a reimport is probably a good idea.

We can try to change a leader by forcing an election (via
[Solr API](https://solr.apache.org/guide/solr/latest/deployment-guide/shard-management.html)
call). Or if we stop the node, it'll lose leader status.
