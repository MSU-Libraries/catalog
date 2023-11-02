from contextlib import contextmanager
import json
import asyncio
import aiohttp


MAX_PARALLEL_REQUESTS = 100
DEFAULT_TIMEOUT = 10


class ExecException(Exception):
    pass


async def async_exec(program: str, *args, timeout: int=DEFAULT_TIMEOUT) -> str:
    try:
        process = await asyncio.create_subprocess_exec(program, *args, stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE)
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
    try:
        return asyncio.get_event_loop()
    except RuntimeError as ex:
        if "There is no current event loop in thread" in str(ex):
            loop = asyncio.new_event_loop()
            asyncio.set_event_loop(loop)
            return asyncio.get_event_loop()
        raise ex


@contextmanager
def get_aiohttp_session(timeout: int=DEFAULT_TIMEOUT) -> aiohttp.ClientSession:
    try:
        conn = aiohttp.TCPConnector(limit_per_host=100, limit=0, ttl_dns_cache=300)
        aiohttp_timeout = aiohttp.ClientTimeout(total=timeout)
        session = aiohttp.ClientSession(connector=conn, timeout=aiohttp_timeout, raise_for_status=True)
        yield session
    finally:
        session.close()
        conn.close()


async def async_single_get(session: aiohttp.ClientSession, url: str, convert_to_json: bool=False) -> str:
    async with session.get(url) as response:
        text = await response.text()
        if convert_to_json:
            result = json.loads(text)
        else:
            result = text
        return result


def async_multiple_get(urls: list[str], convert_to_json: bool=False, timeout: int=DEFAULT_TIMEOUT) -> list:
    async def gather_with_concurrency():
        semaphore = asyncio.Semaphore(MAX_PARALLEL_REQUESTS)
        aiohttp_timeout = aiohttp.ClientTimeout(total=timeout)
        session = aiohttp.ClientSession(connector=conn, timeout=aiohttp_timeout, raise_for_status=True)

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
