# MariaDB

## Helpers

To quick-connect to the database within the container (without having to look
up the password from the Docker secret or CI variable), simply use the
`connect` command.

```bash
docker exec -it $(docker ps -q -f name=catalog-prod-mariadb_galera) connect
# Or:
pc-connect catalog-prod-mariadb_galera
# Then once in the container, run:
connect
```

## Re-deploying

If you ever need to re-deploy the stack, you can use the
[pc-deploy](helper-scripts.md#deploy-helper-pc-deploy) script.

Make sure you run it as the deploy user so that the proper Docker
container registry credentials are passed.

```bash
sudo pc-deploy catalog-prod mariadb-cloud
```

## Restarting

If you need to restart the Galera cluster, the easiest method is to re-run
the CI job that deploys the DB updates. But alternatively, you can restart
the containers one-by-one. Just wait for the restarted container to be
"healthy" before restarting the next.

```bash
docker stop $(docker ps -q -f name=catalog-prod-mariadb_galera)
# Wait for the new container to report healthy
watch 'docker ps | grep catalog-prod-mariadb_galera'
# Now repeat those two steps on the remaining nodes in the cluster
# Once complete, run a final check on the cluster
sudo /usr/local/ncpa/plugins/check_galera.sh catalog-prod
```

## Troubleshooting

### Restoring the cluster

In the event of the database getting de-clustered, where the nodes are
unable to bootstrap themselves, you will need to manually determine which
node should be started up first and be the one with the most up-to-date
source of data.

* To do this, first remove the database stack so that all the containers
  can be stopped as gracefully as possible.

```bash
STACK_NAME=catalog-prod
docker stack rm $STACK_NAME-mariadb
```

* Then look at the MariaDB volume data on each of the nodes to determine
  which had the latest timestamps on its files across all the nodes. This
  can vary on a file-by-file basis, but generally there should be a node
  that is more ahead than others or a table that is more important than
  others and is more up-to-date (i.e. user data vs session data).
  This step can be tricky since some of the files may have more current
  timestamps on one node, but then one other node may have the most current
  timestamp for another particular file. Use your best judgement here.
  Generally the top level files are more important (the galera state files
  and binary logs where it tracks changes), but you also don't want to lose
  data from the `vufind` database.

```bash
# Run these on all of your nodes as root and compare timestamps

STACK_NAME=catalog-prod
sudo -s # root is required to view Docker volume data
cd /var/lib/docker/volumes/${STACK_NAME}-mariadb_db-bitnami/_data/mariadb/data

# Particular file to look at would be (in general order of importance):
# grastate.dat, gvwstate.dat, mysql-bin*
ls -ltr ./

# Key files (tables) here are (in general order of importance):
# change_tracker.ibd, user.ibd, user_list.ibd
ls -ltr vufind

# If the timestamps are too similar,
# try using `stat` to get a more accurate time!
stat grastate.dat
```

* Once you have a node number you want to use as your source of truth, first
  take a backup of that volume data. This is in case the recovery goes wrong
  (for example, you bring up your node, then realize you wanted to use another
  node and switch to the other one, then the sync happens and you end up with
  two nodes without data).

<!-- markdownlint-disable MD013 -->
```bash
STACK_NAME=catalog-prod

# Remove the -n flag if the dry-run looks good
rsync -ain /var/lib/docker/volumes/${STACK_NAME}-mariadb_db-bitnami/_data/mariadb/data/ /tmp/${STACK_NAME}_mariadb_backup_$(date -I)/
```
<!-- markdownlint-enable MD013 -->

!!! note "Restoring from the backup"
    If you need to use this backup, for example, if you loose data on multiple nodes,
    remove the stack again then run the rsync command in the opposite direction to
    restore the files back to their volume and run the below steps again to force
    bootstrap the cluster with that node.

* Now we're ready to start recovering the cluster from the selected node by
  updating the
  `/home/deploy/${STACK_NAME}/docker-compose.mariadb-cloud-force.yml`
  file located on node 1 in the cluster, and setting the
  `"node.labels.nodeid==N"` to change the `N` to you
  your node number, i.e. a value 1-3. Then also update the
  `max_replicas_per_node` to `1` to indicate that you're ready to deploy.

* Now we're ready to bring back up the stack with just the single node in
  bootstrap mode.

<!-- markdownlint-disable MD013 -->
```bash
STACK_NAME=catalog-prod
sudo pc-deploy $STACK_NAME mariadb-cloud-force -v
docker service logs -f -n10 $STACK_NAME-mariadb_galera
```
<!-- markdownlint-enable MD013 -->

* Watch the logs until the state is happy and ready for connections (meaning
  that it will say "ready for connections" in the logs towards the end and
  stop printing messages). Then *bring the stack down again*, so it
  can be re-deployed with the regular cloud compose file. It is important
  to bring the stack down first so that it can cleanly stop first and disable
  its bootstrap state before the other nodes come online.

<!-- markdownlint-disable MD013 -->
```bash
STACK_NAME=catalog-prod
docker stack rm $STACK_NAME-mariadb
# wait for the container to stop, then re-deploy the original stack
sudo pc-deploy $STACK_NAME mariadb-cloud -v
```

!!! note "Note on recovery time"
    It can take a few minutes to have each node come online, and it may appear as if
    their volumes are empty during the recovery process. This is because the SST, or
    state transfer, process is happening where it clears out the data and syncs it
    from the leader node into a hidden `sst` directory until it completes. Depending on
    the size of the database (particularly the sessions table) it can take a bit longer,
    but typically less than 10 minutes. And it will only do this on one node at a time.
    **So be patient while waiting for the recovery!** The service logs will indicate that
    an SST is in progress and you will be able to run the `du` command on the volumes
    to see them slowly growing.

<!-- markdownlint-enable MD013 -->

* The stack should now come back up with all the nodes being healthy and
  joined to the cluster. You can use the steps from [verifying the cluster health](#verifying-cluster-health)
  section below.

* Open the `docker-compose.mariadb-cloud-force.yml` file again and restore it
  back to it's original state so it is ready to use for the next time.
  And so that someone doesn't accidentally deploy using that file without
  modifying it again.
  The file will be re-deployed automatically every time the pipeline is
  run, but this is in case that doesn't happen as frequently.

* The final step is now to remove your leftover backup to save disk space.

```bash
STACK_NAME=catalog-prod
sudo rm -rf /tmp/${STACK_NAME}_mariadb_backup_$(date -I)/
```

### Verifying cluster health

You can verify that all nodes are joined to the cluster via the Docker service
logs and scrolling up to look for the members list. There should be a list
similar to:

```bash
members(3):
    0: 1557dc68-6d5b-11ed-811e-5f59f6f49aa8, galera3
    1: 15663de2-6d5b-11ed-b380-2abbd5ec6e2b, galera2
    2: f48d4f88-6d5a-11ed-978c-1b318d6b5649, galera1
```

You can also verify the cluster status by connecting to the database from
one of the nodes and querying for the WSREP status:

```bash
SHOW GLOBAL STATUS LIKE '%wsrep%'\G
```

some key values are the `wsrep_cluster_size` (which should match the number
of nodes you have) and the `wsrep_cluster_state_uuid` (which should be the
same on all the nodes).

Using the NCPA checks deployed via the catalog-infrastructure repository will
also run many of these health checks.

```bash
sudo /usr/local/ncpa/plugins/check_galera.sh catalog-prod
```

### The `grastate.dat` File

You really shouldn't ever *need* to go look at the `grastate.dat` file, but if you
do, it will quickly tell you the state of the node in the cluster. The file is located
at `/var/lib/docker/volumes/${STACK_NAME}-mariadb_db-bitnami/_data/mariadb/data/grastate.dat`.

If you look inside that file on any healthy running node in the cluster:

```bash
# GALERA saved state
version: 2.1
uuid:    2931f986-b407-11f0-b51e-777ecb991546
seqno:   -1
safe_to_bootstrap: 0
```

Or in a healthy stopped node in the cluster
(if the rest of the cluster is still up):

```bash
# GALERA saved state
version: 2.1
uuid:    2931f986-b407-11f0-b51e-777ecb991546
seqno:   9070
safe_to_bootstrap: 0
```

Or when the cluster has shutdown cleanly (from the last shutdown node):

```bash
# GALERA saved state
version: 2.1
uuid:    2931f986-b407-11f0-b51e-777ecb991546
seqno:   9091
safe_to_bootstrap: 1
```

When the cluster has shutdown cleanly (from the earlier shutdown node):

```bash
# GALERA saved state
version: 2.1
uuid:    2931f986-b407-11f0-b51e-777ecb991546
seqno:   9070
safe_to_bootstrap: 0
```

Or on all the nodes when the cluster has stopped uncleanly (i.e. when they're
logging that no one is safe to bootstrap:

```bash
# GALERA saved state
version: 2.1
uuid:    2931f986-b407-11f0-b51e-777ecb991546
seqno:   -1
safe_to_bootstrap: 0
```

Comparing these, you can immediately notice some patterns:

* When the node has shutdown cleanly, it will have a `seqno` value of a
  positive number
* The "leader" node, if shutdown cleanly, will have `safe_to_bootstrap`
  set to `1`. This means that if *none* of the nodes have a value of `1` if
  they are all shutdown, then the cluster was *not* shutdown cleanly.
* None of the nodes should have `safe_to_bootstrap` set to `1` when the
  node is running, not even the "leader" node.
* The `uuid` field is unique to each node in the cluster, and can be seen
  in the service logs when you see the member list printed.

## How to break the cluster

...for testing purposes only of course. In case you want to test-run the recovery
process, you can intentionally get your Galera cluster in an unhappy state by
force stopping all of the containers on all the nodes at the same time.

Log on to each of the nodes in your cluster and run the commands as close together
as possible:

```bash
STACK_NAME=devel-env
docker kill $(docker ps -q -f name=$STACK_NAME-mariadb_galera)
```

You should be able to tell from the service logs that the cluster is unable to
recover on it's own now and needs your help!

```bash
docker service logs -f -n100 $STACK_NAME-mariadb_galera
# Look for:
# No nodes online and I cannot bootstrap.
# Another node must do the bootstrap.
```
