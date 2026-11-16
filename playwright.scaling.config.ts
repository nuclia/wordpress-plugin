import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/scaling',
  testMatch: '**/*.spec.ts',
  timeout: 120000,
  expect: { timeout: 10000 },
  retries: 0,
  workers: 1,
  reporter: [['list'], ['html', { outputFolder: 'build/scaling-report', open: 'never' }]],
  outputDir: 'build/scaling-results',
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://wordpress',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure'
  },
  projects: [{ name: 'scaling', use: { ...devices['Desktop Chrome'] } }]
});
