<?php

// Simple Date Test
$url = "http://localhost/OJT2/backend/public/index.php/research?start_date=2026-01-01&end_date=2026-12-31";

echo "Testing URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, str_replace(' ', '%20', $url));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (is_array($data)) {
        echo "Success! Returned " . count($data) . " items.\n";
        if (count($data) > 0) {
            $firstDate = $data[0]['publication_date'];
            if (is_array($firstDate) && isset($firstDate['date'])) {
                $firstDate = $firstDate['date'];
            }
            echo "First item date: " . ($firstDate ?? 'N/A') . "\n";
            
            // Update test range to cover EVERYTHING
            $testStart = '1900-01-01';
            $testEnd = '3000-12-31';
            
            echo "Testing Filter: $testStart to $testEnd\n";
            $url = "http://localhost/OJT2/backend/public/index.php/research?start_date=$testStart&end_date=$testEnd";
            
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, str_replace(' ', '%20', $url));
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            $res2 = curl_exec($ch2);
            curl_close($ch2);
            
            $filteredData = json_decode($res2, true);
            
            if (is_array($filteredData)) {
                echo "Filtered Count: " . count($filteredData) . "\n";
                foreach ($filteredData as $item) {
                     $d = $item['publication_date'];
                     if (is_array($d)) $d = $d['date'];
                     echo "  - Found: " . $d . "\n";
                }
            } else {
                echo "Filter request failed.\n";
            }
            
            // Previous loop removed for brevity/replacement
            return; 
        }
    } else {
        echo "Failed to decode JSON.\n";
        echo substr($response, 0, 100);
    }
} else {
    echo "Request failed. HTTP: $httpCode\n";
    echo "Response: " . $response . "\n";
}
