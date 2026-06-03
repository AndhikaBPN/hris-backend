<?php
/**
 * CRON: Monthly Leave Quota Generator
 * Adds 1 quota per eligible user at the start of each month.
 * Schedule: 0 0 1 * * php /path/to/hris-backend/cron/monthly_leave_quota.php
 */

require_once __DIR__ . '/../bootstrap.php';

$db = (new Database())->getConnection();

require_once __DIR__ . '/../app/Models/LeaveBalance.php';
$leaveBalance = new LeaveBalance($db);

$year  = (int) date('Y');
$month = (int) date('n');

echo "[" . date('Y-m-d H:i:s') . "] Monthly leave quota job started. Target: {$year}-{$month}\n";

$stmt = $db->prepare("
    SELECT u.id, u.name, r.role
    FROM users u
    JOIN role r ON u.role_id = r.id
    WHERE r.role IN ('staff', 'team_leader', 'hrd_manager', 'technical_manager')
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = 0;
$skipped = 0;

foreach ($users as $user) {
    // createOrUpdate uses INSERT ... ON DUPLICATE KEY — safe to re-run
    $result = $leaveBalance->createOrUpdate((int) $user['id'], $year, $month, 1, 0);

    if ($result) {
        echo "[OK]   user_id={$user['id']} ({$user['name']}) — quota set for {$year}-{$month}\n";
        $success++;
    } else {
        echo "[FAIL] user_id={$user['id']} ({$user['name']}) — DB error\n";
        $skipped++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done. success={$success}, failed={$skipped}, total=" . count($users) . "\n";
