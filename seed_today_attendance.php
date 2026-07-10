<?php
/**
 * Seed today's attendance (2026-07-10) for batch1 users.
 * Usage: php seed_today_attendance.php
 */

require_once __DIR__ . '/bootstrap.php';

$db   = new Database();
$conn = $db->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$today = '2026-07-10';

$emails = [
    'satrio@hris.com',
    'exel@hris.com',
    'ghusyara@hris.com',
    'andreas@hris.com',
    'keyla@hris.com',
    'alisya@hris.com',
    'fadilla@hris.com',
    'joseph@hris.com',
    'nabila@hris.com',
];

// Also include Andhika if exists
$andhika = $conn->query("SELECT id FROM users WHERE name LIKE '%Andhika%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($andhika) {
    // Use user_id directly later
}

$statusPool = ['valid', 'valid', 'valid', 'late', 'invalid'];

echo "Seeding attendance for $today...\n\n";

$inserted = 0;
$skipped  = 0;

foreach ($emails as $email) {
    $user = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $user->execute([$email]);
    $u = $user->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        echo "  SKIP: $email not found\n";
        continue;
    }
    $userId = (int) $u['id'];

    $schedStmt = $conn->prepare(
        "SELECT ss.id, ss.is_day_off, s.start_time
         FROM shift_schedules ss
         LEFT JOIN shifts s ON s.id = ss.shift_id
         WHERE ss.user_id = ? AND ss.date = ? LIMIT 1"
    );
    $schedStmt->execute([$userId, $today]);
    $sched = $schedStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sched) {
        echo "  SKIP: $email — no schedule for $today\n";
        $skipped++;
        continue;
    }
    if ((int) $sched['is_day_off'] === 1) {
        echo "  SKIP: $email — day off\n";
        $skipped++;
        continue;
    }

    $schedId   = (int) $sched['id'];
    $startTime = $sched['start_time'] ?? '08:00:00';
    $status    = $statusPool[$userId % count($statusPool)];
    $lateMin   = ($status === 'late') ? rand(16, 45) : rand(-5, 10);
    $baseTs    = strtotime("$today $startTime") + $lateMin * 60;

    for ($session = 1; $session <= 2; $session++) {
        $exists = $conn->prepare(
            "SELECT id FROM attendance WHERE user_id = ? AND shift_schedule_id = ? AND session = ?"
        );
        $exists->execute([$userId, $schedId, $session]);
        if ($exists->fetch()) {
            echo "  SKIP: $email session $session already exists\n";
            $skipped++;
            continue;
        }

        $checkIn   = date('Y-m-d H:i:s', $baseTs + ($session - 1) * 4 * 3600);
        $rowStatus = ($status === 'invalid')
            ? 'invalid'
            : ($status === 'late' && $session === 1 ? 'late' : 'valid');

        $lat  = round(-6.2088 + (rand(-100, 100) / 10000), 8);
        $lng  = round(106.8456 + (rand(-100, 100) / 10000), 8);
        $dist = ($status === 'invalid') ? rand(60, 200) : rand(5, 48);

        $conn->prepare(
            "INSERT INTO attendance
                 (user_id, shift_schedule_id, session, latitude, longitude, distance_to_office, status, check_in_time)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$userId, $schedId, $session, $lat, $lng, $dist, $rowStatus, $checkIn]);

        if ($status === 'invalid') {
            $attId = (int) $conn->lastInsertId();
            $conn->prepare(
                "INSERT INTO attendance_logs (attendance_id, user_id, session, message) VALUES (?,?,?,?)"
            )->execute([$attId, $userId, $session, 'Face or geolocation validation failed']);
        }

        echo "  OK: $email session $session — $rowStatus @ $checkIn\n";
        $inserted++;
    }
}

// Andhika
if ($andhika) {
    $userId = (int) $andhika['id'];
    $schedStmt = $conn->prepare(
        "SELECT ss.id, ss.is_day_off, s.start_time
         FROM shift_schedules ss
         LEFT JOIN shifts s ON s.id = ss.shift_id
         WHERE ss.user_id = ? AND ss.date = ? LIMIT 1"
    );
    $schedStmt->execute([$userId, $today]);
    $sched = $schedStmt->fetch(PDO::FETCH_ASSOC);

    if ($sched && !(int) $sched['is_day_off']) {
        $schedId   = (int) $sched['id'];
        $startTime = $sched['start_time'] ?? '18:00:00';
        $baseTs    = strtotime("$today $startTime") + rand(-5, 10) * 60;

        for ($session = 1; $session <= 2; $session++) {
            $exists = $conn->prepare(
                "SELECT id FROM attendance WHERE user_id = ? AND shift_schedule_id = ? AND session = ?"
            );
            $exists->execute([$userId, $schedId, $session]);
            if ($exists->fetch()) { $skipped++; continue; }

            $checkIn = date('Y-m-d H:i:s', $baseTs + ($session - 1) * 4 * 3600);
            $conn->prepare(
                "INSERT INTO attendance
                     (user_id, shift_schedule_id, session, latitude, longitude, distance_to_office, status, check_in_time)
                 VALUES (?,?,?,?,?,?,?,?)"
            )->execute([$userId, $schedId, $session,
                round(-6.2088 + rand(-100, 100) / 10000, 8),
                round(106.8456 + rand(-100, 100) / 10000, 8),
                rand(5, 48), 'valid', $checkIn]);

            echo "  OK: Andhika session $session — valid @ $checkIn\n";
            $inserted++;
        }
    }
}

echo "\nDone. Inserted: $inserted | Skipped: $skipped\n";
