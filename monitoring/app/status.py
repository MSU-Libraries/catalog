import os
import pathlib
import subprocess
import asyncio
import requests
import aiohttp

import util


TIMEOUT = 10


# Traefik

def get_traefik_status():
    try:
        req = requests.get('http://traefik:8081/ping', timeout=TIMEOUT)
        req.raise_for_status()
        contents = req.text
        if contents != 'OK':
            return f'Strange reply from traefik ping: {contents}'
        return 'OK'
    except (requests.exceptions.HTTPError, requests.exceptions.RequestException) as err:
        return f'Error with traefik ping: {err}'


# Galera

def _node_cluster_state_uuid():
    try:
        process = subprocess.run(["mysql", "-h", "galera", "-u", "vufind", "-pvufind", "-ss", "-e",
            "SELECT variable_value from information_schema.global_status WHERE " \
            "variable_name='wsrep_cluster_state_uuid';"],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error getting the cluster state uuid: {err.stderr}"
    except subprocess.TimeoutExpired:
        return "Timeout getting the cluster state uuid"
    return process.stdout.strip()

def _check_cluster_state_uuid(statuses):
    uuid0 = statuses[0]['cluster_state_uuid']
    uuid1 = statuses[1]['cluster_state_uuid']
    uuid2 = statuses[2]['cluster_state_uuid']
    return uuid0 == uuid1 and uuid0 == uuid2

def get_galera_status(statuses):
    try:
        process = subprocess.run(["mysql", "-h", "galera", "-u", "vufind", "-pvufind", "-ss", "-e",
            "SELECT variable_value from information_schema.global_status WHERE variable_name='wsrep_cluster_size';"],
        capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error checking the status: {err.stderr}"
    except subprocess.TimeoutExpired:
        return "Timeout when checking the status"
    cluster_size = process.stdout.strip()
    if cluster_size != '3':
        return f'Error: wrong cluster size: {cluster_size}'
    if not _check_cluster_state_uuid(statuses):
        return 'Error: different cluster state uuids'
    return 'OK'


# Solr

def _check_shard(collection_name, shard_name, shard, live_nodes):
    if shard['state'] != 'active':
        return f"Collection {collection_name} shard {shard_name} has the state " \
            f"{shard['state']} (expected active)."
    replicas = shard['replicas']
    if len(replicas) != 3:
        return 'A replica does not have 3 core nodes.'
    for replica_name, replica in replicas.items():
        if replica['state'] != 'active':
            return f"Collection {collection_name} shard {shard_name} replica {replica_name}" \
                f" has the state {replica['state']} (expected: active)."
        if replica['node_name'] not in live_nodes:
            return f"Collection {collection_name} shard {shard_name} replica " \
                f"{replica_name} has a node name that is not in the list of " \
                f"live nodes: {replica['node_name']}"
    return 'OK'

def _check_collection(collection_name, collection, live_nodes):
    if collection['health'] != 'GREEN':
        return f"Collection {collection_name} has {collection['health']} health."
    if len(collection['shards']) != 1:
        return f"Collection {collection_name} has {len(collection['shards'])} shard(s)."
    for shard_name, shard in collection['shards'].items():
        res = _check_shard(collection_name, shard_name, shard, live_nodes)
        if res != 'OK':
            return res
    return 'OK'

def _node_solr_status():
    node = os.getenv('NODE')
    try:
        req = requests.get(f'http://solr{node}:8983/solr/admin/collections?action=clusterstatus', timeout=TIMEOUT)
        req.raise_for_status()
        j = req.json()
    except requests.exceptions.Timeout:
        return 'Timeout when reading the solr clusterstatus'
    except (requests.exceptions.HTTPError, requests.exceptions.RequestException) as err:
        return f'Error reading the solr clusterstatus: {err}'
    live_nodes = j['cluster']['live_nodes']
    if len(live_nodes) != 3:
        return f'Error: only {len(live_nodes)} live nodes'
    if len(j['cluster']['collections']) != 4:
        return f"Wrong number of collections: {len(j['cluster']['collections'])}"
    for collection_name, collection in j['cluster']['collections'].items():
        res = _check_collection(collection_name, collection, live_nodes)
        if res != 'OK':
            return res
    return 'OK'

def get_solr_status(statuses):
    for node in range(1, 4):
        node_status = statuses[node-1]['solr']
        if node_status != 'OK':
            return f'Error on node {node}: {node_status}'
    return 'OK'


# Vufind

def _check_vufind_home_page(node):
    try:
        req = requests.get(f'http://vufind{node}/', timeout=TIMEOUT)
        req.raise_for_status()
        text = req.text
    except requests.exceptions.Timeout:
        return 'Timeout when reading vufind home page'
    except (requests.exceptions.HTTPError, requests.exceptions.RequestException) as err:
        return f'Error reading vufind home page: {err}'
    if '</html>' not in text:
        return 'Vufind home page not complete'
    if '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
        return 'An error is reported in Vufind home page'
    return 'OK'

def _check_vufind_record_page(node):
    try:
        req = requests.get(f'http://vufind{node}/Record/folio.in00001912238', timeout=TIMEOUT)
        req.raise_for_status()
        text = req.text
    except requests.exceptions.Timeout:
        return 'Timeout when reading vufind record page'
    except (requests.exceptions.HTTPError, requests.exceptions.RequestException) as err:
        return f'Error reading vufind record page: {err}'
    if '</html>' not in text:
        return 'Vufind record page folio.in00001912238 not complete'
    if '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
        return 'An error is reported in Vufind record page folio.in00001912238'
    if 'CR-186011' not in text:
        return 'Vufind record page folio.in00001912238 not complete'
    if 'NAS 1.26:186011' not in text:
        return 'Vufind record page folio.in00001912238 not complete'
    return 'OK'

def _check_vufind_search_page(node):
    try:
        req = requests.get(
            f'http://vufind{node}/Search/Results?limit=5&dfApplied=1&lookfor=+Automated+flight+test&type=AllFields',
            timeout=TIMEOUT)
        req.raise_for_status()
        text = req.text
    except requests.exceptions.Timeout:
        return 'Timeout when reading vufind search page'
    except (requests.exceptions.HTTPError, requests.exceptions.RequestException) as err:
        return f'Error reading vufind search page: {err}'
    if '</html>' not in text:
        return 'Vufind search page not complete'
    if '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
        return 'An error is reported in Vufind search page'
    if 'folio.in00001912238' not in text:
        return 'Vufind search page not complete'
    return 'OK'

def _node_vufind_status():
    node = os.getenv('NODE')
    res = _check_vufind_home_page(node)
    if res != 'OK':
        return res
    res = _check_vufind_record_page(node)
    if res != 'OK':
        return res
    res = _check_vufind_search_page(node)
    if res != 'OK':
        return res
    return 'OK'

def get_vufind_status(statuses):
    for node in range(1, 4):
        node_status = statuses[node-1]['vufind']
        if node_status != 'OK':
            return f'Error on node {node}: {node_status}'
    return 'OK'


# Available memory and disk space

def node_available_memory():
    try:
        process = subprocess.run(["/bin/sh", "-c", "free | grep Mem | awk '{print $7/$2 * 100.0}'"],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error getting available memory: {err.stderr}"
    except subprocess.TimeoutExpired:
        return "Timeout when getting available memory"
    return process.stdout.strip()

def node_available_disk_space():
    try:
        process = subprocess.run(["/bin/sh", "-c", "df / | grep overlay | awk '{print $4/$2 * 100.0}'"],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error getting available disk space: {err.stderr}"
    except subprocess.TimeoutExpired:
        return "Timeout when getting available disk space"
    return process.stdout.strip()

def get_memory_status(statuses):
    for node in range(1, 4):
        available_memory = statuses[node-1]['available_memory']
        try:
            float(available_memory)
        except ValueError:
            return f'Returned value for available memory is not a number on node {node}: {available_memory}'
    lowest = 100
    lowest_node = 0
    for node in range(1, 4):
        available_memory = statuses[node-1]['available_memory']
        if float(available_memory) < lowest:
            lowest_node = node
            lowest = float(available_memory)
    lowest = round(lowest, 1)
    if lowest < 20.:
        return f"Low available memory on node {lowest_node}: {lowest}%"
    return f"OK - lowest available memory: {lowest}%"

def get_disk_space_status(statuses):
    for node in range(1, 4):
        available_disk_space = statuses[node-1]['available_disk_space']
        try:
            float(available_disk_space)
        except ValueError:
            return f'Returned value for available disk space is not a number on node {node}: {available_disk_space}'
    lowest = 100
    lowest_node = 0
    for node in range(1, 4):
        available_disk_space = statuses[node-1]['available_disk_space']
        if float(available_disk_space) < lowest:
            lowest_node = node
            lowest = float(available_disk_space)
    lowest = round(lowest, 1)
    if lowest < 20.:
        return f"Low available disk space on node {lowest_node}: {lowest}%"
    return f"OK - lowest available disk space: {lowest}%"

# Harvests

def _node_harvest_exit_codes():
    paths = {
        'folio': '/mnt/logs/harvests/folio_exit_code',
        'hlm': '/mnt/logs/harvests/hlm_exit_code',
        'authority': '/mnt/logs/harvests/authority_exit_code',
        'reserves': '/mnt/logs/vufind/reserves_exit_code',
        'searches': '/mnt/logs/vufind/searches_exit_code',
        'solr': '/mnt/logs/backups/solr_exit_code',
        'db': '/mnt/logs/backups/db_exit_code',
        'alphabrowse': '/mnt/logs/solr/alphabrowse_exit_code',
    }
    exit_codes = {}
    for name, path in paths.items():
        path = pathlib.Path(path)
        if path.is_file():
            exit_code = path.read_text(encoding="utf8").strip()
        else:
            exit_code = 'file_not_found'
        exit_codes[name] = exit_code
    return exit_codes

def get_harvest_status(name, statuses):
    nb_executed = 0
    node_where_executed = 0
    exit_code = ''
    node_with_first_error = 0
    none_found = True
    for node in range(1, 4):
        code = statuses[node-1]['harvests'][name]
        if code == '0':
            nb_executed += 1
            node_where_executed = node
        elif exit_code == '':
            exit_code = code
            node_with_first_error = node
        if code != 'file_not_found':
            none_found = False
    if nb_executed == 3:
        return 'OK - executed on all 3 nodes'
    if nb_executed == 2:
        return 'OK - executed on 2 nodes'
    if nb_executed == 1:
        return f'OK - executed on node {node_where_executed}'
    if none_found:
        return 'This was not executed on any node'
    if exit_code == 'file_not_found':
        return f'Error: exit code file does not exist on at least node {node_with_first_error}'
    return f'Error: exit code on node {node_with_first_error}: {exit_code}'


# Getting all the node statuses at once

def get_node_status():
    status = {}
    status['cluster_state_uuid'] = _node_cluster_state_uuid()
    status['solr'] = _node_solr_status()
    status['vufind'] = _node_vufind_status()
    status['available_memory'] = node_available_memory()
    status['available_disk_space'] = node_available_disk_space()
    status['harvests'] = _node_harvest_exit_codes()
    return status

def get_node_statuses():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://monitoring{node}/monitoring/node/status')
    try:
        statuses = util.async_get_requests(urls, convert_to_json=True, timeout=15)
    except aiohttp.ClientError as err:
        return f'Error reading node status: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout when reading node status'
    return statuses
