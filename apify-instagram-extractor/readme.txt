=== Apify Instagram Extractor ===
Contributors: mhd_sameerkhan
Tags: instagram, apify, instagram scraper, instagram profile, instagram feed
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extract Instagram profile metrics, bios, avatars, and recent posts using the Apify API with intelligent multi-API key rotation to prevent hitting monthly limits.

== Description ==

**Apify Instagram Extractor** is a high-performance WordPress plugin designed to scrape and display Instagram profiles and recent posts using the official Apify Instagram Scraper actor (`apify~instagram-profile-scraper`).

### 🚀 Key Features

* **Multi-API Key Rotation & Alternating Strategy**: Free Apify accounts are limited to 1,800 free requests per month. This plugin allows you to add multiple Apify API tokens and rotate them alternately (Round-Robin) or by least usage, doubling or tripling your monthly capacity.
* **1,800 Request Limit Meter**: Live visual progress bars show monthly request usage per key. Counters automatically track monthly cycles without manual resets.
* **Failover Protection**: If one API key gets rate limited (HTTP 429) or reaches its monthly quota (HTTP 403), the scraper automatically falls back to your next available token seamlessly.
* **Transient Memory Caching**: Scraped profiles are cached in WordPress transients (configurable: 1h, 6h, 12h, 24h, 7d). Repeat page views consume 0 Apify requests.
* **Live Admin Playground**: Test and extract any Instagram handle or URL with one click. View formatted cards and raw JSON data.
* **Responsive Frontend Shortcodes**:
  * `[instagram_profile username="cristiano"]`: Embed elegant cards with verified badges, follower counts, bios with links, and recent post grids with hover metrics.
  * `[instagram_profile_search]`: Interactive search box allowing site visitors to look up Instagram accounts in real-time.
* **Developer Friendly**: Global PHP helper functions and WordPress REST API endpoint (`/wp-json/apify-insta/v1/profile/{username}`).

== Installation ==

1. Upload the `apify-instagram-extractor` folder to your `/wp-content/plugins/` directory (or upload `apify-instagram-extractor.zip` via **Plugins > Add New > Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Instagram Extractor** in your WordPress sidebar to manage API tokens, test extractions, or customize settings.

== Frequently Asked Questions ==

= How do I add my second Apify API key? =
Navigate to **Instagram Extractor > API Keys & Rotation**, click **Add Another API Token**, enter your token and label, and click **Save & Verify Token**. The plugin will automatically alternate between your keys.

= What rotation mode should I use? =
The default **Alternating / Round-Robin** strategy alternates requests sequentially across all active tokens (Token 1 -> Token 2 -> Token 1 -> Token 2), ensuring balanced usage.

== Changelog ==

= 1.0.0 =
* Initial release with multi-key rotation, 1,800 limit meters, live extractor, shortcodes, and REST API.
