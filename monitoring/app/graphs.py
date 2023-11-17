from datetime import datetime, timedelta
import os
import asyncio
import json
import flask
import mariadb as db
import aiohttp

import util


KNOWN_VARIABLES = ['available_memory', 'available_disk_space', 'apache_requests', 'response_time']


def _times_by_period(period: str) -> list[datetime]:
    delta_by_period = {
        'hour': timedelta(hours=1),
        'day': timedelta(days=1),
        'week': timedelta(days=7),
        'month': timedelta(days=30),
        'year': timedelta(days=365),
    }
    delta = delta_by_period[period]
    period_start = datetime.now() - delta
    period_end = datetime.now()
    return (period_start, period_end)

def _group_by_times(period_start: datetime, period_end: datetime) -> str:
    delta = period_end - period_start
    interval = round(delta.total_seconds() / 100.)
    if interval > 60*60*24*30:
        group = 'MONTH'
    elif interval > 60*60*24:
        group = 'DAY'
    elif interval > 60*60:
        group = 'HOUR'
    else:
        group = 'MINUTE'
    return group

def _sql_query(variable: str, period_start: datetime, period_end: datetime, group: str) -> str:
    sql_start = period_start.strftime('%Y-%m-%d %H:%M:%S')
    sql_end = period_end.strftime('%Y-%m-%d %H:%M:%S')
    sql_select_by_group = {
        'MONTH': 'YEAR(time) AS Year, MONTH(time) AS Month',
        'DAY': 'YEAR(time) AS Year, MONTH(time) AS Month, DAY(time) AS Day',
        'HOUR': 'YEAR(time) AS Year, MONTH(time) AS Month, DAY(time) AS Day, HOUR(time) AS Hour',
        'MINUTE': 'YEAR(time) AS Year, MONTH(time) AS Month, DAY(time) AS Day, ' \
            'HOUR(time) AS Hour, MINUTE(time) AS Minute',
    }
    sql_select = sql_select_by_group[group]
    sql_group_by_group = {
        'MONTH': 'YEAR(time), MONTH(time)',
        'DAY': 'YEAR(time), MONTH(time), DAY(time)',
        'HOUR': 'YEAR(time), MONTH(time), DAY(time), HOUR(time)',
        'MINUTE': 'YEAR(time), MONTH(time), DAY(time), HOUR(time), MINUTE(time)',
    }
    sql_group = sql_group_by_group[group]
    node = os.getenv('NODE')
    if variable in ['apache_requests', 'response_time']:
        aggreg = 'AVG'
    else:
        aggreg = 'MIN'
    return f'SELECT {sql_select}, {aggreg}({variable}) AS {variable} FROM data ' \
        f'WHERE time > "{sql_start}" AND time < "{sql_end}" AND node = {node} GROUP BY {sql_group};'

def node_graph_data(variable: str, period: str) -> dict[str, list] | str:
    if variable not in KNOWN_VARIABLES:
        return 'Error: unknown variable'
    if period not in ['hour', 'day', 'week', 'month', 'year']:
        return 'Error: unknown period'
    # someday this will support any given date/time for start and end
    (period_start, period_end) = _times_by_period(period)
    group = _group_by_times(period_start, period_end)
    conn = None
    try:
        conn = db.connect(user='monitoring', password=os.getenv('MARIADB_MONITORING_PASSWORD'), host='galera',
            database="monitoring")
        cur = conn.cursor()
        cur.execute(_sql_query(variable, period_start, period_end, group))
        pt_x = []
        pt_y = []
        for row in cur:
            if group == 'MONTH':
                x = datetime(row[0], row[1], 1).strftime('%Y-%m')
            elif group == 'DAY':
                x = datetime(row[0], row[1], row[2]).strftime('%Y-%m-%d')
            elif group == 'HOUR':
                x = datetime(row[0], row[1], row[2], row[3]).strftime('%Y-%m-%d %H')
            else:
                x = datetime(row[0], row[1], row[2], row[3], row[4]).strftime('%Y-%m-%d %H:%M:%S')
            if row[-1] is not None:
                pt_x.append(x)
                pt_y.append(row[-1])
    except db.Error as err:
        if conn is not None:
            conn.close()
        return f"Database error: {err}"
    conn.close()
    return {
        'pt_x': pt_x,
        'pt_y': pt_y,
    }

def graph(variable: str, period: str) -> str:
    if variable not in KNOWN_VARIABLES:
        return 'Error: unknown variable'
    if period not in ['hour', 'day', 'week', 'month', 'year']:
        return 'Error: unknown period'
    urls = []
    for node in range(1, 4):
        urls.append(f'http://monitoring{node}/monitoring/node/graph_data/{variable}/{period}')
    try:
        nodes_graph_data = util.multiple_get(urls)
    except aiohttp.ClientError as err:
        return f'Error getting graph data: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout getting graph data'
    data = []
    for node in range(1, 4):
        try:
            j = json.loads(nodes_graph_data[node-1])
        except json.JSONDecodeError as err:
            return f'Error decoding JSON from node {node}: {err}'
        node_data = {}
        node_data['name'] = f'node {node}'
        node_data['type'] = 'scatter'
        node_data['x'] = j['pt_x']
        node_data['y'] = j['pt_y']
        data.append(node_data)
    return flask.render_template('graph.html', variable=variable, period=period, data=data)
