<?php
// Load CodeIgniter
require 'app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Services\ResearchService;

// Access private method via reflection for testing
$service = new ResearchService();
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('parseFlexibleDate');
$method->setAccessible(true);

$testCases = [
    '2018' => '2018-01-01',
    'January-June 2006' => '2006-01-01',
    '01/02/2014' => '2014-01-02', // Assuming US Format (Jan 2) or Euro (Feb 1). PHP typically defaults to M/D/Y with slash. Strtotime "01/02/2014" -> Jan 2, 2014.
    '2023-05-20' => '2023-05-20',
    '' => date('Y-m-d')
];

echo "Testing parseFlexibleDate...\n";
$fails = 0;

foreach ($testCases as $input => $expected) {
    // For today's date dynamic check
    if ($input === '') $expected = date('Y-m-d');
    
    $actual = $method->invoke($service, $input);
    
    if ($actual === $expected) {
        echo "[PASS] '$input' -> $actual\n";
    } else {
        echo "[FAIL] '$input' -> Got $actual, Expected $expected\n";
        $fails++;
    }
}

if ($fails === 0) {
    echo "\nALL TESTS PASSED.\n";
} else {
    echo "\n$fails TESTS FAILED.\n";
}
