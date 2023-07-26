# Load Testing

Load testing can be done against your instance at any time by running the included
[load-test.js](https://github.com/MSU-Libraries/catalog/tree/main/vufind/tests/load_test.js)
script, which uses [k6](https://k6.io/docs/) to submit requests with the given parameters
and provide you a report with the results at the end.

Ideally, you will want to also want to have a connection to your server while you are running
these tests so that you can monitor them for CPU load (using a tool like `htop`) to see what
combination of parameters is the limit your instance can handle. For example, your server
might start to consistently see 100% CPU usage and return failed request responses with slow
response times when you get past 200 users for the search page URL, but the same 200 users
might be fine for the home page URL. This can help you focus your efforts on what pages or
areas of your infrastructure to optomize and know what limits you can expect of your servers.

The script itself has instructions for using it, but as a quick example, you could quickly
run these tests locally within a Docker container:

```bash
# Queries the catalog home page with 100 users over a 1 minute duration
docker run --rm -v /path/to/load_test.js:/load_test.js grafana/k6 run -u 100 -d 1m /load_test.js --env URL="https://catalog.lib.msu.edu"
```