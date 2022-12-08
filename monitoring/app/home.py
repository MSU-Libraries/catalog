import flask

import status


def homepage():
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
