<?php
/**
 * Plugin Name: Nepali Daily Rashifal
 * Plugin URI: https://github.com/janakkhadka100/nepali-daily-rashifal
 * Description: Fetch and publish approved daily Nepali Vedic Rashifal with 12 zodiac readings, featured image, scheduling, categories, and duplicate protection.
 * Version: 1.2.5
 * Author: MuniAstro
 * Author URI: https://muniastro.com/
 * Text Domain: nepali-daily-rashifal
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Nepali_Daily_Rashifal {
    const VERSION = '1.2.5';
    const OPTION = 'nepali_daily_rashifal_settings';
    const LOG_OPTION = 'nepali_daily_rashifal_log';
    const CRON_HOOK = 'nepali_daily_rashifal_sync';
    const RETRY_HOOK = 'nepali_daily_rashifal_retry';
    const META_DATE = '_nepali_daily_rashifal_date_key';
    const META_HASH = '_nepali_daily_rashifal_content_hash';
    const META_IMAGE = '_nepali_daily_rashifal_image_version';
    const LEGACY_OPTION = 'muniastro_rashifal_settings';
    const LEGACY_META_DATE = '_muniastro_date_key';
    const ANALYTICS_CRON_HOOK = 'nepali_daily_rashifal_analytics';
    const INSTALL_ID_OPTION = 'nepali_daily_rashifal_install_id';
    const ANALYTICS_STATUS_OPTION = 'nepali_daily_rashifal_analytics_status';
    const META_VIEWS = '_nepali_daily_rashifal_views';
    const META_VIEW_DAYS = '_nepali_daily_rashifal_view_days';
    const POST_INDEX_OPTION = 'nepali_daily_rashifal_post_index';
    const VIEW_SUMMARY_OPTION = 'nepali_daily_rashifal_view_summary';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'add_privacy_policy_content'));
        add_action('admin_post_ndr_sync_now', array($this, 'manual_sync'));
        add_action('admin_post_ndr_test_api', array($this, 'manual_test_api'));
        add_action('admin_post_ndr_send_analytics', array($this, 'manual_send_analytics'));
        add_action(self::CRON_HOOK, array($this, 'scheduled_sync'));
        add_action(self::RETRY_HOOK, array($this, 'scheduled_retry'));
        add_action(self::ANALYTICS_CRON_HOOK, array($this, 'scheduled_analytics'));
        add_action('template_redirect', array($this, 'track_rashifal_view'));
        add_filter('cron_schedules', array($this, 'cron_schedules'));
        add_action('init', array($this, 'ensure_schedule'));
    }


    public static function defaults() {
        return array(
            'enabled' => 0,
            'api_url' => 'https://fbautopost1.vercel.app/api/public/rashifal/latest',
            'api_token' => '',
            'publish_status' => 'draft',
            'category_id' => 0,
            'latest_category_id' => 0,
            'auto_detect_latest_category' => 0,
            'author_id' => 1,
            'publish_hour' => 5,
            'publish_minute' => 20,
            'retry_hour' => 6,
            'retry_minute' => 50,
            'timezone' => 'Asia/Kathmandu',
            'post_title' => 'आजको वैदिक राशिफल',
            'slug_prefix' => 'vaidic-rashifal',
            'cta_url' => '',
            'cta_label' => 'ज्योतिषसँग च्याट गर्नुहोस्',
            'source_label' => 'MuniAstro',
            'show_source' => 0,
            'local_view_tracking' => 1,
            'analytics_enabled' => 0,
            'analytics_endpoint' => '',
            'analytics_token' => '',
            'registered_site' => 0,
            'registered_site_name' => '',
            'registered_contact_email' => '',
        );
    }

    public static function activate() {
        self::migrate_legacy_settings();
        if (!get_option(self::OPTION)) {
            add_option(self::OPTION, self::defaults());
        }
        if (!get_option(self::INSTALL_ID_OPTION)) {
            add_option(self::INSTALL_ID_OPTION, wp_generate_uuid4(), '', false);
        }
        if (!get_option(self::POST_INDEX_OPTION)) {
            add_option(self::POST_INDEX_OPTION, array(), '', false);
        }
        if (!get_option(self::VIEW_SUMMARY_OPTION)) {
            add_option(self::VIEW_SUMMARY_OPTION, array('total_views' => 0, 'today_views' => 0, 'date' => '', 'tracked_posts' => array()), '', false);
        }
        self::instance()->ensure_schedule(true);
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::RETRY_HOOK);
        wp_clear_scheduled_hook(self::ANALYTICS_CRON_HOOK);
    }

    private static function migrate_legacy_settings() {
        if (get_option(self::OPTION)) {
            return;
        }
        $legacy = get_option(self::LEGACY_OPTION, array());
        if (!is_array($legacy) || empty($legacy)) {
            return;
        }
        $new = wp_parse_args($legacy, self::defaults());
        $new['post_title'] = 'आजको वैदिक राशिफल';
        $new['slug_prefix'] = 'vaidic-rashifal';
        $new['timezone'] = 'Asia/Kathmandu';
        $new['show_source'] = 0;
        if (empty($legacy['latest_category_id'])) {
            $new['auto_detect_latest_category'] = 1;
        }
        update_option(self::OPTION, $new, false);
    }

    public function cron_schedules($schedules) {
        $schedules['ndr_daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display' => __('Nepali Daily Rashifal — Daily', 'nepali-daily-rashifal'),
        );
        return $schedules;
    }

    private function valid_timezone($timezone) {
        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Asia/Kathmandu';
    }

    private function next_timestamp($hour, $minute, $timezone) {
        $tz = new DateTimeZone($this->valid_timezone($timezone));
        $now = new DateTimeImmutable('now', $tz);
        $next = $now->setTime((int) $hour, (int) $minute, 0);
        if ($next <= $now) {
            $next = $next->modify('+1 day');
        }
        return $next->getTimestamp();
    }

    public function ensure_schedule($force = false) {
        $settings = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if ($force) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            wp_clear_scheduled_hook(self::RETRY_HOOK);
            wp_clear_scheduled_hook(self::ANALYTICS_CRON_HOOK);
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(
                $this->next_timestamp($settings['publish_hour'], $settings['publish_minute'], $settings['timezone']),
                'ndr_daily',
                self::CRON_HOOK
            );
        }
        if (!wp_next_scheduled(self::RETRY_HOOK)) {
            wp_schedule_event(
                $this->next_timestamp($settings['retry_hour'], $settings['retry_minute'], $settings['timezone']),
                'ndr_daily',
                self::RETRY_HOOK
            );
        }

        if (!wp_next_scheduled(self::ANALYTICS_CRON_HOOK)) {
            wp_schedule_event(
                $this->next_timestamp(2, 15, $settings['timezone']),
                'ndr_daily',
                self::ANALYTICS_CRON_HOOK
            );
        }
    }

    public function register_settings() {
        register_setting(
            'nepali_daily_rashifal',
            self::OPTION,
            array('sanitize_callback' => array($this, 'sanitize_settings'))
        );
    }


    public function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        $content = '<p>' . esc_html__('Nepali Daily Rashifal can contact the Rashifal API configured by the site administrator when the administrator tests the API, manually syncs, or enables scheduled publishing. The service receives normal server request metadata. Optional remote product analytics are disabled by default and are sent only after an administrator explicitly opts in and configures an HTTPS analytics endpoint. Local readership counters, when enabled, store aggregate counts in this WordPress database and do not store visitor IP addresses, cookies, accounts, or user-agent strings.', 'nepali-daily-rashifal') . '</p>';
        wp_add_privacy_policy_content(
            __('Nepali Daily Rashifal', 'nepali-daily-rashifal'),
            wp_kses_post($content)
        );
    }

    public function sanitize_settings($input) {
        $old = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        $out = self::defaults();
        $out['enabled'] = empty($input['enabled']) ? 0 : 1;
        $out['api_url'] = esc_url_raw(isset($input['api_url']) ? $input['api_url'] : $old['api_url']);
        $out['api_token'] = sanitize_text_field(isset($input['api_token']) ? $input['api_token'] : $old['api_token']);
        $out['publish_status'] = in_array(isset($input['publish_status']) ? $input['publish_status'] : '', array('draft', 'publish'), true) ? $input['publish_status'] : 'draft';
        $out['category_id'] = absint(isset($input['category_id']) ? $input['category_id'] : 0);
        $out['latest_category_id'] = absint(isset($input['latest_category_id']) ? $input['latest_category_id'] : 0);
        $out['auto_detect_latest_category'] = empty($input['auto_detect_latest_category']) ? 0 : 1;
        $out['author_id'] = absint(isset($input['author_id']) ? $input['author_id'] : 1);
        $out['publish_hour'] = min(23, max(0, absint(isset($input['publish_hour']) ? $input['publish_hour'] : 5)));
        $out['publish_minute'] = min(59, max(0, absint(isset($input['publish_minute']) ? $input['publish_minute'] : 20)));
        $out['retry_hour'] = min(23, max(0, absint(isset($input['retry_hour']) ? $input['retry_hour'] : 6)));
        $out['retry_minute'] = min(59, max(0, absint(isset($input['retry_minute']) ? $input['retry_minute'] : 50)));
        $out['timezone'] = $this->valid_timezone(sanitize_text_field(isset($input['timezone']) ? $input['timezone'] : 'Asia/Kathmandu'));
        $out['post_title'] = sanitize_text_field(isset($input['post_title']) ? $input['post_title'] : 'आजको वैदिक राशिफल');
        if ('' === $out['post_title']) {
            $out['post_title'] = 'आजको वैदिक राशिफल';
        }
        $out['slug_prefix'] = sanitize_title(isset($input['slug_prefix']) ? $input['slug_prefix'] : 'vaidic-rashifal');
        if ('' === $out['slug_prefix']) {
            $out['slug_prefix'] = 'vaidic-rashifal';
        }
        $out['cta_url'] = esc_url_raw(isset($input['cta_url']) ? $input['cta_url'] : '');
        $out['cta_label'] = sanitize_text_field(isset($input['cta_label']) ? $input['cta_label'] : 'ज्योतिषसँग च्याट गर्नुहोस्');
        $out['source_label'] = sanitize_text_field(isset($input['source_label']) ? $input['source_label'] : 'MuniAstro');
        $out['show_source'] = empty($input['show_source']) ? 0 : 1;
        $out['local_view_tracking'] = empty($input['local_view_tracking']) ? 0 : 1;
        $out['analytics_enabled'] = empty($input['analytics_enabled']) ? 0 : 1;
        $out['analytics_endpoint'] = esc_url_raw(isset($input['analytics_endpoint']) ? $input['analytics_endpoint'] : '');
        if ($out['analytics_endpoint'] && 0 !== strpos($out['analytics_endpoint'], 'https://')) {
            $out['analytics_endpoint'] = '';
        }
        $out['analytics_token'] = sanitize_text_field(isset($input['analytics_token']) ? $input['analytics_token'] : $old['analytics_token']);
        $out['registered_site'] = empty($input['registered_site']) ? 0 : 1;
        $out['registered_site_name'] = sanitize_text_field(isset($input['registered_site_name']) ? $input['registered_site_name'] : '');
        $out['registered_contact_email'] = sanitize_email(isset($input['registered_contact_email']) ? $input['registered_contact_email'] : '');

        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::RETRY_HOOK);
        wp_clear_scheduled_hook(self::ANALYTICS_CRON_HOOK);
        return $out;
    }

    public function admin_menu() {
        add_menu_page(
            __('Nepali Daily Rashifal', 'nepali-daily-rashifal'),
            __('Daily Rashifal', 'nepali-daily-rashifal'),
            'manage_options',
            'nepali-daily-rashifal',
            array($this, 'settings_page'),
            'dashicons-calendar-alt'
        );
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        $logs = array_slice(array_reverse(get_option(self::LOG_OPTION, array())), 0, 15);
        $next = wp_next_scheduled(self::CRON_HOOK);
        $retry = wp_next_scheduled(self::RETRY_HOOK);
        $analytics_next = wp_next_scheduled(self::ANALYTICS_CRON_HOOK);
        $analytics_status = get_option(self::ANALYTICS_STATUS_OPTION, array());
        $view_summary = $this->local_view_summary();
        $result = '';
        if (
            isset($_GET['ndr_result'], $_GET['_ndr_notice_nonce']) &&
            wp_verify_nonce(
                sanitize_text_field(wp_unslash($_GET['_ndr_notice_nonce'])),
                'ndr_admin_notice'
            )
        ) {
            $result = sanitize_text_field(wp_unslash($_GET['ndr_result']));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Nepali Daily Rashifal', 'nepali-daily-rashifal'); ?></h1>
            <p><?php esc_html_e('Publish approved daily Nepali Vedic Rashifal automatically from a configured API.', 'nepali-daily-rashifal'); ?></p>
            <?php if ($result) : ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html($result); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('nepali_daily_rashifal'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Automatic publishing', 'nepali-daily-rashifal'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($s['enabled'], 1); ?>> <?php esc_html_e('Enable daily API sync', 'nepali-daily-rashifal'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Rashifal API URL', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text code" type="url" name="<?php echo esc_attr(self::OPTION); ?>[api_url]" value="<?php echo esc_attr($s['api_url']); ?>" required><p class="description"><?php esc_html_e('The configured server is contacted only when you test, sync, or enable scheduled sync.', 'nepali-daily-rashifal'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('API token', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION); ?>[api_token]" value="<?php echo esc_attr($s['api_token']); ?>"><p class="description"><?php esc_html_e('Optional Bearer token.', 'nepali-daily-rashifal'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post title', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[post_title]" value="<?php echo esc_attr($s['post_title']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Slug prefix', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text code" type="text" name="<?php echo esc_attr(self::OPTION); ?>[slug_prefix]" value="<?php echo esc_attr($s['slug_prefix']); ?>"><p class="description">Example: vaidic-rashifal-2026-09-25</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post status', 'nepali-daily-rashifal'); ?></th>
                        <td><select name="<?php echo esc_attr(self::OPTION); ?>[publish_status]"><option value="draft" <?php selected($s['publish_status'], 'draft'); ?>><?php esc_html_e('Draft', 'nepali-daily-rashifal'); ?></option><option value="publish" <?php selected($s['publish_status'], 'publish'); ?>><?php esc_html_e('Publish', 'nepali-daily-rashifal'); ?></option></select></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Rashifal category', 'nepali-daily-rashifal'); ?></th>
                        <td><?php wp_dropdown_categories(array('show_option_none' => __('Uncategorized', 'nepali-daily-rashifal'), 'hide_empty' => 0, 'name' => self::OPTION . '[category_id]', 'selected' => $s['category_id'])); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Latest / homepage category', 'nepali-daily-rashifal'); ?></th>
                        <td><?php wp_dropdown_categories(array('show_option_none' => __('None', 'nepali-daily-rashifal'), 'option_none_value' => '0', 'hide_empty' => 0, 'name' => self::OPTION . '[latest_category_id]', 'selected' => $s['latest_category_id'])); ?><br><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[auto_detect_latest_category]" value="1" <?php checked($s['auto_detect_latest_category'], 1); ?>> <?php esc_html_e('If none is selected, try to detect ताजा समाचार / ताजा अपडेट / ताजा खबर / latest-news.', 'nepali-daily-rashifal'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Author', 'nepali-daily-rashifal'); ?></th>
                        <td><?php wp_dropdown_users(array('name' => self::OPTION . '[author_id]', 'selected' => $s['author_id'], 'who' => 'authors')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Timezone', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text code" type="text" name="<?php echo esc_attr(self::OPTION); ?>[timezone]" value="<?php echo esc_attr($s['timezone']); ?>"><p class="description">IANA timezone, e.g. Asia/Kathmandu.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Daily publish time', 'nepali-daily-rashifal'); ?></th>
                        <td><input type="number" min="0" max="23" name="<?php echo esc_attr(self::OPTION); ?>[publish_hour]" value="<?php echo esc_attr($s['publish_hour']); ?>" style="width:70px"> : <input type="number" min="0" max="59" name="<?php echo esc_attr(self::OPTION); ?>[publish_minute]" value="<?php echo esc_attr($s['publish_minute']); ?>" style="width:70px"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Retry time', 'nepali-daily-rashifal'); ?></th>
                        <td><input type="number" min="0" max="23" name="<?php echo esc_attr(self::OPTION); ?>[retry_hour]" value="<?php echo esc_attr($s['retry_hour']); ?>" style="width:70px"> : <input type="number" min="0" max="59" name="<?php echo esc_attr(self::OPTION); ?>[retry_minute]" value="<?php echo esc_attr($s['retry_minute']); ?>" style="width:70px"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('CTA URL', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text" type="url" name="<?php echo esc_attr(self::OPTION); ?>[cta_url]" value="<?php echo esc_attr($s['cta_url']); ?>"><p class="description"><?php esc_html_e('Optional. No public CTA link is added when blank.', 'nepali-daily-rashifal'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('CTA label', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[cta_label]" value="<?php echo esc_attr($s['cta_label']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Source credit', 'nepali-daily-rashifal'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[show_source]" value="1" <?php checked($s['show_source'], 1); ?>> <?php esc_html_e('Show source label in the post', 'nepali-daily-rashifal'); ?></label><br><input type="text" name="<?php echo esc_attr(self::OPTION); ?>[source_label]" value="<?php echo esc_attr($s['source_label']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Local readership analytics', 'nepali-daily-rashifal'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[local_view_tracking]" value="1" <?php checked($s['local_view_tracking'], 1); ?>> <?php esc_html_e('Count views of Rashifal posts locally on this WordPress site. No visitor IP, cookie, account, or user-agent is stored.', 'nepali-daily-rashifal'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Anonymous product analytics', 'nepali-daily-rashifal'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[analytics_enabled]" value="1" <?php checked($s['analytics_enabled'], 1); ?>> <?php esc_html_e('Opt in to send anonymous plugin-usage metrics to the analytics endpoint below.', 'nepali-daily-rashifal'); ?></label><p class="description"><?php esc_html_e('Off by default. Sends an anonymous installation ID, plugin/WordPress/PHP versions, timezone, publish health, post counts, and aggregate Rashifal view totals. It does not send visitor IPs, visitor accounts, page content, or WordPress admin credentials.', 'nepali-daily-rashifal'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Analytics endpoint', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text code" type="url" name="<?php echo esc_attr(self::OPTION); ?>[analytics_endpoint]" value="<?php echo esc_attr($s['analytics_endpoint']); ?>" placeholder="https://example.com/api/nepali-daily-rashifal/telemetry"><p class="description"><?php esc_html_e('Must use HTTPS. Nothing is transmitted when this field is blank or anonymous analytics is disabled.', 'nepali-daily-rashifal'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Analytics token', 'nepali-daily-rashifal'); ?></th>
                        <td><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION); ?>[analytics_token]" value="<?php echo esc_attr($s['analytics_token']); ?>"><p class="description"><?php esc_html_e('Optional Bearer token for your analytics collector.', 'nepali-daily-rashifal'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Identify this site to MuniAstro', 'nepali-daily-rashifal'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[registered_site]" value="1" <?php checked($s['registered_site'], 1); ?>> <?php esc_html_e('Voluntarily register this site. When enabled, the site URL, optional site name, and optional contact email are added to analytics payloads.', 'nepali-daily-rashifal'); ?></label><br><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[registered_site_name]" value="<?php echo esc_attr($s['registered_site_name']); ?>" placeholder="Site / publication name"><br><input class="regular-text" type="email" name="<?php echo esc_attr(self::OPTION); ?>[registered_contact_email]" value="<?php echo esc_attr($s['registered_contact_email']); ?>" placeholder="contact@example.com"><p class="description"><?php esc_html_e('This is separate from anonymous analytics and should be enabled only with the site owner’s consent.', 'nepali-daily-rashifal'); ?></p></td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'nepali-daily-rashifal')); ?>
            </form>

            <h2><?php esc_html_e('Status & tools', 'nepali-daily-rashifal'); ?></h2>
            <p><strong><?php esc_html_e('Next scheduled sync:', 'nepali-daily-rashifal'); ?></strong> <?php echo $next ? esc_html(wp_date('Y-m-d H:i:s T', $next)) : esc_html__('Not scheduled', 'nepali-daily-rashifal'); ?></p>
            <p><strong><?php esc_html_e('Next retry:', 'nepali-daily-rashifal'); ?></strong> <?php echo $retry ? esc_html(wp_date('Y-m-d H:i:s T', $retry)) : esc_html__('Not scheduled', 'nepali-daily-rashifal'); ?></p>
            <p><strong><?php esc_html_e('Local Rashifal views:', 'nepali-daily-rashifal'); ?></strong> <?php echo esc_html(number_format_i18n($view_summary['total_views'])); ?> &nbsp; <strong><?php esc_html_e('Tracked posts:', 'nepali-daily-rashifal'); ?></strong> <?php echo esc_html(number_format_i18n($view_summary['tracked_posts'])); ?></p>
            <p><strong><?php esc_html_e('Next analytics report:', 'nepali-daily-rashifal'); ?></strong> <?php echo $analytics_next ? esc_html(wp_date('Y-m-d H:i:s T', $analytics_next)) : esc_html__('Not scheduled', 'nepali-daily-rashifal'); ?><?php if (!empty($analytics_status['last_message'])) : ?> · <?php echo esc_html($analytics_status['last_message']); ?><?php endif; ?></p>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ndr_test_api"><?php wp_nonce_field('ndr_test_api'); ?><?php submit_button(__('Test API', 'nepali-daily-rashifal'), 'secondary', 'submit', false); ?></form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ndr_sync_now"><?php wp_nonce_field('ndr_sync_now'); ?><?php submit_button(__('Sync Today Now', 'nepali-daily-rashifal'), 'secondary', 'submit', false); ?></form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="ndr_send_analytics"><?php wp_nonce_field('ndr_send_analytics'); ?><?php submit_button(__('Send Analytics Test', 'nepali-daily-rashifal'), 'secondary', 'submit', false); ?></form>
            </div>

            <h2><?php esc_html_e('Recent log', 'nepali-daily-rashifal'); ?></h2>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Time', 'nepali-daily-rashifal'); ?></th><th><?php esc_html_e('Level', 'nepali-daily-rashifal'); ?></th><th><?php esc_html_e('Message', 'nepali-daily-rashifal'); ?></th></tr></thead><tbody>
            <?php if ($logs) : foreach ($logs as $log) : ?>
                <tr><td><?php echo esc_html($log['time']); ?></td><td><?php echo esc_html($log['level']); ?></td><td><?php echo esc_html($log['message']); ?></td></tr>
            <?php endforeach; else : ?>
                <tr><td colspan="3"><?php esc_html_e('No sync attempts yet.', 'nepali-daily-rashifal'); ?></td></tr>
            <?php endif; ?>
            </tbody></table>

            <h2><?php esc_html_e('External service disclosure', 'nepali-daily-rashifal'); ?></h2>
            <p><?php esc_html_e('This plugin retrieves Rashifal JSON and its featured image from the API URL you configure. Local readership counts stay in your WordPress database. Optional product analytics are OFF by default and are sent only after the administrator opts in and configures an HTTPS analytics endpoint. Optional site identification is a separate consent setting.', 'nepali-daily-rashifal'); ?></p>
        </div>
        <?php
    }

    private function redirect_with_notice($message) {
        $url = add_query_arg(
            array(
                'page' => 'nepali-daily-rashifal',
                'ndr_result' => rawurlencode((string) $message),
                '_ndr_notice_nonce' => wp_create_nonce('ndr_admin_notice'),
            ),
            admin_url('admin.php')
        );
        wp_safe_redirect($url);
        exit;
    }

    public function manual_sync() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'nepali-daily-rashifal'));
        }
        check_admin_referer('ndr_sync_now');
        $result = $this->sync(true);
        $this->redirect_with_notice($result);
    }

    public function manual_test_api() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'nepali-daily-rashifal'));
        }
        check_admin_referer('ndr_test_api');
        $result = $this->fetch_payload();
        if (is_wp_error($result)) {
            $message = 'API test failed: ' . $result->get_error_message();
        } else {
            $message = sprintf('API OK — %s — %d Rashis — status: %s', sanitize_text_field($result['dateKey']), count($result['signs']), sanitize_text_field($result['status']));
        }
        $this->redirect_with_notice($message);
    }

    public function manual_send_analytics() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'nepali-daily-rashifal'));
        }
        check_admin_referer('ndr_send_analytics');
        $result = $this->send_analytics(true);
        $message = is_wp_error($result) ? 'Analytics test failed: ' . $result->get_error_message() : $result;
        $this->redirect_with_notice($message);
    }

    public function scheduled_analytics() {
        $this->send_analytics(false);
    }

    public function track_rashifal_view() {
        if (is_admin() || is_feed() || is_preview() || !is_singular('post')) {
            return;
        }

        $settings = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if (empty($settings['local_view_tracking'])) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $date_key = get_post_meta($post_id, self::META_DATE, true);
        if (!$date_key) {
            $date_key = get_post_meta($post_id, self::LEGACY_META_DATE, true);
        }
        if (!$date_key) {
            return;
        }

        $this->index_post($date_key, $post_id);

        $views = (int) get_post_meta($post_id, self::META_VIEWS, true);
        update_post_meta($post_id, self::META_VIEWS, $views + 1);

        $timezone = $this->valid_timezone($settings['timezone']);
        $day = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d');

        $days = get_post_meta($post_id, self::META_VIEW_DAYS, true);
        if (!is_array($days)) {
            $days = array();
        }
        $days[$day] = isset($days[$day]) ? ((int) $days[$day] + 1) : 1;
        if (count($days) > 45) {
            ksort($days);
            $days = array_slice($days, -45, null, true);
        }
        update_post_meta($post_id, self::META_VIEW_DAYS, $days);

        $summary = get_option(self::VIEW_SUMMARY_OPTION, array());
        if (!is_array($summary)) {
            $summary = array();
        }
        $summary = wp_parse_args(
            $summary,
            array(
                'total_views' => 0,
                'today_views' => 0,
                'date' => $day,
                'tracked_posts' => array(),
            )
        );

        if ($summary['date'] !== $day) {
            $summary['date'] = $day;
            $summary['today_views'] = 0;
        }

        $summary['total_views'] = (int) $summary['total_views'] + 1;
        $summary['today_views'] = (int) $summary['today_views'] + 1;
        $summary['tracked_posts'][(string) $post_id] = 1;
        update_option(self::VIEW_SUMMARY_OPTION, $summary, false);
    }

    private function local_view_summary() {
        $settings = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        $timezone = $this->valid_timezone($settings['timezone']);
        $day = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d');

        $summary = get_option(self::VIEW_SUMMARY_OPTION, array());
        if (!is_array($summary)) {
            $summary = array();
        }
        $summary = wp_parse_args(
            $summary,
            array(
                'total_views' => 0,
                'today_views' => 0,
                'date' => $day,
                'tracked_posts' => array(),
            )
        );

        if ($summary['date'] !== $day) {
            $summary['date'] = $day;
            $summary['today_views'] = 0;
            update_option(self::VIEW_SUMMARY_OPTION, $summary, false);
        }

        return array(
            'total_views' => (int) $summary['total_views'],
            'today_views' => (int) $summary['today_views'],
            'tracked_posts' => is_array($summary['tracked_posts']) ? count($summary['tracked_posts']) : 0,
        );
    }

    private function analytics_payload($settings) {
        global $wp_version;
        $summary = $this->local_view_summary();
        $published = (int) wp_count_posts('post')->publish;
        $payload = array(
            'schema_version' => 1,
            'installation_id' => (string) get_option(self::INSTALL_ID_OPTION, ''),
            'plugin' => 'nepali-daily-rashifal',
            'plugin_version' => self::VERSION,
            'wordpress_version' => (string) $wp_version,
            'php_version' => PHP_VERSION,
            'timezone' => $this->valid_timezone($settings['timezone']),
            'auto_sync_enabled' => !empty($settings['enabled']),
            'publish_status' => $settings['publish_status'],
            'rashifal_posts_tracked' => $summary['tracked_posts'],
            'rashifal_views_total' => $summary['total_views'],
            'rashifal_views_today' => $summary['today_views'],
            'site_total_published_posts' => $published,
            'sent_at' => gmdate('c'),
        );
        if (!empty($settings['registered_site'])) {
            $payload['registered_site'] = array(
                'url' => home_url('/'),
                'name' => $settings['registered_site_name'],
                'contact_email' => $settings['registered_contact_email'],
            );
        }
        return $payload;
    }

    private function send_analytics($manual) {
        $settings = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if (empty($settings['analytics_enabled'])) {
            return $manual ? __('Anonymous analytics is disabled. Enable it in settings first.', 'nepali-daily-rashifal') : false;
        }
        if (empty($settings['analytics_endpoint'])) {
            return $manual ? new WP_Error('analytics_endpoint', __('Analytics endpoint is required.', 'nepali-daily-rashifal')) : false;
        }
        if (0 !== strpos($settings['analytics_endpoint'], 'https://')) {
            return new WP_Error('analytics_https', __('Analytics endpoint must use HTTPS.', 'nepali-daily-rashifal'));
        }
        $headers = array('Content-Type' => 'application/json', 'Accept' => 'application/json');
        if (!empty($settings['analytics_token'])) {
            $headers['Authorization'] = 'Bearer ' . $settings['analytics_token'];
        }
        $response = wp_safe_remote_post($settings['analytics_endpoint'], array(
            'timeout' => 15,
            'redirection' => 2,
            'headers' => $headers,
            'body' => wp_json_encode($this->analytics_payload($settings)),
            'user-agent' => 'Nepali-Daily-Rashifal/' . self::VERSION,
        ));
        if (is_wp_error($response)) {
            $this->record_analytics_status(false, $response->get_error_message());
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            /* translators: %d: HTTP status code returned by the analytics endpoint. */
            $error = new WP_Error('analytics_http', sprintf(__('Analytics endpoint returned HTTP %d.', 'nepali-daily-rashifal'), $code));
            $this->record_analytics_status(false, $error->get_error_message());
            return $error;
        }
        $this->record_analytics_status(true, sprintf('Analytics sent successfully (HTTP %d).', $code));
        return __('Analytics sent successfully.', 'nepali-daily-rashifal');
    }

    private function record_analytics_status($success, $message) {
        update_option(self::ANALYTICS_STATUS_OPTION, array(
            'last_sent_at' => gmdate('c'),
            'last_success' => (bool) $success,
            'last_message' => sanitize_text_field($message),
        ), false);
    }

    public function scheduled_sync() {
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if (!empty($s['enabled'])) {
            $this->sync(false);
        }
    }

    public function scheduled_retry() {
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if (empty($s['enabled'])) {
            return;
        }
        $today = (new DateTimeImmutable('now', new DateTimeZone($this->valid_timezone($s['timezone']))))->format('Y-m-d');
        if (!$this->find_post($today)) {
            $this->sync(false);
        }
    }

    private function fetch_payload() {
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if (empty($s['api_url'])) {
            return new WP_Error('api_url', __('API URL is required.', 'nepali-daily-rashifal'));
        }
        $headers = array('Accept' => 'application/json');
        if (!empty($s['api_token'])) {
            $headers['Authorization'] = 'Bearer ' . $s['api_token'];
        }
        $response = wp_safe_remote_get($s['api_url'], array(
            'timeout' => 20,
            'redirection' => 3,
            'headers' => $headers,
            'user-agent' => 'Nepali-Daily-Rashifal/' . self::VERSION,
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            /* translators: %d: HTTP status code returned by the configured Rashifal API. */
            return new WP_Error('http_status', sprintf(__('API returned HTTP %d.', 'nepali-daily-rashifal'), $code));
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $validation = $this->validate_payload($data);
        if (is_wp_error($validation)) {
            return $validation;
        }
        return $data;
    }

    private function sync($manual) {
        unset($manual); // Reserved for future audit context.
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        $data = $this->fetch_payload();
        if (is_wp_error($data)) {
            return $this->fail($data->get_error_message());
        }

        $date = sanitize_text_field($data['dateKey']);
        $hash = hash('sha256', wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $existing = $this->find_post($date);
        $same_payload = $existing && get_post_meta($existing, self::META_HASH, true) === $hash;
        $title = $s['post_title'];
        $categories = $this->post_categories($s);

        if ($same_payload) {
            $current = get_post($existing);
            $current_categories = array_map('intval', wp_get_post_categories($existing));
            $expected_categories = array_map('intval', $categories);
            sort($current_categories);
            sort($expected_categories);
            if ($current && $current->post_title === $title && $current->post_status === $s['publish_status'] && $current_categories === $expected_categories) {
                $this->log('info', 'Already current for ' . $date . ' (post ' . $existing . ')');
                return __('Already current; no duplicate created.', 'nepali-daily-rashifal');
            }
        }

        $postarr = array(
            'ID' => $existing ? $existing : 0,
            'post_title' => $title,
            'post_name' => $existing ? get_post_field('post_name', $existing) : sanitize_title($s['slug_prefix'] . '-' . $date),
            'post_content' => $this->build_content($data, $s),
            'post_excerpt' => sanitize_text_field($title . ' — ' . (!empty($data['nepaliDate']) ? $data['nepaliDate'] : $date)),
            'post_status' => $s['publish_status'],
            'post_type' => 'post',
            'post_author' => $s['author_id'],
            'post_category' => $categories,
            'meta_input' => array(
                self::META_DATE => $date,
                self::META_HASH => $hash,
                self::META_IMAGE => sanitize_text_field(isset($data['imageVersion']) ? $data['imageVersion'] : ''),
            ),
        );

        $post_id = wp_insert_post(wp_slash($postarr), true);
        if (is_wp_error($post_id)) {
            return $this->fail('Post save failed: ' . $post_id->get_error_message());
        }
        $this->index_post($date, $post_id);

        $image_result = ($same_payload && has_post_thumbnail($post_id)) ? true : $this->set_featured_image($post_id, esc_url_raw($data['imageUrl']), $title);
        if (is_wp_error($image_result)) {
            wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
            return $this->fail('Image failed; post kept as draft: ' . $image_result->get_error_message());
        }

        $this->log('success', ($existing ? 'Updated' : 'Created') . ' post ' . $post_id . ' for ' . $date);
        return ($existing ? __('Updated', 'nepali-daily-rashifal') : __('Created', 'nepali-daily-rashifal')) . ' post #' . $post_id . '.';
    }

    private function validate_payload($data) {
        if (!is_array($data)) {
            return new WP_Error('bad_json', __('Invalid JSON payload.', 'nepali-daily-rashifal'));
        }
        foreach (array('dateKey', 'imageUrl', 'signs', 'status') as $key) {
            if (empty($data[$key])) {
                /* translators: %s: Required API response field name. */
                return new WP_Error('missing', sprintf(__('Missing required field: %s', 'nepali-daily-rashifal'), $key));
            }
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['dateKey'])) {
            return new WP_Error('date', __('Invalid dateKey.', 'nepali-daily-rashifal'));
        }
        if (!in_array($data['status'], array('published', 'approved'), true)) {
            return new WP_Error('status', __('Source Rashifal is not approved/published.', 'nepali-daily-rashifal'));
        }
        if (!is_array($data['signs']) || 12 !== count($data['signs'])) {
            return new WP_Error('signs', __('Exactly 12 Rashi sections are required.', 'nepali-daily-rashifal'));
        }
        if (0 !== strpos($data['imageUrl'], 'https://')) {
            return new WP_Error('image', __('HTTPS imageUrl is required.', 'nepali-daily-rashifal'));
        }
        return true;
    }

    private function build_content($d, $s) {
        $html = '<div class="nepali-daily-rashifal">';
        if (!empty($d['nepaliDate'])) {
            $html .= '<p><strong>' . esc_html($d['nepaliDate']);
            if (!empty($d['weekday'])) {
                $html .= ' · ' . esc_html($d['weekday']);
            }
            $html .= '</strong></p>';
        }

        $facts = array();
        foreach (array('tithi' => 'तिथि', 'nakshatra' => 'नक्षत्र', 'moonSign' => 'चन्द्र राशि') as $key => $label) {
            if (!empty($d[$key])) {
                $facts[] = '<strong>' . esc_html($label) . ':</strong> ' . esc_html($d[$key]);
            }
        }
        if ($facts) {
            $html .= '<p>' . implode(' · ', $facts) . '</p>';
        }

        $labels = array(
            'aries' => 'मेष', 'taurus' => 'वृष', 'gemini' => 'मिथुन', 'cancer' => 'कर्कट',
            'leo' => 'सिंह', 'virgo' => 'कन्या', 'libra' => 'तुला', 'scorpio' => 'वृश्चिक',
            'sagittarius' => 'धनु', 'capricorn' => 'मकर', 'aquarius' => 'कुम्भ', 'pisces' => 'मीन',
        );
        foreach ($d['signs'] as $slug => $reading) {
            $body = is_array($reading) ? (isset($reading['body']) ? $reading['body'] : (isset($reading['text']) ? $reading['text'] : '')) : $reading;
            $html .= '<h2>' . esc_html(isset($labels[$slug]) ? $labels[$slug] : ucfirst($slug)) . '</h2><p>' . nl2br(esc_html(wp_strip_all_tags($body))) . '</p>';
        }

        $html .= '<hr><p><em>' . esc_html__('यो सामान्य राशिफल हो; व्यक्तिगत फलादेश जन्मकुण्डली अनुसार फरक हुन सक्छ।', 'nepali-daily-rashifal') . '</em></p>';
        if (!empty($s['cta_url']) && !empty($s['cta_label'])) {
            $html .= '<p><strong><a href="' . esc_url($s['cta_url']) . '" rel="noopener noreferrer">' . esc_html($s['cta_label']) . '</a></strong></p>';
        }
        if (!empty($s['show_source']) && !empty($s['source_label'])) {
            $html .= '<p class="nepali-daily-rashifal-source">' . esc_html__('स्रोत:', 'nepali-daily-rashifal') . ' ' . esc_html($s['source_label']) . '</p>';
        }
        $html .= '</div>';
        return wp_kses_post($html);
    }

    private function post_categories($settings) {
        $ids = array();
        if (!empty($settings['category_id'])) {
            $ids[] = absint($settings['category_id']);
        }
        $latest_id = !empty($settings['latest_category_id']) ? absint($settings['latest_category_id']) : 0;
        if (!$latest_id && !empty($settings['auto_detect_latest_category'])) {
            $latest_id = $this->detect_latest_category();
        }
        if ($latest_id) {
            $ids[] = $latest_id;
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private function detect_latest_category() {
        foreach (array('ताजा समाचार', 'ताजा अपडेट', 'ताजा खबर') as $name) {
            $term = get_term_by('name', $name, 'category');
            if ($term && !is_wp_error($term)) {
                return (int) $term->term_id;
            }
        }
        foreach (array('taja-samachar', 'taja-update', 'taja-khabar', 'latest-news', 'latest') as $slug) {
            $term = get_term_by('slug', $slug, 'category');
            if ($term && !is_wp_error($term)) {
                return (int) $term->term_id;
            }
        }
        return 0;
    }

    private function set_featured_image($post_id, $url, $title) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url, 25);
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        $path = wp_parse_url($url, PHP_URL_PATH);
        $name = sanitize_file_name(basename($path ? $path : 'rashifal.jpg'));
        $file = array('name' => $name ? $name : 'rashifal.jpg', 'tmp_name' => $tmp);
        $attachment = media_handle_sideload($file, $post_id, $title);
        if (is_wp_error($attachment)) {
            wp_delete_file($tmp);
            return $attachment;
        }
        set_post_thumbnail($post_id, $attachment);
        update_post_meta($attachment, '_wp_attachment_image_alt', sanitize_text_field($title));
        return $attachment;
    }

    private function index_post($date, $post_id) {
        $date = sanitize_text_field($date);
        $post_id = absint($post_id);
        if (!$date || !$post_id) {
            return;
        }

        $index = get_option(self::POST_INDEX_OPTION, array());
        if (!is_array($index)) {
            $index = array();
        }
        $index[$date] = $post_id;

        if (count($index) > 500) {
            ksort($index);
            $index = array_slice($index, -500, null, true);
        }
        update_option(self::POST_INDEX_OPTION, $index, false);

        $summary = get_option(self::VIEW_SUMMARY_OPTION, array());
        if (!is_array($summary)) {
            $summary = array();
        }
        $summary = wp_parse_args(
            $summary,
            array(
                'total_views' => 0,
                'today_views' => 0,
                'date' => '',
                'tracked_posts' => array(),
            )
        );
        $summary['tracked_posts'][(string) $post_id] = 1;
        update_option(self::VIEW_SUMMARY_OPTION, $summary, false);
    }

    private function find_post($date) {
        $date = sanitize_text_field($date);
        $index = get_option(self::POST_INDEX_OPTION, array());

        if (is_array($index) && !empty($index[$date])) {
            $indexed_id = absint($index[$date]);
            $indexed_post = get_post($indexed_id);
            if ($indexed_post && 'post' === $indexed_post->post_type) {
                return $indexed_id;
            }
        }

        $settings = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        $candidate_slugs = array(
            sanitize_title($settings['slug_prefix'] . '-' . $date),
            sanitize_title('vaidic-rashifal-' . $date),
            sanitize_title('daily-rashifal-' . $date),
        );

        foreach (array_unique($candidate_slugs) as $slug) {
            $post = get_page_by_path($slug, OBJECT, 'post');
            if ($post instanceof WP_Post) {
                $this->index_post($date, $post->ID);
                return (int) $post->ID;
            }
        }

        return 0;
    }

    private function fail($message) {
        $this->log('error', $message);
        return $message;
    }

    private function log($level, $message) {
        $logs = get_option(self::LOG_OPTION, array());
        $logs[] = array(
            'time' => gmdate('c'),
            'level' => sanitize_key($level),
            'message' => sanitize_text_field($message),
        );
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        update_option(self::LOG_OPTION, $logs, false);
    }
}

register_activation_hook(__FILE__, array('Nepali_Daily_Rashifal', 'activate'));
register_deactivation_hook(__FILE__, array('Nepali_Daily_Rashifal', 'deactivate'));
Nepali_Daily_Rashifal::instance();
