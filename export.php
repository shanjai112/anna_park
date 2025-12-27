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
    'Member No', 
    'Member Name', 
    'Member Type', 
    'Member Class',
    'Admission Date', 
    'Site No', 
    'Address',
    'contactNumber1',
    'contactNumber2',
    'lanlineNumber',
    'email',
    'Share No', 
    'No Of Shares',
    'shareCaptial', 
    'Gender',
    'Caste', 
    'Aadhaar Number', 
    'Family Card', 
    'Voter ID', 
    'PAN Number',
    'Admission Fee Paid',
    'Nominee Name', 
    'Relationship',
    'Total Amount', 
    'Paid Amount', 
    'Remaining Amount',
    'Mode Of Payment',  
    'Signature',
    // NEW SITE DETAILS COLUMNS ADDED
    'Area in Sqft',
    'Area in Cents',
    'N to S (West Side)',
    'E to W (North Side)',
    'E to W (South Side)',
    'N to S (East Side)',
    'Boundary East (SF.NO.)',
    'Boundary West (SF.NO.)',
    'Boundary North (SF.NO.)',
    'Boundary South (SF.NO.)',
    'Patta No',
    'Patta Type',
    'Village',
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