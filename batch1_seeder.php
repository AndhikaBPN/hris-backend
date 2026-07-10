<?php
/**
 * Batch 1 Seeder — seed data for 9 new users.
 *
 * Prereq: run seed_users_batch1.md INSERT query first.
 * Usage:  php batch1_seeder.php
 *
 * Covers: face_embeddings, shift_schedules (Jul 2026),
 *         attendance (Jul 1-9), leave_balances, leave_requests
 */

require_once __DIR__ . '/bootstrap.php';

$db   = new Database();
$conn = $db->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "===========================================\n";
echo " Batch 1 Seeder\n";
echo "===========================================\n\n";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function go(PDO $conn, string $sql, array $p = []): void
{
    $conn->prepare($sql)->execute($p);
}

function one(PDO $conn, string $sql, array $p = []): ?array
{
    $s = $conn->prepare($sql);
    $s->execute($p);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

// ---------------------------------------------------------------------------
// Resolve user IDs
// ---------------------------------------------------------------------------
$emailMap = [
    'satrio'    => 'satrio@hris.com',
    'exel'      => 'exel@hris.com',
    'ghusyara'  => 'ghusyara@hris.com',
    'andreas'   => 'andreas@hris.com',
    'keyla'     => 'keyla@hris.com',
    'alisya'    => 'alisya@hris.com',
    'fadilla'   => 'fadilla@hris.com',
    'joseph'    => 'joseph@hris.com',
    'nabila'    => 'nabila@hris.com',
];

$users = [];
foreach ($emailMap as $key => $email) {
    $row = one($conn, "SELECT id, role_id FROM users WHERE email = ? LIMIT 1", [$email]);
    if (!$row) {
        echo "ERROR: User '$email' not found. Run seed_users_batch1 INSERT first.\n";
        exit(1);
    }
    $users[$key] = ['id' => (int) $row['id'], 'role_id' => (int) $row['role_id']];
    echo "  Found: $email → id={$row['id']}, role_id={$row['role_id']}\n";
}

// ---------------------------------------------------------------------------
// Lookup Andhika Bagaskara (K3 — not in batch INSERT, query by name)
// ---------------------------------------------------------------------------
$andhika = one($conn, "SELECT id, role_id FROM users WHERE name LIKE '%Andhika%' LIMIT 1");
if ($andhika) {
    $users['andhika'] = ['id' => (int) $andhika['id'], 'role_id' => (int) $andhika['role_id']];
    echo "  Found: Andhika Bagaskara → id={$andhika['id']}\n";
} else {
    echo "  WARN: Andhika Bagaskara not found in DB — skipping K3 schedule.\n";
}

$shiftRows = $conn->query("SELECT id, name FROM shifts")->fetchAll(PDO::FETCH_ASSOC);
$shifts = [];
foreach ($shiftRows as $s) {
    $shifts[$s['name']] = (int) $s['id'];
}

// Fixed shift assignment per user key
$userShift = [
    'satrio'   => 'K-Pagi',
    'exel'     => 'K-Dini',
    'andhika'  => 'K-Sore',
    'ghusyara' => 'K-Siang',
    'andreas'  => 'K-Ops',
    'keyla'    => 'K-Sore',
    'alisya'   => 'K-Pagi',
    'fadilla'  => 'K-Siang',
    'joseph'   => 'K-Ops',
    'nabila'   => 'K-Dini',
];

echo "\n";

// ---------------------------------------------------------------------------
// 1. Face Embeddings — 5 samples per user
// ---------------------------------------------------------------------------
echo "[1/5] Seeding face_embeddings...\n";

$i = 0;
foreach ($users as $key => $u) {
    // Skip if already has samples
    $existing = (int) one($conn, "SELECT COUNT(*) AS cnt FROM face_embeddings WHERE user_id = ?", [$u['id']])['cnt'];
    if ($existing >= 5) {
        echo "  skip {$emailMap[$key]} (already has $existing samples)\n";
        $i++;
        continue;
    }

    for ($sample = 0; $sample < 5; $sample++) {
        $vec = [];
        for ($d = 0; $d < 128; $d++) {
            $vec[] = round(sin($i * 97 + $sample * 13 + $d) * 0.3, 6);
        }
        go($conn,
            "INSERT INTO face_embeddings (user_id, embedding) VALUES (?, ?)",
            [$u['id'], json_encode($vec)]
        );
    }
    echo "  inserted 5 samples for {$emailMap[$key]}\n";
    $i++;
}

// ---------------------------------------------------------------------------
// 2. Shift Schedules — July 2026 (fixed shift per user, all 31 days)
// ---------------------------------------------------------------------------
echo "\n[2/5] Seeding shift_schedules (Jul 2026)...\n";

foreach ($users as $key => $u) {
    $sName   = $userShift[$key] ?? null;
    $shiftId = $sName ? ($shifts[$sName] ?? null) : null;
    $count   = 0;

    for ($day = 1; $day <= 31; $day++) {
        $date = sprintf('2026-07-%02d', $day);

        $exists = one($conn, "SELECT id FROM shift_schedules WHERE user_id = ? AND date = ? LIMIT 1", [$u['id'], $date]);
        if ($exists) continue;

        go($conn,
            "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off) VALUES (?,?,?,0)",
            [$u['id'], $shiftId, $date]
        );
        $count++;
    }

    $label = $emailMap[$key] ?? 'andhika';
    echo "  $label ($sName): $count schedules\n";
}

// ---------------------------------------------------------------------------
// 3. Attendance — July 1–9, 2026
// ---------------------------------------------------------------------------
echo "\n[3/5] Seeding attendance (Jul 1–9)...\n";

$statusPool = ['valid', 'valid', 'valid', 'late', 'invalid'];

foreach ($users as $key => $u) {
    $count = 0;
    for ($day = 1; $day <= 9; $day++) {
        $date = sprintf('2026-07-%02d', $day);

        $sched = one($conn,
            "SELECT ss.id, ss.shift_id, ss.is_day_off, s.start_time
             FROM shift_schedules ss
             LEFT JOIN shifts s ON s.id = ss.shift_id
             WHERE ss.user_id = ? AND ss.date = ? LIMIT 1",
            [$u['id'], $date]
        );
        if (!$sched || (int) $sched['is_day_off'] === 1) continue;

        $startTime = $sched['start_time'] ?? '08:00:00';
        $status    = $statusPool[($u['id'] + $day) % count($statusPool)];
        $lateMin   = ($status === 'late') ? rand(16, 45) : rand(-5, 10);
        $baseTs    = strtotime("$date $startTime") + $lateMin * 60;

        for ($session = 1; $session <= 2; $session++) {
            // Check if already exists
            $exists = one($conn,
                "SELECT id FROM attendance WHERE user_id = ? AND shift_schedule_id = ? AND session = ?",
                [$u['id'], (int) $sched['id'], $session]
            );
            if ($exists) continue;

            $checkIn = date('Y-m-d H:i:s', $baseTs + ($session - 1) * 4 * 3600);
            $rowStatus = ($status === 'invalid')
                ? 'invalid'
                : ($status === 'late' && $session === 1 ? 'late' : 'valid');

            $lat = round(-6.2088 + (rand(-100, 100) / 10000), 8);
            $lng = round(106.8456 + (rand(-100, 100) / 10000), 8);
            $dist = ($status === 'invalid') ? rand(60, 200) : rand(5, 48);

            go($conn,
                "INSERT INTO attendance
                     (user_id, shift_schedule_id, session, latitude, longitude, distance_to_office, status, check_in_time)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$u['id'], (int) $sched['id'], $session, $lat, $lng, $dist, $rowStatus, $checkIn]
            );

            if ($status === 'invalid') {
                $attId = (int) $conn->lastInsertId();
                go($conn,
                    "INSERT INTO attendance_logs (attendance_id, user_id, session, message) VALUES (?,?,?,?)",
                    [$attId, $u['id'], $session, 'Face or geolocation validation failed']
                );
            }
            $count++;
        }
    }
    echo "  {$emailMap[$key]}: $count attendance records\n";
}

