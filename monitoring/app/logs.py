import pathlib
import asyncio
import gzip
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
    if service not in paths:
        return 'Error: unknown service.'

    path = pathlib.Path(paths[service])
    if not path.is_file():
        return 'Log file does not exist on this node.'

    full_log = path.read_text(encoding="utf8")

    rotated_1 = pathlib.Path(f'{paths[service]}.1')
    if not rotated_1.is_file():
        return full_log

    log_text = rotated_1.read_text(encoding="utf8")
    if log_text != '':
        if full_log != '':
            full_log = '\n---------------------------\n\n' + full_log
        full_log = log_text + full_log
    for i in range(2, 4):
        rotated_gz = pathlib.Path(f'{paths[service]}.{i}.gz')
        if rotated_gz.is_file():
            with gzip.open(rotated_gz, 'rt') as rotated_gz_file:
                gz_log_text = rotated_gz_file.read()
                if gz_log_text != '':
                    if full_log != '':
                        full_log = '\n---------------------------\n\n' + full_log
                    full_log = gz_log_text + full_log
    return full_log

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
