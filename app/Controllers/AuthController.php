<?php

class AuthController
{
    private AuthService $service;

    public function __construct(PDO $db)
    {
        $this->service = new AuthService($db);
    }

    // POST /api/login
    public function login(): void
    {
        $body = $this->json();

        $email = trim($body['email'] ?? '');
        $password = trim($body['password'] ?? '');

        if (!$email || !$password) {
            ResponseHelper::error('Email and password are required', 422);
            return;
        }

        try {
            $result = $this->service->login($email, $password);
            ResponseHelper::success($result, 'Login successful');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 401);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    // POST /api/logout
    public function logout(): void
    {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? '';

        if (!str_starts_with($auth, 'Bearer ')) {
            ResponseHelper::error('Invalid or missing token', 401);
            return;
        }

        $token = substr($auth, 7);
        $this->service->logout($token);
        ResponseHelper::success(null, 'Logout successful');
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
