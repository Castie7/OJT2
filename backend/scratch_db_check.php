<?php
require 'vendor/autoload.php';
$db = mysqli_connect('localhost', 'root', '', 'rootcrop_db');
$query = "SELECT researches.*, research_details.* FROM researches JOIN research_details ON researches.id = research_details.research_id LIMIT 1";
$result = mysqli_query($db, $query);
$row = mysqli_fetch_assoc($result);
echo "KEYS: " . implode(', ', array_keys($row)) . "\n";
print_r($row);
