<?php

class RoleMiddleware
{
    /**
     * Pastikan user memiliki salah satu role yang diizinkan.
     *
     * @param array  $payload  Payload JWT (dari AuthMiddleware)
     * @param string[] $roles  Role yang diizinkan, e.g. ['superadmin','admin']
     */
    public static function require(array $payload, array $roles): void
    {
        if (!in_array($payload['role'], $roles, true)) {
            ResponseHelper::error('Forbidden: insufficient role', 403);
            exit;
        }
    }
}
