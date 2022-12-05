import atexit
import flask
from apscheduler.schedulers.background import BackgroundScheduler

import logs
import status
import collector
import graphs


app = flask.Flask(__name__, static_url_path='/monitoring/static')

scheduler = BackgroundScheduler()
scheduler.add_job(func=collector.main, id='collector', replace_existing=True, trigger='interval', minutes=1)
scheduler.start()

def shutdown():
    scheduler.shutdown()

atexit.register(shutdown)

@app.route('/monitoring/node/logs/<path:service>')
def node_logs(service):
    return logs.node_logs(service)

@app.route('/monitoring/logs/<path:service>')
def logs_vufind(service):
    return logs.logs_vufind(service)

@app.route('/monitoring/node/cluster_state_uuid')
def cluster_state_uuid():
    return status.cluster_state_uuid()

@app.route('/monitoring/node/available_memory')
def node_available_memory():
    return status.node_available_memory()

@app.route('/monitoring/node/available_disk_space')
def node_available_disk_space():
    return status.node_available_disk_space()

@app.route('/monitoring/node/graph_data/<data>/<period>')
def node_graph_data(data, period):
    if data not in ['available_memory', 'available_disk_space', 'apache_requests']:
        return 'Error: unknown data'
    if period not in ['hour', 'day', 'week', 'month']:
        return 'Error: unknown period'
    return graphs.node_graph_data(data, period)

@app.route('/monitoring/graphs/<data>/<period>')
def graph(data, period):
    if data not in ['available_memory', 'available_disk_space', 'apache_requests']:
        return 'Error: unknown data'
    if period not in ['hour', 'day', 'week', 'month']:
        return 'Error: unknown period'
    return graphs.graph(data, period)

@app.route('/monitoring')
def home():
    status_list = {}
    status_list['memory'] = status.get_memory_status()
    status_list['disk_space'] = status.get_disk_space_status()
    status_list['traefik'] = status.get_traefik_status()
    status_list['galera'] = status.get_galera_status()
    status_list['solr'] = status.get_solr_status()
    status_list['vufind'] = status.get_vufind_status()
    services = {}
    for s_name, s_text in status_list.items():
        if s_text.startswith('OK'):
            color = 'success'
        else:
            color = 'danger'
        services[s_name] = {
            'color': color,
            'status': s_text,
        }
    return flask.render_template('index.html', services=services)


if __name__ == "__main__":
    app.run(debug=True, host='0.0.0.0', port=80)
