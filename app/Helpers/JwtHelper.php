<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class JwtHelper
{
    private static function ttl(): int
    {
        return (int) ($_ENV['JWT_TTL'] ?? 86400);
    }

    private static function secret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? null;
        if (!$secret) {
            throw new \RuntimeException('JWT_SECRET is not configured');
        }
        return $secret;
    }

    // ----------------------------------------------------------------
    // Generate token
    // ----------------------------------------------------------------
    public static function generate(array $payload): string
    {
        $now = time();

        $claims = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + self::ttl(),
        ]);

        return JWT::encode($claims, self::secret(), 'HS256');
    }

    // ----------------------------------------------------------------
    // Verify token — return payload array atau false jika tidak valid
    // ----------------------------------------------------------------
    public static function verify(string $token): array|false
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            return false; // token kadaluarsa
        } catch (SignatureInvalidException $e) {
            return false; // signature salah
        } catch (BeforeValidException $e) {
            return false; // token belum berlaku
        } catch (\Exception $e) {
            return false; // error lainnya
        }
    }
}
