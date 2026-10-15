# Apify Instagram Profile Scraper & Extractor (WordPress Plugin)

[![WordPress Tested](https://img.shields.io/badge/WordPress-5.6%20to%206.7-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%20or%20higher-8892bf.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%20or%20later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Apify Actor](https://img.shields.io/badge/Apify-instagram--profile--scraper-orange.svg)](https://apify.com/apify/instagram-profile-scraper)

A production-ready WordPress plugin for extracting and displaying Instagram profile metrics, biographies, high-resolution avatars, and recent posts using the **Apify API** (`apify~instagram-profile-scraper`).

Designed specifically to **maximize free-tier Apify accounts** (1,800 free requests/month per account) using **intelligent multi-API key rotation, live usage meters, automatic failover, and transient caching**.

> **Note on Security:** This plugin does **NOT** contain any hardcoded API keys. All credentials are stored securely in your WordPress database (`wp_options`) and managed exclusively through the WordPress Admin dashboard.

---

## 🌟 Key Highlights

- **Dynamic API Key Management**:
  - Add primary and backup Apify API tokens securely directly in WordPress Admin (`Instagram Extractor > API Keys & Rotation`). No hardcoded keys in source code.
- **Support for Multiple API Keys**:
  - Add 2nd, 3rd, or more Apify accounts to scale your monthly limit (e.g., 2 accounts = 3,600 free monthly requests).
- **Intelligent Rotation Strategies**:
  - **Round-Robin / Alternating (Default)**: Alternates calls evenly between Key 1 and Key 2 (`Key 1 → Key 2 → Key 1 → Key 2`), ensuring neither key prematurely exhausts its 1,800 monthly limit.
  - **Least Used**: Dynamically picks the key with the fewest requests in the current month.
  - **Sequential / Failover**: Uses Key 1 until 1,800 requests or quota limit, then seamlessly switches to Key 2.
- **Automatic Failover & Protection**:
  - If a key encounters `HTTP 429` (Rate Limit) or `HTTP 403` (Quota Exceeded), it immediately falls back to the next available token in the same request so visitors never see an error.
- **Live Monthly Usage Meters**:
  - Visual progress bar showing exact requests used this month (`42 / 1800`), remaining requests, and usage percentage.
  - Scoped by month (`YYYY-MM`), so limits automatically cycle each month without manual intervention.
- **Built-in Image Proxy & CDN Bypass**:
  - Overcomes Instagram's aggressive `403 Forbidden` hotlinking blocks and signature expirations via secure server-side image proxying.
- **Responsive Media Lightbox & Downloader**:
  - Interactive full-screen post viewer with post caption drawer, smooth rotation animations, circular navigation, and one-click direct media download.
- **Transient Memory Caching**:
  - Saves extracted profiles in WordPress transients (1 hour, 6 hours, 12 hours, 24 hours, or 7 days) to preserve API limits on repeat visits.
- **Live Admin Playground**:
  - Search any Instagram handle or URL directly from WordPress Admin.
  - View live formatted cards, inspect normalized JSON, copy JSON, or export to file.
- **Frontend Shortcodes & Widgets**:
  - `[instagram_profile username="cristiano" layout="card" theme="light"]`
  - `[instagram_profile_search]` interactive AJAX lookup for front-end visitors.
- **Developer Functions & REST API**:
  - `apify_insta_get_profile( $username, $args )`
  - `apify_insta_render_profile( $username, $args )`
  - `GET /wp-json/apify-insta/v1/profile/{username}`

---

## 📁 Repository Structure

```text
apify-instagram-extractor/
├── apify-instagram-extractor.php       # Main plugin entry & metadata
├── readme.txt                          # WordPress standard plugin readme
├── README.md                           # Documentation & installation guide
├── includes/
│   ├── class-apify-token-manager.php   # Multi-token rotation, 1800 limit meters & health tests
│   ├── class-apify-scraper.php         # Apify API client, normalization & audit logs
│   ├── class-admin-dashboard.php       # Admin UI, playground, settings & AJAX handlers
│   ├── class-shortcodes.php            # Frontend shortcodes & search widget
│   ├── class-rest-controller.php       # WordPress REST API endpoint (/wp-json/apify-insta/v1/profile)
│   ├── class-image-proxy.php           # Instagram CDN 403 hotlink bypass & image caching
│   └── class-cache-helper.php          # WordPress transient caching & bulk flush
├── assets/
│   ├── css/
│   │   ├── admin.css                   # Admin dashboard styles & meters
│   │   └── frontend.css                # Profile cards, lightbox, post grid & widget styles
│   └── js/
│       ├── admin.js                    # Admin playground, AJAX modals & token management
│       └── frontend.js                 # Frontend interactive live search & lightbox controller
└── templates/
    ├── profile-card.php                # Standard rich profile card
    ├── profile-compact.php             # Compact badge for sidebars
    ├── profile-banner.php              # Full-width banner layout
    └── search-widget.php               # Frontend live search form
```

---

## 🚀 Installation & Setup Guide

### Step 1: Install the Plugin in WordPress

#### Method A: Upload ZIP via Dashboard (Recommended)
1. Download the latest `apify-instagram-extractor.zip` release from this repository.
2. In your WordPress Admin, go to **Plugins > Add New Plugin > Upload Plugin**.
3. Select `apify-instagram-extractor.zip` and click **Install Now**.
4. Click **Activate Plugin**.

#### Method B: Manual Git / FTP Installation
Clone this repository directly into your WordPress `wp-content/plugins/` directory:
```bash
cd wp-content/plugins/
git clone https://github.com/mhdSameerKhan/apify-instagram-scrapper.git apify-instagram-extractor
```
Then activate the plugin from **Plugins > Installed Plugins** in your WordPress Admin.

---

### Step 2: Get Your Free Apify API Token

1. Sign up for a free account at [Apify.com](https://apify.com/).
2. Navigate to [Apify Console > Settings > Integrations](https://console.apify.com/account/integrations).
3. Under **API Tokens**, copy your Personal API Token (starts with `apify_api_...`).
4. *(Optional for doubled quota)* Create a second free Apify account with another email to obtain a second token.

---

### Step 3: Add Tokens & Configure Rotation in WordPress

1. In your WordPress sidebar, go to **Instagram Extractor > API Keys & Rotation**.
2. Click **Add Another API Token**.
3. Fill in:
   - **Apify API Token**: Paste your token (`apify_api_...`)
   - **Account Label**: e.g., `Main Apify Account`
   - **Monthly Request Limit**: `1800` (free plan default)
4. Click **Save & Verify Token**. The plugin will make a quick ping to Apify to confirm connectivity.
5. Repeat for any additional tokens.
6. Under **Rotation Strategy**, select:
   - **Alternating / Round-Robin (Recommended)**: Perfectly balances usage across accounts (`1 → 2 → 1 → 2`).

---

## 💻 Shortcode Guide

### 1. Interactive Profile Search Widget
Place this anywhere on a page, post, or Elementor block to let visitors look up any Instagram profile in real time:

```text
[instagram_profile]
```

### 2. Pre-load a Specific Instagram Account
Display a specific profile card automatically on page load:

```text
[instagram_profile username="cristiano" posts_count="6" layout="card" theme="light"]
```

### 3. Compact Badge (Perfect for Sidebars / Footers)
```text
[instagram_profile username="nasa" layout="compact" show_posts="no"]
```

### 4. Full-Width Profile Banner
```text
[instagram_profile username="natgeo" layout="banner" posts_count="8"]
```

### Available Shortcode Attributes:

| Attribute | Options | Default | Description |
| :--- | :--- | :--- | :--- |
| `username` | String | *(None)* | Pre-load a specific Instagram handle (e.g. `cristiano`, `natgeo`) |
| `layout` | `card`, `compact`, `banner` | `card` | Layout style for displaying the profile |
| `theme` | `light`, `dark`, `gradient` | `light` | Visual color theme |
| `show_posts` | `yes`, `no` | `yes` | Display recent posts grid |
| `posts_count` | Number (`1` to `24`) | `6` | Number of recent posts to display |
| `show_search` | `yes`, `no` | `yes` | Show the live search bar at the top |

---

## 🛠️ Developer API

### PHP Functions

#### Retrieve Profile Data:
```php
$profile = apify_insta_get_profile( 'cristiano', array(
    'posts_count'   => 6,
    'force_refresh' => false,
) );

if ( ! is_wp_error( $profile ) ) {
    echo esc_html( $profile['fullName'] );           // e.g. "Cristiano Ronaldo"
    echo esc_html( $profile['followersFormatted'] ); // e.g. "640M"
    echo wp_kses_post( $profile['biographyHtml'] );  // Clickable bio hashtags & mentions
}
```

#### Render Profile Template:
```php
apify_insta_render_profile( 'cristiano', array(
    'layout'      => 'card',
    'theme'       => 'light',
    'posts_count' => 6,
) );
```

### REST API Endpoint

Fetch raw profile and post JSON for headless WordPress or mobile applications:

```http
GET /wp-json/apify-insta/v1/profile/cristiano?posts_count=6
```

---

## 🔒 Security & Privacy

- **No Hardcoded API Keys**: Tokens are strictly managed through WordPress settings and stored encrypted in the database.
- **Nonce & Capability Checks**: All AJAX and admin actions are protected with `check_ajax_referer` and `current_user_can('manage_options')`.
- **Sanitized Outputs**: All user inputs and API outputs are sanitized with `sanitize_text_field`, `esc_url`, `esc_attr`, and `wp_kses_post`.
- **CORS & Hotlink Protection**: Proxied Instagram images validate mime types and origins before streaming.

---

## 📄 License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
