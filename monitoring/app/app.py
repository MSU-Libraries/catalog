import os
import flask

import collector
import graphs
import home
import logs
import status


debug = os.getenv('STACK_NAME') != 'catalog-prod'
app = flask.Flask(__name__, static_url_path='/monitoring/static')

# Initialize stats collector with a scheduler

collector.init(debug)

# Routes

# Homepage and status

@app.route('/monitoring/node/status')
def node_status():
    return status.get_node_status()

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
    return graphs.node_graph_data(variable, period)

@app.route('/monitoring/graphs/<variable>/<period>')
def graph(variable, period):
    return graphs.graph(variable, period)


# Run the app

if __name__ == "__main__":
    app.run(debug=debug, host='0.0.0.0', port=80)
