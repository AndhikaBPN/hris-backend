<?php
/**
 * Seeder for Leave Requests
 * Run this to test the Monthly Leave List API.
 * Usage: php seed_leave_requests.php
 */

require_once __DIR__ . '/bootstrap.php';

$db = new Database();
$conn = $db->getConnection();

// 1. Ensure we have some users
$stmt = $conn->query("SELECT id FROM users LIMIT 3");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "[!] No users found. Inserting a test user first...\n";
    // Get a role ID for staff
    $roleStmt = $conn->query("SELECT id FROM role WHERE role = 'staff' LIMIT 1");
    $roleId = $roleStmt->fetch(PDO::FETCH_ASSOC)['id'] ?? 1;

    $conn->prepare("INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)")
         ->execute(['Test Employee', 'test@hris.com', password_hash('password', PASSWORD_BCRYPT), $roleId]);
    
    $userId = $conn->lastInsertId();
    $users = [['id' => $userId]];
    echo "[OK] Test user created (ID: $userId).\n";
}

// 2. Prepare leave data for the CURRENT month
$currentYear = date('Y');
$currentMonth = date('m');
$adminId = $users[0]['id']; // Use the first user as approver

$leaveData = [
    [
        'user_id' => $users[0]['id'],
        'leave_date' => "$currentYear-$currentMonth-05",
        'leave_type' => 'annual',
        'reason' => 'Annual vacation leave',
        'status' => 'approved',
        'approved_by' => $adminId
    ],
    [
        'user_id' => $users[0]['id'],
        'leave_date' => "$currentYear-$currentMonth-12",
        'leave_type' => 'sick',
        'reason' => 'Feeling unwell, flu symptoms',
        'status' => 'approved',
        'approved_by' => $adminId
    ]
];

// Add data for second user if exists
if (isset($users[1])) {
    $leaveData[] = [
        'user_id' => $users[1]['id'],
        'leave_date' => "$currentYear-$currentMonth-15",
        'leave_type' => 'annual',
        'reason' => 'Personal matters',
        'status' => 'approved',
        'approved_by' => $adminId
    ];
}

// Add data for third user if exists
if (isset($users[2])) {
    $leaveData[] = [
        'user_id' => $users[2]['id'],
        'leave_date' => "$currentYear-$currentMonth-20",
        'leave_type' => 'leave_of_absence',
        'reason' => 'Emergency leave',
        'status' => 'approved',
        'approved_by' => $adminId
    ];
}

echo "--- Seeding Approved Leave Requests for $currentYear-$currentMonth ---\n";

foreach ($leaveData as $row) {
    try {
        $sql = "INSERT INTO leave_requests (user_id, leave_date, leave_type, reason, status, approved_by, approved_at) 
                VALUES (:user_id, :leave_date, :leave_type, :reason, :status, :approved_by, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute($row);
        echo "[OK] Seeded leave for User ID {$row['user_id']} on {$row['leave_date']}\n";
    } catch (PDOException $e) {
        echo "[FAIL] Error seeding: " . $e->getMessage() . "\n";
    }
}

echo "--- Seeding Complete ---\n";
echo "You can now test the API: GET /api/leave/monthly\n";
