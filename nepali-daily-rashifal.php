<?php
/**
 * Plugin Name: Nepali Daily Rashifal
 * Plugin URI: https://muniastro.com/
 * Description: Fetch and publish approved daily Nepali Vedic Rashifal with 12 zodiac readings, featured images, scheduling, categories, and duplicate protection.
 * Version: 1.1.0
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
    const VERSION = '1.1.0';
    const OPTION = 'nepali_daily_rashifal_settings';
    const LOG_OPTION = 'nepali_daily_rashifal_log';
    const CRON_HOOK = 'nepali_daily_rashifal_sync';
    const RETRY_HOOK = 'nepali_daily_rashifal_retry';
    const META_DATE = '_nepali_daily_rashifal_date_key';
    const META_HASH = '_nepali_daily_rashifal_content_hash';
    const LEGACY_OPTION = 'muniastro_rashifal_settings';
    const LEGACY_META_DATE = '_muniastro_date_key';

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
        add_action('admin_post_ndr_sync_now', array($this, 'manual_sync'));
        add_action('admin_post_ndr_test_api', array($this, 'manual_test_api'));
        add_action(self::CRON_HOOK, array($this, 'scheduled_sync'));
        add_action(self::RETRY_HOOK, array($this, 'scheduled_retry'));
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
            'show_source' => 1,
        );
    }

    public static function activate() {
        self::migrate_legacy_settings();
        if (!get_option(self::OPTION)) {
            add_option(self::OPTION, self::defaults(), '', false);
        }
        self::instance()->ensure_schedule(true);
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::RETRY_HOOK);
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
        $new['show_source'] = 1;
        if (empty($legacy['latest_category_id'])) {
            $new['auto_detect_latest_category'] = 1;
        }
        update_option(self::OPTION, $new, false);
    }

    public function cron_schedules($schedules) {
        $schedules['ndr_daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display' => 'Nepali Daily Rashifal — Daily',
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
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if ($force) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            wp_clear_scheduled_hook(self::RETRY_HOOK);
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(
                $this->next_timestamp($s['publish_hour'], $s['publish_minute'], $s['timezone']),
                'ndr_daily',
                self::CRON_HOOK
            );
        }
        if (!wp_next_scheduled(self::RETRY_HOOK)) {
            wp_schedule_event(
                $this->next_timestamp($s['retry_hour'], $s['retry_minute'], $s['timezone']),
                'ndr_daily',
                self::RETRY_HOOK
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

    public function sanitize_settings($input) {
        $old = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        $out = self::defaults();
        $out['enabled'] = empty($input['enabled']) ? 0 : 1;
        $out['api_url'] = esc_url_raw(isset($input['api_url']) ? $input['api_url'] : $old['api_url']);
        $out['api_token'] = sanitize_text_field(isset($input['api_token']) ? $input['api_token'] : '');
        $out['publish_status'] = (isset($input['publish_status']) && 'publish' === $input['publish_status']) ? 'publish' : 'draft';
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
        $out['slug_prefix'] = sanitize_title(isset($input['slug_prefix']) ? $input['slug_prefix'] : 'vaidic-rashifal');
        $out['cta_url'] = esc_url_raw(isset($input['cta_url']) ? $input['cta_url'] : '');
        $out['cta_label'] = sanitize_text_field(isset($input['cta_label']) ? $input['cta_label'] : '');
        $out['source_label'] = sanitize_text_field(isset($input['source_label']) ? $input['source_label'] : 'MuniAstro');
        $out['show_source'] = empty($input['show_source']) ? 0 : 1;

        if (!$out['post_title']) {
            $out['post_title'] = 'आजको वैदिक राशिफल';
        }
        if (!$out['slug_prefix']) {
            $out['slug_prefix'] = 'vaidic-rashifal';
        }

        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::RETRY_HOOK);
        return $out;
    }

    public function admin_menu() {
        add_menu_page(
            'Nepali Daily Rashifal',
            'Daily Rashifal',
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
        $result = isset($_GET['ndr_result']) ? sanitize_text_field(wp_unslash($_GET['ndr_result'])) : '';
        $categories = get_categories(array('hide_empty' => false));
        $authors = get_users(array('who' => 'authors'));
        ?>
        <div class="wrap">
            <h1>Nepali Daily Rashifal</h1>
            <p>Publish approved daily Nepali Vedic Rashifal automatically from a configured JSON API.</p>
            <?php if ($result) : ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html($result); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('nepali_daily_rashifal'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Automatic publishing</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($s['enabled'], 1); ?>> Enable daily sync</label></td>
                    </tr>
                    <tr>
                        <th scope="row">Approved Rashifal API</th>
                        <td><input class="regular-text code" type="url" name="<?php echo esc_attr(self::OPTION); ?>[api_url]" value="<?php echo esc_attr($s['api_url']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row">API token</th>
                        <td><input class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr(self::OPTION); ?>[api_token]" value="<?php echo esc_attr($s['api_token']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">Post title</th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[post_title]" value="<?php echo esc_attr($s['post_title']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">Post status</th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION); ?>[publish_status]">
                                <option value="draft" <?php selected($s['publish_status'], 'draft'); ?>>Draft</option>
                                <option value="publish" <?php selected($s['publish_status'], 'publish'); ?>>Publish</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Rashifal category</th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION); ?>[category_id]">
                                <option value="0">— None —</option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($s['category_id'], $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Homepage / latest category</th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION); ?>[latest_category_id]">
                                <option value="0">— None —</option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($s['latest_category_id'], $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[auto_detect_latest_category]" value="1" <?php checked($s['auto_detect_latest_category'], 1); ?>> Auto-detect common “ताजा समाचार / latest news” categories when no category is selected.</label></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Author</th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION); ?>[author_id]">
                                <?php foreach ($authors as $author) : ?>
                                    <option value="<?php echo esc_attr($author->ID); ?>" <?php selected($s['author_id'], $author->ID); ?>><?php echo esc_html($author->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Timezone</th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[timezone]" value="<?php echo esc_attr($s['timezone']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">Daily publish time</th>
                        <td><input type="number" min="0" max="23" name="<?php echo esc_attr(self::OPTION); ?>[publish_hour]" value="<?php echo esc_attr($s['publish_hour']); ?>" style="width:70px"> :
                        <input type="number" min="0" max="59" name="<?php echo esc_attr(self::OPTION); ?>[publish_minute]" value="<?php echo esc_attr($s['publish_minute']); ?>" style="width:70px"></td>
                    </tr>
                    <tr>
                        <th scope="row">Retry time</th>
                        <td><input type="number" min="0" max="23" name="<?php echo esc_attr(self::OPTION); ?>[retry_hour]" value="<?php echo esc_attr($s['retry_hour']); ?>" style="width:70px"> :
                        <input type="number" min="0" max="59" name="<?php echo esc_attr(self::OPTION); ?>[retry_minute]" value="<?php echo esc_attr($s['retry_minute']); ?>" style="width:70px"></td>
                    </tr>
                    <tr>
                        <th scope="row">Slug prefix</th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[slug_prefix]" value="<?php echo esc_attr($s['slug_prefix']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">CTA URL</th>
                        <td><input class="regular-text" type="url" name="<?php echo esc_attr(self::OPTION); ?>[cta_url]" value="<?php echo esc_attr($s['cta_url']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">CTA label</th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[cta_label]" value="<?php echo esc_attr($s['cta_label']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">Source label</th>
                        <td><input class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION); ?>[source_label]" value="<?php echo esc_attr($s['source_label']); ?>">
                        <p><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[show_source]" value="1" <?php checked($s['show_source'], 1); ?>> Show source credit in post</label></p></td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>

            <hr>
            <h2>Tools</h2>
            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ndr_test_api'), 'ndr_test_api')); ?>">Test API</a>
                <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ndr_sync_now'), 'ndr_sync_now')); ?>">Sync Today Now</a>
            </p>
            <p><strong>Next scheduled sync:</strong> <?php echo $next ? esc_html(wp_date('Y-m-d H:i:s T', $next)) : 'Not scheduled'; ?></p>
            <p><strong>Next retry:</strong> <?php echo $retry ? esc_html(wp_date('Y-m-d H:i:s T', $retry)) : 'Not scheduled'; ?></p>

            <h2>Recent log</h2>
            <?php if (!$logs) : ?>
                <p>No log entries yet.</p>
            <?php else : ?>
                <table class="widefat striped"><thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead><tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr><td><?php echo esc_html($log['time']); ?></td><td><?php echo esc_html($log['level']); ?></td><td><?php echo esc_html($log['message']); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function manual_test_api() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized.');
        }
        check_admin_referer('ndr_test_api');
        $data = $this->fetch_payload();
        $message = is_wp_error($data) ? $data->get_error_message() : 'API connection successful. Approved Rashifal received for ' . $data['dateKey'] . '.';
        wp_safe_redirect(add_query_arg('ndr_result', rawurlencode($message), admin_url('admin.php?page=nepali-daily-rashifal')));
        exit;
    }

    public function manual_sync() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized.');
        }
        check_admin_referer('ndr_sync_now');
        $message = $this->sync();
        wp_safe_redirect(add_query_arg('ndr_result', rawurlencode($message), admin_url('admin.php?page=nepali-daily-rashifal')));
        exit;
    }

    public function scheduled_sync() {
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if (!empty($s['enabled'])) {
            $this->sync();
        }
    }

    public function scheduled_retry() {
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if (!empty($s['enabled'])) {
            $this->sync();
        }
    }

    private function fetch_payload() {
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        if (empty($s['api_url'])) {
            return new WP_Error('missing_api_url', 'Rashifal API URL is missing.');
        }

        $headers = array(
            'Accept' => 'application/json',
            'User-Agent' => 'Nepali-Daily-Rashifal/' . self::VERSION . '; ' . home_url('/'),
        );
        if (!empty($s['api_token'])) {
            $headers['Authorization'] = 'Bearer ' . $s['api_token'];
        }

        $response = wp_safe_remote_get(
            $s['api_url'],
            array(
                'timeout' => 20,
                'redirection' => 3,
                'headers' => $headers,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        if (200 !== (int) $code) {
            return new WP_Error('http_error', 'Rashifal API returned HTTP ' . (int) $code . '.');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $valid = $this->validate_payload($data);
        return is_wp_error($valid) ? $valid : $data;
    }

    private function validate_payload($data) {
        if (!is_array($data)) {
            return new WP_Error('invalid_json', 'Invalid JSON payload.');
        }
        foreach (array('dateKey', 'imageUrl', 'signs', 'status') as $key) {
            if (empty($data[$key])) {
                return new WP_Error('missing_field', 'Missing required field: ' . $key);
            }
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['dateKey'])) {
            return new WP_Error('invalid_date', 'Invalid dateKey.');
        }
        if (!in_array($data['status'], array('published', 'approved'), true)) {
            return new WP_Error('invalid_status', 'Source Rashifal is not approved/published.');
        }
        if (!is_array($data['signs']) || 12 !== count($data['signs'])) {
            return new WP_Error('invalid_signs', 'Exactly 12 Rashi sections are required.');
        }
        if (0 !== strpos($data['imageUrl'], 'https://')) {
            return new WP_Error('invalid_image', 'HTTPS imageUrl is required.');
        }
        return true;
    }

    private function sync() {
        $s = wp_parse_args(get_option(self::OPTION, array()), self::defaults());
        $data = $this->fetch_payload();
        if (is_wp_error($data)) {
            return $this->fail($data->get_error_message());
        }

        $date = sanitize_text_field($data['dateKey']);
        $hash = hash('sha256', wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $existing = $this->find_post($date);
        $same_payload = $existing && get_post_meta($existing, self::META_HASH, true) === $hash;
        $categories = $this->post_categories($s);

        $postarr = array(
            'ID' => $existing ? $existing : 0,
            'post_title' => $s['post_title'],
            'post_name' => $existing ? get_post_field('post_name', $existing) : sanitize_title($s['slug_prefix'] . '-' . $date),
            'post_content' => $this->build_content($data, $s),
            'post_excerpt' => sanitize_text_field($s['post_title'] . ' — ' . (!empty($data['nepaliDate']) ? $data['nepaliDate'] : $date)),
            'post_status' => $s['publish_status'],
            'post_type' => 'post',
            'post_author' => $s['author_id'],
            'post_category' => $categories,
            'meta_input' => array(
                self::META_DATE => $date,
                self::META_HASH => $hash,
            ),
        );

        if ($same_payload && $existing) {
            $current = get_post($existing);
            $current_categories = array_map('intval', wp_get_post_categories($existing));
            $expected_categories = array_map('intval', $categories);
            sort($current_categories);
            sort($expected_categories);
            if ($current && $current->post_title === $s['post_title'] && $current->post_status === $s['publish_status'] && $current_categories === $expected_categories) {
                $this->log('info', 'Already current for ' . $date . ' (post ' . $existing . ')');
                return 'Already current; no duplicate created.';
            }
        }

        $post_id = wp_insert_post(wp_slash($postarr), true);
        if (is_wp_error($post_id)) {
            return $this->fail('Post save failed: ' . $post_id->get_error_message());
        }

        if (!$same_payload || !has_post_thumbnail($post_id)) {
            $image = $this->set_featured_image($post_id, esc_url_raw($data['imageUrl']), $s['post_title']);
            if (is_wp_error($image)) {
                wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
                return $this->fail('Image failed; post kept as draft: ' . $image->get_error_message());
            }
        }

        $this->log('success', ($existing ? 'Updated' : 'Created') . ' post ' . $post_id . ' for ' . $date);
        return ($existing ? 'Updated' : 'Created') . ' post #' . $post_id . '.';
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
            'aries' => 'मेष',
            'taurus' => 'वृष',
            'gemini' => 'मिथुन',
            'cancer' => 'कर्कट',
            'leo' => 'सिंह',
            'virgo' => 'कन्या',
            'libra' => 'तुला',
            'scorpio' => 'वृश्चिक',
            'sagittarius' => 'धनु',
            'capricorn' => 'मकर',
            'aquarius' => 'कुम्भ',
            'pisces' => 'मीन',
        );

        foreach ($d['signs'] as $slug => $reading) {
            $body = is_array($reading) ? (isset($reading['body']) ? $reading['body'] : (isset($reading['text']) ? $reading['text'] : '')) : $reading;
            $name = isset($labels[$slug]) ? $labels[$slug] : ucfirst($slug);
            $html .= '<h2>' . esc_html($name) . '</h2><p>' . nl2br(esc_html(wp_strip_all_tags($body))) . '</p>';
        }

        $html .= '<hr><p><em>यो सामान्य राशिफल हो; व्यक्तिगत फलादेश जन्मकुण्डली अनुसार फरक हुन सक्छ।</em></p>';

        if (!empty($s['cta_url']) && !empty($s['cta_label'])) {
            $html .= '<p><strong><a href="' . esc_url($s['cta_url']) . '" rel="noopener noreferrer">' . esc_html($s['cta_label']) . '</a></strong></p>';
        }

        if (!empty($s['show_source']) && !empty($s['source_label'])) {
            $html .= '<p class="nepali-daily-rashifal-source">स्रोत: ' . esc_html($s['source_label']) . '</p>';
        }

        $html .= '</div>';
        return wp_kses_post($html);
    }

    private function post_categories($s) {
        $ids = array();
        if (!empty($s['category_id'])) {
            $ids[] = absint($s['category_id']);
        }
        $latest = !empty($s['latest_category_id']) ? absint($s['latest_category_id']) : 0;
        if (!$latest && !empty($s['auto_detect_latest_category'])) {
            $latest = $this->detect_latest_category();
        }
        if ($latest) {
            $ids[] = $latest;
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
        $file = array(
            'name' => $name ? $name : 'rashifal.jpg',
            'tmp_name' => $tmp,
        );

        $attachment = media_handle_sideload($file, $post_id, $title);
        if (is_wp_error($attachment)) {
            @unlink($tmp);
            return $attachment;
        }

        set_post_thumbnail($post_id, $attachment);
        update_post_meta($attachment, '_wp_attachment_image_alt', sanitize_text_field($title));
        return $attachment;
    }

    private function find_post($date) {
        $ids = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_key' => self::META_DATE,
            'meta_value' => $date,
            'no_found_rows' => true,
        ));
        if ($ids) {
            return (int) $ids[0];
        }

        $legacy = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_key' => self::LEGACY_META_DATE,
            'meta_value' => $date,
            'no_found_rows' => true,
        ));
        return $legacy ? (int) $legacy[0] : 0;
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
