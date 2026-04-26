<?php

class UserShiftConfig
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByUserId(int $userId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM user_shift_configs WHERE user_id = :user_id LIMIT 1");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function upsert(int $userId, array $data): bool
    {
        $sql = "INSERT INTO user_shift_configs (user_id, shift_start_date, shift_start_index)
                VALUES (:user_id, :shift_start_date, :shift_start_index)
                ON DUPLICATE KEY UPDATE
                    shift_start_date = VALUES(shift_start_date),
                    shift_start_index = VALUES(shift_start_index)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id'           => $userId,
            'shift_start_date'  => $data['shift_start_date'],
            'shift_start_index' => $data['shift_start_index']
        ]);
    }

    public function delete(int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM user_shift_configs WHERE user_id = :user_id");
        return $stmt->execute(['user_id' => $userId]);
    }
}
