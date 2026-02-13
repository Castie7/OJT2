<?php
// Load CodeIgniter
require 'app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '\\/ ') . DIRECTORY_SEPARATOR . 'bootstrap.php';

use Config\Database;

$db = Database::connect();
$query = $db->query("DESCRIBE research_details");
$results = $query->getResultArray();

foreach ($results as $row) {
    if ($row['Field'] === 'publication_date') {
        echo "Column: " . $row['Field'] . "\n";
        echo "Type: " . $row['Type'] . "\n";
    }
}
