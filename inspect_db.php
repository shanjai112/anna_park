<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gov";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function getTableColumns($conn, $table) {
    $sql = "SHOW COLUMNS FROM $table";
    $result = $conn->query($sql);
    $columns = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    } else {
        echo "Error getting columns for $table: " . $conn->error . "\n";
    }
    return $columns;
}

echo "Columns in 'members' table:\n";
print_r(getTableColumns($conn, 'members'));

echo "\nColumns in 'deleted_members' table:\n";
print_r(getTableColumns($conn, 'deleted_members'));

$conn->close();
?>
