<?php
session_start();
header("Content-Type: application/json");

$servername = "localhost";
$db_username = "root"; // change if needed
$db_password = "";      // change if needed
$dbname = "gov";

// --- Configuration for Change Control ---
// Fields that REQUIRE Administrator approval for modification (matches script.js)
$admin_approval_fields = [
    'memberName', 'siteNo', 'totalAmount', 'paidAmount', 'shareNo', 'noOfShares'
];
// --- Configuration End ---

// Database connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

$action = $_GET['action'] ?? '';

// ---------- Helpers ----------


function json_error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(["success" => false, "error" => $msg]);
    exit;
}

function require_auth($roles = []) {
    // Note: The structure of $_SESSION['user'] is set in the login case
    if (!isset($_SESSION['user'])) {
        // HTTP 401: Unauthorized
        json_error("Authentication required", 401); 
    }
    if ($roles && !in_array($_SESSION['user']['role'], $roles)) {
        // HTTP 403: Forbidden
        json_error("Permission denied", 403);
    }
    // Return current user role for use in functions
    return $_SESSION['user']['username'];
}

// Helper function for dynamic bind_param due to reference requirement (critical for multi-field insert/archive)
function make_values_referable($arr){
    $refs = array();
    foreach($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}

// Helper to fetch single member data
function getMemberData($conn, $memberNo) {
    $stmt = $conn->prepare("SELECT * FROM members WHERE memberNo=?");
    $stmt->bind_param("s", $memberNo);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();
    return $member;
}


// ----------------------------------------------------
// ✅ SECURITY FIX: Blanket Access Control Check
// ----------------------------------------------------
$public_actions = ['login', 'logout', 'sessionCheck'];
if (!in_array($action, $public_actions)) {
    require_auth();
}

// ---------- Routing ----------
switch ($action) {
    // ----------------- NEW: FINANCIAL SUMMARY -----------------
    case "getFinancialSummary":
        // Financial summary accessible to any logged-in user
        require_auth(); 

        // Calculate sums for Total Commitment, Total Paid, and Total Remaining (calculated column)
        $sql = "SELECT 
                    SUM(totalAmount) AS totalCommitment,
                    SUM(paidAmount) AS totalPaid,
                    SUM(totalAmount - paidAmount) AS totalRemaining
                FROM members"; 
                
        $result = $conn->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            // Ensure values are numbers before encoding, default to 0 if NULL
            $summary = [
                "totalCommitment" => (float)($row['totalCommitment'] ?? 0),
                "totalPaid" => (float)($row['totalPaid'] ?? 0),
                "totalRemaining" => (float)($row['totalRemaining'] ?? 0),
            ];
            echo json_encode(["success" => true, "data" => $summary]);
        } else {
            // Return zeros if no records exist or query fails
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "Failed to fetch financial summary."]);
        }
        break;


    // ----------------- LOGIN -----------------
    case "login":
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            json_error("Username and password required", 400);
        }

        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if ($user && $password === $user['password']) {
            // Map DB roles to App Roles (admin -> administrator, user -> head1)
            $app_role = $user['role'] === "admin" ? "administrator" : "head1";

            $_SESSION['user'] = [
                "id" => $user['id'],
                "username" => $user['username'],
                "role" => $app_role
            ];
            echo json_encode([
                "success" => true,
                "username" => $_SESSION['user']['username'],
                "role" => $_SESSION['user']['role']
            ]);
        } else {
            // Check if demo users need initialization (only on failed login for empty table)
            $result = $conn->query("SELECT COUNT(*) as count FROM users");
            $row = $result->fetch_assoc();
            if ($row['count'] == 0) {
                // Insert a basic admin user and a head1 user
                $conn->query("INSERT INTO users (username, password, role) VALUES ('administrator', 'adminpass', 'admin')");
                $conn->query("INSERT INTO users (username, password, role) VALUES ('head1', 'headpass', 'user')");
                
                // Retry login attempt after creating users
                $stmt->execute();
                $res = $stmt->get_result();
                $user = $res->fetch_assoc();

                if ($user && $password === $user['password']) {
                    $app_role = $user['role'] === "admin" ? "administrator" : "head1";
                    $_SESSION['user'] = [
                        "id" => $user['id'],
                        "username" => $user['username'],
                        "role" => $app_role
                    ];
                    echo json_encode([
                        "success" => true,
                        "username" => $_SESSION['user']['username'],
                        "role" => $_SESSION['user']['role']
                    ]);
                    break;
                }
            }
            
            // Final failure message
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Invalid credentials"
            ]);
        }
        break;

    // ----------------- LOGOUT -----------------
    case "logout":
        session_unset();
        session_destroy();
        echo json_encode(["success" => true]);
        break;

    // ----------------- SESSION CHECK -----------------
    case "sessionCheck":
        if (isset($_SESSION['user'])) {
            echo json_encode([
                "loggedIn" => true,
                "username" => $_SESSION['user']['username'],
                "role" => $_SESSION['user']['role']
            ]);
        } else {
            echo json_encode(["loggedIn" => false]);
        }
        break;

    // ----------------- MEMBERS -----------------
    case "getMembers":
        // Sorting by memberNo numerically, as it's defined as INT in the schema
        $result = $conn->query("SELECT * FROM members ORDER BY CAST(memberNo AS UNSIGNED) ASC");
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode($rows);
        break;

    case "addMember":
        require_auth(["head1"]);

        // 1. DATA CLEANUP AND PREPARATION (Handling Mismatches)
        file_put_contents("db_error_log.txt", "[" . date("Y-m-d H:i:s") . "] Start Add Member Request\n", FILE_APPEND);
        
