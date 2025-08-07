<?php
/**
 * Admin Login API - Basit Test Sistemi
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST metodu kabul edilir');
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        throw new Exception('Kullanıcı adı ve şifre gereklidir');
    }
    
    // Basit test credentials
    $valid_credentials = [
        'admin' => 'password',
        'admin123' => 'admin123',
        'test' => 'test123'
    ];
    
    if (isset($valid_credentials[$username]) && $valid_credentials[$username] === $password) {
        // Session oluştur
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        
        echo json_encode([
            'success' => true,
            'message' => 'Giriş başarılı',
            'admin' => [
                'id' => 1,
                'username' => $username,
                'login_time' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception('Geçersiz kullanıcı adı veya şifre');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
