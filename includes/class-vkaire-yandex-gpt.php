<?php
if (!defined('ABSPATH')) {
    exit;
}

class VKAIRE_Yandex_GPT
{
    public static function generate_summary($text, $settings)
    {
        vkaire_log('Начинаем генерацию обзора через Yandex GPT', 'DEBUG');
        $api_key = trim($settings['yandex_api_key'] ?? '');
        $folder_id = trim($settings['yandex_folder_id'] ?? '');
        $max_tokens = intval($settings['summary_max_tokens'] ?? 500);
        $temperature = floatval($settings['summary_temperature'] ?? 0.3);

        if (empty($api_key) || empty($folder_id)) {
            vkaire_log('API-ключ или Folder ID не заполнены', 'WARNING');
            return null;
        }

        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\[[^\]]+\]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = mb_substr(trim($text), 0, 3000);

        if (mb_strlen($text) < 100) {
            vkaire_log('Текст слишком короткий для обзора (' . mb_strlen($text) . ' символов)', 'WARNING');
            return null;
        }

        vkaire_log('Отправка запроса к Yandex GPT (текст: ' . mb_strlen($text) . ' символов)', 'DEBUG');

        $url = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';
        $model_uri = "gpt://$folder_id/yandexgpt-lite/latest";

        // ИСПРАВЛЕНО: промпт на русском языке
        $prompt = "Напиши краткий и ёмкий анонс этой новости в 2–3 предложениях. Не используй фразы 'В статье обсуждается', 'В статье рассказывается', 'Автор статьи', 'В материале'. Пиши как для соцсетей: кратко, живо и по делу. Просто передай суть, заинтересуй читателя.\n\n" . $text;

        $body = array(
            'modelUri' => $model_uri,
            'completionOptions' => array(
                'stream' => false,
                'temperature' => $temperature,
                'maxTokens' => $max_tokens
            ),
            'messages' => array(
                array('role' => 'user', 'text' => $prompt)
            )
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Api-Key ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            vkaire_log('Ошибка Yandex GPT: ' . $response->get_error_message(), 'ERROR');
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($data['result']['alternatives'][0]['message']['text'])) {
            $summary = $data['result']['alternatives'][0]['message']['text'];
            $summary = wp_trim_words($summary, 50, '…');
            $summary = self::clean_summary($summary);
            vkaire_log('Обзор сгенерирован (длина: ' . mb_strlen($summary) . ' символов)', 'INFO');
            return $summary;
        }

        if (!empty($data['error'])) {
            vkaire_log('Ошибка API Yandex GPT: ' . print_r($data['error'], true), 'ERROR');
            if (strpos($data['error']['message'] ?? '', 'unknown model') !== false) {
                vkaire_log('Пробуем альтернативную модель yandexgpt/latest', 'WARNING');
                return self::generate_summary_alt($text, $settings);
            }
        } else {
            vkaire_log('Неизвестный ответ от Yandex GPT: ' . print_r($data, true), 'ERROR');
        }

        return null;
    }

    private static function clean_summary($text)
    {
        $phrases = array(
            '/^(в статье|в материале|в публикации|автор статьи|в данной статье)\s*(обсуждается|рассказывается|описывается|рассматривается|говорится)\s*/i',
            '/^статья\s*(рассказывает|посвящена|описывает|рассматривает)\s*/i',
            '/^материал\s*(рассказывает|посвящен|описывает|рассматривает)\s*/i',
            '/^публикация\s*(рассказывает|посвящена|описывает|рассматривает)\s*/i',
        );
        $cleaned = $text;
        foreach ($phrases as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }
        $cleaned = preg_replace('/^о том, как\s*/i', 'Как ', $cleaned);
        $cleaned = preg_replace('/^том, что\s*/i', '', $cleaned);
        $cleaned = ltrim($cleaned);
        if (!empty($cleaned)) {
            $cleaned = mb_strtoupper(mb_substr($cleaned, 0, 1)) . mb_substr($cleaned, 1);
        }
        return $cleaned;
    }

    private static function generate_summary_alt($text, $settings)
    {
        vkaire_log('Попытка с альтернативной моделью', 'DEBUG');
        $api_key = trim($settings['yandex_api_key'] ?? '');
        $folder_id = trim($settings['yandex_folder_id'] ?? '');
        $max_tokens = intval($settings['summary_max_tokens'] ?? 500);
        $temperature = floatval($settings['summary_temperature'] ?? 0.3);

        $url = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';
        $model_uri = "gpt://$folder_id/yandexgpt/latest";

        // ИСПРАВЛЕНО: промпт на русском для альтернативной модели
        $prompt = "Кратко опиши суть этой новости в 2–3 предложениях. Не используй фразы 'В статье', 'В материале', 'Автор'. Пиши как для соцсетей – кратко и интересно.\n\n" . $text;

        $body = array(
            'modelUri' => $model_uri,
            'completionOptions' => array(
                'stream' => false,
                'temperature' => $temperature,
                'maxTokens' => $max_tokens
            ),
            'messages' => array(
                array('role' => 'user', 'text' => $prompt)
            )
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Api-Key ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            vkaire_log('Ошибка альтернативной модели: ' . $response->get_error_message(), 'ERROR');
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($data['result']['alternatives'][0]['message']['text'])) {
            $summary = $data['result']['alternatives'][0]['message']['text'];
            $summary = wp_trim_words($summary, 50, '…');
            $summary = self::clean_summary($summary);
            vkaire_log('Обзор сгенерирован альтернативной моделью (длина: ' . mb_strlen($summary) . ' символов)', 'INFO');
            return $summary;
        }

        if (!empty($data['error'])) {
            vkaire_log('Ошибка альтернативной модели API: ' . print_r($data['error'], true), 'ERROR');
        }

        return null;
    }
}