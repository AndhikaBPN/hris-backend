<?php

class AuthMiddleware
{
    /**
     * Validasi JWT dari Authorization header.
     * Return payload jika valid, atau kirim 401 dan exit.
     */
    public function handle(): array
    {
        $headers = getallheaders();
        $auth    = $headers['Authorization'] ?? '';

        if (!str_starts_with($auth, 'Bearer ')) {
            self::unauthorized('Token not provided');
        }

        $token   = substr($auth, 7);
        $payload = JwtHelper::verify($token);

        if (!$payload) {
            self::unauthorized('Invalid or expired token');
        }

        return $payload;
    }

    private static function unauthorized(string $message): never
    {
        ResponseHelper::error($message, 401);
        exit;
    }
}
