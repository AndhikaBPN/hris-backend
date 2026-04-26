<?php

class ShiftSchedule
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        $sql = "SELECT ss.*, s.name as shift_name, s.start_time, s.end_time, s.is_overnight 
                FROM shift_schedules ss
                LEFT JOIN shifts s ON ss.shift_id = s.id
                WHERE ss.user_id = :user_id";
        
        $params = ['user_id' => $userId];

        if (!empty($filters['date'])) {
            $sql .= " AND ss.date = :date";
            $params['date'] = $filters['date'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND ss.date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $filters['start_date'];
            $params['end_date']   = $filters['end_date'];
        }

        $sql .= " ORDER BY ss.date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUserAndDate(int $userId, string $date): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT ss.*, s.name as shift_name, s.start_time, s.end_time, s.is_overnight
             FROM shift_schedules ss
             LEFT JOIN shifts s ON ss.shift_id = s.id
             WHERE ss.user_id = :user_id AND ss.date = :date
             LIMIT 1"
        );
        $stmt->execute([
            'user_id' => $userId,
            'date'    => $date,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, array $data): bool
    {
        $sql = "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off, created_by, notes)
                VALUES (:user_id, :shift_id, :date, :is_day_off, :created_by, :notes)
                ON DUPLICATE KEY UPDATE 
                shift_id = VALUES(shift_id),
                is_day_off = VALUES(is_day_off),
                created_by = VALUES(created_by),
                notes = VALUES(notes)";
                
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id'    => $userId,
            'shift_id'   => $data['shift_id'] ?? null,
            'date'       => $data['date'],
            'is_day_off' => $data['is_day_off'] ?? 0,
            'created_by' => $data['created_by'] ?? null,
            'notes'      => $data['notes'] ?? null
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE shift_schedules SET shift_id = :shift_id, is_day_off = :is_day_off, notes = :notes WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'         => $id,
            'shift_id'   => $data['shift_id'] ?? null,
            'is_day_off' => $data['is_day_off'] ?? 0,
            'notes'      => $data['notes'] ?? null
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM shift_schedules WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
