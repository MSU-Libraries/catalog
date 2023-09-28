# VuFind

## Troubleshooting
[Vufind's troubleshooting page](https://vufind.org/wiki/development:troubleshooting)
is a good reference for debugging options, but we'll highlight a few
specifics we've found helpful.

* The first spot to check for errors is the Docker service logs which
contain both Apache error and access logs (these logs can help identify
critical issues such as the innability to connect to the database) as well
as Vufind's application logs. To view service logs:

```bash
docker service logs -f ${STACK_NAME}-catalog_catalog
```

* To enable debug messages to be printed to the service logs,
update the `file` setting under the `[Logging]` section
in the `local/config/vufind/config.ini` file to include `debug`.
For example: `file = /var/log/vufind/vufind.log:alert,error,notice,debug`

!!! warning "Warning with `debug=true`"
    You can set `debug=true` in the main `config.ini` file to have debug messages
    to be printed to the page instead of the service logs, but be warned that sometime
    too much information is printed to the page
    when `debug` is set to `true`, such as passwords used for ILS calls
    (hopefully this can be fixed to remove that from debug messages entirely).

* To enable higher verbosity in the Apache logging, you can update the
`LogLevel` in the `/etc/apache2/sites-enabled/000-default.conf` file and
then run `apachectl graceful` to apply the changes.

* To enable stack traces to be shown on the page when ever any
exception is thrown, edit the `local/httpd-vufind.conf` file and
uncomment the line that says: `SetEnv VUFIND_ENV development`. Write
the changes to the file and then run `apachectl graceful` to apply
the change.
