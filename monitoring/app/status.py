'''Status checker'''
import os
import pathlib
import asyncio
from datetime import datetime, timedelta
import re
from aiohttp import ClientError, ClientSession
import humanize

from util import ExecException, async_exec, multiple_get, async_single_get, get_aiohttp_session # pylint: disable=import-error


# Galera

async def _node_cluster_state_uuid() -> str:
    try:
        with open(os.getenv('MARIADB_VUFIND_PASSWORD_FILE'), 'r', encoding='UTF-8') as f:
            password = f.read().strip()
            f.close()
        return await async_exec("mariadb", "--skip_ssl", "-h", "galera", "-u", "vufind",
            f"-p{password}", "-ss", "-e",
            "SELECT variable_value from information_schema.global_status WHERE " \
            "variable_name='wsrep_cluster_state_uuid';")
    except ExecException as ex:
        return f"Error getting the cluster state uuid: {ex}"
    except asyncio.TimeoutError:
        return "Timeout getting the cluster state uuid"

def _check_cluster_state_uuid(statuses: list[dict]) -> bool:
    uuid0 = statuses[0]['cluster_state_uuid']
    uuid1 = statuses[1]['cluster_state_uuid']
    uuid2 = statuses[2]['cluster_state_uuid']
    return uuid0 == uuid1 and uuid0 == uuid2

async def get_galera_status(statuses: list[dict]) -> str:
    '''
    Get the current status from Galera
    Args:
        statuses (list): Status data
    Returns:
        (str):  Error message or 'OK'
    '''
    try:
        with open(os.getenv('MARIADB_VUFIND_PASSWORD_FILE'), 'r', encoding='UTF-8') as f:
            password = f.read().strip()
            f.close()
        cluster_size = await async_exec("mariadb", "--skip_ssl", "-h", "galera", "-u", "vufind",
            f"-p{password}", "-ss", "-e",
            "SELECT variable_value from information_schema.global_status " \
            "WHERE variable_name='wsrep_cluster_size';")
        del password
    except ExecException as ex:
        return f"Error checking the galera status: {ex}"
    except asyncio.TimeoutError:
        return "Timeout when checking the galera status"
    if cluster_size != '3':
        return f'Error: wrong cluster size: {cluster_size}'
    if not _check_cluster_state_uuid(statuses):
        return 'Error: different cluster state uuids'
    return 'OK'


# Solr

def _check_shard(collection_name: str, shard_name: str, shard: dict, live_nodes: list) -> str:
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

def _check_collection(collection_name: str, collection: dict, live_nodes: list[str]) -> str:
    if collection['health'] != 'GREEN':
        return f"Collection {collection_name} has {collection['health']} health."
    if len(collection['shards']) != 1:
        return f"Collection {collection_name} has {len(collection['shards'])} shard(s)."
    for shard_name, shard in collection['shards'].items():
        res = _check_shard(collection_name, shard_name, shard, live_nodes)
        if res != 'OK':
            return res
    return 'OK'

async def _node_solr_status(aiohttp_session: ClientSession) -> str:
    node = os.getenv('NODE')
    url = f'http://solr{node}:8983/solr/admin/collections?action=clusterstatus'
    try:
        j = await async_single_get(aiohttp_session, url, convert_to_json=True)
    except asyncio.TimeoutError:
        return 'Timeout when reading the solr clusterstatus'
    except ClientError as err:
        return f'Error reading the solr clusterstatus: {err}'
    live_nodes = j['cluster']['live_nodes']
    if len(live_nodes) != 3:
        return f'Error: only {len(live_nodes)} live nodes'
    if len(j['cluster']['collections']) != 5:
        return f"Wrong number of collections: {len(j['cluster']['collections'])}"
    for collection_name, collection in j['cluster']['collections'].items():
        res = _check_collection(collection_name, collection, live_nodes)
        if res != 'OK':
            return res
    return 'OK'

