<<<<<<< HEAD
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

// Fetch rows with explicit columns in correct order
$sql = "SELECT 
    memberNo, memberName, memberType, memberClass, admissionDate, 
    siteNo, address, contactNumber1, contactNumber2, lanlineNumber, email, 
    shareNo, noOfShares, ShareCapitalAmount, gender, caste, aadhaarNumber, 
    voterId, panNumber, admissionFeePaid, nomineeName, relationship, totalAmount, 
    paidAmount, remainingAmount, modeOfPayment, signature, areaInSqft, areaInCents,
    nameOfCompany, powerAvailable, employeesMen, employeesWomen, 
    totalEmployees, dateOfStarting, pattaNo, pattaType, village, 
    documentNo 
FROM members";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Format Aadhaar to prevent scientific notation in Excel
        if (!empty($row['aadhaarNumber']) && is_numeric($row['aadhaarNumber']) && $row['aadhaarNumber'] != 'died') {
            // Prepend a non-breaking space or use Excel formula trick if needed, 
            // but for a true CSV, a simple number string might be mangled by Excel.
            // Using ="NUMBER" is the most robust way for Excel to treat it as a string.
            $val = number_format((float)$row['aadhaarNumber'], 0, '', '');
            $row['aadhaarNumber'] = '="' . $val . '"';
        }

        // Convert NULL values into empty strings for cleaner CSV
        $cleanRow = array_map(function($value) {
            return $value === null ? '' : $value;
        }, $row);

        fputcsv($output, $cleanRow);
    }
}

fclose($output);
$conn->close();
=======
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
>>>>>>> b116036404b351ab11aafc29c1efd63486b023e6
?>