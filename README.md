# Progress Agentic RAG for WordPress

Optimize your WordPress search with Progress Agentic RAG's AI-powered search API.

> **Disclaimer:** This plugin is provided as-is and is not officially supported software. It is intended for demonstration and educational purposes only. Use at your own risk. No warranty, support, or maintenance is guaranteed.

## Description

Improve search on your site with AI-powered capabilities.

### Features

- Push your content to Progress Agentic RAG for indexing
- Progress Agentic RAG searchbox widget
- Progress Agentic RAG searchbox shortcode
- Widget requests are proxied through the site REST API using the configured `nuclia_token`
- Shortcode requests are direct by default; enable proxying with `proxy="true"` in the shortcode

This plugin requires an Agentic RAG account. You can sign up for a free trial at [Progress Agentic RAG](https://rag.progress.cloud).

Only published posts (not private) are indexed. If a post's status changes, it will be automatically unindexed.

### Shortcode usage

Use:

`[agentic_rag_searchbox]`

- `zone` is optional. If omitted, the plugin uses the Zone configured in plugin settings.
- `kbid` is optional. If omitted, the plugin uses the Knowledge Box ID configured in plugin settings.
- `account`, `state`, and `kbslug` are automatically passed only when the Knowledge Box is detected as private.
- `features` is optional (default: `navigateToLink`).
- `proxy` is optional (`true` routes through the WordPress proxy, default is direct requests).
- `show_config` is optional (default: `false`). Set `show_config="true"` to display the search configuration dropdown.

Examples:

- `[agentic_rag_searchbox]`
- `[agentic_rag_searchbox features="navigateToLink,suggestions"]`
- `[agentic_rag_searchbox proxy="true"]`
- `[agentic_rag_searchbox show_config="true"]` (show search configuration selector)
- `[agentic_rag_searchbox zone="europe-1"]` (override saved Zone)
- `[agentic_rag_searchbox kbid="override-kbid"]` (override saved KB ID)
- `[agentic_rag_searchbox zone="europe-1" kbid="override-kbid"]` (override both)

### About Progress Agentic RAG

Progress Agentic RAG is an easy-to-use, low-code API enabling developers to build AI-powered search engines for any data and any data source in minutes—without worrying about scalability, data indexing, or the complexity of implementing search systems.

### Links

- [Progress Agentic RAG](https://rag.progress.cloud)
- [Development](https://github.com/nuclia/wordpress-plugin)

## Installation

From your WordPress dashboard:

1. Go to **Plugins > Add New > Upload Plugin**
2. Click **Choose File** and select the plugin zip file
3. Click **Install Now**
4. **Activate** Progress Agentic RAG from your Plugins page
5. Click on the new menu item **Progress Agentic RAG** and enter your Zone, Knowledge Box ID, Account ID, and API key
6. Select the post types you want to index and use the buttons to start indexing

### Minimum Requirements

- WordPress 6.8+
- PHP 8.2+ (PHP 8.3 recommended)
- MySQL 5.0+ (MySQL 5.6+ recommended)
- cURL PHP extension
- mbstring PHP extension
- OpenSSL 1.0.1+
- wp_cron enabled

## Frequently Asked Questions

### Can I put more than one Progress Agentic RAG searchbox on the same page?

Yes, but only the first one in the DOM will work.

## Screenshots

1. Plugin settings page
2. Widget settings
3. Searchbox in content with shortcode

## Changelog

### 1.0.0

- Initial release

## License

GNU General Public License v2.0 / MIT License

## Contributors

- Kalyx
- Radek Friedl (Progress Software)
