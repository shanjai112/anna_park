<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db.php';

$current_date = date('Y-m-d');

$memberNo = isset($_GET['memberNo']) ? intval($_GET['memberNo']) : 0;

$stmt = $pdo->prepare("SELECT memberNo, memberName, siteNo FROM members WHERE memberNo=?");
$stmt->execute([$memberNo]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Invalid Member");
}
if (isset($_POST['save_receipt'])) {

    $stmt = $pdo->prepare("
        INSERT INTO receipts 
        (memberNo, siteNo, particulars1, amount1, particulars2, amount2, particulars3, amount3, receipt_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_POST['memberNo'],
        $_POST['siteNo'],
        $_POST['p1'],
        $_POST['a1'],
        $_POST['p2'],
        $_POST['a2'],
        $_POST['p3'],
        $_POST['a3'],
        date('Y-m-d')
    ]);

    echo "<script>window.print()</script>";
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Adjustment Receipt</title>

<style>
body {
    font-family: Arial, sans-serif;
}

.receipt {
    width: 750px;
    margin: auto;
    border: 1px solid #000;
    padding: 20px;
}

.center {
    text-align: center;
}

.row {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

table, th, td {
    border: 1px solid #000;
}

th, td {
    padding: 8px;
}

.right {
    text-align: right;
}

.footer {
    margin-top: 40px;
    display: flex;
    justify-content: space-between;
}

@media print {
    button {
        display: none;
    }
}
</style>
<button onclick="window.print()">Print</button>

</head>

<body>

<form method="POST" id="receiptForm">

<div class="receipt">

    <div class="center">
        <h3>ANNA INDUSTRIAL PARK</h3>
        <strong>(THE ARIGNAR ANNA CO-OPERATIVE INDUSTRIAL ESTATE LTD., IND NO.1807)</strong><br>
        Coimbatore - 641 659
    </div>  
    <div class="center">
        <strong>ADJUSTMENT RECEIPT</strong>
    </div>
    <div class="row">
        <div><strong>Site No:</strong> <?= $member['siteNo'] ?></div>
        <div><strong>Date:</strong> <?= date("d-m-Y") ?></div>
    </div>
    <div class="row">
        <div><strong>M.No:</strong> <?= $member['memberNo'] ?></div>
        <div><strong>Name:</strong> <?= htmlspecialchars($member['memberName']) ?></div>
    </div>
    <table>
        <tr>
            <th>Particulars</th>
            <th>Amount</th>
        </tr>

        <tr>
            <td><input name="p1" required></td>
            <td><input name="a1" required></td>
        </tr>

        <tr>
            <td><input name="p2"></td>
            <td><input name="a2"></td>
        </tr>

        <tr>
            <td><input name="p3"></td>
            <td><input name="a3"></td>
        </tr>
    </table>

    <p>* Cheques are subject to realisation</p>

    <div style="text-align:right;margin-top:40px;">
        <strong>Authorised Signatory</strong>
    </div>

</div>

<input type="hidden" name="save_receipt" value="1">
<input type="hidden" name="memberNo" value="<?= $member['memberNo'] ?>">
<input type="hidden" name="siteNo" value="<?= $member['siteNo'] ?>">

</form>

</body>
</html>
