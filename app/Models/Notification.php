<?php

class Notification
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, type, title, body, data)
             VALUES (:user_id, :type, :title, :body, :data)"
        );
        $stmt->execute([
            'user_id' => $data['user_id'],
            'type'    => $data['type'],
            'title'   => $data['title'],
            'body'    => $data['body'],
            'data'    => isset($data['data']) ? json_encode($data['data']) : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        $unread = $this->countUnread($userId);

        $page   = isset($filters['page'])  ? max(1, (int) $filters['page'])  : 1;
        $limit  = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 20;
        $offset = ($page - 1) * $limit;

        $where  = "WHERE user_id = ?";
        $params = [$userId];

        if (isset($filters['is_read'])) {
            $where   .= " AND is_read = ?";
            $params[] = (int) $filters['is_read'];
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM notifications $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        $stmt = $this->db->prepare(
            "SELECT id, type, title, body, data, is_read, created_at
             FROM notifications $where
             ORDER BY created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['is_read'] = (bool) $row['is_read'];
            $row['data']    = $row['data'] ? json_decode($row['data'], true) : null;
        }

        return [
            'data' => $rows,
            'meta' => [
                'unread_count'  => $unread,
                'current_page'  => $page,
                'per_page'      => $limit,
                'total_records' => $total,
                'last_page'     => $total > 0 ? (int) ceil($total / $limit) : 1,
            ],
        ];
    }

    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE"
        );
        return $stmt->execute([$userId]);
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = FALSE"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    }
}
