<?php
if (!defined('ABSPATH')) {
    exit;
}

class VKAIRE_Settings
{
    private static $option_name = 'vkaire_settings';
    private static $settings = null;

    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }

    public static function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_var-settings') {
            return;
        }
        wp_enqueue_style('vkaire-admin', VKAIRE_ASSETS_URL . 'admin.css', array(), VKAIRE_VERSION);
        wp_enqueue_script('vkaire-admin', VKAIRE_ASSETS_URL . 'admin.js', array('jquery'), VKAIRE_VERSION, true);
        wp_localize_script('vkaire-admin', 'vkaireL10n', array());
    }

    public static function add_admin_menu()
    {
        add_menu_page(
            esc_html__('Reposter For VK Settings', 'milyutin-code-article-reposter-for-vk'),
            esc_html__('Reposter-For-VK', 'milyutin-code-article-reposter-for-vk'),
            'manage_options',
            'var-settings',
            array(__CLASS__, 'render_settings_page'),
            'dashicons-share-alt2',
            30
        );
    }

    public static function register_settings()
    {
        register_setting(
            'vkaire_settings_group',
            self::$option_name,
            array(__CLASS__, 'sanitize_settings')
        );
    }

    public static function sanitize_settings($input)
    {
        $output = array();
        $output['vk_group_id'] = isset($input['vk_group_id']) ? sanitize_text_field($input['vk_group_id']) : '';
        $output['vk_access_token'] = isset($input['vk_access_token']) ? sanitize_text_field($input['vk_access_token']) : '';
        $output['summary_source'] = isset($input['summary_source']) && in_array($input['summary_source'], array('gpt', 'excerpt')) ? $input['summary_source'] : 'gpt';
        $output['yandex_api_key'] = isset($input['yandex_api_key']) ? sanitize_text_field($input['yandex_api_key']) : '';
        $output['yandex_folder_id'] = isset($input['yandex_folder_id']) ? sanitize_text_field($input['yandex_folder_id']) : '';
        $output['summary_max_tokens'] = isset($input['summary_max_tokens']) ? intval($input['summary_max_tokens']) : 500;
        $output['summary_temperature'] = isset($input['summary_temperature']) ? floatval($input['summary_temperature']) : 0.3;
        $output['post_types'] = isset($input['post_types']) ? array_map('sanitize_text_field', $input['post_types']) : array('post');
        $output['auto_publish'] = isset($input['auto_publish']) ? 1 : 0;
        return $output;
    }

    public static function get_settings()
    {
        if (self::$settings === null) {
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
            self::$settings = wp_parse_args(get_option(self::$option_name, array()), $defaults);
        }
        return self::$settings;
    }

    public static function get($key, $default = null)
    {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    public static function render_settings_page()
    {
        $settings = self::get_settings();
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Reposter For VK Settings', 'milyutin-code-article-reposter-for-vk'); ?></h1>
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved!', 'milyutin-code-article-reposter-for-vk'); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('vkaire_settings_group'); ?>
                <div class="vkaire-settings-section">
                    <h2><?php esc_html_e('VKontakte Settings', 'milyutin-code-article-reposter-for-vk'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="vk_group_id"><?php esc_html_e('ID Группы', 'milyutin-code-article-reposter-for-vk'); ?></label></th>
                            <td>
                                <input type="text" id="vk_group_id" name="vkaire_settings[vk_group_id]" value="<?php echo esc_attr($settings['vk_group_id']); ?>" class="regular-text" required>
                                <p class="description"><?php esc_html_e('Числовой ID вашей группы VK, например 123456789', 'milyutin-code-article-reposter-for-vk'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="vk_access_token"><?php esc_html_e('Ключ доступа сообщества', 'milyutin-code-article-reposter-for-vk'); ?></label></th>
                            <td>
                                <input type="password" id="vk_access_token" name="vkaire_settings[vk_access_token]" value="<?php echo esc_attr($settings['vk_access_token']); ?>" class="regular-text" required>
                                <p class="description"><?php esc_html_e('Ключ доступа из раздела «Работа с API» в настройках сообщества.', 'milyutin-code-article-reposter-for-vk'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="vkaire-settings-section">
                    <h2><?php esc_html_e('Краткое содержание', 'milyutin-code-article-reposter-for-vk'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Выберите способ создания краткого содержания', 'milyutin-code-article-reposter-for-vk'); ?></th>
                            <td>
                                <label>
                                    <input type="radio" name="vkaire_settings[summary_source]" value="gpt" <?php checked($settings['summary_source'], 'gpt'); ?>>
                                    <strong><?php esc_html_e('Yandex GPT', 'milyutin-code-article-reposter-for-vk'); ?></strong>
                                    <span class="description">— <?php esc_html_e('создает краткое содержание с помощью нейронной сети (требуются API-ключ и ID папки).', 'milyutin-code-article-reposter-for-vk'); ?></span>
                                </label><br>
                                <label>
                                    <input type="radio" name="vkaire_settings[summary_source]" value="excerpt" <?php checked($settings['summary_source'], 'excerpt'); ?>>
                                    <strong><?php esc_html_e('Первый абзац (отрывок)', 'milyutin-code-article-reposter-for-vk'); ?></strong>
                                    <span class="description">— <?php esc_html_e('используется первый абзац статьи (бесплатно).', 'milyutin-code-article-reposter-for-vk'); ?></span>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="vkaire-settings-section" id="yandex-gpt-settings" style="<?php echo $settings['summary_source'] === 'excerpt' ? 'display:none;' : ''; ?>">
                    <h2><?php esc_html_e('Yandex GPT Settings', 'milyutin-code-article-reposter-for-vk'); ?></h2>
                    <p class="description"><?php esc_html_e('Эти поля используются только при выборе "Yandex GPT".', 'milyutin-code-article-reposter-for-vk'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="yandex_api_key"><?php esc_html_e('API Key', 'milyutin-code-article-reposter-for-vk'); ?></label></th>
                            <td>
                                <input type="password" id="yandex_api_key" name="vkaire_settings[yandex_api_key]" value="<?php echo esc_attr($settings['yandex_api_key']); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('API-ключ из консоли Yandex Cloud', 'milyutin-code-article-reposter-for-vk'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="yandex_folder_id"><?php esc_html_e('ID папки', 'milyutin-code-article-reposter-for-vk'); ?></label></th>
                            <td>
                                <input type="text" id="yandex_folder_id" name="vkaire_settings[yandex_folder_id]" value="<?php echo esc_attr($settings['yandex_folder_id']); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('ID папки из консоли Yandex Cloud', 'milyutin-code-article-reposter-for-vk'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="summary_max_tokens"><?php esc_html_e('Максимальная длина краткого изложения (в токенах)', 'milyutin-code-article-reposter-for-vk'); ?></label></th>
                            <td>
                                <input type="number" id="summary_max_tokens" name="vkaire_settings[summary_max_tokens]" value="<?php echo esc_attr($settings['summary_max_tokens']); ?>" min="50" max="2000" step="10">
                                <p class="description"><?php esc_html_e('Рекомендуется: 300-800 токенов', 'milyutin-code-article-reposter-for-vk'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="summary_temperature"><?php esc_html_e('Температура (креативность)', 'milyutin-code-article-reposter-for-vk'); ?></label></th>
                            <td>
                                <input type="number" id="summary_temperature" name="vkaire_settings[summary_temperature]" value="<?php echo esc_attr($settings['summary_temperature']); ?>" min="0" max="1" step="0.05">
                                <p class="description"><?php esc_html_e('0,2–0,4 — для более предсказуемых результатов', 'milyutin-code-article-reposter-for-vk'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="vkaire-settings-section">
                    <h2><?php esc_html_e('Типы публикаций и публикация', 'milyutin-code-article-reposter-for-vk'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label><?php esc_html_e('Типы публикаций', 'milyutin-code-article-reposter-for-vk'); ?></label></th>
                            <td>
                                <?php foreach ($post_types as $post_type) : ?>
                                    <label>
                                        <input type="checkbox" name="vkaire_settings[post_types][]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $settings['post_types'])); ?>>
                                        <?php echo esc_html($post_type->label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="auto_publish"><?php esc_html_e('Автопубликация', 'milyutin-code-article-reposter-for-vk'); ?></label></th>
                            <td>
                                <input type="checkbox" id="auto_publish" name="vkaire_settings[auto_publish]" value="1" <?php checked(1, $settings['auto_publish']); ?>>
                                <span class="description"><?php esc_html_e('Публиковать автоматически при создании записи', 'milyutin-code-article-reposter-for-vk'); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php submit_button(esc_html__('Сохранить настройки', 'milyutin-code-article-reposter-for-vk')); ?>
            </form>
            <hr>
            <div style="margin-top:20px;padding:15px;background:#f1f1f1;border-left:4px solid #2271b1;">
                <h3><?php esc_html_e('Как получить ключ доступа сообщества', 'milyutin-code-article-reposter-for-vk'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Откройте страницу вашего сообщества ВКонтакте и перейдите в раздел «Управление» → «Работа с API».', 'milyutin-code-article-reposter-for-vk'); ?></li>
                    <li><?php esc_html_e('Нажмите кнопку «Создать ключ».', 'milyutin-code-article-reposter-for-vk'); ?></li>
                    <li><?php esc_html_e('Отметьте необходимые права доступа: «Управление сообществом», «Фотографии», «Файлы», «Истории», «Стена».', 'milyutin-code-article-reposter-for-vk'); ?></li>
                    <li><?php esc_html_e('Подтвердите создание ключа и скопируйте его.', 'milyutin-code-article-reposter-for-vk'); ?></li>
                    <li><?php esc_html_e('Вставьте полученный ключ в поле «Ключ доступа сообщества» выше и сохраните настройки.', 'milyutin-code-article-reposter-for-vk'); ?></li>
                </ol>
                <p><strong><?php esc_html_e('Важно:', 'milyutin-code-article-reposter-for-vk'); ?></strong> <?php esc_html_e('Из-за ограничений API ВКонтакте, введённых с 7 сентября 2026 года, плагин теперь публикует только текстовые записи со ссылкой. Автоматическая загрузка изображений временно недоступна.', 'milyutin-code-article-reposter-for-vk'); ?></p>
                <p><strong><?php esc_html_e('Разработчик:', 'milyutin-code-article-reposter-for-vk'); ?></strong> <a href="https://milyutin-codes.ru/" target="_blank">Милютин Константин</a></p>
                <p><strong><?php esc_html_e('Сайт:', 'milyutin-code-article-reposter-for-vk'); ?></strong> <a href="https://milyutin-codes.ru/" target="_blank">milyutin-codes.ru</a></p>
                <p><strong><?php esc_html_e('Группа ВК:', 'milyutin-code-article-reposter-for-vk'); ?></strong> <a href="https://vk.com/milyutin_codes" target="_blank">@milyutin_codes</a></p>
            </div>
        </div>
        <?php
    }
}