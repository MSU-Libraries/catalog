# General

This page provides general administrative help and troubleshooting
tips.

## Docker

### Host resolution errors

Sometimes Docker can have seemingly mysterious network issues, where
you might see errors like this in the logs:

<!-- markdownlint-disable MD013 MD031 -->
```bash
*5 monitoring could not be resolved (3: Host not found), client: 192.168.0.66, server: proxysolr
```
<!-- markdownlint-enable MD013 MD031 -->

With a multi-node cluster, sometimes the overlay network can start having
issues and needs to be recreated for that service. This can be done, by
removing the service (or stack) and then redeploying it.

For example, to redeploy just `proxysolr` (since we don't want to remove
the whole Solr stack):

```bash
docker service rm catalog-preview-solr_proxysolr
sudo pc-deploy catalog-preview solr-cloud
```

But if it was the Monitoring application having issues, we'd be ok temporarily removing
all those services and could just do this:

```bash
docker stack rm catalog-preview-monitoring
sudo pc-deploy catalog-preview monitoring
```

### Upgrade errors

If you find you're having issues with a newer version of `docker.io`,
(like we did with 28.2.2) you can revert it to your last working version,
found in `/var/log/apt/history.log` using:

```bash
# 27.5.1-0ubuntu3~22.04.2 was the version
# from /var/log/apt/history.log that was running previously
sudo apt install docker.io=27.5.1-0ubuntu3~22.04.2
sudo apt-mark hold docker.io
```

You can also review the [release notes](https://docs.docker.com/engine/release-notes/28/)
to identify if there are known issues in the release that are fixed in newer releases,
so you can safely remove the `apt-mark hold`.
