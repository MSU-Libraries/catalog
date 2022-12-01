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
    status_list = {}
    status_list['traefik'] = status.get_traefik_status()
    status_list['galera'] = status.get_galera_status()
    status_list['solr'] = status.get_solr_status()
    status_list['vufind'] = status.get_vufind_status()
    services = {}
    for s_name, s_text in status_list.items():
        if s_text == 'OK':
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
