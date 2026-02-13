<?php
$host = 'localhost';
$db   = 'research_library_db'; // Guessing DB name from OJT2/env context or connection
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Try to find database.php to get actual creds if needed, or just guess standard XAMPP
// Accessing backend/app/Config/Database.php to confirm
$dbConfig = file_get_contents('app/Config/Database.php');
preg_match("/'database'\s*=>\s*'([^']+)'/", $dbConfig, $matches);
$dbName = $matches[1] ?? 'ojt2'; // Fallback

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=$charset", $user, $pass);
    $stmt = $pdo->query("DESCRIBE research_details");
    while ($row = $stmt->fetch()) {
        if ($row['Field'] === 'publication_date') {
            echo "Column: " . $row['Field'] . "\n";
            echo "Type: " . $row['Type'] . "\n";
        }
    }
} catch (\PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
