// k6 load test: players sitting in live rooms (next step 10).
// Until Reverb lands, every player's room page polls the server once a second,
// so the load is (players x 1 request/s). This script reproduces that with
// real sessions you prepare beforehand on a STAGING copy:
//
//   1. Create N test players and rooms there (e.g. with php artisan tinker).
//   2. Export one "cookie|room-url" line per player to sessions.txt
//      (the Laravel session cookie of a signed-in player, and their room URL).
//   3. k6 run -e BASE_URL=https://staging.example.org -e SESSIONS=sessions.txt tests/load/room-polling.js
//
// Target from the roadmap: 100 rooms x 10 players, p95 under 300 ms, no errors.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const BASE = __ENV.BASE_URL || 'http://localhost:8080';
const sessions = new SharedArray('sessions', () => open(__ENV.SESSIONS || 'sessions.txt').split('\n').filter(Boolean));

export const options = {
    scenarios: {
        players: { executor: 'constant-vus', vus: Math.min(1000, sessions.length), duration: '5m' },
    },
    thresholds: {
        http_req_failed: ['rate<0.005'],
        http_req_duration: ['p(95)<300'],
    },
};

export default function () {
    const [cookie, url] = sessions[(__VU - 1) % sessions.length].split('|');
    const res = http.get(url.startsWith('http') ? url : `${BASE}${url}`, {
        headers: { Cookie: cookie },
        redirects: 0,
    });
    check(res, { 'room page is 200': (r) => r.status === 200 });
    sleep(1);
}
