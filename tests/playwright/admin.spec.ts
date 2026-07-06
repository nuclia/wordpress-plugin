import { expect, test } from '@playwright/test';
import { assertNoTokenLeak, collectBrowserExposure, configureWidget, resetFixture } from './fixtures/helpers';

test('public pages do not expose the configured service token', async ({ page, request }) => {
  await resetFixture(request);
  await configureWidget(request);

  const exposure = collectBrowserExposure(page);
  await page.goto('/');

  await assertNoTokenLeak(page, exposure);
  await expect(page.locator('body')).not.toContainText(process.env.PROGRESS_AGENTIC_RAG_TEST_TOKEN || 'progress-agentic-rag-secret');
});
