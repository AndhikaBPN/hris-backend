<?php

class LeaveRequest
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int|false
    {
        $sql = "INSERT INTO leave_requests (user_id, leave_date, leave_type, reason, doctor_letter, status) 
                VALUES (:user_id, :leave_date, :leave_type, :reason, :doctor_letter, :status)";
                
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'user_id'       => $data['user_id'],
            'leave_date'    => $data['leave_date'],
            'leave_type'    => $data['leave_type'] ?? 'annual',
            'reason'        => $data['reason'] ?? null,
            'doctor_letter' => $data['doctor_letter'] ?? null,
            'status'        => 'pending'
        ]);
        
        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM leave_requests WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM leave_requests WHERE user_id = :user_id ORDER BY leave_date DESC");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT lr.*, u.name as user_name FROM leave_requests lr JOIN users u ON lr.user_id = u.id ORDER BY lr.leave_date DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $id, string $status, int $approvedBy): bool
    {
        $sql = "UPDATE leave_requests 
                SET status = :status, approved_by = :approved_by, approved_at = NOW() 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'          => $id,
            'status'      => $status,
            'approved_by' => $approvedBy
        ]);
    }
}
