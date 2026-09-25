# Analytics Collector Contract (v1)

Version 1.2.1 supports optional remote product analytics. Remote analytics are disabled by default and require explicit WordPress administrator opt-in plus a configured HTTPS endpoint.

## Anonymous payload

```json
{
  "schema_version": 1,
  "installation_id": "uuid",
  "plugin": "nepali-daily-rashifal",
  "plugin_version": "1.2.1",
  "wordpress_version": "6.x",
  "php_version": "8.x",
  "timezone": "Asia/Kathmandu",
  "auto_sync_enabled": true,
  "publish_status": "publish",
  "rashifal_posts_tracked": 30,
  "rashifal_views_total": 12500,
  "rashifal_views_today": 420,
  "site_total_published_posts": 3000,
  "sent_at": "2026-09-25T04:30:00Z"
}
```

The plugin does not send visitor IP addresses, visitor accounts, passwords, admin credentials, or Rashifal post content.

## Optional site registration

When the separate **Identify this site** setting is enabled, the payload may additionally include:

```json
{
  "registered_site": {
    "url": "https://example.com/",
    "name": "Example Publication",
    "contact_email": "owner@example.com"
  }
}
```

This is separate from anonymous analytics and should only be enabled with the site owner's consent.

## Collector recommendations

- Require HTTPS.
- Optionally authenticate with a Bearer token.
- Validate `schema_version`.
- Upsert current installation state by `installation_id`.
- Keep aggregate snapshots only where possible.
- Publish a clear privacy policy and retention period before enabling telemetry in public distribution.
