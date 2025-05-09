'''General utilities'''
import os
from contextlib import asynccontextmanager
import json
import asyncio
import aiohttp
import mariadb as db


MAX_PARALLEL_REQUESTS = 100
DEFAULT_TIMEOUT = 10


class ExecException(Exception):
    '''
    Custom exception class
    '''


async def async_exec(program: str, *args, timeout: int=DEFAULT_TIMEOUT) -> str:
    '''
    Run a command asyncronosly and return the output
    Args:
        program (str): Command to run
        args (*): Parameters to pass to the command
        timeout (int): Timeout for the command
    Returns:
        (str): Output from the command
    '''
    try:
        process = await asyncio.create_subprocess_exec(
            program, *args, stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(process.communicate(), timeout=timeout)
    except asyncio.TimeoutError as err:
        try:
            process.kill()
        except OSError:
            # Ignore 'no such process' error
            pass
        raise err
    if process.returncode != 0:
        raise ExecException(f"Exit code: {process.returncode}. Stderr: {stderr.decode().strip()}")
    return stdout.decode().strip()


def get_eventloop() -> asyncio.events.AbstractEventLoop:
    '''
    Get the asyncio eventloop required to run aysnc requests
    Returns:
        (AbstractEventLoop): Event loop
    '''
    try:
        return asyncio.get_event_loop()
    except RuntimeError as ex:
        if "There is no current event loop in thread" in str(ex):
            loop = asyncio.new_event_loop()
            asyncio.set_event_loop(loop)
            return asyncio.get_event_loop()
        raise ex


@asynccontextmanager
async def get_aiohttp_session(timeout: int=DEFAULT_TIMEOUT) -> aiohttp.ClientSession:
    '''
    Create a session for the asyncio connection
    Args:
        timeout (int): Timeout to set for the session
    Returns:
        (ClientSession): Yields the session and then closes it
    '''
    try:
        conn = aiohttp.TCPConnector(limit_per_host=100, limit=0, ttl_dns_cache=300)
        aiohttp_timeout = aiohttp.ClientTimeout(total=timeout)
        session = aiohttp.ClientSession(
            connector=conn, timeout=aiohttp_timeout, raise_for_status=True
        )
        yield session
    finally:
        await session.close()
        conn.close()


async def async_single_get(session: aiohttp.ClientSession, url: str, convert_to_json: bool=False) -> str: # pylint: disable=line-too-long
    '''
    Perform a single GET request with the session
    Args:
        session (ClientSession): Session object to use
        url (str): URL to query
        covert_to_json (bool): if the response should be converted to JSON
    Returns:
        (str): Respone from the request
    '''
    async with session.get(url) as response:
        text = await response.text()
        if convert_to_json:
            result = json.loads(text)
        else:
            result = text
        return result


def multiple_get(urls: list[str], convert_to_json: bool=False, timeout: int=DEFAULT_TIMEOUT) -> list: # pylint: disable=line-too-long
    '''
    Perform multiple GET requests
    Args:
        urls (list): Urls to query
        convert_to_json (bool): If the response should be converted to JSON
        timeout (int): Timeout for each request
    '''
    async def gather_with_concurrency():
        semaphore = asyncio.Semaphore(MAX_PARALLEL_REQUESTS)
        aiohttp_timeout = aiohttp.ClientTimeout(total=timeout)
        session = aiohttp.ClientSession(
            connector=conn, timeout=aiohttp_timeout, raise_for_status=True
        )

        async def get(url):
            async with semaphore:
                async with session.get(url, ssl=False) as response:
                    text = await response.text()
                    if convert_to_json:
                        obj = json.loads(text)
                    else:
                        obj = text
                    results[url] = obj

        await asyncio.gather(*(get(url) for url in urls))
        await session.close()

    loop = get_eventloop()
    conn = aiohttp.TCPConnector(limit_per_host=100, limit=0, ttl_dns_cache=300)
    results = {}
    loop.run_until_complete(gather_with_concurrency())
    conn.close()
    ordered_results = []
    for url in urls:
        ordered_results.append(results[url])
    return ordered_results

class DBConnection():
    '''
    Open a database connection
    '''
    def __init__(self):
        self.conn = None

    def __enter__(self):
        with open(os.getenv('MARIADB_MONITORING_PASSWORD_FILE'), 'r', encoding='UTF-8') as f:
            password = f.read().strip()
        self.conn = db.connect(
            user='monitoring', password=password, host='galera', database="monitoring"
        )
        del password
        return self.conn

    def __exit__(self, exc_type, exc_value, traceback):
        if self.conn is not None:
            self.conn.close()
