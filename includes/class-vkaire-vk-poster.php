<?php
if (!defined('ABSPATH')) {
    exit;
}

class VKAIRE_VK_Poster
{
    public static function post_to_vk($post_id, $settings)
    {
        vkaire_log('Starting publication of post ID ' . $post_id . ' to VK', 'INFO');
        $token = trim($settings['vk_access_token'] ?? '');
        $group_raw = trim($settings['vk_group_id'] ?? '');
        if (empty($token) || empty($group_raw)) {
            vkaire_log('Missing VK credentials (token or group ID)', 'ERROR');
            return array('success' => false, 'message' => 'Missing VK credentials');
        }
        $owner_id = self::get_group_owner_id($token, $group_raw);
        if (empty($owner_id)) {
            vkaire_log('Could not determine owner_id for group: ' . $group_raw, 'ERROR');
            return array('success' => false, 'message' => 'Could not determine group owner_id');
        }
        vkaire_log('Group owner_id: ' . $owner_id, 'DEBUG');
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            vkaire_log('Post not found: ID ' . $post_id, 'ERROR');
            return array('success' => false, 'message' => 'Post not found');
        }
        $title = wp_strip_all_tags(get_the_title($post));
        if (empty($title)) {
            $title = __('Untitled', 'milyutin-code-article-reposter-for-vk');
        }
        $link = get_permalink($post_id);
        if (empty($link)) {
            vkaire_log('Empty permalink for post ID ' . $post_id, 'ERROR');
            return array('success' => false, 'message' => 'Empty permalink');
        }
        $summary = get_post_meta($post_id, '_vkaire_summary', true);
        if (empty($summary)) {
            $summary = self::get_excerpt($post);
            vkaire_log('Using standard excerpt', 'DEBUG');
        } else {
            vkaire_log('Using generated summary', 'DEBUG');
        }
        $message = $title . "\n\n";
        if (!empty($summary)) {
            $message .= $summary . "\n\n";
        }
        $message .= '👉 Читать статью: ' . $link;
        vkaire_log('Message formed (length: ' . mb_strlen($message) . ' chars)', 'DEBUG');

        $body = array(
            'access_token' => $token,
            'owner_id' => $owner_id,
            'from_group' => 1,
            'message' => $message,
            'v' => VKAIRE_VK_API_VERSION
        );

