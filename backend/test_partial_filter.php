<?php

function testUrl($desc, $params) {
    $url = "http://localhost/OJT2/backend/public/index.php/research?" . $params;
    echo "\n--- $desc ---\n";
    echo "URL: $url\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, str_replace(' ', '%20', $url));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    
    if (is_array($data)) {
        echo "Count: " . count($data) . "\n";
        foreach ($data as $item) {
             $d = $item['publication_date'];
             if (is_array($d)) $d = $d['date'];
             echo "  - ID: {$item['id']} | Date: " . substr($d, 0, 10) . "\n";
        }
    } else {
        echo "Failed to decode response.\n";
    }
}

// Case 1: End Date BEFORE the known item (2026-02-23)
// Should result in 0 items (or only the invalid/old dates if they exist and represent ancient history)
testUrl("Only 'To' Date (2025-12-31)", "end_date=2025-12-31");

// Case 2: End Date AFTER the known item
// Should include the 2026 item
testUrl("Only 'To' Date (2027-12-31)", "end_date=2027-12-31");
