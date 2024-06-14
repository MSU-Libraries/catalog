# Bots

When bots behave themselves and follow the rules, they are perfectly welcome to crawl our site
without it negatively impacting other users. But there are some bots out there that
make as many requests as they can without following the configured bot settings. In these
cases we want to block them since they can over-utilize resources and slow down the site
significantly for other users.

## How to identify and block bots
1. To identify that there is a bot "attack" happening, you can check the CPU and memory usage of the catalog container.
   In our case, their values will be under 20%, and be around 80% or more if there is heavy bot traffic.

   ```bash
   docker stats $(docker ps -q -f name=catalog-prod-catalog_catalog)
   ```

2. Tail the traefik access log to identify the IP or user agent making a drastic number of requests:

   ```bash
   tail -f -n10 /var/lib/docker/volumes/traefik_logs/_data/access.log
   ```

3. If there is a distinct bot user-agent, just use that for the block. But if you only have an IP,
   then determine the best CIDR range to block. The top of the output from the `whois` command
   will tell you the range that you'll want to block (just convert it to CIDR afterward).

   ```bash
   whois [BAD-IP]
   
   ...
   % Information related to '[START-IP] - [END-IP]'
   ...
   netname:        [BOT NAME]
   ...
   ```

4. Now that we know what to block, update the
   [badbots compose file](https://gitlab.msu.edu/msu-libraries/devops/catalog-infrastructure/-/blob/main/configure-playbook/roles/core-stacks/files/docker-compose.badbots.yml)
   with the new `ClientIP` or `User-Agent` in *both* rules in that file. If you know the name
   of the bot (also shown in the `whois` output) then add a comment block in the file for reference.

   ```yaml
   # >> [BOT] [CIDR-RANGE]
   # >> [BOT] User-Agent [BOT1]
   - "traefik.http.routers.badbot-http-router.rule=ClientIP(`[CIDR-RANGE]`) || ClientIP(`[ANOTHER-CIDR]`) || HeadersRegexp(`User-Agent`, `(?i)([BOT1]|[BOT2])`)"
   - "traefik.http.routers.badbot-https-router.rule=ClientIP(`43.128.0.0/10`) || ClientIP(`[ANOTHER-CIDR]`) || HeadersRegexp(`User-Agent`, `(?i)([BOT1]|[BOT2])`)"
   ```

5. Deploying the change will block the bot on all your environments. You can also optionally
   restart the `catalog_caalog` containers on the nodes if the CPU/memory usage didn't
   drop.

   ```bash
   docker stop $(docker ps -q -f name=catalog-prod-catalog_catalog)
   ```
