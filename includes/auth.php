<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function login($username, $password) {

        $stmt = $this->pdo->prepare("
            SELECT * FROM users 
            WHERE username = :username AND password = :password
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username,
            ':password' => $password
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id']   = $user['iduser'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['roletype'];
            $_SESSION['logged_in'] = true;
            return true;
        }

        return false;
    }

    public function logout() {
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function checkAccess($requiredRole = null) {

        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }

        if ($requiredRole && $_SESSION['role'] != $requiredRole) {
            header('Location: /index.php');
            exit;
        }
    }
    
    // Menambahkan metode untuk pengecekan permission berdasarkan role
    public function hasPermission($permission) {
        // Untuk saat ini, kita menggunakan role-based permissions sederhana
        $role = $_SESSION['role'] ?? '';
        
        // Definisikan permissions berdasarkan role
        $permissions = [
            'Admin' => ['view_inventory', 'manage_inventory', 'view_procurement', 'manage_procurement', 'manage_users'],
            'Inventory' => ['view_inventory', 'manage_inventory'],
            'Procurement' => ['view_procurement', 'manage_procurement'],
            'Manager' => ['view_inventory', 'view_procurement', 'manage_procurement'],
            'Leader' => ['view_inventory', 'view_procurement']
        ];
        
        // Cek apakah role memiliki permission yang diminta
        if (isset($permissions[$role]) && in_array($permission, $permissions[$role])) {
            return true;
        }
        
        return false;
    }
    
    // Metode untuk memerlukan permission tertentu
    public function requirePermission($permission) {
        if (!$this->hasPermission($permission)) {
            header('Location: /index.php');
            exit;
        }
    }
    
    // Metode untuk mengecek leader type
    public function getLeaderType() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        // Use $this->pdo instead of global $pdo
        $stmt = $this->pdo->prepare("SELECT leader_type FROM users WHERE iduser = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['leader_type'] : null;
    }
    
    // Metode untuk mengecek apakah user adalah leader dengan type tertentu
    public function isLeaderType($type) {
        if (($this->isLoggedIn()) && ($_SESSION['role'] === 'Leader')) {
            $leaderType = $this->getLeaderType();
            return $leaderType === $type;
        }
        return false;
    }
}

$auth = new Auth($pdo);