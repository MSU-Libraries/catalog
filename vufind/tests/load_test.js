/*  Load testing using k6
 *
 *  You must include the variable for the URL, number of users, and
 *  duration of the test, otherwise this script will fail. You can see
 *  progress as it runs, and can monitor your servers load with tools
 *  like htop as well. The final summary report will identify if any
 *  requests failed and the request time data. More documentation can
 *  be found on the official k6 site: https://k6.io/docs/.
 *
 *  Environment Variables:
 *    URL
 *      The URL to perform the load testing against
 *    K6_VUS
 *      Number of virtual users to make the request with
 *    K6_DURATION
 *      How long the test should be run for (ex: 1m, 30s)
 *
 *  CLI Options:
 *   --vus, -u
 *      Number of virtual users to make the request with
 *   --duration, -d
 *      How long the test should be run for (ex: 1m, 30s)
 *
 *  Usage Examples:
 *  Docker:
 *    docker run --rm -v /path/to/load_test.js:/load_test.js grafana/k6 run -u 10 -d 30s /load_test.js --env URL="https://mysite.edu"
 *  Ubuntu: (Installation: https://k6.io/docs/get-started/installation/#debian-ubuntu)
 *    k6 run -u 10 -d 30s load_test.js --env URL="https://mysite.edu"
 *
 */

import http from 'k6/http';
import {check, sleep} from 'k6';

export default function () {
  let res = http.get(`${__ENV.URL}`);
  check(res, { "status was 200": (r) => r.status == 200 });
  sleep(1);
};
