<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice</title>
<link rel="stylesheet" href="css/index.css">
</head>
<body>

<div class="navbar">
  <h1 class="navbar-title">Invoice</h1>
  <div class="navbar-menu">
    <a href="index.php" class="active">Invoice</a>
    <a href="admin.php">Admin</a>
  </div>
</div>

<div class="page-content">
  <div class="toolbar" id="invoice-toolbar">
    <button type="button" id="print-btn">🖨 Print / Save as PDF</button>
    <a class="btn-link" id="admin-link" href="admin.php">Edit in admin</a>
  </div>

  <div class="page-wrapper" id="invoice-root" data-loading>Loading…</div>
</div>

<script src="js/invoice-render.js"></script>
<script src="js/frontend.js"></script>
</body>
</html>
