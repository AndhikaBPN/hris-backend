<?php

class Attendance
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int|false
    {
        $sql = "INSERT INTO attendance (user_id, shift_schedule_id, session, face_image, latitude, longitude, distance_to_office, status, check_in_time) 
                VALUES (:user_id, :shift_schedule_id, :session, :face_image, :latitude, :longitude, :distance_to_office, :status, :check_in_time)";
                
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'user_id'            => $data['user_id'],
            'shift_schedule_id'  => $data['shift_schedule_id'],
            'session'            => $data['session'],
            'face_image'         => $data['face_image'] ?? null,
            'latitude'           => $data['latitude'] ?? null,
            'longitude'          => $data['longitude'] ?? null,
            'distance_to_office' => $data['distance_to_office'] ?? null,
            'status'             => $data['status'],
            'check_in_time'      => $data['check_in_time'] ?? date('Y-m-d H:i:s')
        ]);
        
        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        $sql = "SELECT a.*, ss.date as shift_date
                FROM attendance a
                JOIN shift_schedules ss ON a.shift_schedule_id = ss.id
                WHERE a.user_id = :user_id";
        
        $params = ['user_id' => $userId];

        if (!empty($filters['date'])) {
            $sql .= " AND ss.date = :date";
            $params['date'] = $filters['date'];
        }

        $sql .= " ORDER BY a.check_in_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll(array $filters = []): array
    {
        $sql = "SELECT a.*, ss.date as shift_date, u.name as user_name 
                FROM attendance a
                JOIN shift_schedules ss ON a.shift_schedule_id = ss.id
                JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['date'])) {
            $sql .= " AND ss.date = :date";
            $params['date'] = $filters['date'];
        }

        $sql .= " ORDER BY a.check_in_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function todayByUserId(int $userId): array
    {
        return $this->getByUserId($userId, ['date' => date('Y-m-d')]);
    }
}