$memberNo            = $_POST['new-memberNo'] ?? null;
        $memberName          = $_POST['new-memberName'] ?? null;
        $memberType          = $_POST['new-memberType'] ?? null;
        $memberClass         = $_POST['new-memberClass'] ?? null;
        $admissionDate       = !empty($_POST['new-admissionDate']) ? date('Y-m-d', strtotime($_POST['new-admissionDate'])) : null;
        $siteNo              = $_POST['new-siteNo'] ?? null;
        
        // Address processing
        $address = implode(', ', array_filter([
            $_POST['new-address-door'] ?? '', 
            $_POST['new-address-street'] ?? '', 
            $_POST['new-address-city'] ?? '', 
            $_POST['new-address-pincode'] ?? ''
        ]));

        $contactNumber1      = $_POST['new-contactNumber1'] ?? null;
        $contactNumber2      = $_POST['new-contactNumber2'] ?? null;
        $lanlineNumber       = $_POST['new-lanlineNumber'] ?? null;
        $email               = $_POST['new-email'] ?? null;
        $shareNo             = $_POST['new-shareNo'] ?? null;
        $noOfShares          = (int)($_POST['new-noOfShare'] ?? 0);
        $ShareCapitalAmount  = $noOfShares * 500;
        $gender              = $_POST['new-gender'] ?? null;
        $caste               = $_POST['new-caste'] ?? null;
        $aadhaarNumber       = $_POST['new-aadhaar'] ?? null;
        $voterId             = $_POST['new-voterID'] ?? null;
        $panNumber           = $_POST['new-pan'] ?? null;
        $admissionFeePaid    = $_POST['new-admissionFeePaid'] ?? null;
        $nomineeName         = $_POST['new-nomineeName'] ?? null;
        $relationship        = $_POST['new-relationship'] ?? null;
        $totalAmount         = (float)($_POST['new-totalAmount'] ?? 0);
        $paidAmount          = (float)($_POST['new-paidAmount'] ?? 0);
        $remainingAmount     = (string)($totalAmount - $paidAmount);
        $modeOfPayment       = $_POST['new-modeOfPayment'] ?? null;
        $signature           = $_POST['new-signature'] ?? null;
        $areaInSqft          = (float)($_POST['new-areaInSqft'] ?? 0);
        $areaInCents         = (float)($_POST['new-areaInCents'] ?? 0);
        
        // RENAMED FIELDS (Must match your DB column names)
        $nameOfCompany       = $_POST['new-nameOfCompany'] ?? null;
        $powerAvailable      = $_POST['new-powerAvailable'] ?? null;
        $employeesMen        = $_POST['new-employeesMen'] ?? null;
        $employeesWomen      = $_POST['new-employeesWomen'] ?? null;
        $totalEmployees      = $_POST['new-totalEmployees'] ?? null;
        $dateOfStarting      = $_POST['new-dateOfStarting'] ?? null;
        
        $pattaNo             = $_POST['new-pattaNo'] ?? null;
        $pattaType           = $_POST['new-pattaType'] ?? null;
        $village             = $_POST['new-village'] ?? null;
        $documentNo          = $_POST['new-documentNo'] ?? null;
        $profilePicPath      = null;
        
        
        // 2. OPTIONAL: Check for NOT NULL violations 
        if (!$memberNo || !$memberName || !$memberType || !$siteNo || !$contactNumber1) {
             $logMessage = "[" . date("Y-m-d H:i:s") . "] Validation Error: Missing required fields (No:$memberNo, Name:$memberName, Type:$memberType, Site:$siteNo, Contact:$contactNumber1)\n";
             file_put_contents("db_error_log.txt", $logMessage, FILE_APPEND);
             json_error("Missing required fields: Member No, Name, Type, Site No, or Contact No 1.", 400);
        }

        // 3. EXECUTE INSERT
        $sql = "INSERT INTO members 
            (memberNo, memberName, memberType, memberClass, admissionDate, siteNo, address, contactNumber1, contactNumber2, lanlineNumber, email, shareNo, noOfShares, ShareCapitalAmount, gender, caste, aadhaarNumber, voterId, panNumber, admissionFeePaid, nomineeName, relationship, totalAmount, paidAmount, remainingAmount, modeOfPayment, signature, areaInSqft, areaInCents, nameOfCompany, powerAvailable, employeesMen, employeesWomen, totalEmployees, dateOfStarting, pattaNo, pattaType, village, documentNo, profilePicPath)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
             $logMessage = "[" . date("Y-m-d H:i:s") . "] Prepare Error: " . $conn->error . "\n";
             file_put_contents("db_error_log.txt", $logMessage, FILE_APPEND);
             json_error("Database Prepare Error: " . $conn->error, 500);
        }
        
        // 3. TYPE STRING (40 characters)
        // Robust mapping:
        // s (memberNo - integer, but bind as string for safety)
        // sssssssssss (11 strings)
        // s (noOfShares - integer, bind as string)
        // d (ShareCapital - decimal)
        // ssssssss (8 strings)
        // dds (2 decimals: total, paid; 1 string: remaining)
        // ss (2 strings)
        // dd (2 decimals: areas)
        // sssssssssss (11 strings: company, power, employees(3), date..pic)
        $type_string = "sssssssssssssdssssssssddsssddsssssssssss";
        
        try {
            $bind_success = $stmt->bind_param($type_string, 
                $memberNo, $memberName, $memberType, $memberClass, $admissionDate,
                $siteNo, $address, $contactNumber1, $contactNumber2, $lanlineNumber,
                $email, $shareNo, $noOfShares, $ShareCapitalAmount,
                $gender, $caste, $aadhaarNumber, $voterId, $panNumber,
                $admissionFeePaid, $nomineeName, $relationship, $totalAmount, $paidAmount,
                $remainingAmount, $modeOfPayment, $signature, $areaInSqft, $areaInCents,
                $nameOfCompany, $powerAvailable, $employeesMen,
                $employeesWomen, $totalEmployees, $dateOfStarting,
                $pattaNo, $pattaType, $village, $documentNo, $profilePicPath
            );
            
            if (!$bind_success) {
                 $logMessage = "[" . date("Y-m-d H:i:s") . "] Bind Param Error: " . $stmt->error . "\n";
                 file_put_contents("db_error_log.txt", $logMessage, FILE_APPEND);
                 json_error("Database Bind Error: " . $stmt->error, 500);
            }
        } catch (Exception $e) {
             $logMessage = "[" . date("Y-m-d H:i:s") . "] Bind Param Exception: " . $e->getMessage() . "\n";
             file_put_contents("db_error_log.txt", $logMessage, FILE_APPEND);
             json_error("Database Bind Exception: " . $e->getMessage(), 500);
        } catch (Error $e) {
             $logMessage = "[" . date("Y-m-d H:i:s") . "] Bind Param Fatal Error: " . $e->getMessage() . "\n";
             file_put_contents("db_error_log.txt", $logMessage, FILE_APPEND);
             json_error("Database Bind Fatal Error: " . $e->getMessage(), 500);
        }
        
        if (!$stmt->execute()) {
             // Provides the specific MySQL error message to the client
             json_error("Database Error: " . $stmt->error, 500);
             error_log("AddMember SQL Error: " . $stmt->error);
             
             // Log actual error to a text file as requested
             $logMessage = "[" . date("Y-m-d H:i:s") . "] Insert Error: " . $stmt->error . "\n";
             file_put_contents("db_error_log.txt", $logMessage, FILE_APPEND);
        }
        
        echo json_encode(["success" => true]);
        break;
        
    // NEW ACTION: Direct update for non-critical fields
    case "directUpdateMember":
        // Authentication check: Only head1 can perform direct updates
        require_auth(["head1"]); 
        global $admin_approval_fields;
        
        $data = json_decode(file_get_contents("php://input"), true);
        $field = $data['field'] ?? null;
        $newValue = $data['newValue'] ?? null;
        $memberNo = $data['memberNo'] ?? null;
        
        if (!$field || $newValue === null || !$memberNo) {
            json_error("Missing update parameters.", 400);
        }

        // Security Check: Ensure the field is NOT one that requires admin approval or deletion flag
        if (in_array($field, $admin_approval_fields) || $field === 'DELETE_MEMBER') {
            json_error("Field '$field' requires administrator approval.", 403);
        }
        
        $member = getMemberData($conn, $memberNo);
        if (!$member) {
            json_error("Member not found.", 404);
        }
        
        // Use the raw new value initially
        $update_value = $newValue; 

        // Recalculate remaining amount if paid or total amount changes
        if ($field === 'totalAmount' || $field === 'paidAmount') {
            
            // --- CRITICAL FIX START: Define local variables for binding ---
            
            // 1. Explicitly cast the incoming value to float
            $newAmount = (float)$newValue; 
            
            // 2. Fetch current values, ensuring they are cast to float
            $currentTotal = (float)$member['totalAmount'];
            $currentPaid = (float)$member['paidAmount'];

            // 3. Determine the new state using the updated value
            $newTotal = ($field === 'totalAmount') ? $newAmount : $currentTotal;
            $newPaid = ($field === 'paidAmount') ? $newAmount : $currentPaid;
            
            // 4. Calculate the new remaining amount
            $remainingAmount_val = $newTotal - $newPaid;
            $remainingAmount_str = (string)$remainingAmount_val; // For VARCHAR binding
            
            // 5. Define variables for binding (MUST be defined locally)
            $member_id = $memberNo;
            
            // 6. Prepare the statement to update the specific field AND remainingAmount
            $stmt = $conn->prepare("UPDATE members SET {$field}=?, remainingAmount=? WHERE memberNo=?");
            
            // Binding: d (for the financial amount: totalAmount or paidAmount), s (for remainingAmount - VARCHAR), s (for memberNo)
            // The first variable to bind must be the $newAmount variable (float), regardless of whether it's total or paid.
            if (!$stmt->bind_param("dss", $newAmount, $remainingAmount_str, $member_id)) {
                 json_error("Direct Update Bind Error (Financial): Failed to bind parameters. Check data types and references. " . $stmt->error, 500);
            }
            
            if (!$stmt->execute()) {
                // Return the specific SQL error for easier debugging
                json_error("Update Error (Financial): " . $stmt->error, 500); 
            }
            
            // --- CRITICAL FIX END ---
            
            echo json_encode(["success" => true]);
            break; // Exit the switch case after success
        }


        // Generic direct update query for all other fields
        $stmt = $conn->prepare("UPDATE members SET {$field}=? WHERE memberNo=?");
        
        // Determine binding type based on field (simple string/number detection)
        $type = (is_numeric($newValue) && strpos($field, 'Amount') !== false) ? 'd' : 's';
        
        $member_id = $memberNo; // Define locally for reference
        $stmt->bind_param($type . 's', $update_value, $member_id);
        
        if (!$stmt->execute()) {
             json_error("Update Error: " . $stmt->error, 500);
        }
        
        echo json_encode(["success" => true]);
        break;


    // ----------------- NEW: PHOTO UPLOAD -----------------
    case "uploadPhoto":
        // Only head1 is permitted to upload/change photos
        require_auth(["head1"]); 

        $memberNo = $_POST['memberNo'] ?? null;

        if (!$memberNo) {
            json_error("Member number required for photo upload.", 400);
        }
        
        if (!isset($_FILES['profilePic']) || $_FILES['profilePic']['error'] !== UPLOAD_ERR_OK) {
            json_error("No file uploaded or an upload error occurred.", 400);
        }

        $file = $_FILES['profilePic'];
        $uploadDir = 'uploads/'; // Assuming an 'uploads' directory in the same location
        
        // Ensure upload directory exists and is writable (optional, but good practice)
        if (!is_dir($uploadDir)) {
            // Attempt to create the directory if it doesn't exist
            if (!mkdir($uploadDir, 0755, true)) {
                 json_error("Upload directory does not exist and could not be created. Check server permissions.", 500);
            }
        }
        

        // 1. Validate file type (basic check for common image formats)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes)) {
            json_error("Invalid file type. Only JPEG, PNG, and GIF images are allowed.", 400);
        }

        // 2. Create a unique filename to prevent overwrites and security issues
        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = $memberNo . '_' . uniqid() . '.' . $fileExt;
        $uploadFile = $uploadDir . $fileName;

        // 3. Move the uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
            // 4. Update the database with the file path
            $stmt = $conn->prepare("UPDATE members SET profilePicPath=? WHERE memberNo=?");
            // Use the base filename for storage, not the full path, as the client prepends 'uploads/'
            $stmt->bind_param("ss", $fileName, $memberNo); 
            
            if (!$stmt->execute()) {
                 // Log error and respond
                 error_log("Photo Upload DB Error: " . $stmt->error);
                 // Clean up the uploaded file if DB update fails
                 unlink($uploadFile); 
                 json_error("Database Error: Failed to save photo path.", 500); 
            }
            $stmt->close();

            echo json_encode(["success" => true, "profilePicPath" => $fileName]);
        } else {
            // A more detailed check could use error codes, but this handles the basic file move failure.
            json_error("Failed to move uploaded file. Check directory permissions (must be writable).", 500);
        }
        break;


    // ----------------- REQUESTS -----------------
    case "getRequests":
        $result = $conn->query("SELECT * FROM update_requests ORDER BY requestDate DESC");
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode($rows);
        break;

    case "addRequest":
        $requestedBy = require_auth(["head1"]); // Only head1 can submit requests
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Check for DELETE_MEMBER request and ensure only a head can submit it
        if ($data['field'] === 'DELETE_MEMBER' && $_SESSION['user']['role'] !== 'head1') {
             json_error("Only a Head 1 can request member deletion.", 403);
        }

        $stmt = $conn->prepare("INSERT INTO update_requests 
             (memberNo, memberName, field, currentValue, newValue, reason, requestedBy) 
             VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss",
            $data['memberNo'],
            $data['memberName'],
            $data['field'],
            $data['currentValue'],
            $data['newValue'],
            $data['reason'],
            $requestedBy // Use logged-in username
        );
        $stmt->execute();
        echo json_encode(["success" => true]);
        break;

    case "updateRequestStatus":
        $admin_username = require_auth(["administrator"]);
        global $admin_approval_fields;
        
        $data = json_decode(file_get_contents("php://input"), true);
        $request_id = $data['id'] ?? null;
        $status = $data['status'] ?? null;
        
        if (!$request_id || !$status) {
             json_error("Missing request ID or status.", 400);
        }
        
        // 1. Update the request status
        $stmt = $conn->prepare("UPDATE update_requests SET status=? WHERE id=?");
        $stmt->bind_param("si", $status, $request_id);
        $stmt->execute();

        if ($status === "approved") {
            
            $field = $data['field'] ?? null;
            $memberNo = $data['memberNo'] ?? null;
            
            if ($field === 'DELETE_MEMBER') {
                 // 2a. Handle Member Deletion and Archiving
                 $result = archiveAndDeleteMember($conn, $memberNo, $admin_username);
                 if ($result !== true) {
                     json_error($result['error'], $result['code']);
                 }
                 
            } elseif (in_array($field, $admin_approval_fields)) {
                // 2b. Handle Normal Approved Updates
                
                $newValue = $data['newValue'] ?? null;
                
                // Special field handling for calculated values
                if ($field === "noOfShares") {
                    $newShares = (int)$newValue;
                    $newShareCapital = $newShares * 500;

                    $stmt2 = $conn->prepare("UPDATE members 
                                                 SET noOfShares=?, ShareCapitalAmount=? 
                                                 WHERE memberNo=?");
                    $stmt2->bind_param("iis", $newShares, $newShareCapital, $memberNo);
                    
                    if (!$stmt2->execute()) {
                        json_error("Shares Update Error: " . $stmt2->error, 500);
                    }
                } else {
                    // Normal update for other whitelisted fields
                    $stmt2 = $conn->prepare("UPDATE members SET {$field}=? WHERE memberNo=?");
                    $stmt2->bind_param("ss", $newValue, $memberNo);
                    
                    if (!$stmt2->execute()) {
                        json_error("Field Update Error: " . $stmt2->error, 500);
                    }
                }
            } else {
                json_error("Attempted approval on non-whitelisted or invalid field.", 403);
            }
        }
        echo json_encode(["success" => true]);
        break;

    default:
        echo json_encode(["error" => "Invalid action"]);
}