        $url = 'https://api.vk.com/method/wall.post';
        vkaire_log('Sending request to VK API', 'DEBUG');
        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'body' => $body
        ));
        if (is_wp_error($response)) {
            vkaire_log('HTTP error: ' . $response->get_error_message(), 'ERROR');
            return array('success' => false, 'message' => 'HTTP error: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            vkaire_log('HTTP error ' . $code . ': ' . mb_substr($raw_body, 0, 300), 'ERROR');
            return array('success' => false, 'message' => 'HTTP ' . $code . ': ' . mb_substr($raw_body, 0, 300));
        }
        $decoded = json_decode($raw_body, true);
        if (!is_array($decoded)) {
            vkaire_log('Failed to parse JSON response', 'ERROR');
            return array('success' => false, 'message' => 'Invalid JSON response');
        }
        if (!empty($decoded['error'])) {
            $err_msg = isset($decoded['error']['error_msg']) ? (string) $decoded['error']['error_msg'] : 'Unknown API error';
            vkaire_log('VK API error: ' . $err_msg, 'ERROR');
            return array('success' => false, 'message' => 'VK API error: ' . $err_msg);
        }
        $vk_post_id = $decoded['response']['post_id'] ?? null;
        if ($vk_post_id !== null) {
            update_post_meta($post_id, '_vkaire_published', $vk_post_id);
            vkaire_log('Successfully published to VK! Post ID: ' . $vk_post_id, 'INFO');
            return array('success' => true, 'message' => 'Published! ID: ' . $vk_post_id, 'post_id' => $vk_post_id);
        }
        vkaire_log('Unknown response from VK: ' . print_r($decoded, true), 'ERROR');
        return array('success' => false, 'message' => 'Unknown response from VK');
    }

    /**
     * Обновляет существующий пост в ВК (wall.edit)
     */
    public static function update_vk_post($post_id, $vk_post_id, $settings)
    {
        vkaire_log('Начинаем обновление поста ВК ID ' . $vk_post_id . ' (WP ID ' . $post_id . ')', 'INFO');

        $token = trim($settings['vk_access_token'] ?? '');
        $group_raw = trim($settings['vk_group_id'] ?? '');
        if (empty($token) || empty($group_raw)) {
            vkaire_log('Не заполнены данные ВК', 'ERROR');
            return array('success' => false, 'message' => 'Missing VK credentials');
        }

        $owner_id = self::get_group_owner_id($token, $group_raw);
        if (empty($owner_id)) {
            vkaire_log('Не удалось определить owner_id', 'ERROR');
            return array('success' => false, 'message' => 'Could not determine group owner_id');
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            vkaire_log('Пост не найден', 'ERROR');
            return array('success' => false, 'message' => 'Post not found');
        }

        $title = wp_strip_all_tags(get_the_title($post));
        if (empty($title)) {
            $title = __('Untitled', 'milyutin-code-article-reposter-for-vk');
        }
        $link = get_permalink($post_id);
        if (empty($link)) {
            vkaire_log('Пустая ссылка для поста ID ' . $post_id, 'ERROR');
            return array('success' => false, 'message' => 'Empty permalink');
        }

        $summary = get_post_meta($post_id, '_vkaire_summary', true);
        if (empty($summary)) {
            $summary = self::get_excerpt($post);
            vkaire_log('Используется стандартный отрывок для обновления', 'DEBUG');
        } else {
            vkaire_log('Используется сгенерированный обзор для обновления', 'DEBUG');
        }

        $message = $title . "\n\n";
        if (!empty($summary)) {
            $message .= $summary . "\n\n";
        }
        $message .= '👉 Читать статью: ' . $link;

        $body = array(
            'access_token' => $token,
            'owner_id' => $owner_id,
            'post_id' => $vk_post_id,
            'message' => $message,
            'v' => VKAIRE_VK_API_VERSION
        );

        vkaire_log('Отправка запроса wall.edit для обновления поста ' . $vk_post_id, 'DEBUG');
        $response = wp_remote_post('https://api.vk.com/method/wall.edit', array(
            'timeout' => 30,
            'body' => $body
        ));

        if (is_wp_error($response)) {
            vkaire_log('HTTP ошибка при wall.edit: ' . $response->get_error_message(), 'ERROR');
            return array('success' => false, 'message' => 'HTTP error: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            vkaire_log('HTTP ошибка ' . $code . ': ' . mb_substr($raw_body, 0, 300), 'ERROR');
            return array('success' => false, 'message' => 'HTTP ' . $code . ': ' . mb_substr($raw_body, 0, 300));
        }

        $decoded = json_decode($raw_body, true);
        if (!is_array($decoded)) {
            vkaire_log('Не удалось разобрать JSON ответа wall.edit', 'ERROR');
            return array('success' => false, 'message' => 'Invalid JSON response');
        }

        if (!empty($decoded['error'])) {
            $err_msg = isset($decoded['error']['error_msg']) ? (string) $decoded['error']['error_msg'] : 'Unknown API error';
            vkaire_log('Ошибка VK API при wall.edit: ' . $err_msg, 'ERROR');
            return array('success' => false, 'message' => 'VK API error: ' . $err_msg);
        }

        vkaire_log('Пост ВК ID ' . $vk_post_id . ' успешно обновлён', 'INFO');
        return array('success' => true, 'message' => 'Обновлено!');
    }

    private static function get_group_owner_id($token, $group_id)
    {
        vkaire_log('Attempting to determine owner_id for: ' . $group_id, 'DEBUG');
        if (preg_match('/^-?\d+$/', $group_id)) {
            $n = (int) $group_id;
            if ($n !== 0) {
                $result = $n < 0 ? (string) $n : '-' . (string) $n;
                vkaire_log('Using numeric ID: ' . $result, 'DEBUG');
                return $result;
            }
        }
        $response = wp_remote_post('https://api.vk.com/method/groups.getById', array(
            'body' => array(
                'access_token' => $token,
                'group_id' => $group_id,
                'v' => VKAIRE_VK_API_VERSION
            ),
            'timeout' => 15
        ));
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data['response'][0]['id'])) {
                $group_id_num = $data['response'][0]['id'];
                vkaire_log('owner_id obtained via groups.getById: -' . $group_id_num, 'DEBUG');
                return '-' . $group_id_num;
            }
        }
        $digits = preg_replace('/\D+/', '', $group_id);
        if (!empty($digits) && (int) $digits !== 0) {
            vkaire_log('Extracted numeric ID: -' . $digits, 'DEBUG');
            return '-' . (string) (int) $digits;
        }
        vkaire_log('Could not determine owner_id', 'ERROR');
        return null;
    }

    private static function get_excerpt($post)
    {
        if (!empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }
        $content = wp_strip_all_tags($post->post_content);
        $content = preg_replace('/\[[^\]]+\]/', '', $content);
        $content = preg_replace('/\s+/', ' ', $content);
        return wp_trim_words($content, 30, '...');
    }
}