import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  timeout: 45000,
  expect: {
    timeout: 10000
  },
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://wordpress',
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
    video: 'retain-on-failure'
  },
  projects: [
    {
      name: 'chromium-desktop',
      use: {
        ...devices['Desktop Chrome']
      }
    },
    {
      name: 'chromium-mobile',
      use: {
        ...devices['Pixel 5']
      }
    }
  ]
});
