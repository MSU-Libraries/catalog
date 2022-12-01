import subprocess
import requests
import aiohttp

import util


TIMEOUT = 10

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


def cluster_state_uuid():
    try:
        process = subprocess.run(["mysql", "-h", "galera", "-u", "vufind", "-pvufind", "-ss", "-e",
            "SELECT variable_value from information_schema.global_status WHERE " \
            "variable_name='wsrep_cluster_state_uuid';"],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error checking the status: {err.stderr}"
    return process.stdout

def check_cluster_state_uuid():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://monitoring{node}/monitoring/node/cluster_state_uuid')
    try:
        uuids = util.async_get_requests(urls)
    except (aiohttp.ClientError) as err:
        return f'Error reading cluster state uuid on node {node}: {err}'
    return uuids[0] == uuids[1] and uuids[0] == uuids[2]

def get_galera_status():
    try:
        process = subprocess.run(["mysql", "-h", "galera", "-u", "vufind", "-pvufind", "-ss", "-e",
            "SELECT variable_value from information_schema.global_status WHERE variable_name='wsrep_cluster_size';"],
        capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error checking the status: {err.stderr}"
    cluster_size = process.stdout.strip()
    if cluster_size != '3':
        return f'Error: wrong cluster size: {cluster_size}'
    if not check_cluster_state_uuid():
        return 'Error: different cluster state uuids'
    return 'OK'


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

def get_solr_status():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://solr{node}:8983/solr/admin/collections?action=clusterstatus')
    try:
        jsons = util.async_get_requests(urls, convert_to_json=True)
    except (aiohttp.ClientError) as err:
        return f'Error reading the solr clusterstatus: {err}'

    for node in range(1, 4):
        j = jsons[node-1]
        live_nodes = j['cluster']['live_nodes']
        if len(live_nodes) != 3:
            return f'Error: node {node}: only {len(live_nodes)} live nodes'
        if len(j['cluster']['collections']) != 4:
            return f"Wrong number of collections: {len(j['cluster']['collections'])}"
        for collection_name, collection in j['cluster']['collections'].items():
            res = _check_collection(collection_name, collection, live_nodes)
            if res != 'OK':
                return res
    return 'OK'


def get_vufind_status():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://vufind{node}/')
    try:
        results = util.async_get_requests(urls)
    except (aiohttp.ClientError) as err:
        return f'Error reading the solr clusterstatus: {err}'
    for node in range(1, 4):
        text = results[node-1]
        if '</html>' not in text:
            return f'Vufind home page not complete for node {node}'
        if '<h1>An error has occurred</h1>' in text:
            return f'An error is reported in Vufind home page for node {node}'
    return 'OK'
