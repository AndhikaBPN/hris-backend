<?php

class TokenBlacklist
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function add(string $token): bool
    {
        $stmt = $this->db->prepare("INSERT INTO token_blacklists (token) VALUES (:token)");
        return $stmt->execute(['token' => $token]);
    }

    public function isBlacklisted(string $token): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM token_blacklists WHERE token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        return (bool) $stmt->fetchColumn();
    }
}
