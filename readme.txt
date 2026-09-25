=== Nepali Daily Rashifal ===
Contributors: janakkhadka
Tags: rashifal, horoscope, nepali, vedic astrology, automation
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.1.0
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

= External service =

This plugin needs a Rashifal JSON API to obtain daily content and a featured-image URL.

The default endpoint is:

https://fbautopost1.vercel.app/api/public/rashifal/latest

The plugin contacts the configured API only when an administrator uses Test API or Sync Today Now, or when automatic sync has been explicitly enabled. If an API token is configured, it is sent as an Authorization Bearer token. The plugin itself does not send analytics or tracking events.

The featured image specified by the API is downloaded to the site's WordPress Media Library when a Rashifal post is created or updated.

Service provider: MuniAstro
Service website: https://muniastro.com/

Before WordPress.org submission, publish the service Terms of Use and Privacy Policy and add their public URLs here.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/, or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate Nepali Daily Rashifal.
3. Open Daily Rashifal in the WordPress dashboard.
4. Confirm the API URL and click Test API.
5. Choose the Rashifal category, optional homepage/latest category, author, status, timezone and publishing time.
6. Enable automatic publishing only after the test succeeds.
7. Click Sync Today Now to verify the first post.

If upgrading from MuniAstro Daily Rashifal Publisher 1.0.x, deactivate the old plugin before activating this one.

== Frequently Asked Questions ==

= Does the plugin generate astrology predictions itself? =

No. It publishes an approved Rashifal payload supplied by the configured API.

= Does it require MuniAstro? =

No. The API URL is configurable. A compatible endpoint must return the required approved daily Rashifal JSON structure.

= Will it publish automatically immediately after activation? =

No. New installations default to automatic sync disabled and post status Draft.

= Can I make the post appear in my homepage/latest-news feed? =

Yes. Select a second category under Homepage / latest category, or enable auto-detection for common latest-news category names.

== Privacy ==

The plugin stores its settings and recent sync-log entries in the WordPress database. It does not implement analytics or user tracking. When the configured Rashifal service is contacted, normal server request metadata is transmitted to that service.

== Changelog ==

= 1.2.5 =
* Changed the Plugin URI to the public GitHub repository so it is distinct from the Author URI, as required by WordPress.org submission validation.
* No functional changes.

= 1.2.3 =
* Removed the unnecessary manual translation loader and Domain Path header for WordPress.org-hosted translations.
* Fixed the deprecated add_option() argument usage while keeping the anonymous installation UUID non-autoloaded.
* Added nonce verification for admin notice query parameters.
* Replaced direct unlink() usage with wp_delete_file().
* No changes to publishing, consent defaults, or Rashifal content behavior.

= 1.2.1 =
* Updated WordPress.org metadata for WordPress 7.1.
* Added direct MuniAstro Terms of Use and Privacy Policy links for the external service disclosure.
* Made public source credit opt-in by default.
* Removed the site URL from the default Rashifal API User-Agent.
* Added suggested Privacy Policy text in WordPress privacy settings.
* Kept remote analytics and site identification disabled by default.

= 1.1.0 =
* Public release as Nepali Daily Rashifal.
* Added configurable API, title, categories, author, post status, timezone and schedule.
* Added Test API and Sync Today Now controls.
* Added optional homepage/latest category and common-category auto-detection.
* Added migration support from MuniAstro Daily Rashifal Publisher 1.0.x.
* Added duplicate protection and featured-image sideloading.
* Added external-service disclosure and GPL licensing.

== Upgrade Notice ==

= 1.2.3 =
Plugin Check hardening for WordPress.org submission.

= 1.2.1 =
Submission-hardening update: source credit is now opt-in by default, external-service legal links are documented, and no site URL is placed in the default API User-Agent.

= 1.1.0 =
Deactivate the old MuniAstro Daily Rashifal Publisher before activating this renamed plugin.