$conn->close();

// --- Additional Helper Functions ---

function archiveAndDeleteMember($conn, $memberNo, $deletedBy) {

    // 1. Fetch member
    $member = getMemberData($conn, $memberNo);
    if (!$member) {
        return ["success" => false, "error" => "Member not found", "code" => 404];
    }

    // 2. Explicit column list (EXCLUDES deleted_id & deletionDate)
$columns = [
    'memberNo','memberName','memberType','memberClass','admissionDate','siteNo','address',
    'contactNumber1','contactNumber2','lanlineNumber','email','shareNo','noOfShares',
    'ShareCapitalAmount','gender','caste','aadhaarNumber','voterId','panNumber',
    'admissionFeePaid','nomineeName','relationship','totalAmount','paidAmount','remainingAmount',
    'modeOfPayment','signature','areaInSqft','areaInCents',
    'nameOfCompany',          // Name of Company
    'powerAvailable',         // Power Available
    'employeesMen',           // Employees Men
    'employeesWomen',         // Employees Women
    'totalEmployees',         // Total Employees
    'dateOfStarting',         // Date of Starting
    'pattaNo','pattaType','village','documentNo','profilePicPath','deletedBy'
];

    $placeholders = implode(',', array_fill(0, count($columns), '?'));

    // 3. Build values & type string
    $values = [];
    $types  = '';

    foreach ($columns as $col) {
        if ($col === 'deletedBy') {
            $values[] = $deletedBy;
            $types .= 's';
        } else {
            $val = $member[$col] ?? null;

            if (is_int($val))       $types .= 'i';
            elseif (is_float($val)) $types .= 'd';
            else                    $types .= 's';

            $values[] = $val;
        }
    }

    // 4. Insert into deleted_members
    $sql = "INSERT INTO deleted_members (" . implode(',', $columns) . ")
            VALUES ($placeholders)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ["success" => false, "error" => $conn->error, "code" => 500];
    }

    call_user_func_array([$stmt, 'bind_param'], make_values_referable(
        array_merge([$types], $values)
    ));

    if (!$stmt->execute()) {
        error_log("Archive error: " . $stmt->error);
        return ["success" => false, "error" => $stmt->error, "code" => 500];
    }

    $stmt->close();

    // 5. Delete from members
    $del = $conn->prepare("DELETE FROM members WHERE memberNo=?");
    $del->bind_param("i", $memberNo);
    $del->execute();
    $del->close();

    return true;
}

?>