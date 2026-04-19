<?php

// ================================================================
// Bootstrap — load env, helpers, database config
// ================================================================

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Timezone
date_default_timezone_set('Asia/Jakarta');

// ----------------------------------------------------------------
// Autoload app classes (manual, tanpa Composer PSR-4)
// ----------------------------------------------------------------
$autoloadDirs = [
    __DIR__ . '/config',
    __DIR__ . '/app/Helpers',
    __DIR__ . '/app/Middleware',
    __DIR__ . '/app/Models',
    __DIR__ . '/app/Services',
    // Controllers di-load on-demand di index.php
];

foreach ($autoloadDirs as $dir) {
    foreach (glob($dir . '/*.php') as $file) {
        require_once $file;
    }
}