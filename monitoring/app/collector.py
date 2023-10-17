import pathlib
import os
import sys
import subprocess
from datetime import datetime, timedelta
from apscheduler.schedulers.background import BackgroundScheduler
import mariadb as db

import status


TIMEOUT = 10
ACCESS_LOG_PATH = '/mnt/logs/apache/access.log'


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
    command = f"tail -c 10240000 {ACCESS_LOG_PATH} | grep '{formatted_time}' | wc -l"
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

def _vufind_search_response_time():
    # get the average search response time in ms from the apache log within the previous minute
    last_minute = datetime.now() - timedelta(minutes=1)
    formatted_time = last_minute.strftime("%d/%b/%Y:%H:%M:[0-9]{2} %z")
    tail = f"tail -c 10240000 {ACCESS_LOG_PATH}"
    grep = f"grep -E '{formatted_time}.*/Search/Results.* [0-9]+$'"
    awk = "awk '{s+=$NF}END{print int(s/NR/1000)}'"
    command = f"{tail} | {grep} | {awk}"
    try:
        process = subprocess.run(["/bin/sh", "-c", command],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        print(f"Error getting response time from apache log: {err.stderr}", file=sys.stderr)
        return 0
    except subprocess.TimeoutExpired:
        print("Timeout getting response time from apache log", file=sys.stderr)
        return 0
    try:
        return int(process.stdout)
    except ValueError:
        print(f"Error interpreting response time from apache log: {process.stdout}", file=sys.stderr)
        print(f"  stderr: {process.stderr}", file=sys.stderr)
        return 0

def main():
    time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    node = os.getenv('NODE')
    memory = status.node_available_memory()
    disk = status.node_available_disk_space()
    if pathlib.Path(ACCESS_LOG_PATH).is_file():
        nb_requests = _get_last_minute_apache_requests()
        response_time = _vufind_search_response_time()
    else:
        nb_requests = 0
        response_time = 0
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
