# Solr

## Updating the Solr Configuration Files
You should not need to do this in production (schema changes should be built into
the Docker image) but for development, sometimes you may want to test changes to the
Solr schema. For simplicity, we've documented how to make those updates to them here
since it has to be done via one of the Solr containers and running
the `zk` commands.

```bash
# Copy the file(s) you need from Zookeeper
solr zk cp zk:/solr/configs/biblio/solrconfig.xml /tmp/solrconfig.xml -z zk1:2181

# Now make your updates at the location you updated them

# Finally, copy those updated files back onto Zookeeper
solr zk cp /tmp/solrconfig.xml zk:/solr/configs/biblio/solrconfig.xml -z zk1:2181
```

## Deleting documents from the Solr index
In the event that you need to manually remove items from one of the Solr collections
you can connect to one of the in the swarm that have `curl` installed and are within
the `internal` network (such as one of the VuFind containers) and run the following
command.

```bash
# Deletes the document with the id of folio.in00006795294 from the biblio collection
curl 'http://solr1:8983/solr/biblio/update' --data '<delete><query>id:folio.in00006795294</query></delete>' -H 'Content-type:text/xml; charset=utf-8'
curl 'http://solr1:8983/solr/biblio/update' --data '<commit/>' -H 'Content-type:text/xml; charset=utf-8'
```

## Running the alphabetical indexing manually
For each node in `solr_cron` (starting with node 3 for beta, node 1 for prod):
```bash
/alpha-browse.sh -v -p /mnt/shared/alpha-browse/$STACK_NAME
```

## Directory structure and files in the Solr image
We use VuFind configurations for alphabetical indexing and browsing. Browsing is started by Solr in the `solr_solr` container, and configuration is done in the Solr config file for the biblio collection `solrconfig.xml`. Indexing is started with VuFind's `index-alphabetic-browse.sh`, which we start with the `alphabrowse.sh` script with cron in the `solr_cron` container. VuFind includes its own bundled version of Solr, so its configurations have paths reflecting that, and it doesn't translate well when using Solr in separate containers.
The constraints are:

- `index-alphabetic-browse.sh` assumes there is an `import` directory in the same directory as itself.
- The `CLASSPATH` in `index-alphabetic-browse.sh` implies `browse-indexing.jar` and `solrmarc_core*.jar` are in `${VUFIND_HOME}/import`, `marc4j*.jar` is in `${VUFIND_HOME}/import/lib/` and other jars are in `${SOLR_HOME}/jars`.
- The `solrconfig.xml` file for the `biblio` collection includes paths to jar libraries.

The Solr `Dockerfile` puts VuFind `/solr/vufind` files into `/solr_confs` and customizes them. It also edits the lib paths in `solrconfig.xml`.
To run `index-alphabetic-browse.sh`, `alphabrowse.sh` sets `SOLR_HOME=/tmp/alpha-browse/build` and `VUFIND_HOME=/solr_confs` and adds a link from `/tmp/alpha-browse/build/jars` to `/bitnami/solr/server/solr/jars/`.

One thing to keep in mind when changing the structure is that the `/bitnami` directory is stored in the `solr_bitnami` Docker volume. So any change to the files in the image copied to `/bitnami` during initialization is not necessarily reflected in the volume after deployment, and it might require manual updates. We used to copy jar files into `/opt/bitnami/solr/server/solr/jars` and `/opt/bitnami/solr/server/solr-webapp/webapp/WEB-INF/lib/` and these files were copied on startup to `/bitnami/solr/...`, but these were not getting updated automatically after a change. Now that we keep files in `/solr_confs` and change the paths in `solrconfig.xml`, updates to the jars are applied automatically.

## Restarting Solr
*this section is a draft*

With restarts on solr, the important part is to make sure the node is back online and joined/synced before restarting the next one.
Restarting is just `docker stop <container>` on each node. Then keeping an eye on the `/solr/` cloud nodes and graph.

In the `/solr/` web page, under the "Cloud" tab on the left there is a "Nodes" and "Graph" section. The nodes section shows if nodes are up and happy, the graph section reports on if the replicas are up and ready.

If the logs are mention being unable to elect a leader, scale to 3 nodes (so they see each other), then scale down to 1 node (last node sees other nodes leave) and then wait for a leader election timeout. The remaining node will eventually (logs will report a timeout period remaining) become the leader once the timeout happens and it stops waiting for its peers to return. Then scale back up to 3.

For example if `solr2` complains after all 3 nodes are restarted that it couldn't connect to `solr1`, restarting `solr1` again fixes it.
```log
ERROR (recoveryExecutor-10-thread-3-processing-solr2:8983_solr biblio9_shard1_replica_n1 biblio9 shard1 core_node3) [c:biblio9 s:shard1 r:core_node3 x:biblio9_shard1_replica_n1] o.a.s.c.RecoveryStrategy Failed to connect leader http://solr1:8983/solr on recovery, try again
```

If a replica won't come online, it could be a stuck leader. In which case changing who is leading that collection's replicas can allow the downed replica to come back online. In extreme cases where the replica just refuses to come back online, remove the replica and then re-add it (via API calls using curl).
This last case might be due to a split brain, so unless we are certain that isn't the case (i.e. we know no Solr updates have occurred), starting a reimport is probably a good idea.
We can try to change a leader by forcing an election (API call). Or if we stop the node, it'll lose leader status.
