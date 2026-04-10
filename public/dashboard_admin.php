<?php
require_once __DIR__ . '/../middleware.php';
require_role([1]);
?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin</title>
<link rel="stylesheet" href="/asset/css/style.css"></head><body>
<?php include __DIR__.'/../_nav.php'; ?>
<h1>Dashboard Admin</h1>
<ul>
  <li><a href="/pages/barang/list.php">Master Barang</a></li>
  <li><a href="/pages/request/list.php">Purchase Requests</a></li>
  <li><a href="/pages/po/list.php">Purchase Orders</a></li>
  <li><a href="/pages/inventory/list.php">Inventory</a></li>
</ul>
</body></html>
