'''Home Page'''
import os
import asyncio
import flask

import status # pylint: disable=import-error

def homepage() -> str:
    '''
    Gets the contents for the home page and renders the template
    '''
    stack_name = os.getenv('STACK_NAME')
    is_prod = stack_name == 'catalog-prod'
    is_dev = stack_name.startswith('devel-') or stack_name.startswith('review-')
    statuses = status.get_node_statuses()

    if isinstance(statuses, str):
        return statuses

    status_list = {}
    status_list['memory'] = status.get_memory_status(statuses)
    status_list['disk_space'] = status.get_disk_space_status(statuses)
    status_list['galera'] = asyncio.run(status.get_galera_status(statuses))
    status_list['solr'] = status.get_solr_status(statuses)
    status_list['vufind'] = status.get_vufind_status(statuses)
    status_list['folio_harvest'] = status.get_cron_status('folio', statuses)
    status_list['hlm_harvest'] = status.get_cron_status('hlm', statuses)
    status_list['authority_harvest'] = status.get_cron_status('authority', statuses)
    status_list['reserves_update'] = status.get_cron_status('reserves', statuses)
    status_list['optimize'] = status.get_cron_status('optimize', statuses)
    status_list['alphabrowse'] = status.get_cron_status('alphabrowse', statuses)
    status_list['searches_cleanup'] = status.get_cron_status('searches', statuses)
    status_list['sessions_cleanup'] = status.get_cron_status('sessions', statuses)
    if is_prod or is_dev:
        status_list['solr_backup'] = status.get_cron_status('solr', statuses)
        status_list['db_backup'] = status.get_cron_status('db', statuses)
        status_list['alpha_backup'] = status.get_cron_status('alpha', statuses)
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
    return flask.render_template(
        'index.html',
        services=services,
        is_prod=is_prod,
        is_dev=is_dev,
        stack_name=stack_name
    )
