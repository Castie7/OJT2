<?php
$url = 'http://localhost/OJT2/backend/public/index.php/test/verify-parser';
echo "Requesting: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo "Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Status: $httpCode\n";
    echo "Response: $response\n";
}
curl_close($ch);
