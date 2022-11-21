from flask import Flask, render_template
from pathlib import Path
import os

app = Flask(__name__)


@app.route('/monitoring/vufind_log')
def vufind_log():
    return Path('/mnt/shared/local/' + os.environ['STACK_NAME'] + '/logs/vufind.log').read_text()

@app.route('/monitoring')
def home():
    return render_template('index.html')


if __name__ == "__main__":
    app.run(debug=True, host='0.0.0.0', port=80)
