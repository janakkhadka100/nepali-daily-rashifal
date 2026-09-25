<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('nepali_daily_rashifal_sync');
wp_clear_scheduled_hook('nepali_daily_rashifal_retry');
delete_option('nepali_daily_rashifal_settings');
delete_option('nepali_daily_rashifal_log');
