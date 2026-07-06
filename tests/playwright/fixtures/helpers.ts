import { expect, type APIRequestContext, type Page, type Request, type Response } from '@playwright/test';

export const CDN_WIDGET_URL = 'https://cdn.rag.progress.cloud/nuclia-widget.umd.js';
export const TEST_ZONE = process.env.PROGRESS_AGENTIC_RAG_TEST_ZONE || 'europe-1';
export const TEST_KBID = process.env.PROGRESS_AGENTIC_RAG_TEST_KBID || 'ci-kb';
export const TEST_TOKEN = process.env.PROGRESS_AGENTIC_RAG_TEST_TOKEN || 'progress-agentic-rag-secret';

const E2E_KEY = process.env.PROGRESS_AGENTIC_RAG_E2E_KEY || 'progress-agentic-rag-e2e';
const E2E_REST_BASE = '/wp-json/progress-agentic-rag-e2e/v1';
const WP_BASE_URL = process.env.WP_BASE_URL || 'http://wordpress';

export type UpstreamScenario = 'success' | 'empty' | 'error' | 'slow';

export type UpstreamLog = {
  method: string;
  url: string;
  path: string;
  query: string;
  headers: Record<string, string>;
  hasServiceHeader: boolean;
  bodyLength: number;
};

export type BrowserExposure = {
  requests: Array<{
    url: string;
    method: string;
    headers: Record<string, string>;
    postData: string;
  }>;
  responses: Array<{
    url: string;
    status: number;
    headers: Record<string, string>;
    text: string;
  }>;
  pageErrors: string[];
};

export async function resetFixture(request: APIRequestContext): Promise<void> {
  await e2eFetch(request, 'reset', { method: 'POST' });
}

export async function configureWidget(
  request: APIRequestContext,
  overrides: {
    connected?: boolean;
    reachable?: boolean;
    zone?: string;
    kbid?: string;
    token?: string;
  } = {}
): Promise<void> {
  await e2eFetch(request, 'configure', {
    method: 'POST',
    data: {
      connected: overrides.connected ?? true,
      reachable: overrides.reachable ?? true,
      zone: overrides.zone ?? TEST_ZONE,
      kbid: overrides.kbid ?? TEST_KBID,
      token: overrides.token ?? TEST_TOKEN
    }
  });
}

export async function setUpstreamScenario(
  request: APIRequestContext,
  scenario: UpstreamScenario,
  delayMs = 0
): Promise<void> {
  await e2eFetch(request, 'scenario', {
    method: 'POST',
    data: {
      scenario,
      delay_ms: delayMs
    }
  });
}

export async function readUpstreamLogs(request: APIRequestContext): Promise<UpstreamLog[]> {
  const payload = await e2eFetch(request, 'logs');

  return Array.isArray(payload.requests) ? payload.requests : [];
}

export async function waitForUpstreamLog(
  request: APIRequestContext,
  predicate: (log: UpstreamLog) => boolean
): Promise<UpstreamLog> {
  let matched: UpstreamLog | undefined;

  await expect
    .poll(
      async () => {
        const logs = await readUpstreamLogs(request);
        matched = logs.find(predicate);

        return matched ? 1 : 0;
      },
      { timeout: 15000 }
    )
    .toBe(1);

  return matched as UpstreamLog;
}

export function collectBrowserExposure(page: Page): BrowserExposure {
  const exposure: BrowserExposure = {
    requests: [],
    responses: [],
    pageErrors: []
  };

  page.on('request', (request: Request) => {
    exposure.requests.push({
      url: request.url(),
      method: request.method(),
      headers: request.headers(),
      postData: request.postData() || ''
    });
  });

  page.on('response', async (response: Response) => {
    const headers = response.headers();
    const contentType = headers['content-type'] || '';
    const shouldScan =
      isWordPressRequest(response.url()) ||
      response.url().includes('/nuclia-proxy/') ||
      contentType.includes('json') ||
      contentType.includes('text') ||
      contentType.includes('javascript');

    if (!shouldScan) {
      return;
    }

    try {
      const text = await response.text();
      exposure.responses.push({
        url: response.url(),
        status: response.status(),
        headers,
        text: text.slice(0, 200000)
      });
    } catch {
      exposure.responses.push({
        url: response.url(),
        status: response.status(),
        headers,
        text: ''
      });
    }
  });

  page.on('pageerror', (error: Error) => {
    exposure.pageErrors.push(error.message);
  });

  return exposure;
}

