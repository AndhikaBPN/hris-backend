<?php

require_once __DIR__ . '/bootstrap.php';

$testFiles = glob(__DIR__ . '/Unit/*Test.php');
$failures = 0;
$tests = 0;

foreach ($testFiles as $file) {
    require_once $file;
}

foreach (get_declared_classes() as $class) {
    if (!str_ends_with($class, 'Test')) {
        continue;
    }

    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract()) {
        continue;
    }

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $tests++;
        try {
            $reflection->newInstance()->{$method->getName()}();
            echo ".";
        } catch (Throwable $e) {
            $failures++;
            echo "F\n";
            echo "{$class}::{$method->getName()} failed: {$e->getMessage()}\n";
        }
    }
}

echo "\n{$tests} tests, {$failures} failures\n";
exit($failures > 0 ? 1 : 0);
