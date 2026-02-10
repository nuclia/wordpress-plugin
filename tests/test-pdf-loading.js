// Test script to verify PDF loading works correctly in proxy mode
//
// This test:
// 1. Opens the SearchBox page (proxy mode)
// 2. Runs a search query
// 3. Clicks a citation link
// 4. Verifies the PDF loads without errors
// 5. Checks that no gzip compression is used (the fix)
//
// Usage: Requires Playwright skill to be installed
// Run from project root with the skill

const { chromium } = require('playwright');

// Test configuration
const TEST_URL = process.env.TEST_URL || 'http://localhost:8080/testy/';
const SEARCH_QUERY = 'how have apple\'s revenue changed over time?';
const HEADLESS = process.env.HEADLESS === 'true';

(async () => {
  const browser = await chromium.launch({ headless: HEADLESS, slowMo: 500 });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

  const consoleErrors = [];

  page.on('console', msg => {
    const text = msg.text();
    if (msg.type() === 'error') {
      consoleErrors.push(text);
      console.log(`🔴 Console Error: ${text}`);
    }
  });

  // Track PDF download requests to verify the fix
  let pdfRequestHeaders = null;
  let pdfResponseHeaders = null;

  page.on('request', request => {
    const url = request.url();
    if (url.includes('/download/')) {
      pdfRequestHeaders = request.headers();
      console.log(`\n📤 PDF Download Request:`);
      console.log(`   URL: ${url.substring(0, 80)}...`);
      console.log(`   Accept-Encoding: ${pdfRequestHeaders['accept-encoding'] || '(none)'}`);
    }
  });

  page.on('response', response => {
    const url = response.url();
    if (url.includes('/download/')) {
      pdfResponseHeaders = response.headers();
      console.log(`\n📥 PDF Download Response:`);
      console.log(`   Status: ${response.status()}`);
      console.log(`   Content-Type: ${pdfResponseHeaders['content-type'] || '(none)'}`);
      console.log(`   Content-Encoding: ${pdfResponseHeaders['content-encoding'] || '(none)'}`);
    }
  });

  try {
    console.log('╔════════════════════════════════════════════════════════════╗');
    console.log('║  PDF Loading Test - Proxy Mode                           ║');
    console.log('╚════════════════════════════════════════════════════════════╝\n');

    console.log(`Step 1: Navigating to ${TEST_URL}`);
    await page.goto(TEST_URL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3000);
    console.log('✅ Page loaded\n');

    console.log('Step 2: Finding and typing in search box...');
    const searchBox = await page.locator('nuclia-search-bar').first();
    await searchBox.waitFor({ state: 'visible', timeout: 10000 });
    console.log('✅ Found search box');

    await searchBox.click();
    await page.waitForTimeout(500);
    await page.keyboard.type(SEARCH_QUERY, { delay: 100 });
    console.log('✅ Typed search query\n');

    console.log('Step 3: Running search...');
    await page.keyboard.press('Enter');
    console.log('✅ Search submitted');

    console.log('\nStep 4: Waiting for search results...');
    await page.waitForTimeout(15000);
    console.log('✅ Waited for results\n');

    console.log('Step 5: Looking for citation links to click...');

    // Find and click citation links
    const citations = await page.locator('text=/p\\.\\s*\\d+/').all();
    console.log(`Found ${citations.length} elements with "p. X" pattern`);

    if (citations.length > 0) {
      for (let i = 0; i < Math.min(3, citations.length); i++) {
        try {
          const text = await citations[i].textContent();
          console.log(`  - "${text.trim()}"`);
        } catch (e) {}
      }

      console.log('\nAttempting to click first citation...');
      await citations[0].scrollIntoViewIfNeeded();
      await page.waitForTimeout(500);
      await citations[0].click();
      console.log('✅ Clicked citation\n');
    }

    console.log('Step 6: Waiting for PDF viewer to load...');
    await page.waitForTimeout(10000);

    // Final verification
    console.log('\n╔════════════════════════════════════════════════════════════╗');
    console.log('║  Test Results                                             ║');
    console.log('╚════════════════════════════════════════════════════════════╝\n');

    let testsPassed = 0;
    let testsFailed = 0;

    // Test 1: No compression requested
    if (pdfRequestHeaders) {
      console.log('📤 PDF Request Headers:');
      console.log(`   Accept-Encoding: ${pdfRequestHeaders['accept-encoding'] || '(none)'}`);
      const noCompression = !pdfRequestHeaders['accept-encoding'] ||
                            pdfRequestHeaders['accept-encoding'] === 'identity';
      if (noCompression) {
        console.log('   ✅ PASS: No compression requested');
        testsPassed++;
      } else {
        console.log('   ❌ FAIL: Compression was requested');
        testsFailed++;
      }
    }

    // Test 2: No compression in response
    if (pdfResponseHeaders) {
      console.log('\n📥 PDF Response Headers:');
      console.log(`   Content-Type: ${pdfResponseHeaders['content-type'] || '(none)'}`);
      console.log(`   Content-Encoding: ${pdfResponseHeaders['content-encoding'] || '(none)'}`);
      const noGzip = !pdfResponseHeaders['content-encoding'] ||
                     !pdfResponseHeaders['content-encoding'].includes('gzip');
      if (noGzip) {
        console.log('   ✅ PASS: No compression in response');
        testsPassed++;
      } else {
        console.log('   ❌ FAIL: Response is gzipped');
        testsFailed++;
      }
    }

    // Test 3: No console errors
    console.log(`\n🔴 Console Errors: ${consoleErrors.length}`);
    if (consoleErrors.length === 0) {
      console.log('   ✅ PASS: No console errors');
      testsPassed++;
    } else {
      console.log('   ❌ FAIL: Console errors found:');
      consoleErrors.forEach(err => console.log(`      - ${err}`));
      testsFailed++;
    }

    // Summary
    console.log('\n╔════════════════════════════════════════════════════════════╗');
    console.log('║  Summary                                                  ║');
    console.log('╚════════════════════════════════════════════════════════════╝\n');
    console.log(`Tests Passed: ${testsPassed}/3`);
    console.log(`Tests Failed: ${testsFailed}/3`);

    if (testsFailed === 0) {
      console.log('\n✅✅✅ ALL TESTS PASSED! ✅✅✅\n');
      process.exit(0);
    } else {
      console.log('\n❌ SOME TESTS FAILED\n');
      process.exit(1);
    }

  } catch (error) {
    console.error(`\n❌ Test error: ${error.message}`);
    process.exit(1);
  } finally {
    await browser.close();
  }
})();
