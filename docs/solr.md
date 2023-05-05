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
