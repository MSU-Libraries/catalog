import subprocess
import asyncio
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
        return f"Error getting the cluster state uuid: {err.stderr}"
    except subprocess.TimeoutExpired:
        return "Timeout getting the cluster state uuid"
    return process.stdout

def check_cluster_state_uuid():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://monitoring{node}/monitoring/node/cluster_state_uuid')
    try:
        uuids = util.async_get_requests(urls)
    except aiohttp.ClientError as err:
        return f'Error reading cluster state uuid: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout when reading cluster state uuid'
    return uuids[0] == uuids[1] and uuids[0] == uuids[2]

def get_galera_status():
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
    except aiohttp.ClientError as err:
        return f'Error reading the solr clusterstatus: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout when reading the solr clusterstatus'

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


def _check_vufind_home_page():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://vufind{node}/')
    try:
        results = util.async_get_requests(urls)
    except aiohttp.ClientError as err:
        return f'Error reading vufind home page: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout when reading vufind home page'
    for node in range(1, 4):
        text = results[node-1]
        if '</html>' not in text:
            return f'Vufind home page not complete for node {node}'
        if '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
            return f'An error is reported in Vufind home page for node {node}'
    return 'OK'

def _check_vufind_record_page():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://vufind{node}/Record/folio.in00001912238')
    try:
        results = util.async_get_requests(urls)
    except aiohttp.ClientError as err:
        return f'Error reading vufind record page: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout when reading vufind record page'
    for node in range(1, 4):
        text = results[node-1]
        if '</html>' not in text:
            return f'Vufind record page folio.in00001912238 not complete for node {node}'
        if '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
            return f'An error is reported in Vufind record page folio.in00001912238 for node {node}'
        if 'CR-186011' not in text:
            return f'Vufind record page folio.in00001912238 not complete for node {node}'
        if 'NAS 1.26:186011' not in text:
            return f'Vufind record page folio.in00001912238 not complete for node {node}'
    return 'OK'

def _check_vufind_search_page():
    urls = []
    for node in range(1, 4):
        urls.append(
            f'http://vufind{node}/Search/Results?limit=5&dfApplied=1&lookfor=+Automated+flight+test&type=AllFields')
    try:
        results = util.async_get_requests(urls)
    except aiohttp.ClientError as err:
        return f'Error reading vufind search page: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout when reading vufind search page'
    for node in range(1, 4):
        text = results[node-1]
        if '</html>' not in text:
            return f'Vufind search page not complete for node {node}'
        if '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
            return f'An error is reported in Vufind search page for node {node}'
        if 'folio.in00001912238' not in text:
            return f'Vufind search page not complete for node {node}'
    return 'OK'

def get_vufind_status():
    res = _check_vufind_home_page()
    if res != 'OK':
        return res
    res = _check_vufind_record_page()
    if res != 'OK':
        return res
    res = _check_vufind_search_page()
    if res != 'OK':
        return res
    return 'OK'

def node_available_memory():
    try:
        process = subprocess.run(["/bin/sh", "-c", "free | grep Mem | awk '{print $7/$2 * 100.0}'"],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error getting available memory: {err.stderr}"
    except subprocess.TimeoutExpired:
        return "Timeout when getting available memory"
    return process.stdout

def node_available_disk_space():
    try:
        process = subprocess.run(["/bin/sh", "-c", "df / | grep overlay | awk '{print $4/$2 * 100.0}'"],
            capture_output=True, text=True, timeout=TIMEOUT, check=True)
    except subprocess.CalledProcessError as err:
        return f"Error getting available disk space: {err.stderr}"
    except subprocess.TimeoutExpired:
        return "Timeout when getting available disk space"
    return process.stdout

def get_memory_status():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://monitoring{node}/monitoring/node/available_memory')
    try:
        mems = util.async_get_requests(urls)
    except aiohttp.ClientError as err:
        return f'Error reading available memory: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout when reading available memory'
    for node in range(1, 4):
        try:
            float(mems[node-1])
        except ValueError:
            return f'Returned value for available memory is not a number on node {node}: {mems[node-1]}'
    lowest = 100
    lowest_node = 0
    for node in range(1, 4):
        if float(mems[node-1]) < lowest:
            lowest_node = node
            lowest = float(mems[node-1])
    lowest = round(lowest, 1)
    if lowest < 20.:
        return f"Low available memory on node {lowest_node}: {lowest}%"
    return f"OK - lowest available memory: {lowest}%"

def get_disk_space_status():
    urls = []
    for node in range(1, 4):
        urls.append(f'http://monitoring{node}/monitoring/node/available_disk_space')
    try:
        disk_spaces = util.async_get_requests(urls)
    except aiohttp.ClientError as err:
        return f'Error reading available disk space: {err}'
    except asyncio.exceptions.TimeoutError:
        return 'Timeout when reading available disk space'
    for node in range(1, 4):
        try:
            float(disk_spaces[node-1])
        except ValueError:
            return f'Returned value for available disk space is not a number on node {node}: {disk_spaces[node-1]}'
    lowest = 100
    lowest_node = 0
    for node in range(1, 4):
        if float(disk_spaces[node-1]) < lowest:
            lowest_node = node
            lowest = float(disk_spaces[node-1])
    lowest = round(lowest, 1)
    if lowest < 20.:
        return f"Low available disk space on node {lowest_node}: {lowest}%"
    return f"OK - lowest available disk space: {lowest}%"
