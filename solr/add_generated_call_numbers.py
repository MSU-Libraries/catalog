#!/usr/bin/env python

import http.client
import json
import time

BATCH_SIZE = 65536


class StatusException(Exception):
    pass


def read_results():
    print("Read results")
    filename = "/mnt/shared/call-numbers/call_numbers.json"
    with open(filename, 'r', encoding='utf-8') as f:
        return json.load(f)


def connect():
    headers = {"Content-type": "application/json"}
    conn = http.client.HTTPConnection('localhost:8983')
    return conn, headers


def chunks(lst, n):
    # could be replaced by itertools.batched with python 3.12
    for i in range(0, len(lst), n):
        yield lst[i:i + n]


def check_status(resp):
    if resp.status != 200:
        print(f"\nUnexpected status: {resp.status} {resp.reason}\n")
        print("Response:\n")
        print(resp.read())
        raise StatusException(f"Unexpected status: {resp.status} {resp.reason}")


def send_batch_to_solr(conn, headers, batch):
    update_objects = []
    for doc in batch:
        obj = {"id": doc['id'], "callnumber-label": {"add-distinct": doc['cn']}}
        update_objects.append(obj)
    query = json.dumps(update_objects, ensure_ascii=False)
    path = '/solr/biblio/update?commit=true'
    conn.request('POST', path, body=query, headers=headers)
    resp = conn.getresponse()
    check_status(resp)
    resp.read()


def send_by_batch(docs, conn, headers):
    print("Send by batch")
    n = 0
    for batch in chunks(docs, BATCH_SIZE):
        n = n + 1
        print(f"Batch number {n} / {int(len(docs)/BATCH_SIZE)+1}", end='\r')
        send_batch_to_solr(conn, headers, batch)
    print("\n")


def remove_deleted_records(conn, headers):
    print("Remove deleted records")
    path = '/solr/biblio/update?commit=true'
    query = json.dumps({"delete": {"query": "!(institution:*)"}})
    conn.request('POST', path, body=query, headers=headers)
    resp = conn.getresponse()
    check_status(resp)
    resp.read()


def main():
    start = time.perf_counter()
    docs = read_results()
    conn, headers = connect()
    try:
        send_by_batch(docs, conn, headers)
        remove_deleted_records(conn, headers)
    finally:
        conn.close()
    end = time.perf_counter()
    print(f"\nDone. Execution time: {int(end - start)} s")


main()
