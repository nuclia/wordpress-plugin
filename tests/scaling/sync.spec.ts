import { expect, test, type APIRequestContext, type Page } from '@playwright/test';

const route = '/wp-json/rag-scaling/v1/';
const type = 'rag_scale';
const startAction = 'progress_agentic_rag_manual_sync_start';
const statusAction = 'progress_agentic_rag_background_sync_status';

async function fixture(request: APIRequestContext, operation: string, data = {}): Promise<any> {
  const response = await request.post(route + operation, {
    headers: { 'X-RAG-Scaling-Key': 'scaling-only' },
    data
  });
  expect(response.ok(), await response.text()).toBeTruthy();
  return response.json();
}

async function openAdmin(page: Page): Promise<void> {
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('scaling-only');
  await page.locator('#wp-submit').click();
  await page.waitForURL(/wp-admin/);
  // Keep the tests in control of polling, rather than racing the UI timer.
  await page.route('**/assets/js/admin.js*', (route) => route.abort());
  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag&tab=indexation');
}

async function ajax(page: Page, action: string): Promise<any> {
  return page.evaluate(async ({ action, type }) => {
    const config = (window as any).progressAgenticRagAdmin;
    const body = new URLSearchParams({
      action,
      _ajax_nonce: config.nonce,
      'post_types[]': type
    });
    const started = performance.now();
    const response = await fetch(config.ajaxUrl, { method: 'POST', body });
    return { status: response.status, elapsed: performance.now() - started, body: await response.json() };
  }, { action, type });
}

test.beforeEach(async ({ request }) => {
  expect(await fixture(request, 'seed')).toMatchObject({ posts: 10000, mapped: 9000 });
});

test('fake KB reproduces ignored offsets and advancing page numbers', async ({ request }) => {
  const first = await fixture(request, 'probe', { query: 'page=0&size=250' });
  const ignored = await fixture(request, 'probe', { query: 'from=250&size=250' });
  const second = await fixture(request, 'probe', { query: 'page=1&size=250' });
  expect(ignored.resources[0].id).toBe(first.resources[0].id);
  expect(second.resources[0].id).not.toBe(first.resources[0].id);
  expect(second.pagination.page).toBe(1);
});

test('a 409 conflict lookup is one slug request, not a scan of the 10,000-resource KB', async ({ request }) => {
  const result = await fixture(request, 'lookup');
  const { requests } = await fixture(request, 'metrics');
  expect(result).toMatchObject({ ok: true, rid: expect.stringContaining('scale-rid-') });
  expect(requests).toHaveLength(1);
  expect(new URL(requests[0].url).pathname).toMatch(/\/slug\/\d+$/);
});

test('repeated status polls do not contact the KB or enqueue work', async ({ page, request }) => {
  await openAdmin(page);
  await fixture(request, 'clear');
  const before = await fixture(request, 'metrics');
  for (let poll = 0; poll < 3; poll++) {
    const response = await ajax(page, statusAction);
    expect.soft(response.body.success).toBe(true);
  }
  const after = await fixture(request, 'metrics');
  expect.soft(after.requests, 'Status reporting must make zero upstream calls').toHaveLength(0);
  expect(after.pending).toBe(before.pending);
});

test('manual start returns before slow KB preparation', async ({ page, request }) => {
  await fixture(request, 'seed', { delay_ms: 100 });
  await openAdmin(page);
  await fixture(request, 'clear');
  const response = await ajax(page, startAction);
  const metrics = await fixture(request, 'metrics');
  expect.soft(metrics.requests, 'No remote preparation should run inside the start request').toHaveLength(0);
  expect.soft(response.elapsed, 'Start should take under 2s even when a full scan would take at least 4s').toBeLessThan(2000);
  expect.soft(response.body.success).toBe(true);
  expect.soft(response.body.data.status).toMatch(/running|preparing|starting/);
  expect(metrics.pending, 'Start must actually persist a background task').toBeGreaterThan(0);
});

test('one preparation job performs at most one KB page and leaves continuation work', async ({ page, request }) => {
  await openAdmin(page);
  await ajax(page, startAction);
  await fixture(request, 'clear');
  const worker = await fixture(request, 'run-one');
  const metrics = await fixture(request, 'metrics');
  expect(worker.ran, 'Manual start must queue preparation for the worker').toBe(true);
  expect.soft(worker.status, 'The first preparation chunk must not fail').toBe('complete');
  expect(worker.yielded, 'The actual Action Scheduler runner must yield after a preparation chunk').toBe(true);
  expect.soft(metrics.requests.length, 'One chunk may fetch at most one KB page').toBeLessThanOrEqual(1);
  expect.soft(metrics.pending, 'Remaining preparation must survive as queued continuation work').toBeGreaterThan(0);
  expect(metrics.mapped, 'The first page must not prematurely discard later-page mappings').toBeGreaterThanOrEqual(9000);
});

test('10,000 unsynced posts do not all get scheduled inside a single start request', async ({ page, request }) => {
  // An empty KB avoids the pagination bug, exposing unbounded scheduling independently.
  await fixture(request, 'seed', { mapped: 0, upstream: 0 });
  await openAdmin(page);
  const response = await ajax(page, startAction);
  expect(response.body.success).toBe(true);
  const metrics = await fixture(request, 'metrics');
  expect(metrics.pending, 'The start request must persist work').toBeGreaterThan(0);
  expect(metrics.pending, 'Preparation/discovery must queue bounded batches, not all 10,000 posts at once').toBeLessThanOrEqual(100);
});

