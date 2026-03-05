<?php
session_start();

// Authentication Check
if (!isset($_SESSION['user'])) {
    die("Authentication required");
}

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "gov";

// Database connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Clear any previous output (prevents "unreadable content" errors due to whitespace)
if (ob_get_length()) ob_clean();

// Set headers for Word document download
// Note: We use .doc instead of .docx because the HTML-to-Word trick is most 
// compatible with the legacy .doc extension in modern Microsoft Word.
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=\"member_addresses.doc\"");
header("Pragma: no-cache");
header("Expires: 0");

// Fetch member names and addresses
$sql = "SELECT memberName, address FROM members ORDER BY CAST(memberNo AS UNSIGNED) ASC";
$result = $conn->query($sql);

echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>";
echo "<head>";
echo "<meta charset='utf-8'>";
echo "<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>90</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->";
echo "<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.4; }
    .member-block { margin-bottom: 25px; page-break-inside: avoid; }
    .name-line { font-weight: bold; font-size: 14pt; }
    .address-line { font-size: 12pt; }
</style>";
echo "</head>";
echo "<body>";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $name = $row['memberName'];
        $fullAddress = $row['address'];
        
        $parts = explode(", ", $fullAddress);
        $door = isset($parts[0]) ? trim($parts[0]) : "";
        $street = isset($parts[1]) ? trim($parts[1]) : "";
        $city = isset($parts[2]) ? trim($parts[2]) : "";
        $pincode = isset($parts[3]) ? trim($parts[3]) : "";
        
        echo "<div class='member-block'>";
        echo "<div class='name-line'>" . htmlspecialchars($name) . "</div>";
        
        $line2 = array_filter([$door, $street]);
        echo "<div class='address-line'>" . htmlspecialchars(implode(", ", $line2)) . "</div>";
        
        $line3 = array_filter([$city, $pincode]);
        echo "<div class='address-line'>" . htmlspecialchars(implode(", ", $line3)) . "</div>";
        echo "</div>";
    }
} else {
    echo "No members found.";
}

echo "</body>";
echo "</html>";

$conn->close();
exit();
?>


