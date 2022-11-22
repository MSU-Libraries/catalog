from flask import Flask, render_template, Response
from pathlib import Path
import os
import requests

app = Flask(__name__, static_url_path='/monitoring/static')

def raise_exception_for_reply(r):
    raise Exception('Status code: {}. Response: "{}"'.format(r.status_code, r.text))

@app.route('/monitoring/node/logs/vufind')
def node_logs_vufind():
    return Path('/mnt/logs/vufind/vufind.log').read_text()

@app.route('/monitoring/node/logs/apache/error')
def node_logs_apache_error():
    return Path('/mnt/logs/apache/error.log').read_text()

@app.route('/monitoring/node/logs/apache/access')
def node_logs_apache_access():
    return Path('/mnt/logs/apache/access.log').read_text()

@app.route('/monitoring/node/logs/simplesamlphp')
def node_logs_simplesamlphp():
    return Path('/mnt/logs/simplesamlphp/simplesamlphp.log').read_text()

@app.route('/monitoring/logs/<path:service>')
def logs_vufind(service):
    logs = []
    for node in range(1, 4):
        contents = ''
        try:
            r = requests.get('http://monitoring{}/monitoring/node/logs/{}'.format(node, service))
            if r.status_code != 200:
                raise_exception_for_reply(r)
            contents = r.text
        except Exception as err:
            contents = 'Error reading the log: {}'.format(err)
        logs.append(contents)
    return render_template('logs.html', service=service, log1=logs[0], log2=logs[1], log3=logs[2])

@app.route('/monitoring')
def home():
    return render_template('index.html')


if __name__ == "__main__":
    app.run(debug=True, host='0.0.0.0', port=80)
