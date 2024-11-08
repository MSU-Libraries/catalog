'''App entrypoint'''
import os
import flask

import collector # pylint: disable=import-error
import graphs # pylint: disable=import-error
import home # pylint: disable=import-error
import logs # pylint: disable=import-error
import status # pylint: disable=import-error


debug = os.getenv('STACK_NAME') != 'catalog-prod'
app = flask.Flask(__name__, static_url_path='/monitoring/static')

# Initialize stats collector with a scheduler

collector.init(debug)

# Routes

# Homepage and status

@app.route('/monitoring/node/status')
def node_status():
    '''
    Get the node status data
    '''
    return status.get_node_status()

@app.route('/monitoring')
def homepage():
    '''
    Render the home page
    '''
    return home.homepage() # pylint: disable=c-extension-no-member

# Logs

@app.route('/monitoring/node/logs/<path:service>')
def node_logs(service):
    '''
    Get the logs for the service on the node
    '''
    return logs.node_logs(service)

@app.route('/monitoring/logs/<path:service>')
def logs_vufind(service):
    '''
    Get the logs for the requested service
    '''
    return logs.logs_vufind(service)

# Graphs

@app.route('/monitoring/node/graph_data/<variable>/<period>')
def node_graph_data(variable, period):
    '''
    Get the data for the requested graph
    '''
    return graphs.node_graph_data(variable, period)

@app.route('/monitoring/graphs/<variable>/<period>')
def graph(variable, period):
    '''
    Render the requested graph
    '''
    return graphs.graph(variable, period)


# Run the app

if __name__ == "__main__":
    app.run(debug=debug, host='0.0.0.0', port=80)
