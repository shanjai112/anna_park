<?php
require 'db.php';

$stmt = $pdo->query("SELECT * FROM receipts ORDER BY id DESC");
$receipts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Receipt Dashboard</title>
</head>
<body>

<h2>Receipts</h2>

<table border="1" cellpadding="8">
  <tr>
    <th>Receipt No</th>
    <th>Name</th>
    <th>Amount</th>
    <th>Action</th>
  </tr>

  <?php foreach ($receipts as $r): ?>
  <tr>
    <td><?= htmlspecialchars($r['receipt_no']) ?></td>
    <td><?= htmlspecialchars($r['name']) ?></td>
    <td><?= number_format($r['amount'], 2) ?></td>
    <td>
      <a href="print_receipt.php?id=<?= $r['id'] ?>" target="_blank">
        Print Receipt
      </a>
    </td>
  </tr>
  <?php endforeach; ?>

</table>

</body>
</html>
