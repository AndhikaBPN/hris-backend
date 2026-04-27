<?php

class AuthService
{
    private User $userModel;
    private TokenBlacklist $tokenBlacklist;

    public function __construct(PDO $db)
    {
        $this->userModel = new User($db);
        $this->tokenBlacklist = new TokenBlacklist($db);
    }

    /**
     * Validasi kredensial user dan generate JWT token.
     * Throw Exception jika email/password salah atau user tidak aktif.
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            throw new \InvalidArgumentException('Invalid email or password');
        }

        if (!(bool) $user['is_active']) {
            throw new \InvalidArgumentException('Your account is not active');
        }

        if (!password_verify($password, $user['password'])) {
            throw new \InvalidArgumentException('Invalid email or password');
        }

        // Generate JWT token
        $payload = [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        $token = JwtHelper::generate($payload);

        return [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ]
        ];
    }

    /**
     * Invalidate token dengan memasukannya ke blacklist table.
     */
    public function logout(string $token): void
    {
        if ($token) {
            // Hindari duplikasi jika sudah di-blacklist
            if (!$this->tokenBlacklist->isBlacklisted($token)) {
                $this->tokenBlacklist->add($token);
            }
        }
    }
}
