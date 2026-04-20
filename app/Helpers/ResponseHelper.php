<?php

class ResponseHelper
{
    public static function success(mixed $data = null, string $message = 'OK', int $code = 200, ?array $meta = null): void
    {
        $body = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];
        
        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        self::json($body, $code);
    }

    public static function error(string $message = 'Error', int $code = 400, mixed $errors = null): void
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        self::json($body, $code);
    }

    private static function json(array $body, int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
