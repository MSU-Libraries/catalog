import json
import asyncio
import aiohttp

MAX_PARALLEL_REQUESTS = 100
TIMEOUT = 10

def async_get_requests(urls, convert_to_json=False):
    conn = aiohttp.TCPConnector(limit_per_host=100, limit=0, ttl_dns_cache=300)
    results = []

    async def gather_with_concurrency():
        semaphore = asyncio.Semaphore(MAX_PARALLEL_REQUESTS)
        aiohttp_timeout = aiohttp.ClientTimeout(total=TIMEOUT)
        session = aiohttp.ClientSession(connector=conn, timeout=aiohttp_timeout, raise_for_status=True)

        async def get(url):
            async with semaphore:
                async with session.get(url, ssl=False) as response:
                    text = await response.text()
                    if convert_to_json:
                        obj = json.loads(text)
                    else:
                        obj = text
                    results.append(obj)

        await asyncio.gather(*(get(url) for url in urls))
        await session.close()

    def get_or_create_eventloop():
        try:
            return asyncio.get_event_loop()
        except RuntimeError as ex:
            if "There is no current event loop in thread" in str(ex):
                loop = asyncio.new_event_loop()
                asyncio.set_event_loop(loop)
                return asyncio.get_event_loop()
            raise ex

    loop = get_or_create_eventloop()
    loop.run_until_complete(gather_with_concurrency())
    conn.close()
    return results
