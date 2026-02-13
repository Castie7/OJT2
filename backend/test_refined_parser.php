<?php
// Load CodeIgniter
require 'app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Services\ResearchService;

// Access private method
$service = new ResearchService();
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('parseFlexibleDate');
$method->setAccessible(true);

$testCases = [
    'January-June 2006' => '2006-01-01',
    'January -June 2010' => '2010-01-01', // The user's failing case
    'March - June 2015' => '2015-03-01',  // Both sides spaces
    'April- August 2020' => '2020-04-01', // Right side space
    '2018' => '2018-01-01',
    '01/02/2014' => '2014-01-02'
];

echo "Testing Refined parseFlexibleDate...\n";
$fails = 0;

foreach ($testCases as $input => $expected) {
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