// ---------------------------------------------------------------------------
// 4. Leave Balances — July 2026
// ---------------------------------------------------------------------------
echo "\n[4/5] Seeding leave_balances...\n";

foreach ($users as $key => $u) {
    go($conn,
        "INSERT IGNORE INTO leave_balances (user_id, year, month, quota, used) VALUES (?,2026,7,1,0)",
        [$u['id']]
    );
    echo "  {$emailMap[$key]}: quota=1, used=0\n";
}

// ---------------------------------------------------------------------------
// 5. Leave Requests
// ---------------------------------------------------------------------------
echo "\n[5/5] Seeding leave_requests...\n";

$leaveData = [
    ['satrio',   'annual',          '2026-07-14', '2026-07-14', 'Keperluan keluarga',      'pending'],
    ['ghusyara', 'sick',            '2026-07-03', '2026-07-03', 'Demam dan flu',           'approved'],
    ['keyla',    'permit',          '2026-07-07', '2026-07-07', 'Urusan administrasi',     'rejected'],
    ['nabila',   'annual',          '2026-07-21', '2026-07-22', 'Liburan keluarga',        'pending'],
    ['alisya',   'sick',            '2026-07-08', '2026-07-08', 'Sakit kepala migrain',    'approved'],
    ['exel',     'leave_of_absence','2026-07-17', '2026-07-18', 'Keperluan mendadak',      'pending'],
    ['fadilla',  'permit',          '2026-07-10', '2026-07-10', 'Menghadiri acara kantor', 'approved'],
];

foreach ($leaveData as [$key, $type, $from, $to, $reason, $status]) {
    $uid = $users[$key]['id'];

    // Skip if already exists
    $exists = one($conn,
        "SELECT id FROM leave_requests WHERE user_id = ? AND leave_date_from = ? AND leave_type = ?",
        [$uid, $from, $type]
    );
    if ($exists) {
        echo "  skip {$emailMap[$key]} $type $from (exists)\n";
        continue;
    }

    go($conn,
        "INSERT INTO leave_requests (user_id, leave_type, leave_date_from, leave_date_to, reason, status, created_at)
         VALUES (?,?,?,?,?,?,?)",
        [$uid, $type, $from, $to, $reason, $status, "$from 08:00:00"]
    );

    if ($status === 'approved' && $type === 'annual') {
        go($conn,
            "UPDATE leave_balances SET used = used + 1 WHERE user_id = ? AND year = 2026 AND month = 7",
            [$uid]
        );
    }

    echo "  {$emailMap[$key]}: $type $from–$to [$status]\n";
}

// ---------------------------------------------------------------------------
echo "\n===========================================\n";
echo " Batch 1 Seeder done!\n";
echo "===========================================\n";
