<?php
// _nav.php
$user = current_user();
?>
<div class="topbar">
  <div class="brand">SIMBA</div>
  <div class="right">
    Halo, <?=htmlspecialchars($user['nama'] ?? $user['username'] ?? 'Tamu')?> |
    <a href="/public/logout.php">Logout</a>
  </div>
</div>
<hr>