test('preparation continues across bounded jobs and finishes without losing existing mappings', async ({ page, request }) => {
  await fixture(request, 'seed', { mapped: 10000 });
  await openAdmin(page);
  const start = await ajax(page, startAction);
  expect(start.body.success).toBe(true);

  let finalStatus = start.body.data;
  for (let job = 0; job < 100 && finalStatus.status !== 'complete'; job++) {
    await fixture(request, 'clear');
    const worker = await fixture(request, 'run-one');
    expect(worker.ran, 'Preparation must persist continuation work until it is complete').toBe(true);
    expect(worker.status).toBe('complete');
    const metrics = await fixture(request, 'metrics');
    expect(metrics.requests.length, `Preparation job ${job} exceeded its API-call budget`).toBeLessThanOrEqual(1);
    expect(metrics.mapped).toBe(10000);
    finalStatus = worker.manual;
  }
  expect(finalStatus.status, 'Preparation must eventually finish, not only stay within each job budget').toBe('complete');
  expect((await fixture(request, 'metrics')).pending).toBe(0);
});

test('one complete background scan recovers 1,000 missing local mappings without uploading duplicates', async ({ page, request }) => {
  await openAdmin(page);
  const start = await ajax(page, startAction);
  let finalStatus = start.body.data;
  for (let job = 0; job < 100 && finalStatus.status === 'running'; job++) {
    const worker = await fixture(request, 'run-one', { preparation: true });
    expect(worker.ran).toBe(true);
    expect(worker.status).toBe('complete');
    finalStatus = worker.manual;
  }
  expect(finalStatus).toMatchObject({ status: 'complete', total: 0, recovered: 1000 });
  const metrics = await fixture(request, 'metrics');
  expect(metrics.mapped).toBe(10000);
  expect(metrics.pending).toBe(0);
  expect(metrics.requests).toHaveLength(40);
  expect(metrics.requests.every((entry: any) => entry.method === 'GET')).toBe(true);
});

test('stale mappings are removed only after the scan and missing upstream posts are queued in batches', async ({ page, request }) => {
  await fixture(request, 'seed', { mapped: 10000, upstream: 9000 });
  await openAdmin(page);
  let status = (await ajax(page, startAction)).body.data;
  for (let job = 0; job < 200 && status.preparing; job++) {
    const worker = await fixture(request, 'run-one', { preparation: true });
    expect(worker.ran).toBe(true);
    expect(worker.status).toBe('complete');
    status = worker.manual;
  }
  expect(status).toMatchObject({ status: 'running', preparing: false, total: 1000 });
  const before = await fixture(request, 'metrics');
  expect(before.mapped).toBe(9000);
  expect(before.pending).toBe(1000);
  const uploaded = await fixture(request, 'run-one');
  expect(uploaded.status, JSON.stringify(uploaded)).toBe('complete');
  expect(uploaded.manual, JSON.stringify(uploaded)).toMatchObject({ completed: 1, failed: 0 });
  expect((await fixture(request, 'metrics')).mapped).toBe(9001);
  await fixture(request, 'clear');
  expect(await fixture(request, 'replay', { args: uploaded.args })).toMatchObject({ completed: 1, failed: 0 });
  expect((await fixture(request, 'metrics')).requests).toHaveLength(0);
});

test('a missing continuation is recovered from its checkpoint and duplicate jobs do nothing', async ({ page, request }) => {
  await openAdmin(page);
  await ajax(page, startAction);
  const worker = await fixture(request, 'run-one');
  await fixture(request, 'clear');
  const interrupted = await fixture(request, 'interrupt');
  expect((await fixture(request, 'metrics')).staged).toBeGreaterThan(0);
  expect(interrupted.after).toEqual(interrupted.before);
  expect(interrupted.next_attempt).toBeGreaterThan(Date.now() / 1000);
  expect(interrupted.recovery_scheduled).toBe(true);
  expect((await fixture(request, 'metrics')).pending).toBe(1);
  await fixture(request, 'replay', { args: worker.args });
  expect((await fixture(request, 'metrics')).requests).toHaveLength(0);
  const cancelled = await ajax(page, 'progress_agentic_rag_manual_sync_cancel');
  expect(cancelled.body.data.status).toBe('cancelled');
  expect((await fixture(request, 'metrics')).pending).toBe(0);
  expect((await fixture(request, 'metrics')).staged).toBe(0);
});

test('rate-limit responses identify the cause and do not retry in a tight loop', async ({ request }) => {
  await fixture(request, 'seed', { mode: 'rate-limit' });
  const result = await fixture(request, 'lookup');
  const metrics = await fixture(request, 'metrics');
  expect(result.ok).toBe(false);
  expect.soft(result.error).toMatch(/rate.?limit|too many requests|429/i);
  expect(metrics.requests).toHaveLength(1);
  expect(metrics.mapped).toBe(9000);
});

test('status with 10,000 completed actions does not hydrate the entire queue', async ({ page, request }) => {
  await fixture(request, 'seed', { queue: true });
  await openAdmin(page);
  await fixture(request, 'clear');
  const response = await ajax(page, statusAction);
  const metrics = await fixture(request, 'metrics');
  expect(response.body.success).toBe(true);
  expect(response.body.data.completed).toBe(10000);
  expect(metrics.requests).toHaveLength(0);
  // Allow normal bookkeeping queries, but not one fetch per completed action.
  expect(metrics.ajax.queue_reads).toBeLessThan(100);
});
