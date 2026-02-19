<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gov"; // change if needed

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=members_export.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Write column headers
fputcsv($output, [
    'Member No', 'Member Name', 'Member Type', 'Member Class', 'Admission Date', 
    'Site No', 'Address', 'Contact 1', 'Contact 2', 'Landline', 'Email', 
    'Share No', 'No of Shares', 'Share Capital', 'Gender', 'Caste', 'Aadhaar', 
    'Voter ID', 'PAN', 'Member Status', 'Nominee', 'Relationship', 'Total Amount', 
    'Paid Amount', 'Remaining', 'Payment Mode', 'Signature', 'Area Sqft', 'Area Cents',
    'Name of Company', 'Power Available', 'Employees Men', 'Employees Women', 
    'Total Employees', 'Date of Starting', 'Patta No', 'Patta Type', 'Village', 
    'Document No'
]);

// Fetch rows
$sql = "SELECT * FROM members";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Convert NULL values into "NULL" text
        $cleanRow = array_map(function($value) {
            return $value === null ? 'NULL' : $value;
        }, $row);

        fputcsv($output, $cleanRow);
    }
}

fclose($output);
$conn->close();
?>