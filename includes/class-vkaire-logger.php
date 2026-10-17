<?php
if (!defined('ABSPATH')) {
    exit;
}

class VKAIRE_Logger
{
    private static $log_file = VKAIRE_LOG_FILE;
    private static $max_log_size = 1048576; // 1 MB

    public static function init()
    {
        add_action('init', array(__CLASS__, 'create_log_directory'));
        add_action('admin_menu', array(__CLASS__, 'add_log_page'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('wp_ajax_vkaire_clear_logs', array(__CLASS__, 'ajax_clear_logs'));
        add_action('wp_ajax_vkaire_refresh_logs', array(__CLASS__, 'ajax_refresh_logs'));
    }

    public static function enqueue_assets($hook)
    {
        if ($hook !== 'var-settings_page_vkaire-logs') {
            return;
        }
        wp_enqueue_style('vkaire-admin', VKAIRE_ASSETS_URL . 'admin.css', array(), VKAIRE_VERSION);
        wp_enqueue_script('vkaire-admin', VKAIRE_ASSETS_URL . 'admin.js', array('jquery'), VKAIRE_VERSION, true);
        wp_localize_script('vkaire-admin', 'vkaireL10n', array(
            'refreshing'      => __('Refreshing...', 'milyutin-code-article-reposter-for-vk'),
            'refreshed'       => __('Refreshed', 'milyutin-code-article-reposter-for-vk'),
            'connection_error'=> __('Connection error', 'milyutin-code-article-reposter-for-vk'),
            'confirm_clear'   => __('Are you sure you want to clear all logs?', 'milyutin-code-article-reposter-for-vk'),
            'clearing'        => __('Clearing...', 'milyutin-code-article-reposter-for-vk'),
            'logs_cleared'    => __('Logs cleared', 'milyutin-code-article-reposter-for-vk'),
            'refresh_nonce'   => wp_create_nonce('vkaire_refresh_logs'),
            'clear_nonce'     => wp_create_nonce('vkaire_clear_logs'),
        ));
    }

    public static function create_log_directory()
    {
        $log_dir = dirname(self::$log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
            file_put_contents($log_dir . '/.htaccess', 'Deny from all');
        }
        if (!file_exists(self::$log_file)) {
            file_put_contents(self::$log_file, '');
        }
    }

    public static function add_log_page()
    {
        add_submenu_page(
            'var-settings',
            esc_html__('Reposter Logs', 'milyutin-code-article-reposter-for-vk'),
            esc_html__('Logs', 'milyutin-code-article-reposter-for-vk'),
            'manage_options',
            'vkaire-logs',
            array(__CLASS__, 'render_log_page')
        );
    }

    public static function render_log_page()
    {
        $log_content = self::get_log_content();
        $log_size = self::get_log_size();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Reposter Logs', 'milyutin-code-article-reposter-for-vk'); ?></h1>
            <div style="margin-bottom: 20px;">
                <button type="button" id="vkaire-refresh-logs" class="button button-secondary"><?php esc_html_e('Refresh', 'milyutin-code-article-reposter-for-vk'); ?></button>
                <button type="button" id="vkaire-clear-logs" class="button button-secondary" style="background: #dc3232; color: white;"><?php esc_html_e('Clear Logs', 'milyutin-code-article-reposter-for-vk'); ?></button>
                <span style="margin-left: 15px; color: #666; font-size: 13px;"><?php esc_html_e('Log size:', 'milyutin-code-article-reposter-for-vk'); ?> <?php echo esc_html(self::format_size($log_size)); ?></span>
                <span id="vkaire-log-message" style="margin-left: 15px;"></span>
            </div>
            <div id="vkaire-log-container" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; max-height: 600px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($log_content); ?></div>
            <div style="margin-top: 20px; padding: 15px; background: #f1f1f1; border-left: 4px solid #2271b1;">
                <p style="margin:0;"><strong><?php esc_html_e('Info:', 'milyutin-code-article-reposter-for-vk'); ?></strong> <?php esc_html_e('Logs are stored in:', 'milyutin-code-article-reposter-for-vk'); ?> <code><?php echo esc_html(self::$log_file); ?></code></p>
                <p style="margin:5px 0 0;"><strong><?php esc_html_e('Log levels:', 'milyutin-code-article-reposter-for-vk'); ?></strong> <span style="color:#4CAF50;">✓ INFO</span> | <span style="color:#FFC107;">⚠️ WARNING</span> | <span style="color:#F44336;">❌ ERROR</span> | <span style="color:#2196F3;">🔵 DEBUG</span></p>
            </div>
        </div>
        <?php
    }

    public static function log($message, $level = 'INFO')
    {
        if (file_exists(self::$log_file) && filesize(self::$log_file) > self::$max_log_size) {
            self::rotate_log();
        }
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    public static function info($message) { self::log($message, 'INFO'); }
    public static function warning($message) { self::log($message, 'WARNING'); }
    public static function error($message) { self::log($message, 'ERROR'); }
    public static function debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log($message, 'DEBUG');
        }
    }

    public static function get_log_content()
    {
        if (!file_exists(self::$log_file)) {
            return __('Log file not found', 'milyutin-code-article-reposter-for-vk');
        }
        $content = file_get_contents(self::$log_file);
        if ($content === false) {
            return __('Could not read log file', 'milyutin-code-article-reposter-for-vk');
        }
        if (empty($content)) {
            return __('Log is empty', 'milyutin-code-article-reposter-for-vk');
        }
        return $content;
    }

    public static function get_log_size()
    {
        if (!file_exists(self::$log_file)) {
            return 0;
        }
        return filesize(self::$log_file);
    }

    private static function format_size($bytes)
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    private static function rotate_log()
    {
        if (file_exists(self::$log_file)) {
            $backup_file = self::$log_file . '.old';
            rename(self::$log_file, $backup_file);
            file_put_contents(self::$log_file, '');
            self::info('Log rotated (size exceeded limit)');
        }
    }

    public static function ajax_clear_logs()
    {
        check_ajax_referer('vkaire_clear_logs', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        if (file_exists(self::$log_file)) {
            file_put_contents(self::$log_file, '');
            self::info('Logs cleared by user');
            wp_send_json_success('Logs cleared');
        } else {
            wp_send_json_error('Log file not found');
        }
    }

    public static function ajax_refresh_logs()
    {
        check_ajax_referer('vkaire_refresh_logs', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        $content = self::get_log_content();
        wp_send_json_success($content);
    }
}

function vkaire_log($message, $level = 'INFO')
{
    VKAIRE_Logger::log($message, $level);
}