export async function gotoWidgetPage(page: Page): Promise<Response> {
  const cdnResponse = page.waitForResponse((response) => response.url() === CDN_WIDGET_URL, { timeout: 20000 });

  await page.goto('/');

  return cdnResponse;
}

export async function waitForWidgetReady(page: Page): Promise<Record<string, string | null>> {
  await expect(page.locator('[data-progress-agentic-rag-search-widget]')).toHaveCount(1);
  await expect(page.locator('nuclia-search-bar')).toHaveCount(1);
  await expect(page.locator('nuclia-search-results')).toHaveCount(1);
  await page.waitForFunction(
    () => Boolean(customElements.get('nuclia-search-bar') && customElements.get('nuclia-search-results')),
    { timeout: 20000 }
  );

  const readyState = await page.locator('nuclia-search-bar').evaluate(async (element) => {
    const widget = element as HTMLElement & { onReady?: () => Promise<unknown> };

    if (typeof widget.onReady !== 'function') {
      return 'missing-on-ready';
    }

    await Promise.race([
      widget.onReady(),
      new Promise((_, reject) => {
        window.setTimeout(() => reject(new Error('Widget onReady timed out.')), 20000);
      })
    ]);

    return 'ready';
  });

  expect(readyState).toBe('ready');

  return page.locator('nuclia-search-bar').evaluate((element) => {
    return Object.fromEntries(element.getAttributeNames().map((name) => [name, element.getAttribute(name)]));
  });
}

export async function triggerWidgetSearch(page: Page, query: string): Promise<void> {
  await page.locator('nuclia-search-bar').evaluate((element, value) => {
    const widget = element as HTMLElement & { search?: (query: string) => void };

    if (typeof widget.search === 'function') {
      widget.search(value);
      return;
    }

    const input = element.shadowRoot?.querySelector('input, textarea') as HTMLInputElement | HTMLTextAreaElement | null;
    if (!input) {
      throw new Error('The widget did not expose a search method or input control.');
    }

    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new KeyboardEvent('keypress', { key: 'Enter', bubbles: true }));
  }, query);
}

export async function assertNoTokenLeak(
  page: Page,
  exposure: BrowserExposure,
  token = TEST_TOKEN
): Promise<void> {
  const storage = await page.evaluate(() => {
    const readStorage = (storage: Storage) => {
      const values: Record<string, string | null> = {};

      for (let index = 0; index < storage.length; index += 1) {
        const key = storage.key(index);
        if (key) {
          values[key] = storage.getItem(key);
        }
      }

      return values;
    };

    return {
      localStorage: readStorage(window.localStorage),
      sessionStorage: readStorage(window.sessionStorage)
    };
  });

  const visibleSurfaces = JSON.stringify({
    html: await page.content(),
    storage,
    requests: exposure.requests,
    responses: exposure.responses
  });

  expect(visibleSurfaces).not.toContain(token);
  expect(visibleSurfaces).not.toContain(`Bearer ${token}`);
}

export function directProgressApiRequests(exposure: BrowserExposure): BrowserExposure['requests'] {
  return exposure.requests.filter((request) => {
    const url = new URL(request.url);

    return url.hostname.endsWith('.rag.progress.cloud') && url.hostname !== 'cdn.rag.progress.cloud';
  });
}

export function proxyRequests(exposure: BrowserExposure): BrowserExposure['requests'] {
  return exposure.requests.filter((request) => new URL(request.url).pathname.includes('/nuclia-proxy/'));
}

async function e2eFetch(
  request: APIRequestContext,
  path: string,
  options: {
    method?: string;
    data?: Record<string, unknown>;
  } = {}
): Promise<any> {
  const response = await request.fetch(`${E2E_REST_BASE}/${path}`, {
    method: options.method || 'GET',
    data: options.data,
    headers: {
      'X-Progress-Agentic-Rag-E2E-Key': E2E_KEY
    }
  });

  if (!response.ok()) {
    throw new Error(`E2E fixture ${path} failed with ${response.status()}: ${await response.text()}`);
  }

  return response.json();
}

function isWordPressRequest(url: string): boolean {
  return url.startsWith(WP_BASE_URL);
}
