<?php

class LeaveBalance
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        $sql = "SELECT * FROM leave_balances WHERE user_id = :user_id";
        $params = ['user_id' => $userId];

        if (!empty($filters['year'])) {
            $sql .= " AND year = :year";
            $params['year'] = $filters['year'];
        }

        if (!empty($filters['month'])) {
            $sql .= " AND month = :month";
            $params['month'] = $filters['month'];
        }

        $sql .= " ORDER BY year DESC, month DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createOrUpdate(int $userId, int $year, int $month, int $quota = 1, int $used = 0): bool
    {
        $sql = "INSERT INTO leave_balances (user_id, year, month, quota, used) 
                VALUES (:user_id, :year, :month, :quota, :used)
                ON DUPLICATE KEY UPDATE 
                quota = VALUES(quota), used = VALUES(used)";
                
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'year'    => $year,
            'month'   => $month,
            'quota'   => $quota,
            'used'    => $used
        ]);
    }

    public function incrementUsed(int $userId, int $year, int $month): bool
    {
        $sql = "UPDATE leave_balances SET used = used + 1 WHERE user_id = :user_id AND year = :year AND month = :month";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'year'    => $year,
            'month'   => $month
        ]);
    }

    public function getYearlySummary(int $userId, int $year): array
    {
        $sql = "SELECT 
                    SUM(quota) as total_quota, 
                    SUM(used) as total_used,
                    (SUM(quota) - SUM(used)) as remaining_quota
                FROM leave_balances 
                WHERE user_id = :user_id AND year = :year";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'year'    => $year
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_quota'     => (int) ($result['total_quota'] ?? 0),
            'total_used'      => (int) ($result['total_used'] ?? 0),
            'remaining_quota' => (int) ($result['remaining_quota'] ?? 0)
        ];
    }
}