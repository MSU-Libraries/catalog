import json
import requests
import subprocess

TIMEOUT = 10

def raise_exception_for_reply(r):
    raise Exception('Status code: {}. Response: "{}"'.format(r.status_code, r.text))


def cluster_state_uuid():
    process = subprocess.run(["mysql", "-h", "galera", "-u", "vufind", "-pvufind", "-ss", "-e",
        "SELECT variable_value from information_schema.global_status WHERE variable_name='wsrep_cluster_state_uuid';"],
        capture_output=True, text=True, timeout=TIMEOUT)
    if process.returncode != 0:
        return "Error checking the status: {}".format(process.stderr)
    return process.stdout

def check_cluster_state_uuid():
    uuids = []
    for node in range(1, 4):
        contents = ''
        try:
            r = requests.get('http://monitoring{}/monitoring/node/cluster_state_uuid'.format(node), timeout=TIMEOUT)
            if r.status_code != 200:
                raise_exception_for_reply(r)
            contents = r.text
        except Exception as err:
            return 'Error reading cluster state uuid on node {}: {}'.format(node, err)
        uuids.append(contents)
    return uuids[0] == uuids[1] and uuids[0] == uuids[2]

def get_galera_status():
    process = subprocess.run(["mysql", "-h", "galera", "-u", "vufind", "-pvufind", "-ss", "-e",
        "SELECT variable_value from information_schema.global_status WHERE variable_name='wsrep_cluster_size';"],
        capture_output=True, text=True, timeout=TIMEOUT)
    if process.returncode != 0:
        return "Error checking the status: {}".format(process.stderr)
    if process.stdout.strip() != '3':
        return 'WRONG CLUSTER SIZE'
    if not check_cluster_state_uuid():
        return 'DIFFERENT CLUSTER STATE UUIDS'
    return 'OK'


def get_solr_status():
    results = []
    for node in range(1, 4):
        contents = ''
        try:
            r = requests.get('http://solr{}:8983/solr/admin/collections?action=clusterstatus'.format(node), timeout=TIMEOUT)
            if r.status_code != 200:
                raise_exception_for_reply(r)
            contents = r.text
        except Exception as err:
            return 'Error reading the solr clusterstatus: {}'.format(err)
        j = json.loads(contents)
        live_nodes = j['cluster']['live_nodes']
        if len(live_nodes) != 3:
            return 'Error: node {}: only {} live nodes'.format(node, live_nodes)
        if len(j['cluster']['collections']) != 4:
            return 'Wrong number of collections: {}'.format(len(j['cluster']['collections']))
        for collection_name, collection in j['cluster']['collections'].items():
            if collection['health'] != 'GREEN':
                return 'Collection {} has {} health.'.format(collection_name, collection['health'])
            if len(collection['shards']) != 1:
                return 'Collection {} has {} shard(s).'.format(collection_name, len(collection['shards']))
            for shard_name, shard in collection['shards'].items():
                if shard['state'] != 'active':
                    return 'Collection {} shard {} has the state {} (expected active).'.format(
                        collection_name, shard_name, shard['state'])
                replicas = shard['replicas']
                if len(replicas) != 3:
                    return 'A replica does not have 3 core nodes.'
                for replica_name, replica in replicas.items():
                    if replica['state'] != 'active':
                        return 'Collection {} shard {} replica {} has the state {} (expected: active).'.format(
                            collection_name, shard_name, replica_name, replica['state'])
                    if replica['node_name'] not in live_nodes:
                        return 'Collection {} shard {} replica {} has a node name that is not in the list of live nodes: {}' \
                            .format(collection_name, shard_name, replica_name, replica['node_name'])
    return 'OK'

def get_vufind_status():
    for node in range(1, 4):
        contents = ''
        try:
            r = requests.get('http://vufind{}/'.format(node), timeout=TIMEOUT)
            if r.status_code != 200:
                raise_exception_for_reply(r)
            contents = r.text
        except Exception as err:
            return 'Error getting vufind home page on node {}: {}'.format(node, err)
        if '</html>' not in contents:
            return 'Vufind home page not complete for node {}'.format(node)
        if '<h1>An error has occurred</h1>' in contents:
            return 'An error is reported in Vufind home page for node {}'.format(node)
    return 'OK'

