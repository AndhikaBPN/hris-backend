<?php

require_once __DIR__ . '/bootstrap.php';

echo "===========================================\n";
echo " HRIS Attendance - Database Migration\n";
echo "===========================================\n\n";

$db   = new Database();
$conn = $db->getConnection();

// Ambil semua file migration secara urut berdasarkan nama file
$migrationPath = __DIR__ . '/database/migrations/';
$files = glob($migrationPath . '*.sql');
sort($files); // pastikan urutan numerik

if (empty($files)) {
    echo "No migration files found.\n";
    exit(1);
}

$success = 0;
$failed  = 0;

foreach ($files as $file) {
    $filename = basename($file);
    $sql      = file_get_contents($file);

    try {
        $conn->exec($sql);
        echo "[OK]   {$filename}\n";
        $success++;
    } catch (PDOException $e) {
        echo "[FAIL] {$filename} → " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n===========================================\n";
echo " Done: {$success} success, {$failed} failed.\n";
echo "===========================================\n";
