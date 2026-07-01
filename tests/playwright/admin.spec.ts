import { expect, test } from '@playwright/test';

test('public pages do not expose the configured service token', async ({ page }) => {
  const token = process.env.PROGRESS_AGENTIC_RAG_TEST_TOKEN || 'progress-agentic-rag-secret';

  await page.goto('/');

  await expect(page.locator('body')).not.toContainText(token);
});
