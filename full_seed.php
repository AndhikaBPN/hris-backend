<?php
/**
 * Full Seed — all tables except users (no NULLs anywhere).
 *
 * Tables covered:
 *   face_embeddings, shift_schedules, attendance, attendance_logs,
 *   leave_balances, leave_requests, notifications
 *
 * Safe to re-run (skips existing rows).
 * Usage: php full_seed.php
 */

require_once __DIR__ . '/bootstrap.php';

$db   = new Database();
$conn = $db->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "===========================================\n";
echo " Full Seed (no NULLs)\n";
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

function all(PDO $conn, string $sql, array $p = []): array
{
    $s = $conn->prepare($sql);
    $s->execute($p);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

function exists(PDO $conn, string $sql, array $p = []): bool
{
    $s = $conn->prepare($sql);
    $s->execute($p);
    return (bool) $s->fetch();
}

// ---------------------------------------------------------------------------
// Load reference data
// ---------------------------------------------------------------------------
$users = all($conn, "SELECT u.id, u.name, u.role_id, r.role FROM users u JOIN role r ON r.id = u.role_id WHERE u.is_active = 1 ORDER BY u.id");
$shifts = [];
foreach (all($conn, "SELECT * FROM shifts") as $s) {
    $shifts[$s['id']] = $s;
}

// Get a manager ID for leave approvals
$manager = one($conn, "SELECT u.id FROM users u JOIN role r ON r.id = u.role_id WHERE r.role IN ('hrd_manager','c_level') AND u.is_active = 1 LIMIT 1");
$managerId = $manager ? (int) $manager['id'] : 1;

// Compute shift duration in seconds (handle overnight)
function shiftDuration(array $shift): int
{
    [$sh, $sm] = explode(':', $shift['start_time']);
    [$eh, $em] = explode(':', $shift['end_time']);
    $start = (int)$sh * 3600 + (int)$sm * 60;
    $end   = (int)$eh * 3600 + (int)$em * 60;
    if ($end <= $start) {
        $end += 86400; // overnight
    }
    return $end - $start;
}

echo "  Users found: " . count($users) . "\n";
echo "  Shifts found: " . count($shifts) . "\n";
echo "  Manager ID for approvals: $managerId\n\n";

// ---------------------------------------------------------------------------
// 1. Face Embeddings — 5 samples per user
// ---------------------------------------------------------------------------
echo "[1/6] face_embeddings...\n";

$idx = 0;
foreach ($users as $u) {
    $uid = (int) $u['id'];
    $cnt = (int) one($conn, "SELECT COUNT(*) AS c FROM face_embeddings WHERE user_id = ?", [$uid])['c'];
    if ($cnt >= 5) { $idx++; echo "  skip uid=$uid (has $cnt)\n"; continue; }

    for ($s = 0; $s < 5; $s++) {
        $vec = [];
        for ($d = 0; $d < 128; $d++) {
            $vec[] = round(sin($idx * 97 + $s * 13 + $d) * 0.3, 6);
        }
        go($conn, "INSERT INTO face_embeddings (user_id, embedding) VALUES (?,?)", [$uid, json_encode($vec)]);
    }
    echo "  uid=$uid ({$u['name']}): 5 samples\n";
    $idx++;
}

// ---------------------------------------------------------------------------
// 2. Shift Schedules — July 2026 (all users, all 31 days)
// ---------------------------------------------------------------------------
echo "\n[2/6] shift_schedules (Jul 2026)...\n";

// Fixed shift per user based on shift times. Fall back to Pagi (id=1) if none found.
// Pull current assignment from existing shift_schedules or assign by role.
$rotCycle = [1, 1, 2, 2, 3, 3, null, null]; // Pagi,Pagi,Siang,Siang,Malam,Malam,Off,Off

foreach ($users as $uIdx => $u) {
    $uid    = (int) $u['id'];
    $role   = $u['role'];
    $count  = 0;

    // Try to get shift assignment from any existing schedule this user has
    $existingSched = one($conn, "SELECT shift_id FROM shift_schedules WHERE user_id = ? AND shift_id IS NOT NULL LIMIT 1", [$uid]);
    $fixedShiftId  = $existingSched ? (int) $existingSched['shift_id'] : null;

    for ($day = 1; $day <= 31; $day++) {
        $date = sprintf('2026-07-%02d', $day);
        if (exists($conn, "SELECT id FROM shift_schedules WHERE user_id = ? AND date = ?", [$uid, $date])) continue;

        if ($fixedShiftId) {
            // User already has a fixed shift assignment — use it every day
            go($conn, "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off) VALUES (?,?,?,0)", [$uid, $fixedShiftId, $date]);
        } elseif ($role === 'hrd_manager') {
            $dow = (int) date('N', strtotime($date));
            if ($dow >= 6) {
                go($conn, "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off) VALUES (?,4,?,0)", [$uid, $date]);
            } else {
                go($conn, "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off) VALUES (?,4,?,0)", [$uid, $date]);
            }
        } elseif ($role === 'technical_manager') {
            go($conn, "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off) VALUES (?,5,?,0)", [$uid, $date]);
        } else {
            // Rotation: stagger by user index
            $phase = (($day - 1) + $uIdx * 2) % 8;
            $shiftId = $rotCycle[$phase];
            if ($shiftId === null) {
                // Day off — use shift_id=1 but is_day_off=1 (NOT NULL shift_id)
                go($conn, "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off) VALUES (?,1,?,1)", [$uid, $date]);
            } else {
                go($conn, "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off) VALUES (?,?,?,0)", [$uid, $shiftId, $date]);
            }
        }
        $count++;
    }
    echo "  uid=$uid ({$u['name']}): $count schedules\n";
}

// ---------------------------------------------------------------------------
// 3. Attendance — July 1–10, 2026 (all columns filled, no NULL)
// ---------------------------------------------------------------------------
echo "\n[3/6] attendance (Jul 1–10)...\n";

$statusPool = ['valid', 'valid', 'valid', 'late', 'invalid'];

foreach ($users as $u) {
    $uid   = (int) $u['id'];
    $count = 0;

    for ($day = 1; $day <= 10; $day++) {
        $date = sprintf('2026-07-%02d', $day);

        $sched = one($conn,
            "SELECT ss.id, ss.is_day_off, ss.shift_id, s.start_time, s.end_time, s.is_overnight, s.late_tolerance_minutes
             FROM shift_schedules ss
             JOIN shifts s ON s.id = ss.shift_id
             WHERE ss.user_id = ? AND ss.date = ? LIMIT 1",
            [$uid, $date]
        );
        if (!$sched || (int) $sched['is_day_off'] === 1) continue;

        $schedId    = (int) $sched['id'];
        $startTime  = $sched['start_time'];
        $isOvernight = (bool) $sched['is_overnight'];
        $status     = $statusPool[($uid + $day) % count($statusPool)];
        $lateMin    = ($status === 'late') ? rand(16, 45) : rand(-3, 8);
        $baseTs     = strtotime("$date $startTime") + $lateMin * 60;

        // Compute shift duration for checkout time
        [$sh, $sm] = explode(':', $sched['start_time']);
        [$eh, $em] = explode(':', $sched['end_time']);
        $startSec  = (int)$sh * 3600 + (int)$sm * 60;
        $endSec    = (int)$eh * 3600 + (int)$em * 60;
        if ($endSec <= $startSec) $endSec += 86400;
        $durationSec = $endSec - $startSec;

        for ($session = 1; $session <= 2; $session++) {
            if (exists($conn, "SELECT id FROM attendance WHERE user_id=? AND shift_schedule_id=? AND session=?", [$uid, $schedId, $session])) continue;

            $checkInTs  = $baseTs + ($session - 1) * (int)($durationSec / 2);
            $checkOutTs = $checkInTs + (int)($durationSec / 2) - rand(300, 900); // ~end of session minus buffer
            $checkIn    = date('Y-m-d H:i:s', $checkInTs);
            $checkOut   = date('Y-m-d H:i:s', $checkOutTs);

            $rowStatus  = ($status === 'invalid')
                ? 'invalid'
                : ($status === 'late' && $session === 1 ? 'late' : 'valid');

            $lat        = round(-6.2088 + rand(-200, 200) / 10000, 8);
            $lng        = round(106.8456 + rand(-200, 200) / 10000, 8);
            $dist       = ($status === 'invalid') ? (float) rand(60, 200) : (float) rand(5, 48);
            $faceImage  = "storage/faces/user_{$uid}_session{$session}_{$date}.jpg";

            go($conn,
                "INSERT INTO attendance
                     (user_id, shift_schedule_id, session, face_image, latitude, longitude,
                      distance_to_office, status, check_in_time, check_out_time)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$uid, $schedId, $session, $faceImage, $lat, $lng, $dist, $rowStatus, $checkIn, $checkOut]
            );

            if ($status === 'invalid') {
                $attId = (int) $conn->lastInsertId();
                go($conn,
                    "INSERT INTO attendance_logs (attendance_id, user_id, session, message) VALUES (?,?,?,?)",
                    [$attId, $uid, $session, 'Face recognition or geolocation validation failed on clock-in']
                );
            }
            $count++;
        }
    }
    echo "  uid=$uid ({$u['name']}): $count attendance records\n";
}

