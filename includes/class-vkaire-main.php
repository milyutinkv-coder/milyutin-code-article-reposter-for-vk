<?php
if (!defined('ABSPATH')) {
    exit;
}

class VKAIRE_Main
{
    private static $is_publishing = false;

    public static function init()
    {
        add_action('publish_post', array(__CLASS__, 'on_post_publish'), 10, 2);
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('wp_ajax_vkaire_manual_publish', array(__CLASS__, 'ajax_manual_publish'));
        add_action('wp_ajax_vkaire_test_publish', array(__CLASS__, 'ajax_test_publish'));
        add_filter('manage_posts_columns', array(__CLASS__, 'add_vk_column'));
        add_action('manage_posts_custom_column', array(__CLASS__, 'render_vk_column'), 10, 2);
    }

    public static function enqueue_assets($hook)
    {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        wp_enqueue_script('vkaire-admin', VKAIRE_ASSETS_URL . 'admin.js', array('jquery'), VKAIRE_VERSION, true);
        wp_localize_script('vkaire-admin', 'vkaireL10n', array(
            'publishing'    => __('Publishing...', 'milyutin-code-article-reposter-for-vk'),
            'published'     => __('Published to VK', 'milyutin-code-article-reposter-for-vk'),
            'publish_now'   => __('Publish now', 'milyutin-code-article-reposter-for-vk'),
            'network_error' => __('Network error', 'milyutin-code-article-reposter-for-vk'),
        ));
    }

    public static function activate()
    {
        if (!get_option('vkaire_settings')) {
            $defaults = array(
                'vk_group_id' => '',
                'vk_access_token' => '',
                'summary_source' => 'gpt',
                'yandex_api_key' => '',
                'yandex_folder_id' => '',
                'summary_max_tokens' => 500,
                'summary_temperature' => 0.3,
                'post_types' => array('post'),
                'auto_publish' => 1
            );
            update_option('vkaire_settings', $defaults);
        }
        VKAIRE_Logger::create_log_directory();
        vkaire_log('Плагин активирован', 'INFO');
    }

    public static function deactivate()
    {
        vkaire_log('Плагин деактивирован', 'INFO');
    }

    public static function on_post_publish($post_id, $post)
    {
        if (self::$is_publishing) {
            vkaire_log('Публикация уже выполняется, пропускаем', 'DEBUG');
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }
        $already_published = get_post_meta($post_id, '_vkaire_published', true);
        if (!empty($already_published)) {
            vkaire_log('Пост ID ' . $post_id . ' уже опубликован в ВК (ID: ' . $already_published . ')', 'DEBUG');
            return;
        }
        $settings = VKAIRE_Settings::get_settings();
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post');
        if (!in_array($post->post_type, $post_types)) {
            vkaire_log('Тип поста ' . $post->post_type . ' не в списке публикации', 'DEBUG');
            return;
        }
        if (empty($settings['auto_publish'])) {
            vkaire_log('Автопубликация отключена', 'DEBUG');
            return;
        }
        self::$is_publishing = true;
        try {
            vkaire_log('Автопубликация поста ID ' . $post_id . ' (' . $post->post_title . ')', 'INFO');
            $summary_source = isset($settings['summary_source']) ? $settings['summary_source'] : 'gpt';
            if ($summary_source === 'gpt' && !empty($settings['yandex_api_key']) && !empty($settings['yandex_folder_id'])) {
                vkaire_log('Генерация обзора через Yandex GPT для поста ID ' . $post_id, 'INFO');
                $summary = VKAIRE_Yandex_GPT::generate_summary($post->post_content, $settings);
                if ($summary) {
                    update_post_meta($post_id, '_vkaire_summary', $summary);
                    vkaire_log('Обзор сохранён для поста ID ' . $post_id, 'INFO');
                } else {
                    vkaire_log('Не удалось сгенерировать обзор для поста ID ' . $post_id, 'WARNING');
                    delete_post_meta($post_id, '_vkaire_summary');
                }
            } else {
                vkaire_log('Используется первый абзац (excerpt) для поста ID ' . $post_id, 'INFO');
                delete_post_meta($post_id, '_vkaire_summary');
            }
            set_time_limit(120);
            $result = VKAIRE_VK_Poster::post_to_vk($post_id, $settings);
            if ($result['success']) {
                vkaire_log('Опубликовано в ВК: ' . $result['message'], 'INFO');
            } else {
                vkaire_log('Ошибка публикации в ВК: ' . $result['message'], 'ERROR');
            }
        } catch (Exception $e) {
            vkaire_log('Критическая ошибка: ' . $e->getMessage(), 'ERROR');
        }
        self::$is_publishing = false;
    }

