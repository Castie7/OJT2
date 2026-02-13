<?php

// Security Test Script
// Run from command line: php security_test.php

$baseUrl = 'http://localhost/OJT2/backend/public/index.php';

function testRequest($method, $url, $data = [], $headers = []) {
    global $baseUrl;
    $ch = curl_init($baseUrl . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } elseif ($method === 'OPTIONS') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $responseHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }
    curl_close($ch);

    return ['code' => $httpCode, 'headers' => $responseHeaders, 'body' => $body];
}

echo "Running Security Tests...\n";
echo "Target: $baseUrl\n\n";

// ---------------------------------------------------------
// TEST 1: Unauthenticated CSV Import (Should FAIL if secure)
// ---------------------------------------------------------
echo "[TEST 1] Testing Unauthenticated CSV Import...\n";
// Create a dummy CSV content? Attempting upload might be tricky with simple curl if looking for file. 
// But ResearchController checks user BEFORE file. So even without file, it should fail 403/401.
$res = testRequest('POST', '/research/import-csv', []);
if ($res['code'] == 403 || $res['code'] == 401) {
    echo "✅ PASS: Endpoint refused access (HTTP {$res['code']})\n";
} elseif ($res['code'] == 200 || $res['code'] == 400) {
    // 400 means it processed the request but found no file -> SECURITY FAIL
    echo "❌ FAIL: Endpoint is accessible! (HTTP {$res['code']})\n";
} else {
    echo "⚠️ WARN: Unexpected response code (HTTP {$res['code']})\n";
}
echo "\n";

// ---------------------------------------------------------
// TEST 2: Security Headers (X-Frame-Options)
// ---------------------------------------------------------
echo "[TEST 2] Checking Security Headers...\n";
$res = testRequest('GET', '/auth/verify'); // Use a lightweight endpoint
if (stripos($res['headers'], 'X-Frame-Options: DENY') !== false || stripos($res['headers'], 'X-Frame-Options: SAMEORIGIN') !== false) {
    echo "✅ PASS: X-Frame-Options header found.\n";
} else {
    echo "❌ FAIL: X-Frame-Options header MISSING.\n";
}
echo "\n";

// ---------------------------------------------------------
// TEST 3: CORS Openness
// ---------------------------------------------------------
echo "[TEST 3] Checking CORS...\n";
// Send an origin that isn't localhost
$headers = ['Origin: http://evil.com'];
$res = testRequest('OPTIONS', '/auth/login', [], $headers);
if (stripos($res['headers'], 'Access-Control-Allow-Origin: http://evil.com') !== false) {
    echo "⚠️ NOTE: CORS allows 'http://evil.com' (This is expected currently, but noted).\n";
} else {
    echo "✅ PASS: CORS did not reflect evil origin (or restricted it).\n";
}

echo "\nDone.\n";
