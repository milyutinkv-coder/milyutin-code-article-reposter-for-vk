<?php
/**
 * Plugin Name: Milyutin Code Article Reposter for VK
 * Description: Automatically repost articles to VKontakte with AI-generated summaries (Yandex GPT) or first paragraph.
 * Version: 1.0.2
 * Author: Милютин Константин
 * Author URI: https://milyutin-codes.ru/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: milyutin-code-article-reposter-for-vk
 */

if (!defined('ABSPATH')) {
    exit;
}

define('VKAIRE_PLUGIN_FILE', __FILE__);
define('VKAIRE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VKAIRE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VKAIRE_ASSETS_URL', VKAIRE_PLUGIN_URL . 'assets/');
define('VKAIRE_VERSION', '1.0.2');
define('VKAIRE_VK_API_VERSION', '5.199');

// Определяем путь к логам в папке uploads
$upload_dir = wp_upload_dir();
define('VKAIRE_LOG_DIR', $upload_dir['basedir'] . '/reposter-for-vk-logs/');
define('VKAIRE_LOG_FILE', VKAIRE_LOG_DIR . 'var-log.txt');

// Подключаем классы
require_once VKAIRE_PLUGIN_DIR . 'includes/class-vkaire-settings.php';
require_once VKAIRE_PLUGIN_DIR . 'includes/class-vkaire-yandex-gpt.php';
require_once VKAIRE_PLUGIN_DIR . 'includes/class-vkaire-vk-poster.php';
require_once VKAIRE_PLUGIN_DIR . 'includes/class-vkaire-main.php';
require_once VKAIRE_PLUGIN_DIR . 'includes/class-vkaire-logger.php';

// Инициализация
add_action('plugins_loaded', function() {
    VKAIRE_Settings::init();
    VKAIRE_Main::init();
    VKAIRE_Logger::init();
});

// Активация/деактивация
register_activation_hook(VKAIRE_PLUGIN_FILE, array('VKAIRE_Main', 'activate'));
register_deactivation_hook(VKAIRE_PLUGIN_FILE, array('VKAIRE_Main', 'deactivate'));