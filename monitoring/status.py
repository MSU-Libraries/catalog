import json
import requests
import subprocess

def raise_exception_for_reply(r):
    raise Exception('Status code: {}. Response: "{}"'.format(r.status_code, r.text))


def cluster_state_uuid():
    process = subprocess.run(["mysql", "-h", "galera", "-u", "vufind", "-pvufind", "-ss", "-e", "SELECT variable_value from information_schema.global_status WHERE variable_name='wsrep_cluster_state_uuid';"], capture_output=True)
    if process.returncode != 0:
        return "Error checking the status: {}".format(process.stderr)
    return process.stdout

def check_cluster_state_uuid():
    uuids = []
    for node in range(1, 4):
        contents = ''
        try:
            r = requests.get('http://monitoring{}/monitoring/node/cluster_state_uuid'.format(node))
            if r.status_code != 200:
                raise_exception_for_reply(r)
            contents = r.text
        except Exception as err:
            return 'Error reading cluster state uuid on node {}: {}'.format(node, err)
        uuids.append(contents)
    return uuids[0] == uuids[1] and uuids[0] == uuids[2]

def get_galera_status():
    process = subprocess.run(["mysql", "-h", "galera", "-u", "vufind", "-pvufind", "-ss", "-e", "SELECT variable_value from information_schema.global_status WHERE variable_name='wsrep_cluster_siz';"], capture_output=True)
    if process.returncode != 0:
        return "Error checking the status: {}".format(process.stderr)
    if process.stdout != '3':
        return 'WRONG CLUSTER SIZE'
    if not check_cluster_state_uuid():
        return 'DIFFERENT CLUSTER STATE UUIDS'
    return 'OK'


def get_solr_status():
    results = []
    for node in range(1, 4):
        contents = ''
        try:
            r = requests.get('http://solr{}:8983/solr/admin/collections?action=clusterstatus'.format(node))
            if r.status_code != 200:
                raise_exception_for_reply(r)
            contents = r.text
        except Exception as err:
            return 'Error reading the solr clusterstatus: {}'.format(err)
        j = json.loads(contents)
        live_nodes = j['cluster']['live_nodes'].length
        if live_nodes != 3:
            return 'Error: node {}: {} live nodes'.format(node, live_nodes)
    return 'OK'
