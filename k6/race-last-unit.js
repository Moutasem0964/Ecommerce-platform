// R2 proof: many orders try to reserve the LAST unit of a product at the same instant.
// Expected: exactly 1 succeeds, all others get FAILED_PRECONDITION, stock ends at 0.
//
// Run (from repo root):
//   docker run --rm --network ecommerce-platform_backend -v ${PWD}/proto:/proto:ro -v ${PWD}/k6:/scripts:ro grafana/k6:2.3.0 run /scripts/race-last-unit.js
import grpc from 'k6/net/grpc';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const TARGET = __ENV.INVENTORY_TARGET || 'inventory:9090';
const BUYERS = Number(__ENV.BUYERS || 50);
const PRODUCT_ID = Number(__ENV.PRODUCT_ID || 9001);

const client = new grpc.Client();
client.load(['/proto'], 'inventory/v1/inventory.proto');

const reserved = new Counter('orders_reserved');
const rejected = new Counter('orders_rejected_no_stock');
const unexpected = new Counter('orders_unexpected_result');

export const options = {
  scenarios: {
    last_unit_race: {
      executor: 'per-vu-iterations', // every buyer (VU) runs exactly once, all in parallel
      vus: BUYERS,
      iterations: 1,
      maxDuration: '30s',
    },
  },
  // The test FAILS unless the result is exactly right
  thresholds: {
    orders_reserved: ['count==1'],
    orders_rejected_no_stock: [`count==${BUYERS - 1}`],
    orders_unexpected_result: ['count==0'],
  },
};

// Runs once before the race: put exactly 1 unit in stock
export function setup() {
  client.connect(TARGET, { plaintext: true });
  const res = client.invoke('inventory.v1.InventoryService/SetStock', { product_id: PRODUCT_ID, quantity: 1 });
  client.close();
  if (res.status !== grpc.StatusOK) {
    throw new Error('SetStock failed: ' + JSON.stringify(res.error));
  }
  return {
    runId: Date.now() % 1000000000, // makes order ids unique per run
    startAt: Date.now() + 3000,     // common start time for every buyer
  };
}

// Each buyer: connect, wait for the common start time, then try to reserve
export default function (data) {
  client.connect(TARGET, { plaintext: true });

  const waitSeconds = (data.startAt - Date.now()) / 1000;
  if (waitSeconds > 0) {
    sleep(waitSeconds); // all buyers fire together
  }

  const orderId = data.runId * 1000 + __VU;
  const res = client.invoke('inventory.v1.InventoryService/ReserveStock', {
    order_id: orderId,
    items: [{ product_id: PRODUCT_ID, quantity: 1 }],
  });

  if (res.status === grpc.StatusOK) {
    reserved.add(1);
    console.log(`order ${orderId} WON the last unit`);
  } else if (res.status === grpc.StatusFailedPrecondition) {
    rejected.add(1);
  } else {
    unexpected.add(1);
    console.error(`order ${orderId} unexpected status ${res.status}: ${JSON.stringify(res.error)}`);
  }

  client.close();
}

// Runs once after the race: stock must be exactly 0, never negative
export function teardown() {
  client.connect(TARGET, { plaintext: true });
  const res = client.invoke('inventory.v1.InventoryService/GetStock', { product_ids: [PRODUCT_ID] });
  client.close();
  const qty = res.message.items[0].availableQuantity || 0;
  console.log(`final stock of product ${PRODUCT_ID}: ${qty}`);
  check(qty, { 'stock ended at exactly 0': (q) => q === 0 });
}
