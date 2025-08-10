# WP Cache Inspector

A WordPress plugin to **diagnose and debug caching** issues across server, CDN, and plugin layers.  
Detects active caches, runs HIT/MISS tests, checks response headers, inspects OPcache/APCu, and provides tools to enable a **developer no-cache mode** or purge known caches.

---

## ✨ Features

- **Cache Detection**
  - Detect LiteSpeed, Cloudflare, Varnish, Nginx FastCGI, reverse-proxy caches
  - Detect popular caching plugins (LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache)
  - Detect Redis/Memcached object caches

- **Header Inspection**
  - View key response headers (`Cache-Control`, `Age`, `x-cache`, `x-litespeed-cache`, `cf-cache-status`, etc.)
  - See HIT/MISS results between consecutive requests
  - Detect if REST API endpoints are cached incorrectly

- **Developer No-Cache Mode**
  - One-click toggle to set a browser cookie for 15 minutes
  - Forces `Cache-Control: no-store` headers for all front-end requests in your browser

- **Purge Known Caches**
  - LiteSpeed Cache (plugin/server)
  - WP Rocket
  - W3 Total Cache
  - WP Super Cache
  - SiteGround Optimizer
  - Redis/Memcached via `wp_cache_flush()`

- **Environment Info**
  - PHP version
  - Server software
  - OPcache status & memory usage
  - APCu status
  - External object cache detection

---

## 📷 Screenshots

1. **Main dashboard** showing detected caches and page cache probe results.
2. **Developer no-cache toggle** and purge buttons.
3. **Detailed request table** with headers and timing.

---

## 🚀 Installation

1. Download or clone this repository into your `wp-content/plugins` directory:
   ```bash
   git clone https://github.com/ghouliaras/wp-cache-inspector.git wp-content/plugins/wp-cache-inspector
