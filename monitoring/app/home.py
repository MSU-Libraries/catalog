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
    status_list['folio_harvest'] = status.get_folio_harvest_status()
    status_list['hlm_harvest'] = status.get_hlm_harvest_status()
    status_list['authority_harvest'] = status.get_authority_harvest_status()
    status_list['reserves_update'] = status.get_reserves_update_status()
    status_list['searches_cleanup'] = status.get_searches_cleanup_status()
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
