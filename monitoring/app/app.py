import os
import flask

import collector


debug = os.getenv('STACK_NAME') != 'catalog-prod'
app = flask.Flask(__name__, static_url_path='/monitoring/static')

collector.init(debug)

import routes # pylint: disable=wrong-import-position,unused-import

if __name__ == "__main__":
    app.run(debug=debug, host='0.0.0.0', port=80)
