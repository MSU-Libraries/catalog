import os
import sys
import subprocess
from datetime import datetime, timedelta
import requests
from apscheduler.schedulers.background import BackgroundScheduler
import mariadb as db

import status


TIMEOUT = 10


def init(debug):
    if not debug or os.getenv('WERKZEUG_RUN_MAIN') == 'true':
        scheduler = BackgroundScheduler()
        scheduler.add_job(func=main, id='collector', replace_existing=True, trigger='interval', minutes=1)
        scheduler.start()

def _get_last_minute_apache_requests():
    # get the number of requests from the apache log within all of the previous minute
    last_minute = datetime.now() - timedelta(minutes=1)
    formatted_time = last_minute.strftime("%d/%b/%Y:%H:%M:.. %z")
    # Tail of logs to use -c in order to avoid needed to parse file for line endings
    # Max tail chars = 10000 lines * 1024 max log entry length
    command = f"tail -c 10240000 /mnt/logs/apache/access.log | grep '{formatted_time}' | wc -l"
    try:
        process = subprocess.run(["/bin/sh", "-c", command],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        print(f"Error getting number of apache requests: {err.stderr}", file=sys.stderr)
        return 0
    except subprocess.TimeoutExpired:
        print("Timeout getting number of apache requests", file=sys.stderr)
        return 0
    try:
        return int(process.stdout)
    except ValueError:
        print(f"Error interpreting number of apache requests: {process.stdout}", file=sys.stderr)
        print(f"  stderr: {process.stderr}", file=sys.stderr)
        return 0

def _vufind_search_response_time(node):
    try:
        req = requests.get(
            f'http://vufind{node}/Search/Results?limit=20&dfApplied=1&lookfor=life+in+the+new+world&type=AllFields',
            timeout=TIMEOUT)
        req.raise_for_status()
        return req.elapsed.microseconds // 1000
    except requests.exceptions.Timeout:
        return TIMEOUT * 1000
    except (requests.exceptions.HTTPError, requests.exceptions.RequestException):
        return 0

def main():
    time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    node = os.getenv('NODE')
    memory = status.node_available_memory()
    disk = status.node_available_disk_space()
    nb_requests = _get_last_minute_apache_requests()
    response_time = _vufind_search_response_time(node)
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
