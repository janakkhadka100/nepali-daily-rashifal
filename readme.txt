=== Nepali Daily Rashifal ===
Contributors: janakkhadka
Tags: rashifal, horoscope, nepali, vedic astrology, automation
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.2.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically fetch and publish approved daily Nepali Vedic Rashifal with 12 zodiac readings and a featured image.

== Description ==

Nepali Daily Rashifal publishes an approved daily Vedic Rashifal as a standard WordPress post.

Features:

* 12 Nepali Rashi sections in each post.
* Configurable title, category, author, post status, timezone, publish time, and retry time.
* Optional second category for a site's latest-news or homepage feed.
* Optional auto-detection for common Nepali latest-news category names.
* Featured image sideloaded into the WordPress Media Library.
* Duplicate protection based on the Rashifal date.
* Manual API test and manual sync tools.
* Automatic daily sync using WP-Cron.
* Optional CTA link and source label controlled by the site administrator.
* Migration support for MuniAstro Daily Rashifal Publisher 1.0.x settings and existing daily posts.
* Optional local aggregate readership counters for Rashifal posts.
* Optional anonymous product analytics, disabled by default and requiring explicit administrator opt-in plus an HTTPS collector endpoint.
* Separate voluntary site-registration consent for sending site URL, site name, and contact email.

= External service =

This plugin needs a Rashifal JSON API to obtain the daily content and featured-image URL. The default API URL is the MuniAstro public Rashifal endpoint:

https://fbautopost1.vercel.app/api/public/rashifal/latest

The plugin contacts the configured API only when an administrator clicks Test API or Sync Today Now, or when automatic sync has been explicitly enabled in the plugin settings. Requests contain the site's normal HTTP request metadata and a plugin User-Agent. If an API token is configured, it is sent as an Authorization Bearer token. By default, the plugin does not send product analytics. Version 1.2.5 includes optional anonymous product analytics that are OFF by default. They are transmitted only after an administrator explicitly enables analytics and configures an HTTPS analytics endpoint. Anonymous payloads can contain an installation UUID, plugin/WordPress/PHP versions, timezone, auto-sync state, publish status, aggregate Rashifal post/view counts, and send time. Visitor IP addresses, visitor accounts, page content, WordPress passwords, and WordPress administrator credentials are not included.

A separate "Identify this site" option can voluntarily add the site URL, optional site/publication name, and optional contact email to analytics payloads. This setting is also OFF by default and should be enabled only with the site owner's consent.

The featured image specified by the API is downloaded to the site's WordPress Media Library when a Rashifal post is created or updated.

Service provider: MuniAstro
Service website: https://muniastro.com/

Service Terms of Use: https://www.muniastro.com/en/terms
Service Privacy Policy: https://www.muniastro.com/en/privacy

== Installation ==

1. Upload the `nepali-daily-rashifal` folder to `/wp-content/plugins/`, or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate **Nepali Daily Rashifal**.
3. Open **Daily Rashifal** in the WordPress dashboard.
4. Confirm the API URL and click **Test API**.
5. Choose the Rashifal category, author, post status, and publishing time.
6. Enable automatic publishing only after the test succeeds.
7. Click **Sync Today Now** to verify the first post.

If upgrading from **MuniAstro Daily Rashifal Publisher 1.0.x**, deactivate the old plugin before activating this one. Version 1.1.0 imports compatible settings and recognizes existing daily-post metadata to avoid duplicates.

== Frequently Asked Questions ==

= Does the plugin generate astrology predictions itself? =

No. It publishes an approved Rashifal payload supplied by the configured API.

= Does it require MuniAstro? =

No. The API URL is configurable. A compatible endpoint must return the required approved daily Rashifal JSON structure.

= Will it publish automatically immediately after activation? =

No. New installations default to automatic sync disabled and post status Draft. The administrator must configure and enable publishing.

= Can I make the post appear in my homepage/latest-news feed? =

Yes. Select a second category under Latest / homepage category, or enable optional auto-detection for common latest-news category names.

