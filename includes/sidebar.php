<nav class="sidebar">
    <div class="sidebar-menu">
        <div class="sidebar-header">
            <h3>Internal Management System</h3>
        </div>
        
        <ul class="sidebar-nav">
            <li>
                <a href="/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li>
                <a href="/pages/procurement.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'procurement.php' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Procurement</span>
                </a>
            </li>
            <li>
                <a href="/pages/master-data.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'master-data.php' ? 'active' : ''; ?>">
                    <i class="fas fa-database"></i>
                    <span>Master Data</span>
                </a>
            </li>
            <?php if ($auth->hasPermission('manage_users')): ?>
            <li>
                <a href="/pages/user-management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'user-management.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>User Management</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>