def get_solr_status(statuses: list[dict]) -> str:
    '''
    Get the current status from Solr
    Args:
        statuses (list): Status data
    Returns:
        (str):  Error message or 'OK'
    '''
    for node in range(1, 4):
        node_status = statuses[node-1]['solr']
        if node_status != 'OK':
            return f'Error on node {node}: {node_status}'
    return 'OK'


# Vufind

async def _check_vufind_home_page(node: str, aiohttp_session: ClientSession) -> str:
    try:
        text = await async_single_get(aiohttp_session, f'http://vufind{node}/')
    except asyncio.TimeoutError:
        return 'Timeout when reading vufind home page'
    except ClientError as err:
        return f'Error reading vufind home page: {err}'
    if '</html>' not in text:
        return 'Vufind home page not complete'
    if '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
        return 'An error is reported in Vufind home page'
    return 'OK'

async def _check_vufind_record_page(node: str, aiohttp_session: ClientSession) -> str:
    try:
        text = await async_single_get(
            aiohttp_session, f'http://vufind{node}/Record/folio.in00006782951'
        )
    except asyncio.TimeoutError:
        return 'Timeout when reading vufind record page'
    except ClientError as err:
        return f'Error reading vufind record page: {err}'
    if '</html>' not in text:
        result = 'Vufind record page folio.in00006782951 not complete'
    elif '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
        result = 'An error is reported in Vufind record page folio.in00006782951'
    elif 'Bill Konigsberg' not in text:
        result = 'Vufind record page folio.in00006782951 not complete'
    elif 'PS3611.O586 O98 2014' not in text:
        result = 'Vufind record page folio.in00006782951 not complete'
    else:
        result = 'OK'
    return result

async def _check_vufind_search_base(aiohttp_session: ClientSession, url: str, lookfor: str, page: str) -> str: # pylint: disable=line-too-long
    try:
        text = await async_single_get(aiohttp_session, url)
    except asyncio.TimeoutError:
        return f'Timeout when reading vufind {page} page'
    except ClientError as err:
        return f'Error reading vufind {page} page: {err}'
    if '</html>' not in text:
        return f'Vufind {page} page not complete'
    if '<h1>An error has occurred' in text or '<p>An error has occurred' in text:
        return f'An error is reported in Vufind {page} page'
    if lookfor not in text:
        return f'Vufind {page} page not complete'
    return 'OK'

async def _check_vufind_search_page(node: str, aiohttp_session: ClientSession) -> str:
    url = f'http://vufind{node}/Search/Results?limit=5&dfApplied=1&lookfor=Out+of+the+pocket&type=AllFields' # pylint: disable=line-too-long
    return await _check_vufind_search_base(
        aiohttp_session, url, 'folio.in00006782951', 'search'
    )

async def _check_vufind_browse_by_subject_page(node: str, aiohttp_session: ClientSession) -> str:
    url = f'http://vufind{node}/Alphabrowse/Home?source=topic&from=Science+Fiction'
    return await _check_vufind_search_base(
        aiohttp_session, url, '>Science fiction.</a>', 'browse by subject'
    )

async def _check_vufind_browse_by_author_page(node: str, aiohttp_session: ClientSession) -> str:
    url = f'http://vufind{node}/Alphabrowse/Home?source=author&from=Hoek%2C+M'
    return await _check_vufind_search_base(
        aiohttp_session, url, '>Hoek, Marga</a>', 'browse by author'
    )

async def _check_vufind_browse_by_title_page(node: str, aiohttp_session: ClientSession) -> str:
    url = f'http://vufind{node}/Alphabrowse/Home?source=title&from=Artificial+Intelligence+and+the+City' # pylint: disable=line-too-long
    return await _check_vufind_search_base(
        aiohttp_session, url, 'Urbanistic Perspectives on AI', 'browse by title'
    )

async def _check_vufind_browse_by_call_number_page(node: str, aiohttp_session: ClientSession) -> str: # pylint: disable=line-too-long
    url = f'http://vufind{node}/Alphabrowse/Home?source=lcc&from=DC96.S'
    return await _check_vufind_search_base(
        aiohttp_session, url, 'DC96 .S25 2022', 'browse by call number'
    )

