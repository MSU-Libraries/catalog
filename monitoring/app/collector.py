import os
import sys
import subprocess
from datetime import datetime, timedelta
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
    command = f"tail -1000 /mnt/logs/apache/access.log | grep '{formatted_time}' | wc -l"
    try:
        process = subprocess.run(["/bin/sh", "-c", command],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        print(f"Error getting number of apache requests: {err.stderr}", file=sys.stderr)
    except subprocess.TimeoutExpired:
        print("Timeout getting number of apache requests", file=sys.stderr)
    try:
        nb_requests = int(process.stdout)
    except ValueError:
        nb_requests = 0
    return nb_requests

def main():
    time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    node = os.getenv('NODE')
    memory = status.node_available_memory()
    disk = status.node_available_disk_space()
    nb_requests = _get_last_minute_apache_requests()
    conn = db.connect(user='monitoring', password='monitoring', host='galera', database="monitoring")
    cur = conn.cursor()
    try:
        statement = "INSERT INTO data (node, time, available_memory, available_disk_space, " \
            "apache_requests) VALUES (%s, %s, %s, %s, %s)"
        data = (node, time, memory, disk, nb_requests)
        cur.execute(statement, data)
        conn.commit()
    except db.Error as err:
        print(f"Error adding entry to database: {err}", file=sys.stderr)
    conn.close()
