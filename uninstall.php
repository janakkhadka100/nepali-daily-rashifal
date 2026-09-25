<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
wp_clear_scheduled_hook('nepali_daily_rashifal_sync');
wp_clear_scheduled_hook('nepali_daily_rashifal_retry');
wp_clear_scheduled_hook('nepali_daily_rashifal_analytics');
delete_option('nepali_daily_rashifal_settings');
delete_option('nepali_daily_rashifal_log');
delete_option('nepali_daily_rashifal_install_id');
delete_option('nepali_daily_rashifal_analytics_status');
