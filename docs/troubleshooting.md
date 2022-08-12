# Troubleshooting

## Vufind
[Vufind's troubleshooting page](https://vufind.org/wiki/development:troubleshooting)
is a good reference for debugging options, but we'll highlight a few 
specifics we've found helpful.

* The first spot to check for errors is the Apache error logs in
`/var/log/apache2/error.log` which can print critical issues such
as the innability to connect to the database.

* To enable stack traces to be shown on the page when ever any 
exception is thrown, edit the `local/httpd-vufind.conf` file and
uncomment the line that says: `SetEnv VUFIND_ENV development`. Write
the changes to the file and then run `apachectl graceful` to apply
the change.

* To enable debug messages to be displayed on the page, set `debug = true`
in the `local/config/vufind/config.ini` file. 

!!! warning "Warning with `deubg=true`"
    Be warned that sometime too much information is printed to the page
    when `debug` is set to `true`, such as passwords used for ILS calls 
    (hopefully this can be fixed to remove that from debug messages entirely).

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
mode by adding to the public-traefik service: `--api.debug=true`.
This enables all of the [debug endpoints](https://doc.traefik.io/traefik/operations/api/#debug).

```
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
