import flask
import logs
import status

app = flask.Flask(__name__, static_url_path='/monitoring/static')

@app.route('/monitoring/node/logs/<path:service>')
def node_logs(service):
    return logs.node_logs(service)

@app.route('/monitoring/logs/<path:service>')
def logs_vufind(service):
    return logs.logs_vufind(service)

@app.route('/monitoring/node/cluster_state_uuid')
def cluster_state_uuid():
    return status.cluster_state_uuid()

@app.route('/monitoring')
def home():
    galera_status = status.get_galera_status()
    solr_status = status.get_solr_status()
    return render_template('index.html', galera_status=galera_status, solr_status=solr_status)


if __name__ == "__main__":
    app.run(debug=True, host='0.0.0.0', port=80)
