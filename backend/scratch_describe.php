<?php
$db = mysqli_connect('localhost', 'root', '', 'rootcrop_db');
echo "--- RESEARCHES ---\n";
$res = mysqli_query($db, 'DESCRIBE researches');
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
echo "\n--- RESEARCH_DETAILS ---\n";
$res = mysqli_query($db, 'DESCRIBE research_details');
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
