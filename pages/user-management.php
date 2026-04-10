<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and has admin access
$auth->checkAccess('Admin');

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_leader_type'])) {
        $iduser = $_POST['iduser'];
        $leader_type = $_POST['leader_type'];
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET leader_type = ? WHERE iduser = ?");
            $stmt->execute([$leader_type, $iduser]);
            $message = "Leader type berhasil diupdate";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    } elseif (isset($_POST['add_user'])) {
        $iduser = 'USR-' . date('YmdHis');
        $username = trim($_POST['username']);
        $nama = trim($_POST['nama']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = trim($_POST['email']);
        $roletype = $_POST['roletype'];
        $leader_type = !empty($_POST['leader_type']) ? $_POST['leader_type'] : null;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (iduser, username, nama, password, email, roletype, leader_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$iduser, $username, $nama, $password, $email, $roletype, $leader_type]);
            $message = "User berhasil ditambahkan";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    } elseif (isset($_POST['update_user'])) {
        $iduser = $_POST['iduser'];
        $nama = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $roletype = $_POST['roletype'];
        $leader_type = !empty($_POST['leader_type']) ? $_POST['leader_type'] : null;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET nama = ?, email = ?, roletype = ?, leader_type = ? 
                WHERE iduser = ?
            ");
            $stmt->execute([$nama, $email, $roletype, $leader_type, $iduser]);
            $message = "User berhasil diupdate";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get all users with leader_type
$stmt = $pdo->query("
    SELECT iduser, username, nama, email, roletype, leader_type, created_at 
    FROM users 
    ORDER BY roletype, nama
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->query("
    SELECT 
        roletype,
        leader_type,
        COUNT(*) as count
    FROM users
    GROUP BY roletype, leader_type
    ORDER BY roletype, leader_type
");
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Simba</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .user-management-container {
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #067A67;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #067A67;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .users-table thead {
            background: #067A67;
            color: white;
        }
        
        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
        }
        
        .users-table tbody tr {
            border-bottom: 1px solid #eee;
        }
        
        .users-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-admin { background: #dc3545; color: white; }
        .badge-manager { background: #007bff; color: white; }
        .badge-leader { background: #ffc107; color: #333; }
        .badge-procurement { background: #28a745; color: white; }
        .badge-inventory { background: #17a2b8; color: white; }
        .badge-staff { background: #6c757d; color: white; }
        
        .badge-leader-type {
            background: #e9ecef;
            color: #495057;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #067A67;
            color: white;
        }
        
        .btn-primary:hover {
            background: #045d4d;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-dialog {
            background: white;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #067A67;
            box-shadow: 0 0 0 3px rgba(6, 122, 103, 0.1);
        }
        
        .leader-type-selector {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .leader-type-option {
            flex: 1;
            min-width: 120px;
        }
        
        .leader-type-radio {
            display: none;
        }
        
        .leader-type-label {
            display: block;
            padding: 15px;
            text-align: center;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .leader-type-radio:checked + .leader-type-label {
            border-color: #067A67;
            background: #067A67;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="user-management-container">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-users-cog"></i> User Management</h1>
            <p>Kelola user dan leader type assignment</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <?php
            $roleColors = [
                'Admin' => '#dc3545',
                'Manager' => '#007bff',
                'Leader' => '#ffc107',
                'Procurement' => '#28a745',
                'Inventory' => '#17a2b8',
                'Staff' => '#6c757d'
            ];
            
            $groupedStats = [];
            foreach ($stats as $stat) {
                $role = $stat['roletype'];
                if (!isset($groupedStats[$role])) {
                    $groupedStats[$role] = ['total' => 0, 'types' => []];
                }
                $groupedStats[$role]['total'] += $stat['count'];
                if ($stat['leader_type']) {
                    $groupedStats[$role]['types'][$stat['leader_type']] = $stat['count'];
                }
            }
            
            foreach ($groupedStats as $role => $data): ?>
                <div class="stat-card" style="border-left-color: <?php echo $roleColors[$role] ?? '#067A67'; ?>">
                    <div class="stat-label"><?php echo htmlspecialchars($role); ?></div>
                    <div class="stat-value"><?php echo $data['total']; ?></div>
                    <?php if (!empty($data['types'])): ?>
                        <div style="margin-top: 10px; font-size: 12px;">
                            <?php foreach ($data['types'] as $type => $count): ?>
                                <div><?php echo htmlspecialchars($type); ?>: <?php echo $count; ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Users Table -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>All Users</h2>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add User
            </button>
        </div>
        
        <table class="users-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Leader Type</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['nama']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo strtolower($user['roletype']); ?>">
                                <?php echo htmlspecialchars($user['roletype']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['roletype'] === 'Leader'): ?>
                                <span class="badge badge-leader-type">
                                    <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($user['leader_type']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick="editUser('<?php echo htmlspecialchars($user['iduser']); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>Edit User</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="iduser" id="edit_iduser">
                    
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" name="nama" id="edit_nama" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="roletype" id="edit_roletype" onchange="toggleLeaderType()" required>
                            <option value="Admin">Admin</option>
                            <option value="Manager">Manager</option>
                            <option value="Leader">Leader</option>
                            <option value="Procurement">Procurement</option>
                            <option value="Inventory">Inventory</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="leaderTypeGroup" style="display: none;">
                        <label class="form-label">Leader Type</label>
                        <div class="leader-type-selector">
                            <div class="leader-type-option">
                                <input type="radio" name="leader_type" value="Teknisi" id="edit_teknisi" class="leader-type-radio">
                                <label for="edit_teknisi" class="leader-type-label">
                                    <i class="fas fa-tools"></i><br>Teknisi
                                </label>
                            </div>
                            <div class="leader-type-option">
                                <input type="radio" name="leader_type" value="Marketing" id="edit_marketing" class="leader-type-radio">
                                <label for="edit_marketing" class="leader-type-label">
                                    <i class="fas fa-bullhorn"></i><br>Marketing
                                </label>
                            </div>
                            <div class="leader-type-option">
                                <input type="radio" name="leader_type" value="Office" id="edit_office" class="leader-type-radio">
                                <label for="edit_office" class="leader-type-label">
                                    <i class="fas fa-building"></i><br>Office
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>Add User</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" id="addForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" name="nama" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="roletype" id="add_roletype" onchange="toggleAddLeaderType()" required>
                            <option value="Admin">Admin</option>
                            <option value="Manager">Manager</option>
                            <option value="Leader">Leader</option>
                            <option value="Procurement">Procurement</option>
                            <option value="Inventory">Inventory</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="addLeaderTypeGroup" style="display: none;">
                        <label class="form-label">Leader Type</label>
                        <div class="leader-type-selector">
                            <div class="leader-type-option">
                                <input type="radio" name="leader_type" value="Teknisi" id="add_teknisi" class="leader-type-radio">
                                <label for="add_teknisi" class="leader-type-label">
                                    <i class="fas fa-tools"></i><br>Teknisi
                                </label>
                            </div>
                            <div class="leader-type-option">
                                <input type="radio" name="leader_type" value="Marketing" id="add_marketing" class="leader-type-radio">
                                <label for="add_marketing" class="leader-type-label">
                                    <i class="fas fa-bullhorn"></i><br>Marketing
                                </label>
                            </div>
                            <div class="leader-type-option">
                                <input type="radio" name="leader_type" value="Office" id="add_office" class="leader-type-radio">
                                <label for="add_office" class="leader-type-label">
                                    <i class="fas fa-building"></i><br>Office
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const usersData = <?php echo json_encode($users); ?>;
        
        function toggleLeaderType() {
            const roleType = document.getElementById('edit_roletype').value;
            const leaderTypeGroup = document.getElementById('leaderTypeGroup');
            leaderTypeGroup.style.display = roleType === 'Leader' ? 'block' : 'none';
        }
        
        function toggleAddLeaderType() {
            const roleType = document.getElementById('add_roletype').value;
            const leaderTypeGroup = document.getElementById('addLeaderTypeGroup');
            leaderTypeGroup.style.display = roleType === 'Leader' ? 'block' : 'none';
        }
        
        function editUser(iduser) {
            const user = usersData.find(u => u.iduser === iduser);
            if (!user) return;
            
            document.getElementById('edit_iduser').value = user.iduser;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_nama').value = user.nama;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_roletype').value = user.roletype;
            
            // Set leader type
            if (user.leader_type) {
                const radio = document.querySelector(`input[name="leader_type"][value="${user.leader_type}"]`);
                if (radio) radio.checked = true;
            }
            
            toggleLeaderType();
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }
        
        function openAddModal() {
            document.getElementById('addForm').reset();
            document.getElementById('addModal').classList.add('show');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>
