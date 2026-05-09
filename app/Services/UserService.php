<?php

class UserService
{
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->userModel = new User($db);
    }

    /** Ambil detail user berdasarkan ID. */
    public function getById(int $id): array
    {
        $user = $this->userModel->findById($id);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        unset($user['password']);
        return $user;
    }

    /** Ambil semua user (dengan filter opsional). */
    public function getAll(array $filters = []): array
    {
        $result = $this->userModel->all($filters);

        // Clean out password hash from response data array
        $result['data'] = array_map(function ($user) {
            unset($user['password']);
            return $user;
        }, $result['data']);

        return $result;
    }

    /** Ambil daftar user yang ulang tahun bulan ini. */
    public function getBirthdaysThisMonth(): array
    {
        $month = (int) date('m');
        $users = $this->userModel->getBirthdays($month);

        return array_map(function ($user) {
            unset($user['password']);
            return $user;
        }, $users);
    }

    /** Buat user baru. Hash password sebelum simpan. */
    public function create(array $data): int
    {
        if (empty($data['email']) || empty($data['password']) || (empty($data['role_id']))) {
            throw new \InvalidArgumentException('Incomplete data (email, password, role)');
        }

        if ($this->userModel->findByEmail($data['email'])) {
            throw new \InvalidArgumentException('Email is already in use');
        }

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);

        $id = $this->userModel->create($data);
        if (!$id) {
            throw new \RuntimeException('Failed to create user');
        }

        return $id;
    }

    /** Update data user (tanpa password). */
    public function update(int $id, array $data): bool
    {
        $user = $this->userModel->findById($id);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        // Buang password jika tidak sengaja terkirim di body
        unset($data['password']);

        return $this->userModel->update($id, $data);
    }

    /** Soft delete (nonaktifkan) user. */
    public function delete(int $id): bool
    {
        $user = $this->userModel->findById($id);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        return $this->userModel->update($id, ['is_active' => 0]);
    }

    /** Aktifkan / nonaktifkan user. */
    public function setActive(int $id, bool $isActive): bool
    {
        return $this->userModel->update($id, ['is_active' => $isActive ? 1 : 0]);
    }

    /** Ambil jumlah user aktif. */
    public function getActiveCount(): int
    {
        return $this->userModel->countActive();
    }

    /** Ambil daftar team leader yang aktif. */
    public function getTeamLeaders(array $filters = []): array
    {
        $filters['role_id'] = 4;
        $filters['is_active'] = 1;

        $result = $this->userModel->all($filters);

        $result['data'] = array_map(function ($user) {
            unset($user['password'], $user['email'], $user['birth_date'], $user['manager_id'], $user['team_id']);
            return $user;
        }, $result['data']);

        return $result;
    }
}
