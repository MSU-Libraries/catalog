# Traefik

## Re-deploying

If you ever need to re-deploy the stack, you can use the
[pc-deploy](helper-scripts.md#deploy-helper-pc-deploy) script.

Make sure you run it as the deploy user so that the proper Docker
container registry credentials are passed.

```bash
sudo -Hu deploy pc-deploy core-stacks traefik
```

## Troubleshooting

* Your first line of defense when debugging issues with Traefik is
navigating to the Traefik dashboard at
[https://your-site/dashboard/](https://your-site/dashboard/)
where you can see all the routers and services that have been defined.
This is helpful when the issue is a configuration issue either in the
Traefik command or labels.

* When you have basic authentication enabled, ensure that the
password hash has an appropriate cost setting; 9 or less might make
brute forcing easier, while 12 or higher will add significant amounts
of CPU load to Traefik, causing page loads to be extremely slow. A setting
of 10 is recommended.

```bash
htpasswd -n -B -C 10 mylogin
```

* To debug performance issues in Traefik, you can enable debug
mode by adding to the traefik service: `--api.debug=true`.
This enables all the [debug endpoints](https://doc.traefik.io/traefik/operations/api/#debug).

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

## Resetting the Let's Encrypt Config

Periodically you may want to reset the Let's Encrypt config to clear out
old certificates for sites that no longer exist. This is the
process you will want to use that will ensure there is limited downtime.

### On the development nodes

* Create temporary node labels to identify which node should be taken offline.
  Start with taking the 3rd node offline.

```bash
docker node update --label-add deployglobal=true catalog-1-dev.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-2-dev.aws.lib.msu.edu
docker node update --label-add deployglobal=false catalog-3-dev.aws.lib.msu.edu
```

* Create a temporary service constraint to only allow the nodes with the label
  value of `true` to be online.

```bash
docker service update --constraint-add 'node.labels.deployglobal == true' traefik_traefik
```

* On the 3rd node, clear out the Let's Encrypt config

```bash
mkdir -p /tmp/acme_backup/
mv /var/lib/docker/volumes/traefik_traefik/_data/*.json /tmp/acme_backup
```

* Swap the label values to take the 2nd node offline

```bash
docker node update --label-add deployglobal=true catalog-1-dev.aws.lib.msu.edu
docker node update --label-add deployglobal=false catalog-2-dev.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-3-dev.aws.lib.msu.edu
```

* On the 2nd node, clear out the Let's Encrypt config

```bash
mkdir -p /tmp/acme_backup/
mv /var/lib/docker/volumes/traefik_traefik/_data/*.json /tmp/acme_backup
```

* Swap the label values to take the 1st node offline

```bash
docker node update --label-add deployglobal=false catalog-1-dev.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-2-dev.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-3-dev.aws.lib.msu.edu
```

* On the 1st node, clear out the Let's Encrypt config

```bash
mkdir -p /tmp/acme_backup/
mv /var/lib/docker/volumes/traefik_traefik/_data/*.json /tmp/acme_backup
```

* Remove the labels and temporary service constraint

```bash
docker service update --constraint-rm 'node.labels.deployglobal == true' traefik_traefik
docker node update --label-rm  deployglobal catalog-1-dev.aws.lib.msu.edu
docker node update --label-rm  deployglobal catalog-2-dev.aws.lib.msu.edu
docker node update --label-rm  deployglobal catalog-3-dev.aws.lib.msu.edu
```

### On the production nodes

* Update the local DNS entries that are pointing to the `.aws.lib.msu.edu`
  sites and have them point to the 1st node and wait for DNS to update

* On the 1st node, we will need to prevent traefik from running temporarily
  while we quickly remove the certificate files. *DO THIS QUICKLY
  TO LIMIT DOWNTIME*.

```bash
docker node update --label-add deployglobal=false catalog-1.aws.lib.msu.edu
docker node update --label-add deployglobal=false catalog-2.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-3.aws.lib.msu.edu
docker service update --constraint-add 'node.labels.deployglobal == true' traefik_traefik
# Wait for the traefik container to stop
watch 'docker ps | grep traefik'

mkdir -p /tmp/acme_backup/
mv /var/lib/docker/volumes/traefik_traefik/_data/*.json /tmp/acme_backup
docker node update --label-add deployglobal=true catalog-1.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-2.aws.lib.msu.edu
docker node update --label-add deployglobal=false catalog-3.aws.lib.msu.edu
tail -f /var/lib/docker/volumes/traefik_logs/_data/traefik.log -n10

## If logs are unhealthy or unable to create new certificates, restore previous ones
cp /tmp/acme_backup/* /var/lib/docker/volumes/traefik_traefik/_data/
```

* Update local DNS to point now to the 2nd node and wait for DNS to update

* On the 2nd node, prevent traefik from running temporarily to remove the old
  certificate files.

```bash
docker node update --label-add deployglobal=true catalog-1.aws.lib.msu.edu
docker node update --label-add deployglobal=false catalog-2.aws.lib.msu.edu
docker node update --label-add deployglobal=false catalog-3.aws.lib.msu.edu
# Wait for the traefik container to stop
watch 'docker ps | grep traefik'

mkdir -p /tmp/acme_backup/
mv /var/lib/docker/volumes/traefik_traefik/_data/*.json /tmp/acme_backup
docker node update --label-add deployglobal=false catalog-1.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-2.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-3.aws.lib.msu.edu
tail -f /var/lib/docker/volumes/traefik_logs/_data/traefik.log -n10

## If logs are unhealthy or unable to create new certificates, restore previous ones
cp /tmp/acme_backup/* /var/lib/docker/volumes/traefik_traefik/_data/
```

* Update local DNS to point now to the 3rd node and wait for DNS to update

* On the 3rd node, prevent traefik from running temporarily to remove the old
  certificate files.

```bash
docker node update --label-add deployglobal=true catalog-1.aws.lib.msu.edu
docker node update --label-add deployglobal=false catalog-2.aws.lib.msu.edu
docker node update --label-add deployglobal=false catalog-3.aws.lib.msu.edu
# Wait for the traefik container to stop
watch 'docker ps | grep traefik'

mkdir -p /tmp/acme_backup/
mv /var/lib/docker/volumes/traefik_traefik/_data/*.json /tmp/acme_backup
docker node update --label-add deployglobal=true catalog-1.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-2.aws.lib.msu.edu
docker node update --label-add deployglobal=true catalog-3.aws.lib.msu.edu
tail -f /var/lib/docker/volumes/traefik_logs/_data/traefik.log -n10

## If logs are unhealthy or unable to create new certificates, restore previous ones
cp /tmp/acme_backup/* /var/lib/docker/volumes/traefik_traefik/_data/
```

* Remove the temporary constraint and labels

```bash
docker service update --constraint-rm 'node.labels.deployglobal == true' traefik_traefik
docker node update --label-rm  deployglobal catalog-1.aws.lib.msu.edu
docker node update --label-rm  deployglobal catalog-2.aws.lib.msu.edu
docker node update --label-rm  deployglobal catalog-3.aws.lib.msu.edu
```
