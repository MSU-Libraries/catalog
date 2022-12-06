import os
from datetime import datetime
import mariadb as db

import status


def main():
    time = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    print('collector main - time:' + time)
    node = os.getenv('NODE')
    memory = status.node_available_memory()
    disk = status.node_available_disk_space()
    conn = db.connect(user='monitoring', password='monitoring', host='galera', database="monitoring")
    cur = conn.cursor()
    try:
        statement = "INSERT INTO data (node, time, available_memory, available_disk_space, " \
            "apache_requests) VALUES (%s, %s, %s, %s, %s)"
        data = (node, time, memory, disk, 0)
        cur.execute(statement, data)
        conn.commit()
    except db.Error as err:
        print(f"Error adding entry to database: {err}")
