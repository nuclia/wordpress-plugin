import { expect, test } from '@playwright/test';
import { assertNoTokenLeak, collectBrowserExposure, configureWidget, gotoWidgetPage, loginAsAdmin, readFixtureSettings, resetFixture, TEST_TOKEN, waitForWidgetReady } from './fixtures/helpers';

test.afterEach(async ({ request }) => {
  await resetFixture(request);
});

test('e2e fixture restores existing plugin settings after configuration changes', async ({ request }, testInfo) => {
  test.skip(testInfo.project.name.includes('mobile'), 'Settings preservation only needs one browser project.');

  await resetFixture(request);
  let before: Awaited<ReturnType<typeof readFixtureSettings>>;
  try {
    before = await readFixtureSettings(request);
  } catch (error) {
    test.skip(String(error).includes(' 404:'), 'The running E2E fixture does not expose settings snapshots.');
    throw error;
  }

  await configureWidget(request, { connected: false });
  const during = await readFixtureSettings(request);
  expect(during.zone).toBe('');
  expect(during.kbid).toBe('');
  expect(during.token_saved).toBe(false);
  expect(during.backup_exists).toBe(true);

  await resetFixture(request);
  const after = await readFixtureSettings(request);
  expect(after).toEqual(before);
});

test('public pages do not expose the configured service token', async ({ page, request }) => {
 await resetFixture(request);
 await configureWidget(request);

  const exposure = collectBrowserExposure(page);
  await page.goto('/');

  await assertNoTokenLeak(page, exposure);
  await expect(page.locator('body')).not.toContainText(process.env.PROGRESS_AGENTIC_RAG_TEST_TOKEN || 'progress-agentic-rag-secret');
});

test('admin tabs expose safe operations without leaking the service token', async ({ page, request }) => {
  await resetFixture(request);
  await configureWidget(request, { connected: false });
  await loginAsAdmin(page);

  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag');
  const pluginNav = page.getByRole('navigation', { name: 'Progress Agentic RAG sections' });
  await expect(pluginNav.getByRole('link', { name: 'Dashboard' })).toHaveCount(0);
  await expect(page.getByRole('img', { name: 'Progress' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Connection settings' })).toBeVisible();
  await expect(pluginNav.getByRole('link', { name: 'Connection settings' })).toHaveAttribute('aria-current', 'page');

  await configureWidget(request);
  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag');
  await expect(page.getByRole('heading', { name: 'Indexation' })).toBeVisible();

  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag&tab=synced-content');
  await expect(page.getByRole('heading', { name: 'Synchronized content' })).toBeVisible();

  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag&tab=sync-history');
  await expect(page.getByRole('heading', { name: 'Sync history' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Queue health' })).toBeVisible();

  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag&tab=search-widget');
  await expect(page.getByRole('heading', { name: 'Search widget' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Gutenberg block' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Elementor widget' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Widget options' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Response appearance' })).toBeVisible();
  await expect(page.getByText('[progress_agentic_rag_search features="answers,rephrase,filter,suggestions"]')).toBeVisible();
  await expect(page.getByText('answers, rephrase, filter, suggestions')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Automatic front page' })).toHaveCount(0);
  await expect(page.getByRole('heading', { name: 'Manual embed code' })).toHaveCount(0);

  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag&tab=diagnostics');
  await page.locator('[data-progress-agentic-rag-export-diagnostics]').click();
  await expect(page.locator('[data-progress-agentic-rag-diagnostics-output]')).toHaveValue(/"token_saved": true/);
  await expect(page.locator('[data-progress-agentic-rag-diagnostics-output]')).not.toHaveValue(new RegExp(TEST_TOKEN));

  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag&tab=connection');
  await page.locator('[data-progress-agentic-rag-test-connection]').click();
  await expect(page.locator('[data-progress-agentic-rag-connection-test-output]')).toContainText('Progress Agentic RAG validated the connection successfully.');
  await expect(page.locator('body')).not.toContainText(TEST_TOKEN);
});

test('response appearance settings persist validated admin choices', async ({ page, request }) => {
  await resetFixture(request);
  await configureWidget(request);
  await loginAsAdmin(page);
  await page.goto('/wp-admin/admin.php?page=progress-agentic-rag&tab=search-widget');

  await page.getByLabel('Accent color').fill('#123456');
  await page.getByLabel('Base font size').fill('18');
  await page.getByLabel('Font family').selectOption('inter');
  await page.getByLabel('Card shadow').selectOption('subtle');
  await page.getByRole('button', { name: 'Save response appearance' }).click();

  await expect(page).toHaveURL(/tab=search-widget/);
  await expect(page.getByLabel('Accent color')).toHaveValue('#123456');
  await expect(page.getByLabel('Base font size')).toHaveValue('18');
  await expect(page.getByLabel('Font family')).toHaveValue('inter');
  await expect(page.getByLabel('Card shadow')).toHaveValue('subtle');

  await gotoWidgetPage(page);
  await waitForWidgetReady(page);
  const responseStyle = await page.locator('nuclia-search-results').getAttribute('style');
  expect(responseStyle).toContain('--progress-agentic-rag-widget-accent-color:#123456');
  expect(responseStyle).toContain('--progress-agentic-rag-widget-font-family:Inter, Arial, sans-serif');
  expect(responseStyle).toContain('--progress-agentic-rag-widget-font-size:18px');
  expect(responseStyle).toContain('--progress-agentic-rag-widget-shadow:0 1px 2px');
});
