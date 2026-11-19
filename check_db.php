<?php
$conn = new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    echo "Connect error: " . $conn->connect_error . "\n";
    exit;
}
echo "Connected successfully.\n";

$dbs = ['presensi', 'presensi_pkl'];
foreach ($dbs as $db) {
    echo "=== DB: $db ===\n";
    if ($conn->select_db($db)) {
        $res = $conn->query("SHOW TABLES");
        while ($row = $res->fetch_array()) {
            $table = $row[0];
            $countRes = $conn->query("SELECT COUNT(*) FROM `$table`");
            $count = $countRes ? $countRes->fetch_row()[0] : 'err';
            echo " - Table: $table ($count rows)\n";
        }
    } else {
        echo " Does not exist or cannot select.\n";
    }
}
