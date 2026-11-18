<h1 align="center">WP Cache Inspector</h1>

<p align="center">
  Diagnose server, CDN, page, object and browser caching in WordPress — from one admin screen.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/version-1.0.0-2563EB?style=flat-square" alt="Version 1.0.0" />
  <img src="https://img.shields.io/badge/PHP-7.4%2B-1E293B?style=flat-square&logo=php&logoColor=white" alt="PHP 7.4+" />
  <img src="https://img.shields.io/badge/WordPress-plugin-1E293B?style=flat-square&logo=wordpress&logoColor=white" alt="WordPress plugin" />
  <img src="https://img.shields.io/badge/license-GPL--2.0-1E293B?style=flat-square" alt="License GPL-2.0" />
</p>

---

## Why

"Why am I still seeing the old version?" is one of the most common WordPress support questions. The answer is usually a cache — but which one? Server, CDN, a caching plugin, the object cache or the browser.

**WP Cache Inspector** answers that in seconds. It probes your own site, reads the response headers, detects the caching layers in play and gives you plain-language verdicts — plus the tools to bypass or purge caches while you work.

## Features

**Cache detection**
- Server and CDN caches from response headers: **LiteSpeed, Cloudflare, Varnish, Nginx FastCGI** and generic reverse-proxy caches
- Caching plugins: **LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache**
- External object cache (Redis / Memcached drop-ins)

**Page cache probe**
- Requests your homepage repeatedly and compares the responses — status, timing and key headers (`cache-control`, `age`, `x-cache`, `x-litespeed-cache`, `cf-cache-status`, `x-varnish`, `x-fastcgi-cache` and more)
- Checks whether a request with `Cache-Control: no-cache` is respected
- Calls a built-in REST endpoint that must **never** be cached, and warns you if your server or CDN caches it anyway

**Clear verdicts** — e.g. *"Page cache: likely ACTIVE"*, *"REST endpoint not cached (good)"* or *"WARNING: REST endpoint appears cached"*.

**Developer no-cache mode**
- One click sets a cookie for **15 minutes** in *your* browser only
- While it's on, front-end responses are sent with `Cache-Control: no-store`, so you see your changes immediately — visitors are unaffected

**Purge known caches** with one button:
LiteSpeed Cache · WP Rocket · W3 Total Cache · WP Super Cache · SiteGround Optimizer · the WordPress object cache (`wp_cache_flush()`)

**Environment overview** — server software, PHP version, OPcache status and memory, APCu, external object cache.

## Requirements

- PHP **7.4** or higher
- An administrator account (`manage_options`)
- Loopback HTTP requests allowed — the probes request your own site

## Installation

**From a ZIP**
1. Download this repository as a ZIP (**Code → Download ZIP**).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the ZIP and click **Install Now**.
3. Activate **WP Cache Inspector**.

**With Git**
```bash
cd wp-content/plugins
git clone https://github.com/ghouliaras/wp-cache-inspector.git
```
Then activate it from **Plugins**.

## Usage

1. Go to **Tools → Cache Inspector**.
2. The page runs the probes automatically and shows:
   - **Environment** — server, PHP, OPcache, APCu, object cache
   - **Detected caching plugins / layers**
   - **Page cache probe** — every request with its status, timing and key headers
   - **Verdicts** — a short summary of what's going on
3. Use **Turn on (15 min)** to enable developer no-cache mode in your browser, or **Purge known caches** to clear every supported cache at once.

> **Tip:** if the REST endpoint `/wp-json/wpci/v1/ping` ever shows a cache HIT or a non-zero `Age`, your server or CDN is caching dynamic content it shouldn't. Review your CDN / LiteSpeed rules.

## How it works

| Piece | What it does |
| --- | --- |
| `GET /wp-json/wpci/v1/ping` | Public REST endpoint that always sends `no-store` headers and returns a random value — used to detect incorrect caching of dynamic endpoints. |
| `wpci_dev` cookie | Set for 15 minutes by the no-cache toggle; while present, front-end requests from that browser get `no-store` headers. Cleared on plugin deactivation. |
| Purge button | Calls each supported plugin's own purge API only if that plugin is installed. |

## Changelog

**1.0.0** — Initial release.

## License

GPL-2.0 — see [LICENSE](LICENSE).

---

<p align="center">
  Built by <a href="https://github.com/ghouliaras"><b>George Chouliaras</b></a> · WordPress, WooCommerce &amp; Laravel developer ·
  <a href="https://www.flowcreations.gr/contact/">Available for projects</a>
</p>
