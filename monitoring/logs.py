import pathlib
import flask
import requests

TIMEOUT = 10

def node_logs(service):
    paths = {
        'vufind':         '/mnt/logs/vufind/vufind.log',
        'apache/error':   '/mnt/logs/apache/error.log',
        'apache/access':  '/mnt/logs/apache/access.log',
        'simplesamlphp':  '/mnt/logs/simplesamlphp/simplesamlphp.log',
        'mariadb':        '/mnt/logs/mariadb/mysqld.log',
        'traefik/log':    '/mnt/traefik_logs/traefik/traefik.log',
        'traefik/access': '/mnt/traefik_logs/traefik/access.log',
    }
    if service in paths:
        path = pathlib.Path(paths[service])
        if path.is_file():
            return path.read_text(encoding="utf8")
        return 'Log file does not exist on this node.'
    return 'Error: unknown service.'

def logs_vufind(service):
    logs = []
    for node in range(1, 4):
        try:
            req = requests.get(f'http://monitoring{node}/monitoring/node/logs/{service}', timeout=TIMEOUT)
            req.raise_for_status()
            contents = req.text
        except (requests.exceptions.HTTPError, requests.exceptions.RequestException) as err:
            contents = f'Error reading the log: {err}'
        logs.append(contents)
    return flask.render_template('logs.html', service=service, log1=logs[0], log2=logs[1], log3=logs[2])
