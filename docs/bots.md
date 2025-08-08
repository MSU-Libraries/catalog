# Bots

When bots behave themselves and follow the rules, they are perfectly welcome to
crawl our site without it negatively impacting other users. But there are some
bots out there that make as many requests as they can without following the
configured bot settings. In these cases we want to block them since they can
over-utilize resources and slow down the site significantly for other users.

## Generalized bot protection service: go-away

The catalog comes with a built-in configurable `captcha` service based on the
`go-away` bot detection tool, which we've modified to add a custom theme to.
([original source repo](https://git.gammaspectra.live/git/go-away),
[modified source repo](https://gitlab.msu.edu/msu-libraries/public/go-away))

This captcha service proxies all traffic between Traefik and VuFind containers
so that it can identify and provide "challenges" for visitors. These challenges
are automatic and do not require any user input, but may in some cases show a
temporary challenge page for a second or two the first time a user visits.

The configuration and rule definition for what traffic is given challenges and
what traffic is considered okay versus suspected bots are available in the files:

* [`captcha/config.yml`](https://github.com/MSU-Libraries/catalog/blob/main/captcha/config.yml)
* [`captcha/rules.yml`](https://github.com/MSU-Libraries/catalog/blob/main/captcha/policy.yml)

Documentation on how to configure `go-away` is available on their [wiki](https://git.gammaspectra.live/git/go-away/wiki/?action=_pages).

## How to identify and block specific bot sources

<!-- markdownlint-disable MD031 MD013 -->
1. To identify that there is a bot "attack" happening, you can check the CPU
   and memory usage of the catalog container. If you see unusually high CPU load
   versus normal traffic, this might be an indicator of bots. Additionally,
   if you see a rise in the number of requests to the catalog (such as from the
   [Apache Requests Graph](https://catalog.lib.msu.edu/monitoring/graphs/apache_requests/day)
   on the monitoring site), this can be an indication that bots are crawling
   the site aggressively
   ```bash
   # Checking container stats for CPU usage
   docker stats $(docker ps -q -f name=catalog-prod-catalog_catalog)
   ```

2. Find a time when you suspect the bots are causing excess load and grab the
   Apache access logs for the stack to identify the IP(s) or user agent(s):
   ```bash
   # Watching current logs in the docker logs volume on a host machine
   tail -f -n10 /var/lib/docker/volumes/catalog-prod_logs/_data/apache/access.log
   # Getting logs for a specific time from an older access log
   zgrep -F "29/Jul/2025:16:42:" /var/lib/docker/volumes/catalog-prod_logs/_data/apache/access.log.2.gz
   ```

3. If there is a distinct bot user-agent, just use that for the block. But
   if you only have an IP, then determine the best CIDR range to block.
   The output from the `whois` command can help you identify the larger CIDR range
   boundaries that you'll want to block (if no CIDR is provided by `whois` then
   you may need to compute this yourself).
   ```bash
   whois [BAD-IP]
   
   ...
   % Information related to '[START-IP] - [END-IP]'
   ...
   netname:        [BOT NAME]
   ...
   ```

4. Be cautious to not block what might be real users; though bots often fake their
   user agents to appear like regular browser, sometimes real users might have
   an unusual user agent too. Look for wider patterns to help identify bots.
   But once you have a CIDR range or user agent you know you want blocked, go
   and edit the `policy.yml` file for the `captcha` service. You could create
   a new rule if needed, but existing rules are likely sufficient. Consider adding
   IP ranges to the `malicious` network list and user agents to the
   `undesired-crawlers` rule.

5. Deploying the change will apply the bot change to the specific environment
   only (e.g.  `devel-mysite` or `catalog-preview`); you will need to merge into
   `main` and deploy to production for the change to take effect.

## Emergency Live Changes

If you need to make an __emergency__ change, you can do so live, but do so
with great caution. Making a live change will be _overwritten_ next time that
environment is deployed. To make the change, either scale the `captcha` service
down to a single container, or you can make the change individually in all 3
`captcha` containers.

For example, to make live changes to `catalog-prod` deployment:

```bash
# Scale down to 1 container
docker service scale catalog-prod-catalog-captcha=1
# Connect to that captcha container
pc-connect catalog-prod-catalog-captcha -c bash

# Inside the container, edit the policy.yml file as desired
vim /policy.yml
# Once saved, send a SIGHUP to the go-away service to reload configs
kill -HUP 1
```

The live change should now be applied to that one running container.

<!-- markdownlint-enable MD031 MD013-->
