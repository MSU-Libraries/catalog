from flask import Flask, render_template
from pathlib import Path
import os

app = Flask(__name__)


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

@app.route('/monitoring')
def home():
    return render_template('index.html')


if __name__ == "__main__":
    app.run(debug=True, host='0.0.0.0', port=80)
