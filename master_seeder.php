<?php
/**
 * Master Seeder for HRIS Attendance System
 * Populates all tables with meaningful test data.
 * Usage: php master_seeder.php
 */

require_once __DIR__ . '/bootstrap.php';

$db = new Database();
$conn = $db->getConnection();

echo "--- Starting Master Seeder ---\n";

try {
    // 1. Roles (Ensuring they exist)
    $roles = ['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff'];
    $roleIds = [];
    foreach ($roles as $r) {
        $stmt = $conn->prepare("INSERT IGNORE INTO `role` (role) VALUES (?)");
        $stmt->execute([$r]);

        $s = $conn->prepare("SELECT id FROM `role` WHERE role = ?");
        $s->execute([$r]);
        $roleIds[$r] = $s->fetch(PDO::FETCH_ASSOC)['id'];
    }
    echo "[OK] Roles verified.\n";

    // 2. Office Locations
    $conn->exec("INSERT IGNORE INTO office_locations (id, name, latitude, longitude, radius_meters) 
                 VALUES (1, 'Main Office', -6.2088, 106.8456, 50)");
    echo "[OK] Office location seeded.\n";

    // 3. Teams
    $teams = ['Alpha', 'Trojan', 'Eagle', 'Phoenix'];
    $teamIds = [];
    foreach ($teams as $t) {
        $stmt = $conn->prepare("INSERT IGNORE INTO team (team_name) VALUES (?)");
        $stmt->execute([$t]);

        $s = $conn->prepare("SELECT id FROM team WHERE team_name = ?");
        $s->execute([$t]);
        $teamIds[$t] = $s->fetch(PDO::FETCH_ASSOC)['id'];
    }
    echo "[OK] Teams seeded.\n";

    // 4. Users (Hierarchical)
    $password = password_hash('password', PASSWORD_BCRYPT);
    $usersToSeed = [
        ['name' => 'CEO Admin', 'email' => 'admin@hris.com', 'role' => 'c_level', 'manager' => null, 'team' => null, 'dob' => '1980-01-15'],
        ['name' => 'HR Manager', 'email' => 'hr@hris.com', 'role' => 'hrd_manager', 'manager' => 'admin@hris.com', 'team' => null, 'dob' => '1985-05-20'],
        ['name' => 'Tech Manager', 'email' => 'tech@hris.com', 'role' => 'technical_manager', 'manager' => 'admin@hris.com', 'team' => null, 'dob' => '1988-08-10'],
        ['name' => 'Team Lead Alpha', 'email' => 'lead.alpha@hris.com', 'role' => 'team_leader', 'manager' => 'tech@hris.com', 'team' => 'Alpha', 'dob' => date('Y') . '-' . date('m') . '-10'], // Birthday today/this month
        ['name' => 'Staff Backend', 'email' => 'staff.backend@hris.com', 'role' => 'staff', 'manager' => 'lead.alpha@hris.com', 'team' => 'Alpha', 'dob' => '1995-12-25'],
        ['name' => 'Staff Frontend', 'email' => 'staff.frontend@hris.com', 'role' => 'staff', 'manager' => 'lead.alpha@hris.com', 'team' => 'Alpha', 'dob' => date('Y') . '-' . date('m') . '-20'], // Birthday this month
    ];

    foreach ($usersToSeed as $u) {
        $managerId = null;
        if ($u['manager']) {
            $s = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $s->execute([$u['manager']]);
            $managerId = $s->fetch(PDO::FETCH_ASSOC)['id'] ?? null;
        }

        $teamId = $u['team'] ? ($teamIds[$u['team']] ?? null) : null;

        $stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password, role_id, manager_id, team_id, birth_date) 
                               VALUES (:name, :email, :password, :role_id, :manager_id, :team_id, :birth_date)");
        $stmt->execute([
            'name' => $u['name'],
            'email' => $u['email'],
            'password' => $password,
            'role_id' => $roleIds[$u['role']],
            'manager_id' => $managerId,
            'team_id' => $teamId,
            'birth_date' => $u['dob']
        ]);
    }
    echo "[OK] Users seeded.\n";

    // 5. Update Team Lead IDs
    $conn->exec("UPDATE team SET team_lead_id = (SELECT id FROM users WHERE email = 'lead.alpha@hris.com') WHERE team_name = 'Alpha'");
    echo "[OK] Team leads updated.\n";

    // 6. Shifts (Ensuring they exist)
    $shifts = [
        [1, 'Pagi', '06:00:00', '14:00:00', '09:30:00', '10:30:00', 0],
        [2, 'Siang', '14:00:00', '22:00:00', '17:30:00', '18:30:00', 0],
        [3, 'Malam', '22:00:00', '06:00:00', '01:30:00', '02:30:00', 1],
    ];
    foreach ($shifts as $sh) {
        $stmt = $conn->prepare("INSERT IGNORE INTO shifts (id, name, start_time, end_time, break_start, break_end, is_overnight) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($sh);
    }
    echo "[OK] Shifts verified.\n";

    // 7. Shift Schedules & Leave Balances
    $userIdsStmt = $conn->query("SELECT id FROM users WHERE role_id != " . $roleIds['c_level']);
    $userIds = $userIdsStmt->fetchAll(PDO::FETCH_COLUMN);

    $currentMonth = date('m');
    $currentYear = date('Y');
    $today = date('Y-m-d');

    foreach ($userIds as $uid) {
        // Leave Balance
        $conn->prepare("INSERT IGNORE INTO leave_balances (user_id, year, month, quota, used) VALUES (?, ?, ?, 1, 0)")
            ->execute([$uid, $currentYear, (int) $currentMonth]);

        // Shift Schedule for Today
        $conn->prepare("INSERT IGNORE INTO shift_schedules (user_id, shift_id, date, is_day_off) VALUES (?, 1, ?, 0)")
            ->execute([$uid, $today]);
    }
    echo "[OK] Shift schedules and leave balances seeded for today.\n";

    // 8. Attendance (Today)
    $scheduleIdsStmt = $conn->prepare("SELECT id, user_id FROM shift_schedules WHERE date = ?");
    $scheduleIdsStmt->execute([$today]);
    $schedules = $scheduleIdsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($schedules as $sch) {
        // Clock In Session 1
        $conn->prepare("INSERT IGNORE INTO attendance (user_id, shift_schedule_id, session, status, check_in_time, distance_to_office) 
                        VALUES (?, ?, 1, 'valid', ?, 10.5)")
            ->execute([$sch['user_id'], $sch['id'], $today . ' 06:05:00']);
    }
    echo "[OK] Attendance (today) seeded.\n";

    // 9. Leave Requests (Monthly)
    $conn->prepare("INSERT IGNORE INTO leave_requests (user_id, leave_date, leave_type, reason, status, approved_by, approved_at) 
                    VALUES (?, ?, 'annual', 'Family gathering', 'approved', (SELECT id FROM users WHERE email='hr@hris.com'), NOW())")
        ->execute([$userIds[0], $today]);
    echo "[OK] Leave requests seeded.\n";

    echo "\n--- Master Seeding Complete ---\n";
    echo "Default Login for all: email@hris.com / password\n";

} catch (Exception $e) {
    echo "[FAIL] Error: " . $e->getMessage() . "\n";
}
