<?php

class UserService
{
    private User $userModel;
    private OtpService $otpService;

    public function __construct(PDO $db)
    {
        $this->userModel = new User($db);
        $this->otpService = new OtpService($db);
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
            unset(
                $user['password'],
                $user['photo_profile'],
                $user['role_id'],
                $user['manager_id'],
                $user['manager_name'],
                $user['team_id'],
                $user['team_name'],
                $user['birth_date']
            );
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
    public function create(array $data, ?array $photoFile = null): int
    {
        if (empty($data['email']) || empty($data['password']) || empty($data['role_id'])) {
            throw new \InvalidArgumentException('Incomplete data (email, password, role)');
        }

        if (empty($data['gender']) || empty($data['phone']) || empty($data['address'])) {
            throw new \InvalidArgumentException('Incomplete data (gender, phone, address)');
        }

        $validGenders = ['male', 'female'];
        if (!in_array($data['gender'], $validGenders, true)) {
            throw new \InvalidArgumentException('Gender must be male or female');
        }

        $validReligions = ['Islam', 'Kristen', 'Katolik', 'Buddha', 'Hindu', 'Konghucu'];
        if (!empty($data['religion']) && !in_array($data['religion'], $validReligions, true)) {
            throw new \InvalidArgumentException('Invalid religion value');
        }

        if ($this->userModel->findByEmail($data['email'])) {
            throw new \InvalidArgumentException('Email is already in use');
        }

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);

        if ($photoFile && $photoFile['error'] === UPLOAD_ERR_OK) {
            $data['photo_profile'] = $this->savePhoto($photoFile);
        }

        $id = $this->userModel->create($data);
        if (!$id) {
            throw new \RuntimeException('Failed to create user');
        }

        $this->otpService->sendWelcomeOtp($data['email'], $data['name'] ?? '');

        return $id;
    }

    /** Update data user (tanpa password). */
    public function update(int $id, array $data, ?array $photoFile = null): bool
    {
        $user = $this->userModel->findById($id);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        unset($data['password']);

        if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female'], true)) {
            throw new \InvalidArgumentException('Gender must be male or female');
        }

        $validReligions = ['Islam', 'Kristen', 'Katolik', 'Buddha', 'Hindu', 'Konghucu'];
        if (!empty($data['religion']) && !in_array($data['religion'], $validReligions, true)) {
            throw new \InvalidArgumentException('Invalid religion value');
        }

        if ($photoFile && $photoFile['error'] === UPLOAD_ERR_OK) {
            if (!empty($user['photo_profile']) && file_exists($user['photo_profile'])) {
                unlink($user['photo_profile']);
            }
            $data['photo_profile'] = $this->savePhoto($photoFile);
        }

        $ok = $this->userModel->update($id, $data);
        if (!$ok) {
            throw new \RuntimeException('Failed to update user');
            // throw new ErrorException($ok);
        }
        return true;
    }

    private function savePhoto(array $file): string
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed, true)) {
            throw new \InvalidArgumentException('Photo must be JPEG, PNG, or WebP');
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('Photo size must not exceed 10MB');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('photo_', true) . '.' . $ext;
        $dest = __DIR__ . '/../../storage/profiles/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest) && !rename($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Failed to save photo');
        }

        return 'storage/profiles/' . $filename;
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
