<?php
// auth.php
session_start();
require_once __DIR__ . '/db.php';

function attempt_login($username, $password) {
    global $mysqli;
    $sql = "SELECT * FROM user WHERE username = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    if (!$user) return false;
    if ($password === $user['password']) {
        $_SESSION['user'] = [
            'iduser' => $user['iduser'],
            'nama' => $user['nama'],
            'username' => $user['username'],
            'rolestype' => (int)$user['rolestype']
        ];
        return true;
    }
    return false;
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: /public/login.php");
        exit;
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function logout() {
    session_unset();
    session_destroy();
}
