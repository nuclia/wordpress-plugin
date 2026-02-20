# Progress Agentic RAG for WordPress

Optimize your WordPress search with Progress Agentic RAG's AI-powered search API.

> **Disclaimer:** This plugin is provided as-is and is not officially supported software. It is intended for demonstration and educational purposes only. Use at your own risk. No warranty, support, or maintenance is guaranteed.

## Description

Improve search on your site with AI-powered capabilities.

### Features

- Push your content to Progress Agentic RAG for indexing
- Configure your search widget directly in the Progress Agentic RAG dashboard
- Widget requests are proxied through your WordPress site using the configured credentials

This plugin requires an Agentic RAG account. You can sign up for a free trial at [Progress Agentic RAG](https://rag.progress.cloud).

Only published posts (not private) are indexed. If a post's status changes, it will be automatically unindexed.

### Configuring the Search Widget

After configuring your credentials in the plugin settings:

1. Click **Open Progress Agentic RAG Dashboard** on the settings page
2. Navigate to the Widgets section in your dashboard
3. Configure and customize your search widget
4. Copy the generated embed code to your WordPress site

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
7. Click **Open Progress Agentic RAG Dashboard** and navigate to the Widgets section to configure your search widget

### Minimum Requirements

- WordPress 6.8+
- PHP 8.1+ (PHP 8.3 recommended)
- MySQL 5.0+ (MySQL 5.6+ recommended)
- cURL PHP extension
- mbstring PHP extension
- OpenSSL 1.0.1+
- wp_cron enabled

## Screenshots

1. Plugin settings page with credentials and indexing options
2. Widgets dashboard link for search widget configuration

## Changelog

### 1.0.0

- Initial release

## License

GNU General Public License v2.0 / MIT License

## Contributors

- Kalyx
- Radek Friedl (Progress Software)
