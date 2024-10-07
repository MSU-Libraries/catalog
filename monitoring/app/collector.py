import asyncio
import pathlib
import os
import re
import sys
from datetime import datetime, timedelta
from apscheduler.schedulers.background import BackgroundScheduler
import mariadb as db

import status
from util import get_eventloop


ACCESS_LOG_PATH = '/mnt/logs/apache/access.log'


def init(debug: bool):
    if not debug or os.getenv('WERKZEUG_RUN_MAIN') == 'true':
        scheduler = BackgroundScheduler()
        scheduler.add_job(func=main, id='collector', replace_existing=True, trigger='interval', minutes=1)
        scheduler.start()

def _analyse_log() -> dict[str, int | None]:
    # Get the number of requests from the apache log within the previous minute
    # and the average search response time in ms from the apache log within the previous minute
    path = pathlib.Path(ACCESS_LOG_PATH)
    if not path.is_file():
        return {
            'request_count': 0,
            'response_time': None,
        }
    last_minute = datetime.now() - timedelta(minutes=1)
    formatted_time = last_minute.strftime("%d/%b/%Y:%H:%M:\\d\\d %z")
    request_count = 0
    response_time_total = 0
    response_time_count = 0
    time_pattern = re.compile(formatted_time)
    search_pattern = re.compile(r'GET /Search/Results.* (\d+)$')
    try:
        with open(path, 'rb') as log_file:
            # Seek log file in order to avoid parsing the whole file for line endings
            # 10000 lines * 1024 max log entry length
            if path.stat().st_size > 10240000:
                log_file.seek(-10240000, os.SEEK_END)
            while True:
                line = log_file.readline().decode(encoding='utf-8', errors='ignore')
                if not line:
                    break
                time_match = time_pattern.search(line)
                if time_match:
                    request_count += 1
                    search_match = search_pattern.search(line, time_match.end())
                    if search_match:
                        time_nano = int(search_match.group(1))
                        response_time_count += 1
                        response_time_total += time_nano // 1000
    except OSError as err:
        print(f"Error reading the apache log file: {err}", file=sys.stderr)
        return {
            'request_count': 0,
            'response_time': None,
        }
    return {
        'request_count': request_count,
        'response_time': None if response_time_count == 0 else response_time_total // response_time_count,
    }

def _read_docker_stats():
    variables = {}
    with open(f'/mnt/shared/docker_stats/{os.getenv('NODE')}/{os.getenv('STACK_NAME')}', 'r', encoding='UTF-8') as f:
        while True:
            line = f.readline()
            if not line:
                break
            parts = line.split()
            if len(parts) == 3:
                variables[f'{parts[0]}_cpu'] = parts[1]
                variables[f'{parts[0]}_mem'] = parts[2]
    return variables

def main():
    time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    node = os.getenv('NODE')
    event_loop = get_eventloop()
    results = event_loop.run_until_complete(asyncio.gather(
        status.node_available_memory(),
        status.node_available_disk_space()
    ))
    memory = results[0]
    disk = results[1]
    stats = _read_docker_stats()
    log_results = _analyse_log()
    nb_requests = log_results['request_count']
    response_time = log_results['response_time']
    conn = None
    try:
        with open(os.getenv('MARIADB_MONITORING_PASSWORD_FILE'), 'r', encoding='UTF-8') as f:
            password = f.read().strip()
            f.close()
        conn = db.connect(user='monitoring', password=password, host='galera', database="monitoring")
        del password
        cur = conn.cursor()
        statement = "INSERT INTO data (node, time, available_memory, available_disk_space, " \
            "apache_requests, response_time, solr_solr_cpu, solr_solr_mem, solr_cron_cpu, solr_cron_mem, " \
            "solr_zk_cpu, solr_zk_mem, catalog_catalog_cpu, catalog_catalog_mem, mariadb_galera_cpu, " \
            "mariadb_galera_mem) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"
        data = (
            node, time, memory, disk, nb_requests, response_time, stats.get('solr_solr_cpu'),
            stats.get('solr_solr_mem'), stats.get('solr_cron_cpu'), stats.get('solr_cron_mem'),
            stats.get('solr_zk_cpu'), stats.get('solr_zk_mem'), stats.get('catalog_catalog_cpu'),
            stats.get('catalog_catalog_mem'), stats.get('mariadb_galera_cpu'), stats.get('mariadb_galera_mem')
        )
        cur.execute(statement, data)
        conn.commit()
    except db.Error as err:
        print(f"Error adding entry to database: {err}", file=sys.stderr)
    if conn is not None:
        conn.close()
