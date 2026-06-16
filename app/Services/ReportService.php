<?php

class ReportService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ----------------------------------------------------------------
    // Attendance Summary Report
    // ----------------------------------------------------------------

    public function attendanceReport(array $filters, array $authUser): array
    {
        $this->applyRoleScope($filters, $authUser);

        $year  = (int) ($filters['year']  ?? date('Y'));
        $month = (int) ($filters['month'] ?? date('n'));

        $noPaginate = !empty($filters['no_paginate']);
        [$page, $limit, $offset] = $this->paginate($filters);

        $where  = 'WHERE u.is_active = 1';
        $params = [':year' => $year, ':month' => $month, ':year2' => $year, ':month2' => $month];

        $countWhere  = 'WHERE u.is_active = 1';
        $countParams = [];

        if (!empty($filters['user_id'])) {
            $where .= ' AND u.id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
            $countWhere .= ' AND u.id = :user_id';
            $countParams[':user_id'] = (int) $filters['user_id'];
        } elseif (!empty($filters['manager_id'])) {
            $where .= ' AND u.manager_id = :manager_id';
            $params[':manager_id'] = (int) $filters['manager_id'];
            $countWhere .= ' AND u.manager_id = :manager_id';
            $countParams[':manager_id'] = (int) $filters['manager_id'];
        }

        $total = 0;
        if (!$noPaginate) {
            $countStmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT u.id) AS cnt FROM users u JOIN role r ON r.id = u.role_id $countWhere"
            );
            $countStmt->execute($countParams);
            $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        }

        $sql = "
            SELECT
                u.id                                        AS user_id,
                u.name,
                r.role,
                COUNT(DISTINCT ss.id)                       AS total_days,
                COUNT(DISTINCT CASE WHEN a.session = 1 AND a.status IN ('valid','late') THEN ss.id END) AS present,
                COUNT(DISTINCT CASE WHEN a.session = 1 AND a.status = 'late'           THEN ss.id END) AS late,
                COUNT(DISTINCT CASE WHEN a.session = 1                                  THEN ss.id END) AS session_1_count,
                COUNT(DISTINCT CASE WHEN a.session = 2                                  THEN ss.id END) AS session_2_count,
                COALESCE(al.invalid_count, 0)               AS invalid_records
            FROM users u
            JOIN role r ON r.id = u.role_id
            LEFT JOIN shift_schedules ss
                ON ss.user_id = u.id
                AND YEAR(ss.date) = :year
                AND MONTH(ss.date) = :month
                AND ss.is_day_off = 0
            LEFT JOIN attendance a ON a.shift_schedule_id = ss.id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS invalid_count
                FROM attendance_logs
                WHERE YEAR(created_at) = :year2 AND MONTH(created_at) = :month2
                GROUP BY user_id
            ) al ON al.user_id = u.id
            {$where}
            GROUP BY u.id, u.name, r.role
            ORDER BY u.name
        ";

        if (!$noPaginate) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $days = (int) $row['total_days'];
            $row['absent']               = $days - (int) $row['present'];
            $row['session_1_completion'] = $days > 0 ? round((int) $row['session_1_count'] / $days * 100, 1) : 0;
            $row['session_2_completion'] = $days > 0 ? round((int) $row['session_2_count'] / $days * 100, 1) : 0;
            unset($row['session_1_count'], $row['session_2_count']);
        }
        unset($row);

        $meta = $noPaginate ? null : $this->paginationMeta($total, $page, $limit);
        return $this->wrap('attendance', ['year' => $year, 'month' => $month], $rows, $meta);
    }

    // ----------------------------------------------------------------
    // Leave Utilization Report
    // ----------------------------------------------------------------

    public function leaveReport(array $filters, array $authUser): array
    {
        $this->applyRoleScope($filters, $authUser);

        $year = (int) ($filters['year'] ?? date('Y'));

        $noPaginate = !empty($filters['no_paginate']);
        [$page, $limit, $offset] = $this->paginate($filters);

        $where  = 'WHERE u.is_active = 1';
        $params = [
            ':year'  => $year,
            ':year2' => $year,
            ':year3' => $year,
            ':year4' => $year,
            ':year5' => $year,
        ];

        $countWhere  = 'WHERE u.is_active = 1';
        $countParams = [];

        if (!empty($filters['user_id'])) {
            $where .= ' AND u.id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
            $countWhere .= ' AND u.id = :user_id';
            $countParams[':user_id'] = (int) $filters['user_id'];
        } elseif (!empty($filters['manager_id'])) {
            $where .= ' AND u.manager_id = :manager_id';
            $params[':manager_id'] = (int) $filters['manager_id'];
            $countWhere .= ' AND u.manager_id = :manager_id';
            $countParams[':manager_id'] = (int) $filters['manager_id'];
        }

        $total = 0;
        if (!$noPaginate) {
            $countStmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT u.id) AS cnt FROM users u JOIN role r ON r.id = u.role_id $countWhere"
            );
            $countStmt->execute($countParams);
            $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        }

        $sql = "
            SELECT
                u.id                                         AS user_id,
                u.name,
                r.role,
                COALESCE(lb.total_quota, 0)                  AS total_quota,
                COALESCE(lr_a.annual_used, 0)                AS annual_used,
                COALESCE(lr_s.sick_used, 0)                  AS sick_used,
                COALESCE(lr_p.pending_requests, 0)           AS pending_requests,
                COALESCE(lr_r.rejected_requests, 0)          AS rejected_requests
            FROM users u
            JOIN role r ON r.id = u.role_id
            LEFT JOIN (
                SELECT user_id, SUM(quota) AS total_quota
                FROM leave_balances
                WHERE year = :year
                GROUP BY user_id
            ) lb ON lb.user_id = u.id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS annual_used
                FROM leave_requests
                WHERE leave_type = 'annual' AND status = 'approved' AND YEAR(leave_date_from) = :year2
                GROUP BY user_id
            ) lr_a ON lr_a.user_id = u.id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS sick_used
                FROM leave_requests
                WHERE leave_type = 'sick' AND status = 'approved' AND YEAR(leave_date_from) = :year3
                GROUP BY user_id
            ) lr_s ON lr_s.user_id = u.id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS pending_requests
                FROM leave_requests
                WHERE status = 'pending' AND YEAR(leave_date_from) = :year4
                GROUP BY user_id
            ) lr_p ON lr_p.user_id = u.id
            LEFT JOIN (
                SELECT user_id, COUNT(*) AS rejected_requests
                FROM leave_requests
                WHERE status = 'rejected' AND YEAR(leave_date_from) = :year5
                GROUP BY user_id
            ) lr_r ON lr_r.user_id = u.id
            {$where}
            ORDER BY u.name
        ";

        if (!$noPaginate) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $totalUsed = (int) $row['annual_used'] + (int) $row['sick_used'];
            $row['remaining'] = max(0, (int) $row['total_quota'] - $totalUsed);
        }
        unset($row);

        $meta = $noPaginate ? null : $this->paginationMeta($total, $page, $limit);
        return $this->wrap('leave', ['year' => $year], $rows, $meta);
    }

    // ----------------------------------------------------------------
    // Employee Master Data Report
    // ----------------------------------------------------------------

    public function employeesReport(array $filters, array $authUser): array
    {
        $this->applyRoleScope($filters, $authUser);

        $noPaginate = !empty($filters['no_paginate']);
        [$page, $limit, $offset] = $this->paginate($filters);

        $where  = 'WHERE 1=1';
        $params = [];

        $countWhere  = 'WHERE 1=1';
        $countParams = [];

        if (!empty($filters['role'])) {
            $where .= ' AND r.role = :role';
            $params[':role'] = $filters['role'];
            $countWhere .= ' AND r.role = :role';
            $countParams[':role'] = $filters['role'];
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $statusVal = in_array($filters['status'], ['1', 'active', 'true']) ? 1 : 0;
            $where .= ' AND u.is_active = :is_active';
            $params[':is_active'] = $statusVal;
            $countWhere .= ' AND u.is_active = :is_active';
            $countParams[':is_active'] = $statusVal;
        }

        if (!empty($filters['manager_id'])) {
            $where .= ' AND u.manager_id = :manager_id';
            $params[':manager_id'] = (int) $filters['manager_id'];
            $countWhere .= ' AND u.manager_id = :manager_id';
            $countParams[':manager_id'] = (int) $filters['manager_id'];
        } elseif (!empty($filters['user_id'])) {
            $where .= ' AND u.id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
            $countWhere .= ' AND u.id = :user_id';
            $countParams[':user_id'] = (int) $filters['user_id'];
        }

        $total = 0;
        if (!$noPaginate) {
            $countStmt = $this->db->prepare(
                "SELECT COUNT(*) AS cnt FROM users u JOIN role r ON r.id = u.role_id $countWhere"
            );
            $countStmt->execute($countParams);
            $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        }

        $sql = "
            SELECT
                u.id                        AS user_id,
                u.name,
                u.email,
                u.phone,
                r.role,
                u.manager_id,
                m.name                      AS manager_name,
                u.team_id,
                u.is_active,
                u.created_at               AS join_date
            FROM users u
            JOIN role r ON r.id = u.role_id
            LEFT JOIN users m ON m.id = u.manager_id
            {$where}
            ORDER BY u.name
        ";

        if (!$noPaginate) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meta = $noPaginate ? null : $this->paginationMeta($total, $page, $limit);
        return $this->wrap('employees', [], $rows, $meta);
    }

    // ----------------------------------------------------------------
    // Shift Schedule Report
    // ----------------------------------------------------------------

    public function shiftsReport(array $filters, array $authUser): array
    {
        $this->applyRoleScope($filters, $authUser);

        $year  = (int) ($filters['year']  ?? date('Y'));
        $month = (int) ($filters['month'] ?? date('n'));

        $noPaginate = !empty($filters['no_paginate']);
        [$page, $limit, $offset] = $this->paginate($filters);

        $where  = 'WHERE YEAR(ss.date) = :year AND MONTH(ss.date) = :month AND u.is_active = 1';
        $params = [':year' => $year, ':month' => $month];

        if (!empty($filters['user_id'])) {
            $where .= ' AND u.id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        } elseif (!empty($filters['manager_id'])) {
            $where .= ' AND u.manager_id = :manager_id';
            $params[':manager_id'] = (int) $filters['manager_id'];
        }

        $total = 0;
        if (!$noPaginate) {
            $countStmt = $this->db->prepare(
                "SELECT COUNT(*) AS cnt
                 FROM shift_schedules ss
                 JOIN users u ON u.id = ss.user_id
                 JOIN role r ON r.id = u.role_id
                 $where"
            );
            $countStmt->execute($params);
            $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        }

        $sql = "
            SELECT
                u.id                                                AS user_id,
                u.name,
                ss.date,
                ss.is_day_off,
                ss.notes                                           AS override_reason,
                IF(ss.notes IS NOT NULL AND ss.notes != '', 1, 0)  AS is_override,
                s.name                                             AS shift_name,
                s.start_time,
                s.end_time
            FROM shift_schedules ss
            JOIN users u ON u.id = ss.user_id
            JOIN role r ON r.id = u.role_id
            LEFT JOIN shifts s ON s.id = ss.shift_id
            {$where}
            ORDER BY ss.date, u.name
        ";

        if (!$noPaginate) {
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meta = $noPaginate ? null : $this->paginationMeta($total, $page, $limit);
        return $this->wrap('shifts', ['year' => $year, 'month' => $month], $rows, $meta);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function applyRoleScope(array &$filters, array $authUser): void
    {
        $role = $authUser['role'] ?? '';
        if ($role === 'staff') {
            $filters['user_id'] = $authUser['id'];
            unset($filters['manager_id']);
        } elseif ($role === 'team_leader') {
            $filters['manager_id'] = $authUser['id'];
            unset($filters['user_id']);
        }
    }

    private function paginate(array $filters): array
    {
        $page   = isset($filters['page'])  ? max(1, (int) $filters['page'])  : 1;
        $limit  = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 25;
        $offset = ($page - 1) * $limit;
        return [$page, $limit, $offset];
    }

    private function paginationMeta(int $total, int $page, int $limit): array
    {
        return [
            'current_page'  => $page,
            'per_page'      => $limit,
            'total_records' => $total,
            'last_page'     => $total > 0 ? (int) ceil($total / $limit) : 1,
        ];
    }

    private function wrap(string $type, array $filtersMeta, array $data, ?array $meta = null): array
    {
        $result = [
            'report_type'  => $type,
            'generated_at' => date('Y-m-d H:i:s'),
            'filters'      => $filtersMeta,
            'record_count' => count($data),
            'data'         => $data,
        ];
        if ($meta !== null) {
            $result['meta'] = $meta;
        }
        return $result;
    }
}
