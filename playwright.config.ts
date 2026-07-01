import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/playwright',
  timeout: 30000,
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://wordpress',
    trace: 'on-first-retry'
  }
});
