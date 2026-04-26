<?php

if (!class_exists(\PHPUnit\Framework\TestCase::class)) {
    require_once __DIR__ . '/Support/FallbackTestCase.php';
}

require_once __DIR__ . '/../app/Models/User.php';
