<?php
/**
 * CineNova — core HTTP/JSON response helpers.
 * Every backend/api/*.php entry point uses these so response shape
 * (success/data/message vs. error) is identical everywhere, per the
 * project's API design contract:
 *   { "success": true,  "data": ..., "message": "..." }
 *   { "success": false, "error": "..." }
 */
declare(strict_types=1);

final class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Success envelope: {"success": true, "data": ..., "message"?: ...} */
    public static function success($data = null, string $message = '', int $status = 200): void
    {
        $payload = ['success' => true];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        if ($message !== '') {
            $payload['message'] = $message;
        }
        self::json($payload, $status);
    }

    /** Error envelope: {"success": false, "error": "..."} */
    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::json(array_merge(['success' => false, 'error' => $message], $extra), $status);
    }
}
