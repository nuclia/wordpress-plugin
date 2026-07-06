import { expect, test } from '@playwright/test';
import {
  CDN_WIDGET_URL,
  TEST_KBID,
  TEST_TOKEN,
  TEST_ZONE,
  assertNoTokenLeak,
  collectBrowserExposure,
  configureWidget,
  directProgressApiRequests,
  gotoWidgetPage,
  proxyRequests,
  readUpstreamLogs,
  resetFixture,
  setUpstreamScenario,
  triggerWidgetSearch,
  waitForUpstreamLog,
  waitForWidgetReady
} from './fixtures/helpers';

test.beforeEach(async ({ request }) => {
  await resetFixture(request);
  await configureWidget(request);
  await setUpstreamScenario(request, 'success');
});

test('front page renders the live CDN widget with the expected proxy contract', async ({ page }) => {
  const exposure = collectBrowserExposure(page);
  const cdnResponse = await gotoWidgetPage(page);

  expect(cdnResponse.status()).toBe(200);
  await expect(page.locator(`script[src="${CDN_WIDGET_URL}"]`)).toHaveCount(1);

  const attributes = await waitForWidgetReady(page);
  expect(attributes.knowledgebox).toBe(TEST_KBID);
  expect(attributes.zone).toBe(TEST_ZONE);
  expect(attributes.backend).toContain(`/nuclia-proxy/${TEST_ZONE}`);
  expect(attributes.proxy).toBe('true');
  expect(attributes.features).toBe('answers,rephrase,filter,suggestions');
  expect(attributes.apikey).toBeUndefined();

  await assertNoTokenLeak(page, exposure);
  expect(exposure.pageErrors).toEqual([]);
});

test('widget and CDN are not exposed until the connection is complete and reachable', async ({ page, request }, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Connection matrix only needs one browser project.');

  const cases = [
    { connected: false, reachable: false, token: TEST_TOKEN },
    { connected: true, reachable: false, token: TEST_TOKEN },
    { connected: true, reachable: true, token: '' }
  ];

  for (const settings of cases) {
    await configureWidget(request, settings);
    await page.goto(`/?case=${cases.indexOf(settings)}`);

    await expect(page.locator('[data-progress-agentic-rag-search-widget]')).toHaveCount(0);
    await expect(page.locator(`script[src="${CDN_WIDGET_URL}"]`)).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText(TEST_TOKEN);
  }
});

test('search requests go through the WordPress proxy without browser-visible credentials', async ({ page, request }) => {
  const exposure = collectBrowserExposure(page);
  const cdnResponse = await gotoWidgetPage(page);

  expect(cdnResponse.status()).toBe(200);
  await waitForWidgetReady(page);
  const logCountBeforeSearch = (await readUpstreamLogs(request)).length;
  await triggerWidgetSearch(page, 'fixture search query');

  await expect
    .poll(async () => (await readUpstreamLogs(request)).length, { timeout: 15000 })
    .toBeGreaterThan(logCountBeforeSearch);

  const logsAfterSearch = (await readUpstreamLogs(request)).slice(logCountBeforeSearch);
  const upstreamRequest = logsAfterSearch.find((log) => log.hasServiceHeader) || (await waitForUpstreamLog(request, (log) => log.hasServiceHeader));

  expect(upstreamRequest.url).toContain(`${TEST_ZONE}.rag.progress.cloud`);
  expect(upstreamRequest.url).toContain(`/api/`);
  expect(upstreamRequest.url).toContain(TEST_KBID);
  expect(upstreamRequest.headers['X-NUCLIA-SERVICEACCOUNT']).toBe('Bearer [redacted]');
  expect(directProgressApiRequests(exposure)).toEqual([]);
  await expect.poll(() => proxyRequests(exposure).length, { timeout: 15000 }).toBeGreaterThan(0);

  await assertNoTokenLeak(page, exposure);
  expect(exposure.pageErrors).toEqual([]);
});