// ---------------------------------------------------------------------------
// 4. Leave Balances — Jan–Jul 2026
// ---------------------------------------------------------------------------
echo "\n[4/6] leave_balances (Jan–Jul 2026)...\n";

foreach ($users as $u) {
    $uid = (int) $u['id'];
    for ($month = 1; $month <= 7; $month++) {
        if (exists($conn, "SELECT 1 FROM leave_balances WHERE user_id=? AND year=2026 AND month=?", [$uid, $month])) continue;
        $used = ($month <= 6) ? rand(0, 1) : 0; // Jul = fresh
        go($conn, "INSERT INTO leave_balances (user_id, year, month, quota, used) VALUES (?,2026,?,1,?)", [$uid, $month, $used]);
    }
    echo "  uid=$uid ({$u['name']}): 7 months\n";
}

// ---------------------------------------------------------------------------
// 5. Leave Requests — approved & rejected only (no pending → no NULL approved_by)
// ---------------------------------------------------------------------------
echo "\n[5/6] leave_requests...\n";

$leaveTypes = [
    ['annual',          'Liburan bersama keluarga',       null,                        3],
    ['sick',            'Demam tinggi dan flu berat',      'storage/doctor/letter.pdf', 1],
    ['permit',          'Mengurus keperluan administrasi', 'storage/doctor/permit.pdf', 2],
    ['leave_of_absence','Keperluan mendesak keluarga',     'storage/doctor/absence.pdf',4],
];

