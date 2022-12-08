import graphs
import home
import logs
import status
from monitoring import app


# Homepage and status

@app.route('/monitoring/node/cluster_state_uuid')
def cluster_state_uuid():
    return status.cluster_state_uuid()

@app.route('/monitoring/node/available_memory')
def node_available_memory():
    return status.node_available_memory()

@app.route('/monitoring/node/available_disk_space')
def node_available_disk_space():
    return status.node_available_disk_space()

@app.route('/monitoring')
def homepage():
    return home.homepage()


# Logs

@app.route('/monitoring/node/logs/<path:service>')
def node_logs(service):
    return logs.node_logs(service)

@app.route('/monitoring/logs/<path:service>')
def logs_vufind(service):
    return logs.logs_vufind(service)


# Graphs

@app.route('/monitoring/node/graph_data/<variable>/<period>')
def node_graph_data(variable, period):
    if variable not in ['available_memory', 'available_disk_space', 'apache_requests']:
        return 'Error: unknown variable'
    if period not in ['hour', 'day', 'week', 'month', 'year']:
        return 'Error: unknown period'
    return graphs.node_graph_data(variable, period)

@app.route('/monitoring/graphs/<variable>/<period>')
def graph(variable, period):
    if variable not in ['available_memory', 'available_disk_space', 'apache_requests']:
        return 'Error: unknown variable'
    if period not in ['hour', 'day', 'week', 'month', 'year']:
        return 'Error: unknown period'
    return graphs.graph(variable, period)
