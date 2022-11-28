# Backup and Restore

# Backup
Included with this repository is a script and automated job to backup both the MariaDB
database and the Solr index to the shared storage location (`/mnt/shared`).

The script is part of the `vufind` image used by the `cron` service in the `catalog`
stack. The shared storage is mounted on the `solr` service so that the cluster has
the ability to write its snapshot data directly to it. For more information on how
SolrCloud backups work, see the
[official documentation](https://solr.apache.org/guide/8_9/making-and-restoring-backups.html#solrcloud-backups).

The database backup is a dump of all the tables while putting the galera node into a
desynchronized state while the backup is running to help ensure the backup is in a more
consistent state. In case the galera cluster ever gets into a de-clustered state,
this backup script will take a dump from all three of the galera nodes just to be
safe.

The automated job will run on a nightly basis and keep a rolling rotation of 3 backups
of both the database and Solr index.

The code for the backup script can be found at:
[backup.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/backup.sh)  
The schedule for the automated cron job can be found at:
[crontab](https://github.com/MSU-Libraries/catalog/blob/main/vufind/cron.d/crontab)

## Manual backup
If you want to manually run the script from the `catalog_cron` container (
or the `catalog_catalog` container) to ensure it runs sucessfully you could
run something similar to:
```bash
./backup.sh --db --solr --verbose
```

# Restore
Should the need to restore from one of these backups arise, simply use the provided
restore script giving it the path to the compressed backup you want to restore
using. You can restore one or more Solr collections at a time as well as the database.

The code for the backup script can be found at:
[restore.sh](https://github.com/MSU-Libraries/catalog/blob/main/vufind/restore.sh)

Examples for restoring from backups:
```bash
# Restoring the `biblio` index
./restore.sh --biblio /mnt/shared/backups/solr/biblio/snapshot.123.tar.gz

# Restoring the database
./restore.sh --db /mnt/shared/backups/db/dbbackup.tar

# Restore the database using the backup from node 2
./restore.sh --db /mnt/shared/backups/db/dbbackup.tar --node 2

# Restoring the `authority` and `biblio` index
./restore -b /tmp/biblio.tar.gz -a /tmp/authority.tar.gz -v 
```
