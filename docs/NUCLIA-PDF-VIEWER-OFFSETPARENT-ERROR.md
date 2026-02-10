# Nuclia PDF Loading Issue - Root Cause Analysis

## Issue

When using the WordPress proxy mode (`proxy="true"`), PDFs fail to load in the Nuclia widget's embedded PDF viewer when clicking citation links (e.g., "p. 17").

## Actual Root Cause (NOT timing/offsetParent)

The issue is **binary data corruption** caused by improper handling of gzip-compressed PDF responses, NOT a timing or layout issue.

### The Bug Chain

1. **Proxy requests with compression**: The proxy sends `Accept-Encoding: gzip` to Nuclia
2. **Nuclia returns gzipped PDF**: Response includes `Content-Encoding: gzip` header
3. **WordPress HTTP API doesn't decompress when streaming**: When `stream => true` is set, WordPress writes raw gzipped bytes to the temp file without decompression
4. **Proxy strips encoding header**: The `content-encoding` header is in the `skip_headers` array, so it gets removed
5. **Browser receives corrupted data**: The browser gets gzipped PDF bytes without knowing they're compressed, tries to render them as PDF → FAIL

### Evidence

From `wp-includes/Requests/src/Requests.php:733-778`:

```php
// Body is ONLY parsed when NOT streaming to file
if (!$options['filename']) {
    $body = substr($return->raw, $pos + 4);
    $return->body = $body;
}

// Decompression only happens on $return->body
if (isset($return->headers['content-encoding'])) {
    $return->body = self::decompress($return->body);
}
```

When `$options['filename']` is set (streaming):
- The body is NOT parsed into `$return->body`
- The decompression code runs on empty `$return->body`
- Raw gzipped data is written directly to file
- **NO decompression occurs**

### Code Reference

**File**: `includes/nuclia-proxy-rest.php`

**Before (broken):**
```php
$headers = array_filter([
    'Accept-Encoding' => 'gzip, deflate, br, zstd'  // Always requests compression
]);

$should_stream = str_contains($normalized_path, '/download/');  // Checked AFTER headers
```

**After (fixed):**
```php
$should_stream = str_contains($normalized_path, '/download/');  // Checked FIRST

$headers = array_filter([
    // Don't request compression for streaming downloads - WordPress doesn't decompress when streaming
    'Accept-Encoding' => $should_stream ? 'identity' : 'gzip, deflate, br, zstd'
]);
```

## Why Direct Mode Works

Without proxy (`apiKey` mode):
- Widget talks directly to Nuclia
- Browser handles the response and decompression natively
- No intermediate proxy to strip headers

## Testing

To verify the fix:

1. Use the manual test script:
```bash
cd /Users/hare/.claude/plugins/cache/playwright-skill/playwright-skill/4.1.0/skills/playwright-skill
node run.js /tmp/playwright-manual-pdf-test.js
```

2. In the browser:
   - Run search: "how have apple's revenue changed over time?"
   - Click a citation link (e.g., "p. 10" or "p. 17")
   - Verify PDF loads correctly

3. Check network logs:
   - Look for `/download/` requests
   - Verify `Accept-Encoding: identity` (no gzip)
   - Verify response has NO `Content-Encoding: gzip` header

## Previously Documented (Incorrect) Theory

The previous documentation suggested this was an `offsetParent` timing issue. This was **incorrect**. The actual issue was gzip compression being applied to streamed downloads without proper decompression, resulting in binary corruption.

## References

- WordPress Requests library: `wp-includes/Requests/src/Requests.php`
- Streaming does NOT decompress: Line 733 `if (!$options['filename'])`
- Proxy header filtering: `includes/nuclia-proxy-rest.php` line 231
