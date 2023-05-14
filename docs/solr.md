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
