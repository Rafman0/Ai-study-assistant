<?php
/**
 * Shared AI client
 * Single server-side integration point for the configured Gemini/OpenAI-compatible API.
 * The API key never leaves the server.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Recursively coerce every string into valid UTF-8 so the JSON payload we send
 * to the AI provider can never fail to encode (PDF text extraction and free-form
 * user input can legally contain bytes that are not valid UTF-8).
 * @param mixed $value
 * @return mixed
 */
function ai_sanitize_utf8($value) {
    if (is_string($value)) {
        if ($value === '' || (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8'))) {
            return $value;
        }
        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($clean !== false && $clean !== '') {
                return $clean;
            }
        }
        // Minimal fallback: keep printable characters, drop remaining control bytes
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    }
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = ai_sanitize_utf8($item);
        }
    }
    return $value;
}

/**
 * Call the configured AI chat-completions endpoint
 * @param array $data OpenAI-compatible request payload (model, messages, ...)
 * @param int $timeout Seconds to wait for the provider
 * @return string|null Response content, or null on failure (already logged)
 */
function call_ai_api($data, $timeout = 30) {
    try {
        $data = ai_sanitize_utf8($data);
        $body = json_encode($data);
        if (!is_string($body) || $body === '') {
            error_log("AI Tutor API error: failed to encode UTF-8 payload");
            return null;
        }

        // One transparent retry for transient provider errors (429 rate limit / 503 overloaded)
        $attempts = 0;
        retry:
        $ch = curl_init(AI_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . AI_API_KEY,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        // Windows dev stacks often lack a system CA bundle; use the bundled one when present
        if (defined('CA_BUNDLE_PATH') && CA_BUNDLE_PATH && file_exists(CA_BUNDLE_PATH)) {
            curl_setopt($ch, CURLOPT_CAINFO, CA_BUNDLE_PATH);
        }

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (($http_code === 429 || $http_code === 503) && $attempts < 2) {
            sleep(2);
            goto retry;
        }

        if ($http_code !== 200 || $result === false) {
            throw new Exception('AI API request failed');
        }

        $api_response = json_decode($result, true);
        $content = $api_response['choices'][0]['message']['content'] ?? '';

        return $content !== '' ? $content : null;
    } catch (Exception $e) {
        error_log("AI Tutor API error: " . $e->getMessage());
        return null;
    }
}
