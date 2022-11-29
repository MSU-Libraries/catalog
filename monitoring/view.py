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
    services = {}
    services['galera'] = status.get_galera_status()
    services['solr'] = status.get_solr_status()
    services['vufind'] = status.get_vufind_status()
    for s_name, s in services.items():
        if s == 'OK':
            color = 'success'
        else:
            color = 'danger'
        services[s_name] = '<span class="text-{}">{}</span>'.format(color, s)
    return flask.render_template('index.html', services=services)


if __name__ == "__main__":
    app.run(debug=True, host='0.0.0.0', port=80)
