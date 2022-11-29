import flask
import pathlib
import requests

TIMEOUT = 10

def raise_exception_for_reply(r):
    raise Exception('Status code: {}. Response: "{}"'.format(r.status_code, r.text))

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
    if (service in paths):
        path = pathlib.Path(paths[service])
        if (path.is_file()):
            return path.read_text()
        else:
            return 'Log file does not exist on this node.'
    else:
        return 'Error: unknown service.'

def logs_vufind(service):
    logs = []
    for node in range(1, 4):
        contents = ''
        try:
            r = requests.get('http://monitoring{}/monitoring/node/logs/{}'.format(node, service), timeout=TIMEOUT)
            if r.status_code != 200:
                raise_exception_for_reply(r)
            contents = r.text
        except Exception as err:
            contents = 'Error reading the log: {}'.format(err)
        logs.append(contents)
    return flask.render_template('logs.html', service=service, log1=logs[0], log2=logs[1], log3=logs[2])
