<?php

class AttendanceLog
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int|false
    {
        $sql = "INSERT INTO attendance_logs (attendance_id, user_id, session, message) 
                VALUES (:attendance_id, :user_id, :session, :message)";
                
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'attendance_id' => $data['attendance_id'] ?? null,
            'user_id'       => $data['user_id'] ?? null,
            'session'       => $data['session'] ?? null,
            'message'       => $data['message']
        ]);
        
        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function getByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM attendance_logs WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
