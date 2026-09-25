# Nepali Daily Rashifal

A WordPress plugin for publishing approved daily Nepali Vedic Rashifal posts from a configurable JSON API.

## Current release

**1.1.0**

## Features

- Publishes all 12 Nepali Rashi sections as a normal WordPress post
- Configurable title, categories, author, post status, timezone and schedule
- Optional homepage/latest-news category
- Featured-image import to the WordPress Media Library
- Duplicate protection by Rashifal date
- Manual **Test API** and **Sync Today Now**
- WP-Cron daily publishing and retry
- Migration support from **MuniAstro Daily Rashifal Publisher 1.0.x**
- Configurable external Rashifal API

## Installation

1. Download the release ZIP.
2. In WordPress go to **Plugins → Add New → Upload Plugin**.
3. Upload and activate **Nepali Daily Rashifal**.
4. Open **Daily Rashifal** in the WordPress dashboard.
5. Run **Test API**.
6. Configure categories, author, publish status, timezone and schedule.
7. Enable automatic sync when ready.

## Upgrade from MuniAstro Daily Rashifal Publisher

Deactivate the old plugin first. Version 1.1.0 imports compatible settings and recognizes the legacy date metadata to avoid duplicate daily posts.

## External service

The default source is the MuniAstro public Rashifal endpoint. Administrators may configure another compatible endpoint. See `readme.txt` for the external-service disclosure.

## License

GPLv2 or later.
