import pathlib
import asyncio
import flask
import aiohttp

import util


def node_logs(service):
    paths = {
        'vufind':                  '/mnt/logs/vufind/vufind.log',
        'apache/error':            '/mnt/logs/apache/error.log',
        'apache/access':           '/mnt/logs/apache/access.log',
        'simplesamlphp':           '/mnt/logs/simplesamlphp/simplesamlphp.log',
        'mariadb':                 '/mnt/logs/mariadb/mysqld.log',
        'traefik/log':             '/mnt/traefik_logs/traefik/traefik.log',
        'traefik/access':          '/mnt/traefik_logs/traefik/access.log',
        'harvests/folio':          '/mnt/logs/harvests/folio.log',
        'harvests/hlm':            '/mnt/logs/harvests/hlm.log',
        'harvests/authority':      '/mnt/logs/harvests/authority.log',
        'vufind/reserves_update':  '/mnt/logs/vufind/reserves_update.log',
        'solr/alphabrowse':        '/mnt/logs/solr/alphabrowse.log',
        'vufind/searches_cleanup': '/mnt/logs/vufind/searches_cleanup.log',
        'backups/solr':            '/mnt/logs/backups/solr.log',
        'backups/db':              '/mnt/logs/backups/db.log',
    }
    if service in paths:
        path = pathlib.Path(paths[service])
        if path.is_file():
            return path.read_text(encoding="utf8")
        return 'Log file does not exist on this node.'
    return 'Error: unknown service.'

def logs_vufind(service):
    urls = []
    for node in range(1, 4):
        urls.append(f'http://monitoring{node}/monitoring/node/logs/{service}')
    try:
        logs = util.async_get_requests(urls)
    except aiohttp.ClientError as err:
        return f'Error reading the {service} log: {err}'
    except asyncio.exceptions.TimeoutError:
        return f'Timeout when reading the {service} log'
    return flask.render_template('logs.html', service=service, log1=logs[0], log2=logs[1], log3=logs[2])
