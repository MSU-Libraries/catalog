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
navigating to the Traefik dashboard at https://your-site/dashboard/
where you can see all the routers and services that have been defined.
This is helpful when the issue is a configuration issue either in the
Traefik command or labels.

* When you have basic authentication enabled, ensure that the
password hash has an appropriate cost setting; 9 or less might make
brute forcing easier, while 12 or higher will add significant amounts
of CPU load to Traefik, causing page loads to be extremely slow. A setting
of 10 is recommended.
```
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
