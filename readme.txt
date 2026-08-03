=== Progress Agentic RAG connector ===
Contributors: progresssoftware
Tags: search, ai, knowledge-base, rag, indexing
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Index WordPress content into Progress Agentic RAG and power knowledge-base search.

== Description ==

Progress Agentic RAG connects WordPress content to the Progress Agentic RAG service so site owners can build searchable knowledge bases from selected public content.

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

1. Upload the plugin files to the `/wp-content/plugins/progress-agentic-rag` directory, or install the plugin through WordPress.
2. Activate the plugin through the Plugins screen.
3. Configure Progress Agentic RAG from the Progress Agentic RAG admin menu.

== Frequently Asked Questions ==

= Does the plugin expose the service token to visitors? =

No. Service credentials stay server-side, and public widget requests use the server-side proxy.

== Changelog ==

= 1.0.0 =

* Initial release.
