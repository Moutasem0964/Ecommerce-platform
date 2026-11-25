import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://web/api/v1';
const LABEL = __ENV.LABEL || 'run';
const RATE = Number(__ENV.RATE || 50);
const DURATION = __ENV.DURATION || '10s';
const INSTANCES = (__ENV.INSTANCES || '').split(',').filter(Boolean).map((pair) => pair.split('='));
const NAME_BY_IP = Object.fromEntries(INSTANCES.map(([name, ip]) => [ip, name]));

const servedBy = new Counter('served_by');
const retried = new Counter('retried_on_another_instance');

const thresholds = {
  http_req_failed: ['rate<0.01'],
  retried_on_another_instance: ['count>=0'],
};
for (const [name] of INSTANCES) {
  thresholds[`served_by{instance:${name}}`] = ['count>=0'];
}

export const options = {
  scenarios: {
    load: {
      executor: 'constant-arrival-rate',
      rate: RATE,
      timeUnit: '1s',
      duration: DURATION,
      preAllocatedVUs: 20,
      maxVUs: 100,
    },
  },
  summaryTrendStats: ['avg', 'med', 'p(95)', 'max'],
  thresholds,
};

const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

export function setup() {
  const probe = http.get(`${BASE}/ping`);
  if (probe.status === 429) {
    throw new Error('API rate limit is active (HTTP 429). Set API_RATE_LIMIT=1000000 in app/.env and recreate the app containers.');
  }
}

export default function () {
  const res = http.get(`${BASE}/products?page=${rand(1, 20)}`, { tags: { name: 'products_page' } });
  check(res, { 'status 200': (r) => r.status === 200 });

  const hops = (res.headers['X-Upstream'] || '').split(',').map((h) => h.trim()).filter(Boolean);
  if (hops.length > 1) {
    retried.add(1);
  }
  const ip = hops.length ? hops[hops.length - 1].split(':')[0] : 'none';
  servedBy.add(1, { instance: NAME_BY_IP[ip] || ip });
}

export function handleSummary(data) {
  const total = data.metrics.http_reqs.values.count;
  const failedRate = data.metrics.http_req_failed.values.rate;
  const failed = Math.round(failedRate * total);
  const duration = data.metrics.http_req_duration.values;
  const retries = data.metrics.retried_on_another_instance ? data.metrics.retried_on_another_instance.values.count : 0;

  const lines = [
    '',
    `R5 load distribution (${LABEL})`,
    '----------------------------------------',
    `requests     ${total}`,
    `failed       ${failed} (${(failedRate * 100).toFixed(2)}%)`,
    `retried      ${retries} (nginx moved the request to another instance)`,
    `p95          ${duration['p(95)'].toFixed(1)} ms`,
    '',
    'served by:',
  ];
  for (const [name] of INSTANCES) {
    const metric = data.metrics[`served_by{instance:${name}}`];
    const count = metric ? metric.values.count : 0;
    const share = total ? ((count / total) * 100).toFixed(1) : '0.0';
    lines.push(`  ${name.padEnd(28)} ${String(count).padStart(5)}  ${share.padStart(5)}%`);
  }
  lines.push('');

  return {
    stdout: lines.join('\n'),
    [`/results/r5-${LABEL}.json`]: JSON.stringify(data, null, 2),
  };
}
