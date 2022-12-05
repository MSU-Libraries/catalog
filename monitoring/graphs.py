from datetime import datetime, timedelta
import os
import io
import flask
import matplotlib.pyplot as plt
import numpy as np
import mariadb as db

def _times_by_period(period):
    delta_by_period = {
        'hour': timedelta(hours=1),
        'day': timedelta(days=1),
        'week': timedelta(days=7),
        'month': timedelta(days=30)
    }
    delta = delta_by_period[period]
    period_start = datetime.now() - delta
    period_end = datetime.now()
    return (period_start, period_end)

def _group_by_times(period_start, period_end):
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

def _sql_query(data, period_start, period_end, group):
    sql_start = period_start.strftime('%Y-%m-%d %H:%M:%S')
    sql_end = period_end.strftime('%Y-%m-%d %H:%M:%S')
    sql_select_by_group = {
        'MONTH': 'YEAR(time) AS Year, MONTH(time) AS Month',
        'DAY': 'YEAR(time) AS Year, MONTH(time) AS Month, DAY(time) AS Day',
        'HOUR': 'YEAR(time) AS Year, MONTH(time) AS Month, DAY(time) AS Day, HOUR(time) AS Hour',
        'MINUTE': 'YEAR(time) AS Year, MONTH(time) AS Month, DAY(time) AS Day, HOUR(time) AS Hour, MINUTE(time) AS Minute'
    }
    sql_select = sql_select_by_group[group]
    sql_group_by_group = {
        'MONTH': 'YEAR(time), MONTH(time)',
        'DAY': 'YEAR(time), MONTH(time), DAY(time)',
        'HOUR': 'YEAR(time), MONTH(time), DAY(time), HOUR(time)',
        'MINUTE': 'YEAR(time), MONTH(time), DAY(time), HOUR(time), MINUTE(time)'
    }
    sql_group = sql_group_by_group[group]
    node = os.getenv('NODE')
    return f'SELECT {sql_select}, AVG({data}) AS {data} FROM data ' \
        f'WHERE time > "{sql_start}" AND time < "{sql_end}" AND node = {node} GROUP BY {sql_group};'

def _get_db_data(data, period):
    # someday this will support any given date/time for start and end
    (period_start, period_end) = _times_by_period(period)
    group = _group_by_times(period_start, period_end)
    conn = db.connect(user='monitoring', password='monitoring', host='galera', database="monitoring")
    cur = conn.cursor()
    cur.execute(_sql_query(data, period_start, period_end, group))
    result = []
    for row in cur:
        result.append(row['data'])
    return result

def _get_title(data, period):
    return f'{data} for the last {period}'

def graph(data, period):
    try:
        values = _get_db_data(data, period)
    except db.Error as err:
        return f"Database error: {err}"
    plt.plot(np.array(values))
    plt.title(_get_title(data, period))
    streamed_file = io.StringIO()
    plt.savefig(streamed_file, format = "svg")
    svg = streamed_file.getvalue()
    return flask.render_template('graph.html', data=data, period=period, svg=svg)
