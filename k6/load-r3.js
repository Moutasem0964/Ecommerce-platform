import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://web/api/v1';
const LABEL = __ENV.LABEL || 'run';
const PRODUCTS = Number(__ENV.PRODUCTS || 10000);
const SHOPPERS = Number(__ENV.SHOPPERS || 20);
const CATEGORIES = ['kitchen', 'electronics', 'books', 'toys', 'garden', 'sports', 'beauty', 'office', 'pets', 'automotive', 'music', 'clothing'];
const ENDPOINTS = ['products_page', 'products_by_category', 'product_details', 'my_orders'];

const thresholds = { http_req_failed: [{ threshold: 'rate<0.01', abortOnFail: true, delayAbortEval: '10s' }] };
for (const name of ENDPOINTS) {
  thresholds[`http_req_duration{name:${name}}`] = ['max>=0'];
  thresholds[`http_reqs{name:${name}}`] = ['count>=0'];
}

export const options = {
  scenarios: {
    shoppers: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: __ENV.QUICK === '1'
        ? [
            { duration: '10s', target: 50 },
            { duration: '50s', target: 50 },
            { duration: '5s', target: 0 },
          ]
        : [
            { duration: '30s', target: 20 },
            { duration: '1m', target: 20 },
            { duration: '30s', target: 50 },
            { duration: '1m', target: 50 },
            { duration: '15s', target: 0 },
          ],
      gracefulRampDown: '10s',
    },
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
  thresholds,
};

const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

export function setup() {
  for (let i = 0; i < 80; i++) {
    const probe = http.get(`${BASE}/ping`, { tags: { name: 'setup_probe' } });
    if (probe.status === 429) {
      throw new Error('API rate limit is still active (HTTP 429). Set API_RATE_LIMIT=1000000 in app/.env and recreate the app container.');
    }
  }

  const emails = ['sara@test.com'];
  for (let i = 1; i < SHOPPERS; i++) {
    emails.push(`load${i}@test.local`);
  }

  const tokens = emails.map((email) => {
    const res = http.post(`${BASE}/auth/login`, JSON.stringify({ email, password: 'secret123' }), {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      tags: { name: 'setup_login' },
    });
    if (res.status !== 200) {
      throw new Error(`login failed for ${email}: HTTP ${res.status}`);
    }
    return res.json('access_token');
  });

  return { tokens };
}

export default function (data) {
  const roll = Math.random();
  const headers = { Accept: 'application/json' };
  let res;

  if (roll < 0.35) {
    res = http.get(`${BASE}/products?page=${rand(1, Math.ceil(PRODUCTS / 15))}`, { headers, tags: { name: 'products_page' } });
  } else if (roll < 0.55) {
    const category = CATEGORIES[rand(0, CATEGORIES.length - 1)];
    res = http.get(`${BASE}/products?category=${category}&page=${rand(1, 50)}`, { headers, tags: { name: 'products_by_category' } });
  } else if (roll < 0.85) {
    res = http.get(`${BASE}/products/${rand(1, PRODUCTS)}`, { headers, tags: { name: 'product_details' } });
  } else {
    const token = data.tokens[rand(0, data.tokens.length - 1)];
    res = http.get(`${BASE}/orders?page=${rand(1, 5)}`, {
      headers: { ...headers, Authorization: `Bearer ${token}` },
      tags: { name: 'my_orders' },
    });
  }

  check(res, { 'status is 200': (r) => r.status === 200 });
  sleep(Math.random() * 0.4 + 0.1);
}

const ms = (v) => (v === undefined ? '-' : v.toFixed(1));

export function handleSummary(data) {
  const endpoints = {};
  for (const name of ENDPOINTS) {
    const d = data.metrics[`http_req_duration{name:${name}}`];
    const c = data.metrics[`http_reqs{name:${name}}`];
    if (!d) continue;
    endpoints[name] = {
      requests: c ? c.values.count : 0,
      avg: d.values.avg,
      p50: d.values.med,
      p95: d.values['p(95)'],
      p99: d.values['p(99)'],
      max: d.values.max,
    };
  }

  const all = data.metrics.http_req_duration.values;
  const result = {
    label: LABEL,
    date: new Date().toISOString(),
    overall: {
      requests: data.metrics.http_reqs.values.count,
      rps: data.metrics.http_reqs.values.rate,
      failed_rate: data.metrics.http_req_failed.values.rate,
      p50: all.med,
      p95: all['p(95)'],
      p99: all['p(99)'],
      max: all.max,
    },
    endpoints,
  };

  const lines = [
    '',
    `R3 load test: ${LABEL}`,
    `requests ${result.overall.requests}, ${result.overall.rps.toFixed(1)} req/s, failed ${(result.overall.failed_rate * 100).toFixed(2)}%`,
    '',
    'endpoint                 requests     p50 ms     p95 ms     p99 ms     max ms',
  ];
  for (const [name, e] of Object.entries(endpoints)) {
    lines.push(`${name.padEnd(24)} ${String(e.requests).padStart(8)} ${ms(e.p50).padStart(10)} ${ms(e.p95).padStart(10)} ${ms(e.p99).padStart(10)} ${ms(e.max).padStart(10)}`);
  }
  lines.push(`${'ALL'.padEnd(24)} ${String(result.overall.requests).padStart(8)} ${ms(all.med).padStart(10)} ${ms(all['p(95)']).padStart(10)} ${ms(all['p(99)']).padStart(10)} ${ms(all.max).padStart(10)}`);
  lines.push('');

  return {
    stdout: lines.join('\n'),
    [`/results/${LABEL}.json`]: JSON.stringify(result, null, 2),
  };
}