async def _check_vufind_browse_by_series_page(node: str, aiohttp_session: ClientSession) -> str:
    url = f'http://vufind{node}/Alphabrowse/Home?source=series&from=Concise+H'
    return await _check_vufind_search_base(
        aiohttp_session, url, '>Concise Hornbook Series</a>', 'browse by series'
    )

async def _node_vufind_status(aiohttp_session: ClientSession) -> str:
    node = os.getenv('NODE')
    results = await asyncio.gather(
        _check_vufind_home_page(node, aiohttp_session),
        _check_vufind_record_page(node, aiohttp_session),
        _check_vufind_search_page(node, aiohttp_session),
        _check_vufind_browse_by_subject_page(node, aiohttp_session),
        _check_vufind_browse_by_author_page(node, aiohttp_session),
        _check_vufind_browse_by_title_page(node, aiohttp_session),
        _check_vufind_browse_by_call_number_page(node, aiohttp_session),
        _check_vufind_browse_by_series_page(node, aiohttp_session),
    )
    for res in results:
        if res != 'OK':
            return res
    return 'OK'

def get_vufind_status(statuses: list[dict]) -> str:
    '''
    Get the current status for VuFind on all nodes
    Args:
        statuses (list): Raw status data
    Returns:
        (str): Message with the current VuFind status
    '''
    stack_name = os.getenv('STACK_NAME')
    one_vufind = re.fullmatch(r'devel-.*|review-.*', stack_name)
    missing_vufind_count = 0
    for node in range(1, 4):
        node_status = statuses[node-1]['vufind']
        if (one_vufind and 'Name does not resolve' in node_status and missing_vufind_count < 2):
            missing_vufind_count += 1
            node_status = 'OK'
        if node_status != 'OK':
            return f'Error on node {node}: {node_status}'
    return 'OK'


# Available memory and disk space

async def node_available_memory() -> str:
    '''
    Get the memory available on the current node
    Returns:
        (str): Amount of memory available, or the error retrieving it
    '''
    try:
        return await async_exec("/bin/sh", "-c", "free | grep Mem | awk '{print $7/$2 * 100.0}'")
    except ExecException as ex:
        return f"Error getting available memory: {ex}"
    except asyncio.TimeoutError:
        return "Timeout when getting available memory"

async def node_available_disk_space() -> str:
    '''
    Get the disk space available on the current node
    Returns:
        (str): Amount of disk space available, or the error retrieving it
    '''
    try:
        return await async_exec(
            "/bin/sh", "-c", "df / | grep overlay | awk '{print $4/$2 * 100.0}'"
        )
    except ExecException as ex:
        return f"Error getting available disk space: {ex}"
    except asyncio.TimeoutError:
        return "Timeout when getting available disk space"

def get_memory_status(statuses: list[dict]) -> str:
    '''
    Get the memory available on each node
    Args:
        statuses (list): Status data
    Returns:
        (str): Message with information on the memory
    '''
    for node in range(1, 4):
        available_memory = statuses[node-1]['available_memory']
        try:
            float(available_memory)
        except ValueError:
            return f'Returned value for available memory is not a number on node {node}: ' \
            f'{available_memory}'
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

def get_disk_space_status(statuses: list[dict]) -> str:
    '''
    Get the disk space available on each node
    Args:
        statuses (list): Status data
    Returns:
        (str): Message with information on the disk usage
    '''
    for node in range(1, 4):
        available_disk_space = statuses[node-1]['available_disk_space']
        try:
            float(available_disk_space)
        except ValueError:
            return f'Returned value for available disk space is not a number on node {node}:' \
            f'{available_disk_space}'
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

def _harvest_delta(name: str) -> timedelta:
    '''
    Get the expected delay between harvest updates
    Args:
        name (str): Name of the harvest
    Return:
        (timedelta): Amount of time between harvests
    '''
    if name == 'authority':
        return timedelta(days=7)
    if name == 'optimize':
        return timedelta(days=31)
    return timedelta(days=1)

