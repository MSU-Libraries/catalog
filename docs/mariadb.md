# MariaDB

## Troubleshooting

In the event of the database getting de-clustered, where the nodes are un-able to bootstrap themselves, you
will need to manually determine which node should be started up first and be the one with the most up-to-date
source of data.

To do this, first remove the database stack so that all the containers can be stopped as gracefully as possible.

```bash
docker stack rm [STACK_NAME]-mariadb
```

Then look at the MariaDb volume data on each of the nodes to determine which had the latest timestamps on it's files
across all the nodes. This can vary on a file-by-file basis, but generally there should be a node that is more ahead
than others or a table that is more important than others and is more up-to-date (i.e. user data vs session data).

```bash
# Run this on all of your nodes and compare timestamps

# Particular file to look at would be: gvwstate.dat, grastate.dat, mysql-bin*
ls -ltr /var/lib/docker/volumes/[STACK_NAME]-mariadb_db-bitnami/_data/mariadb/data

# Key files (tables) here are: change_tracker.ibd, user.ibd, user_list.ibd
ls -ltr /var/lib/docker/volumes/[STACK_NAME]-mariadb_db-bitnami/_data/mariadb/data/vufind

# If the timestamps are too similar, try using `stat` to get a more accurate time!
stat grastate.dat
```

This step can be tricky since some of the files may have more current timestamps on one node, but then one other
node may have the most current timestamp for another particular file. Use your best judgement here. Generally the
top level files are more important (the galera state files and binary logs where it tracks changes), but you also
don't want to lose data from the `vufind` database. Making sure you have your backup located before attempting this
would be a good idea if you are not confident in which node to pick.

Once you have the node number you want to bring up as your source of truth, update the
`docker-compose.mariadb-cloud-force.yml` file and update the `"node.labels.nodeid==N"`
to change the `N` to you your node number, i.e. a value 1-3. Then also update the
`max_replicas_per_node` to `1` to indicate that you're ready to deploy.

Now we're ready to bring back up the stack with just the single node in bootstrap mode.

```bash
docker stack deploy --with-registry-auth -c <(docker-compose -f docker-compose.mariadb-cloud-force.yml config) [STACK_NAME]-mariadb
docker service logs -f
```

Watch the logs until the state is happy and ready for connections (meaning that it will say "ready for connections"
in the logs towards the end and stop printing messages). Then *bring the stack down again* so it
can be re-deployed with the regular cloud compose file. It is important to bring the stack down first so that
it can cleanly stop first and disable its bootstrap state before the other nodes come online.

```bash
docker stack rm [STACK_NAME]-mariadb
# wait for the container to stop
docker stack deploy --with-registry-auth -c <(docker-compose -f docker-compose.mariadb-cloud.yml config) [STACK_NAME]-mariadb
```

The stack should now come back up with all of the nodes being healthy and joined to the cluster.

### Verifying cluter health

You can verify that all nodes are joined to the cluster via the Docker service logs and scrolling up to look
for the members list. There should be a list similar to:

```bash
members(3):
 	0: 1557dc68-6d5b-11ed-811e-5f59f6f49aa8, galera3
 	1: 15663de2-6d5b-11ed-b380-2abbd5ec6e2b, galera2
 	2: f48d4f88-6d5a-11ed-978c-1b318d6b5649, galera1
```

You can also verify the cluster status by connecting to the database from one of the nodes and querying for the
WSREP status:

```bash
SHOW GLOBAL STATUS LIKE '%wsrep%'\G
```

some key values are the `wsrep_cluster_size` (which should match the number of nodes you have) and the
`wsrep_cluster_state_uuid` (which should be the same on all of the nodes).
