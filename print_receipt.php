<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db.php';

$memberNo = isset($_GET['memberNo']) ? (int)$_GET['memberNo'] : 0;

// Fetch member details
$stmt = $pdo->prepare("SELECT memberNo, memberName, siteNo FROM members WHERE memberNo = ?");
$stmt->execute([$memberNo]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    die("Invalid Member");
}

// Initialize receipt number
$receiptNo = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_receipt'])) {

    try {
        $stmt = $pdo->prepare("
            INSERT INTO receipts
            (memberNo, siteNo, particulars1, amount1,
             particulars2, amount2, particulars3, amount3, receipt_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            (int)$_POST['memberNo'],
            $_POST['siteNo'],
            $_POST['p1'],
            (float)$_POST['a1'],
            $_POST['p2'] ?: null,
            $_POST['a2'] !== '' ? (float)$_POST['a2'] : null,
            $_POST['p3'] ?: null,
            $_POST['a3'] !== '' ? (float)$_POST['a3'] : null,
            date('Y-m-d')
        ]);

        // Get auto-incremented receipt number
        $receiptNo = $pdo->lastInsertId();

    } catch (PDOException $e) {
        die("Insert Failed: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Adjustment Receipt</title>
<style>
body { font-family: Arial, sans-serif; }
.receipt { width: 750px; margin: auto; border: 1px solid #000; padding: 20px; }
.center { text-align: center; }
.row { display: flex; justify-content: space-between; margin-top: 10px; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
table, th, td { border: 1px solid #000; }
th, td { padding: 8px; }
.footer { margin-top: 40px; display: flex; justify-content: space-between; }

@media print {
    button, input { border: none; }
    button { display: none; }
}
</style>
</head>
<body>

<form method="POST">

<button type="submit">Save & Print</button>

<div class="receipt">

    <div class="center">
        <h3>ANNA INDUSTRIAL PARK</h3>
        <strong>(THE ARIGNAR ANNA CO-OPERATIVE INDUSTRIAL ESTATE LTD., IND NO.1807)</strong><br>
        Coimbatore - 641 659
    </div>

    <div class="center"><strong>ADJUSTMENT RECEIPT</strong></div>
    <div class="center"><strong>Receipt No: <?= htmlspecialchars($receiptNo ?? 'Pending') ?></strong></div>

    <div class="row">
        <div><strong>Site No:</strong> <?= htmlspecialchars($member['siteNo']) ?></div>
        <div><strong>Date:</strong> <?= date("d-m-Y") ?></div>
    </div>

    <div class="row">
        <div><strong>M.No:</strong> <?= htmlspecialchars($member['memberNo']) ?></div>
        <div><strong>Name:</strong> <?= htmlspecialchars($member['memberName']) ?></div>
    </div>

    <table>
        <tr>
            <th>Particulars</th>
            <th>Amount</th>
        </tr>
        <tr>
            <td><input name="p1" required value="<?= htmlspecialchars($_POST['p1'] ?? '') ?>"></td>
            <td><input type="text" name="a1" inputmode="decimal" pattern="[0-9]*\.?[0-9]+" value="<?= htmlspecialchars($_POST['a1'] ?? '') ?>"></td>
        </tr>
        <tr>
            <td><input name="p2" value="<?= htmlspecialchars($_POST['p2'] ?? '') ?>"></td>
            <td><input type="text" name="a2" inputmode="decimal" pattern="[0-9]*\.?[0-9]+" value="<?= htmlspecialchars($_POST['a2'] ?? '') ?>"></td>
        </tr>
        <tr>
            <td><input name="p3" value="<?= htmlspecialchars($_POST['p3'] ?? '') ?>"></td>
            <td><input type="text" name="a3" inputmode="decimal" pattern="[0-9]*\.?[0-9]+" value="<?= htmlspecialchars($_POST['a3'] ?? '') ?>"></td>
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

<?php if ($receiptNo): ?>
<script>
    window.print(); // Automatically opens print dialog after saving
</script>
<?php endif; ?>

</body>
</html>
