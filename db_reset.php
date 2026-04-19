<?php

/**
 * Drop & recreate database hris_attendance.
 * Jalankan SEKALI sebelum migrate.php jika ingin reset total.
 *
 * Usage: php db_reset.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? 'hris_attendance';

try {
    // Connect tanpa pilih database
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    echo "[OK] Database '{$dbName}' dropped.\n";

    $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] Database '{$dbName}' created.\n";

    echo "\nSekarang jalankan: php migrate.php\n";
} catch (PDOException $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}
