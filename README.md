# Nepali Daily Rashifal

A WordPress plugin for publishing approved daily Nepali Vedic Rashifal posts from a configurable JSON API.

## Current release

**1.2.0**

## Features

- Publishes all 12 Nepali Rashi sections as a normal WordPress post
- Configurable title, categories, author, post status, timezone and schedule
- Optional homepage/latest-news category
- Featured-image import to the WordPress Media Library
- Duplicate protection by Rashifal date
- Manual **Test API** and **Sync Today Now**
- WP-Cron daily publishing and retry
- Migration support from **MuniAstro Daily Rashifal Publisher 1.0.x**
- Local aggregate Rashifal view counters
- Optional anonymous product analytics with explicit admin opt-in
- Separate voluntary site registration for site URL/name/contact email
- Configurable HTTPS analytics collector endpoint

## Analytics & privacy

Remote product analytics are **off by default**.

When an administrator opts in and configures an HTTPS collector, the plugin can send aggregate product metrics such as an anonymous installation UUID, plugin/WordPress/PHP versions, timezone, publishing health, Rashifal post count and aggregate view totals.

It does **not** include visitor IP addresses, visitor accounts, WordPress passwords, admin credentials or Rashifal post content.

A separate **Identify this site** setting can voluntarily add the site's URL, publication name and contact email. This option is also off by default and should only be enabled with the site owner's consent.

Local readership counting can be enabled independently and stays in the WordPress database.

## Installation

1. Download the release ZIP.
2. In WordPress go to **Plugins → Add New → Upload Plugin**.
3. Upload and activate **Nepali Daily Rashifal**.
4. Open **Daily Rashifal** in the WordPress dashboard.
5. Run **Test API**.
6. Configure categories, author, publish status, timezone and schedule.
7. Enable automatic sync when ready.
8. For product telemetry, configure an HTTPS collector and explicitly enable anonymous analytics.

## Upgrade from MuniAstro Daily Rashifal Publisher

Deactivate the old plugin first. The plugin imports compatible settings and recognizes legacy date metadata to avoid duplicate daily posts.

## External service

The default Rashifal source is the MuniAstro public Rashifal endpoint. Administrators may configure another compatible endpoint.

## License

GPLv2 or later.