    public static function add_meta_box()
    {
        $settings = VKAIRE_Settings::get_settings();
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'vkaire_meta_box',
                'Публикация в ВК',
                array(__CLASS__, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    public static function render_meta_box($post)
    {
        $published = get_post_meta($post->ID, '_vkaire_published', true);
        $nonce = wp_create_nonce('vkaire_manual_' . $post->ID);
        ?>
        <div id="vkaire-meta-box">
            <?php if ($published) : ?>
                <p style="color: #4CAF50;">✅ Опубликовано в ВК</p>
                <p><small>ID: <?php echo esc_html($published); ?></small></p>
            <?php else : ?>
                <p><button type="button" class="button vkaire-manual-btn" data-post-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">Опубликовать сейчас</button></p>
            <?php endif; ?>
            <div id="vkaire-meta-result" style="margin-top:10px;"></div>
        </div>
        <?php
    }

    public static function ajax_manual_publish()
    {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$post_id || !wp_verify_nonce($nonce, 'vkaire_manual_' . $post_id)) {
            vkaire_log('Ошибка безопасности при ручной публикации', 'ERROR');
            wp_send_json_error('Ошибка безопасности');
        }
        if (!current_user_can('edit_post', $post_id)) {
            vkaire_log('Недостаточно прав для поста ID ' . $post_id, 'ERROR');
            wp_send_json_error('Недостаточно прав');
        }
        $already_published = get_post_meta($post_id, '_vkaire_published', true);
        if (!empty($already_published)) {
            wp_send_json_error('Пост уже опубликован в ВК (ID: ' . $already_published . ')');
            return;
        }
        $settings = VKAIRE_Settings::get_settings();
        $post = get_post($post_id);
        $summary_source = isset($settings['summary_source']) ? $settings['summary_source'] : 'gpt';
        if ($summary_source === 'gpt' && !empty($settings['yandex_api_key']) && !empty($settings['yandex_folder_id'])) {
            vkaire_log('Ручная генерация обзора через Yandex GPT для поста ID ' . $post_id, 'INFO');
            $summary = VKAIRE_Yandex_GPT::generate_summary($post->post_content, $settings);
            if ($summary) {
                update_post_meta($post_id, '_vkaire_summary', $summary);
            } else {
                delete_post_meta($post_id, '_vkaire_summary');
            }
        } else {
            delete_post_meta($post_id, '_vkaire_summary');
        }
        set_time_limit(120);
        $result = VKAIRE_VK_Poster::post_to_vk($post_id, $settings);
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public static function ajax_test_publish()
    {
        check_ajax_referer('vkaire_test', 'nonce');
        if (!current_user_can('manage_options')) {
            vkaire_log('Недостаточно прав для теста', 'ERROR');
            wp_send_json_error('Недостаточно прав');
        }
        $posts = get_posts(array('posts_per_page' => 1, 'post_status' => 'publish'));
        if (empty($posts)) {
            vkaire_log('Нет опубликованных постов для теста', 'WARNING');
            wp_send_json_error('Нет опубликованных постов');
        }
        vkaire_log('Тестовая публикация поста ID ' . $posts[0]->ID, 'INFO');
        $settings = VKAIRE_Settings::get_settings();
        set_time_limit(120);
        $result = VKAIRE_VK_Poster::post_to_vk($posts[0]->ID, $settings);
        if ($result['success']) {
            wp_send_json_success('Тест успешен! ' . $result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public static function add_vk_column($columns)
    {
        $columns['vkaire_vk_status'] = 'ВК';
        return $columns;
    }

    public static function render_vk_column($column, $post_id)
    {
        if ($column === 'vkaire_vk_status') {
            $published = get_post_meta($post_id, '_vkaire_published', true);
            if ($published) {
                echo '<span style="color:#4CAF50;font-size:18px;" title="Опубликовано в ВК">✓</span>';
            } else {
                echo '<span style="color:#999;" title="Не опубликовано">—</span>';
            }
        }
    }
}