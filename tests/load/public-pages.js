// k6 load test: public pages and the liveness endpoint (next step 10).
// Run against a staging copy, never production:
//   k6 run -e BASE_URL=https://staging.example.org tests/load/public-pages.js
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://localhost:8080';

export const options = {
    scenarios: {
        visitors: { executor: 'ramping-vus', startVUs: 0, stages: [
            { duration: '1m', target: 50 },
            { duration: '3m', target: 200 },
            { duration: '1m', target: 0 },
        ] },
    },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<800'],
    },
};

export default function () {
    for (const path of ['/', '/leaderboard', '/about', '/terms', '/up']) {
        const res = http.get(`${BASE}${path}`);
        check(res, { [`${path} is 200`]: (r) => r.status === 200 });
    }
    sleep(1);
}