$statuses = ['approved', 'approved', 'rejected'];

$dayOffset = 0;
foreach ($users as $uIdx => $u) {
    $uid = (int) $u['id'];

    for ($l = 0; $l < 2; $l++) {
        [$type, $reason, $docLetter, $monthRef] = $leaveTypes[($uIdx + $l) % count($leaveTypes)];
        $status    = $statuses[($uIdx + $l) % count($statuses)];

        $fromDay   = rand(1, 25);
        $fromDate  = sprintf('2026-%02d-%02d', $monthRef, $fromDay);
        $toDate    = sprintf('2026-%02d-%02d', $monthRef, min($fromDay + rand(0, 2), 28));
        $approvedAt = date('Y-m-d H:i:s', strtotime($toDate) + 86400);

        // doctor_letter required for sick — for others use 'storage/doctor/na.pdf' to avoid NULL
        $doc = $docLetter ?? 'storage/doctor/na.pdf';

        if (exists($conn, "SELECT 1 FROM leave_requests WHERE user_id=? AND leave_date_from=? AND leave_type=?", [$uid, $fromDate, $type])) continue;

        go($conn,
            "INSERT INTO leave_requests
                 (user_id, leave_type, leave_date_from, leave_date_to, reason, doctor_letter, status, approved_by, approved_at)
             VALUES (?,?,?,?,?,?,?,?,?)",
            [$uid, $type, $fromDate, $toDate, $reason, $doc, $status, $managerId, $approvedAt]
        );
        echo "  uid=$uid ({$u['name']}): $type $fromDate [$status]\n";

        if ($status === 'approved' && $type === 'annual') {
            go($conn, "UPDATE leave_balances SET used = used + 1 WHERE user_id=? AND year=2026 AND month=?", [$uid, $monthRef]);
        }
    }
    $dayOffset += 3;
}

// ---------------------------------------------------------------------------
// 6. Notifications — 3 per user
// ---------------------------------------------------------------------------
echo "\n[6/6] notifications...\n";

$notifTemplates = [
    [
        'type'  => 'leave_approved',
        'title' => 'Cuti Disetujui',
        'body'  => 'Pengajuan cuti tahunan kamu telah disetujui oleh HRD Manager.',
    ],
    [
        'type'  => 'leave_submitted',
        'title' => 'Pengajuan Cuti Baru',
        'body'  => 'Ada pengajuan cuti baru yang perlu ditinjau.',
    ],
    [
        'type'  => 'leave_rejected',
        'title' => 'Cuti Ditolak',
        'body'  => 'Pengajuan cuti kamu untuk bulan ini ditolak.',
    ],
];

foreach ($users as $uIdx => $u) {
    $uid = (int) $u['id'];
    for ($n = 0; $n < 3; $n++) {
        $tpl = $notifTemplates[($uIdx + $n) % count($notifTemplates)];
        $data = json_encode([
            'leave_id'       => $uIdx * 3 + $n + 1,
            'requester_id'   => $uid,
            'requester_name' => $u['name'],
            'leave_type'     => 'annual',
            'status'         => str_replace('leave_', '', $tpl['type']),
        ]);
        $isRead  = ($n === 0) ? 1 : 0;
        $created = date('Y-m-d H:i:s', strtotime("2026-07-0" . ($n + 1) . " 09:00:00"));

        go($conn,
            "INSERT INTO notifications (user_id, type, title, body, data, is_read, created_at) VALUES (?,?,?,?,?,?,?)",
            [$uid, $tpl['type'], $tpl['title'], $tpl['body'], $data, $isRead, $created]
        );
    }
    echo "  uid=$uid ({$u['name']}): 3 notifications\n";
}

// ---------------------------------------------------------------------------
echo "\n===========================================\n";
echo " Full Seed done!\n";
echo "===========================================\n";
