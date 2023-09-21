import pathlib
import asyncio
import gzip
import re
import subprocess
import flask
import aiohttp

import util


MAX_FULL_FILE = 10*1024*1024 # Max file size to return the full contents; arbitrary 10 MB
BEGIN_END_BYTES = MAX_FULL_FILE // 2
TIMEOUT = 10


def read_beginning_and_end(path):
    command = f'head -c {BEGIN_END_BYTES} {path}; echo -e "\n\n[...]\n"; tail -c {BEGIN_END_BYTES} {path}'
    try:
        process = subprocess.run(["/bin/sh", "-c", command],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error reading log file {path}: {err.stderr}\n"
    except subprocess.TimeoutExpired:
        return f"Timeout reading log file {path}\n"
    return process.stdout


def add_file_to_log(path, full_log):
    if path.stat().st_size > MAX_FULL_FILE:
        log_text = 'Detected a large file. Showing beginning and end...\n' + read_beginning_and_end(path)
    else:
        if path.name.endswith('.gz'):
            with gzip.open(path, 'rt') as gz_file:
                log_text = gz_file.read()
        else:
            log_text = path.read_text(encoding="utf8", errors="ignore")

    if log_text != '':
        log_text = path.name + ':\n' + log_text
        if full_log != '':
            full_log = '\n---------------------------\n\n' + full_log
        full_log = log_text + full_log

    return full_log


def node_logs(service):
    paths = {
        'vufind':                  '/mnt/logs/vufind/vufind.log',
        'apache/error':            '/mnt/logs/apache/error.log',
        'apache/access':           '/mnt/logs/apache/access.log',
        'simplesamlphp':           '/mnt/logs/simplesamlphp/simplesamlphp.log',
        'mariadb':                 '/mnt/logs/mariadb/mysqld.log',
        'traefik/log':             '/mnt/traefik_logs/traefik.log',
        'traefik/access':          '/mnt/traefik_logs/access.log',
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
    full_log = ''

    latest_matching_paths = path.parent.glob(re.sub(r"\.log", "_latest.log.*", path.name))
    for latest_path in latest_matching_paths:
        full_log = add_file_to_log(latest_path, full_log)

    if not path.is_file():
        return f'Log file does not exist on this node: {path.name}'
    full_log = add_file_to_log(path, full_log)

    rotated_1 = pathlib.Path(f'{paths[service]}.1')
    if not rotated_1.is_file():
        return full_log
    full_log = add_file_to_log(rotated_1, full_log)

    for i in range(2, 4):
        rotated_gz = pathlib.Path(f'{paths[service]}.{i}.gz')
        if rotated_gz.is_file():
            full_log = add_file_to_log(rotated_gz, full_log)

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
