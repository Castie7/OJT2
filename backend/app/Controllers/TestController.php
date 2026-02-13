<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\ResearchService;
use ReflectionClass;

class TestController extends ResourceController
{
    public function verifyRefinedParser()
    {
        $service = new ResearchService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('parseFlexibleDate');
        $method->setAccessible(true);

        $testCases = [
            'January-June 2006' => '2006-01-01',
            'January -June 2010' => '2010-01-01', // User reported case
            'March - June 2015' => '2015-03-01',  
            'April- August 2020' => '2020-04-01', 
            '2018' => '2018-01-01',
        ];

        $results = [];
        $fails = 0;

        foreach ($testCases as $input => $expected) {
            $actual = $method->invoke($service, $input);
            $pass = ($actual === $expected);
            if (!$pass) $fails++;

            $results[] = [
                'input' => $input,
                'expected' => $expected,
                'actual' => $actual,
                'status' => $pass ? 'PASS' : 'FAIL'
            ];
        }

        return $this->respond([
            'summary' => $fails === 0 ? 'ALL PASSED' : "$fails FAILED",
            'tests' => $results
        ]);
    }
}
