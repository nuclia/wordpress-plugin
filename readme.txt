=== Progress Agentic RAG connector ===
Contributors: progresssoftware
Tags: search, ai, knowledge-base, rag, indexing
Requires at least: 6.8
Tested up to: 7.1
Stable tag: 1.0.3
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Index WordPress content into Progress Agentic RAG and power knowledge-base search.

== Description ==

Progress Agentic RAG connector connects WordPress content to the Progress Agentic RAG service so site owners can build searchable knowledge bases from selected public content.

The plugin keeps service credentials server-side, indexes selected content in the background, and provides WordPress-native administration and search integration.

When the service connection is configured and validated, the plugin embeds the Progress Agentic RAG search widget on the WordPress front page. Widget requests are proxied through WordPress so the Service Access token stays on the server.

== External Service ==

This plugin connects to the Progress Agentic RAG service. When indexing is enabled, selected public WordPress content and configured taxonomy labels may be sent to Progress Agentic RAG. When the front-page search widget is rendered, visitor search queries and widget API requests are sent to Progress Agentic RAG through the WordPress proxy. The plugin stores service configuration in WordPress options and must keep service access tokens on the server.

Service details:

* Service provider: Progress Software
* Service website: https://www.progress.com/
* Widget script: https://cdn.rag.progress.cloud/nuclia-widget.umd.js
* Terms and privacy: https://www.progress.com/legal

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/progress-agentic-rag-connector` directory, or install the plugin through WordPress.
2. Activate the plugin through the Plugins screen.
3. Configure Progress Agentic RAG from the Progress Agentic RAG admin menu.

== Frequently Asked Questions ==

= Does the plugin expose the service token to visitors? =

No. Service credentials stay server-side, and public widget requests use the server-side proxy.

== Developer Hooks ==

The plugin's front-end proxy exposes two filters for site owners/developers who need
non-default behavior. Both can be added in a small must-use plugin or a theme's
`functions.php`.

`progress_agentic_rag_proxy_max_body_bytes` (int)
Maximum size, in bytes, of a request body the proxy will forward to Progress Agentic
RAG. Defaults to 5242880 (5 MB), which covers normal chat/search requests and a
single moderate image upload. Raise it if you enable larger image uploads in the
widget.

    add_filter( 'progress_agentic_rag_proxy_max_body_bytes', function () {
        return 10 * MB_IN_BYTES;
    } );

`progress_agentic_rag_proxy_allowed_origins` (string[])
Origins allowed to receive a reflected `Access-Control-Allow-Origin` response from
the proxy. Defaults to the site's own origin only. The requesting `Origin` header
value is passed as the second argument.

    add_filter( 'progress_agentic_rag_proxy_allowed_origins', function ( $origins, $requesting_origin ) {
        $origins[] = 'https://staging.example.com';
        return $origins;
    }, 10, 2 );

== Changelog ==

= 1.0.3 =

* Security: hardened the front-end proxy — allow-listed and size-capped request paths,
  query strings, and bodies; restricted CORS to the site's own origin; forced unsafe
  upstream Content-Types (HTML/SVG/XML/JS) to download instead of render inline.
* Dev: added CI Plugin Check enforcement; fixed readme/header metadata flagged by it.

= 1.0.2 =

* Fix: Admin pages no longer perform blocking upstream sync/labelset calls
  during render, which caused 504 timeouts on slow or large sites. The
  taxonomy labeling tab now loads instantly from cache and refreshes its
  labelset dropdown in the background instead of fetching it synchronously.

= 1.0.1 =

* Fix: Advanced Custom Fields (ACF) data (e.g. Branch page locations, opening hours, contact
  details) is now indexed alongside post content, so it can be discovered and answered by
  Agentic RAG.

= 1.0.0 =

* Initial release.