def _node_cron_exit_codes() -> dict[str, str]:
    '''
    Get the exit code for the cron jobs on the node
    Returns:
        (dict): A dict with each cron job and their exit code
    '''
    paths = {
        'folio': '/mnt/logs/harvests/folio_exit_code',
        'hlm': '/mnt/logs/harvests/hlm_exit_code',
        'authority': '/mnt/logs/harvests/authority_exit_code',
        'reserves': '/mnt/logs/vufind/reserves_exit_code',
        'searches': '/mnt/logs/vufind/searches_exit_code',
        'sessions': '/mnt/logs/vufind/sessions_exit_code',
        'optimize': '/mnt/logs/vufind/optimize_exit_code',
        'solr': '/mnt/logs/backups/solr_exit_code',
        'db': '/mnt/logs/backups/db_exit_code',
        'alpha': '/mnt/logs/backups/alpha_exit_code',
        'alphabrowse': '/mnt/logs/alphabrowse/alphabrowse_exit_code',
    }
    exit_codes = {}
    for name, path in paths.items():
        path = pathlib.Path(path)
        if path.is_file():
            date = datetime.fromtimestamp(path.stat().st_mtime)
            check_date = datetime.now() - _harvest_delta(name)
            if date > check_date:
                exit_code = path.read_text(encoding="utf8").strip()
            else:
                exit_code = 'too_old'
        else:
            exit_code = 'file_not_found'
        exit_codes[name] = exit_code
    return exit_codes

def get_cron_status(name: str, statuses: list[dict]) -> str:
    '''
    Get the status of a given cron
    Args:
        name (str): Name of the cron
        statuses (list): Status data
    Returns:
        (str): Current status of that cron job
    '''
    nb_executed = 0
    node_where_executed = 0
    exit_code = ''
    node_with_error = 0
    for node in range(1, 4):
        code = statuses[node-1]['harvests'][name]
        if code == '0':
            nb_executed += 1
            node_where_executed = node
        elif exit_code == '' and code not in ('file_not_found', 'too_old'):
            exit_code = code
            node_with_error = node
    if nb_executed == 3:
        return 'OK - executed on all 3 nodes'
    if nb_executed == 2:
        return 'OK - executed on 2 nodes'
    if nb_executed == 1:
        return f'OK - executed on node {node_where_executed}'
    if exit_code == '':
        readable_delta = humanize.naturaldelta(_harvest_delta(name))
        readable_delta = re.sub(r'^a\s', '', readable_delta)
        return f'This was not executed on any node in the last {readable_delta}'
    return f'Error: exit code on node {node_with_error}: {exit_code}'


# Getting all the node statuses at once

def get_node_status() -> dict:
    '''
    Get the status for the current node
    Returns:
        (dict): Data with the service status on the current node
    '''
    async def async_inner():
        async with get_aiohttp_session() as aiohttp_session:
            commands = [
                _node_cluster_state_uuid(),
                _node_solr_status(aiohttp_session),
                _node_vufind_status(aiohttp_session),
                node_available_memory(),
                node_available_disk_space()
            ]
            return await asyncio.gather(*commands)
    results = asyncio.run(async_inner())
    status = {}
    status['cluster_state_uuid'] = results[0]
    status['solr'] = results[1]
    status['vufind'] = results[2]
    status['available_memory'] = results[3]
    status['available_disk_space'] = results[4]
    status['harvests'] = _node_cron_exit_codes()
    return status

def get_node_statuses() -> list[dict] | str:
    '''
    Get the status of each monitoting node
    Returns:
        (list|str): The status from each node or the error message
    '''
    urls = []
    for node in range(1, 4):
        urls.append(f'http://monitoring{node}/monitoring/node/status')
    try:
        statuses = multiple_get(urls, convert_to_json=True, timeout=20)
    except ClientError as err:
        return f'Error reading node status: {err}'
    except asyncio.TimeoutError:
        return 'Timeout when reading node status'
    return statuses