= What happens if the featured image download fails? =

The post is kept as a draft and the failure is recorded in the plugin log.

== Privacy ==

The plugin stores its settings and up to 100 recent sync-log entries in the WordPress database. If local readership analytics is enabled, it stores only aggregate view counters on Rashifal posts; it does not store visitor IP addresses, visitor accounts, or user-agent strings. Optional remote product analytics are OFF by default and require explicit administrator opt-in plus an HTTPS analytics endpoint. Optional site identification is a separate consent setting. When the configured Rashifal or analytics service is contacted, normal server request metadata is necessarily transmitted to that service. See the External service section for details.

== Changelog ==

= 1.2.5 =
* Changed the Plugin URI to the public GitHub repository so it is distinct from the Author URI, as required by WordPress.org submission validation.
* No functional changes.

= 1.2.4 =
* Replaced postmeta search queries with an internal date-to-post index for faster daily duplicate detection.
* Replaced aggregate readership postmeta scans with a compact local summary option updated during normal views.
* Added slug-based compatibility lookup for existing v1.x and legacy MuniAstro daily posts without slow meta queries.
* Preserved local per-post view counters and all analytics consent defaults.

= 1.2.3 =
* Removed the unnecessary manual translation loader and Domain Path header for WordPress.org-hosted translations.
* Fixed the deprecated add_option() argument usage while keeping the anonymous installation UUID non-autoloaded.
* Added nonce verification for admin notice query parameters.
* Replaced direct unlink() usage with wp_delete_file().
* No changes to publishing, consent defaults, or Rashifal content behavior.

= 1.2.2 =
* Added required translator comments for translatable strings with placeholders.
* Removed the GitHub-only README markdown file from the WordPress.org distribution ZIP.
* No functional changes to Rashifal publishing or analytics consent behavior.

= 1.2.1 =
* Updated WordPress.org metadata for WordPress 7.1.
* Added direct MuniAstro Terms of Use and Privacy Policy links for the external service disclosure.
* Made public source credit opt-in by default.
* Removed the site URL from the default Rashifal API User-Agent.
* Added suggested Privacy Policy text in WordPress privacy settings.
* Kept remote analytics and site identification disabled by default.

= 1.2.0 =
* Added local aggregate Rashifal readership counters.
* Added opt-in anonymous product telemetry with a configurable HTTPS collector endpoint.
* Added a separate voluntary site-registration consent option for site URL/name/contact email.
* Added daily telemetry scheduling, manual analytics test, and analytics delivery status.
* Analytics remain disabled by default for WordPress.org privacy compliance.

= 1.1.0 =
* Renamed the distributable plugin to Nepali Daily Rashifal.
* Added public-plugin defaults with automatic sync disabled on new installs.
* Added Test API and Sync Today Now controls.
* Added configurable title, timezone, retry time, categories, CTA, and source credit.
* Added optional latest/homepage category and common-category auto-detection.
* Added migration support from MuniAstro Daily Rashifal Publisher 1.0.x.
* Preserved duplicate protection and featured-image sideloading.
* Added GPL headers, external-service documentation, and WordPress.org-ready readme metadata.

== Upgrade Notice ==

= 1.2.5 =
Submission metadata fix: Plugin URI and Author URI are now distinct.

= 1.2.4 =
Performance cleanup for WordPress.org: removes Plugin Check slow postmeta-query warnings.

= 1.2.3 =
Plugin Check hardening for WordPress.org submission.

= 1.2.2 =
WordPress.org submission cleanup for internationalization and distribution packaging.

= 1.2.1 =
Submission-hardening update: source credit is now opt-in by default, external-service legal links are documented, and no site URL is placed in the default API User-Agent.

= 1.2.0 =
Adds privacy-conscious local readership analytics and optional remote product telemetry. Remote analytics remain disabled until an administrator opts in and configures an HTTPS endpoint.

= 1.1.0 =
Deactivate the old MuniAstro Daily Rashifal Publisher before activating this renamed plugin. Existing compatible settings are imported automatically.
