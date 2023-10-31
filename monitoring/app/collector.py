import pathlib
import os
import re
import sys
from datetime import datetime, timedelta
from apscheduler.schedulers.background import BackgroundScheduler
import mariadb as db

import status


ACCESS_LOG_PATH = '/mnt/logs/apache/access.log'


def init(debug):
    if not debug or os.getenv('WERKZEUG_RUN_MAIN') == 'true':
        scheduler = BackgroundScheduler()
        scheduler.add_job(func=main, id='collector', replace_existing=True, trigger='interval', minutes=1)
        scheduler.start()

def _analyse_log():
    print('_analyse_log')
    # Get the number of requests from the apache log within the previous minute
    # and the average search response time in ms from the apache log within the previous minute
    if not pathlib.Path(ACCESS_LOG_PATH).is_file():
        print('not a file')
        return {
            'request_count': 0,
            'response_time': None,
        }
    last_minute = datetime.now() - timedelta(minutes=1)
    formatted_time = last_minute.strftime("%d/%b/%Y:%H:%M:\\d\\d %z")
    print(formatted_time)
    request_count = 0
    response_time_total = 0
    response_time_count = 0
    time_pattern = re.compile(formatted_time)
    search_pattern = re.compile(r'GET /Search/Results.* (\d+)$')
    try:
        with open(ACCESS_LOG_PATH, 'r', encoding='utf-8') as log_file:
            # Seek log file in order to avoid parsing the whole file for line endings
            # 10000 lines * 1024 max log entry length
            print('after open')
            log_file.seek(-10240000, whence=os.SEEK_END)
            print('after seek')
            while True:
                line = log_file.readline()
                print(line)
                if not line:
                    break
                if time_pattern.match(line):
                    print('time match')
                    request_count += 1
                    search_match = search_pattern.match(line)
                    if search_match:
                        print('search match')
                        time_nano = search_match.group(1)
                        response_time_count += 1
                        response_time_total += time_nano // 1000
    except OSError as err:
        print('os error')
        print(f"Error reading the apache log file: {err}", file=sys.stderr)
        return {
            'request_count': 0,
            'response_time': None,
        }
    print('finished ok')
    return {
        'request_count': request_count,
        'response_time': None if response_time_count == 0 else response_time_total / response_time_count,
    }

def main():
    time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    node = os.getenv('NODE')
    memory = status.node_available_memory()
    disk = status.node_available_disk_space()
    log_results = _analyse_log()
    nb_requests = log_results['request_count']
    response_time = log_results['response_time']
    print(f'nb_requests={nb_requests}')
    print(f'response_time={response_time}')
    conn = None
    try:
        conn = db.connect(user='monitoring', password=os.getenv('MARIADB_MONITORING_PASSWORD'), host='galera',
            database="monitoring")
        cur = conn.cursor()
        statement = "INSERT INTO data (node, time, available_memory, available_disk_space, " \
            "apache_requests, response_time) VALUES (%s, %s, %s, %s, %s, %s)"
        data = (node, time, memory, disk, nb_requests, response_time)
        cur.execute(statement, data)
        conn.commit()
    except db.Error as err:
        print(f"Error adding entry to database: {err}", file=sys.stderr)
    if conn is not None:
        conn.close()