test('proxy rejects unsafe methods and encoded unsafe paths', async ({ request }, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Proxy edge matrix only needs one browser project.');

  const basePath = `/nuclia-proxy/${TEST_ZONE}/v1/kb/${TEST_KBID}/find`;
  const optionsResponse = await request.fetch(basePath, {
    method: 'OPTIONS',
    headers: {
      Origin: 'https://example.test',
      'Access-Control-Request-Method': 'POST'
    }
  });

  expect(optionsResponse.status()).toBe(200);
  expect(optionsResponse.headers()['access-control-allow-methods']).toContain('GET, POST, OPTIONS');

  const putResponse = await request.fetch(basePath, { method: 'PUT' });
  expect(putResponse.status()).toBe(405);
  await expectJsonCode(putResponse, 'progress_agentic_rag_proxy_method_not_allowed');

  const invalidZoneResponse = await request.get(`/nuclia-proxy/bad_zone/v1/kb/${TEST_KBID}/find`);
  expect(invalidZoneResponse.status()).toBe(400);
  await expectJsonCode(invalidZoneResponse, 'progress_agentic_rag_proxy_invalid_zone');

  const traversalResponse = await request.get(`/nuclia-proxy/${TEST_ZONE}/%2e%2e/secret`);
  expect(traversalResponse.status()).toBe(400);
  await expectJsonCode(traversalResponse, 'progress_agentic_rag_proxy_invalid_path');

  const crlfResponse = await request.get(`/nuclia-proxy/${TEST_ZONE}/v1/kb/${TEST_KBID}/%0a/find`);
  expect(crlfResponse.status()).toBe(400);
  await expectJsonCode(crlfResponse, 'progress_agentic_rag_proxy_invalid_path');
});

test('proxy cleans query values, redacts ephemeral tokens, and only forwards allowed headers', async ({ request }, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Header/query matrix only needs one browser project.');

  const response = await request.get(
    `/nuclia-proxy/${TEST_ZONE}/v1/kb/${TEST_KBID}/find?progress_agentic_rag_proxy=1&rest_route=/bad&foo=bar&eph-token=raw-secret`,
    {
      headers: {
        Authorization: 'Bearer browser-secret',
        'x-synchronous': 'true',
        'x-ndb-client': 'playwright'
      }
    }
  );

  expect(response.status()).toBe(200);
  expect(response.headers()['x-nuclia-upstream-url']).toContain('foo=bar');
  expect(response.headers()['x-nuclia-upstream-url']).toContain('eph-token=REDACTED');
  expect(response.headers()['x-nuclia-upstream-url']).not.toContain('raw-secret');

  const upstreamRequest = await waitForUpstreamLog(request, (log) => log.url.includes('foo=bar'));

  expect(upstreamRequest.hasServiceHeader).toBe(false);
  expect(upstreamRequest.url).toContain('foo=bar');
  expect(upstreamRequest.url).toContain('eph-token=REDACTED');
  expect(upstreamRequest.url).not.toContain('progress_agentic_rag_proxy');
  expect(upstreamRequest.url).not.toContain('rest_route');
  expect(upstreamRequest.headers.authorization).toBeUndefined();
  expect(upstreamRequest.headers.Authorization).toBeUndefined();
  expect(upstreamRequest.headers['x-synchronous']).toBe('true');
  expect(upstreamRequest.headers['x-ndb-client']).toBe('playwright');
});

test('proxy preserves safe upstream failure and empty-result behavior', async ({ request }, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Upstream scenario matrix only needs one browser project.');

  await setUpstreamScenario(request, 'error');
  const errorResponse = await request.post(`/nuclia-proxy/${TEST_ZONE}/v1/kb/${TEST_KBID}/find`, {
    data: {
      query: 'fixture'
    }
  });

  expect(errorResponse.status()).toBe(503);
  expect(errorResponse.headers()['x-nuclia-upstream-status']).toBe('503');
  expect(errorResponse.headers()['x-e2e-upstream']).toBe('ok');
  expect(errorResponse.headers()['content-encoding']).toBeUndefined();
  expect(await errorResponse.json()).toEqual({ detail: 'Fixture upstream failure' });

  await setUpstreamScenario(request, 'empty');
  const emptyResponse = await request.post(`/nuclia-proxy/${TEST_ZONE}/v1/kb/${TEST_KBID}/find`, {
    data: {
      query: 'fixture'
    }
  });
  const emptyBody = await emptyResponse.json();

  expect(emptyResponse.status()).toBe(200);
  expect(emptyResponse.headers()['x-nuclia-upstream-status']).toBe('200');
  expect(emptyBody.total).toBe(0);

  await setUpstreamScenario(request, 'slow', 120);
  const slowResponse = await request.post(`/nuclia-proxy/${TEST_ZONE}/v1/kb/${TEST_KBID}/find`, {
    data: {
      query: 'fixture'
    }
  });

  expect(slowResponse.status()).toBe(200);
  expect((await readUpstreamLogs(request)).length).toBeGreaterThanOrEqual(3);
});

async function expectJsonCode(response: { json: () => Promise<any> }, code: string): Promise<void> {
  const body = await response.json();

  expect(body.code).toBe(code);
}
