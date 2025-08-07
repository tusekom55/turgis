<?php
require_once '../config.php';
require_once '../utils/security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Sadece POST metodu kabul edilir']);
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['error' => 'Kullanıcı adı ve şifre gerekli']);
    exit;
}

try {
    $conn = db_connect();
    
    // Admin kullanıcısını kontrol et
    $stmt = $conn->prepare('SELECT id, password, role FROM users WHERE username = ? AND role = "admin"');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Şifre kontrolü (düz metin - test için)
        if ($password === $user['password']) {
            // Session başlat
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $username;
            
            // Son giriş zamanını güncelle
            $update_stmt = $conn->prepare('UPDATE users SET son_giris = NOW() WHERE id = ?');
            $update_stmt->bind_param('i', $user['id']);
            $update_stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Giriş başarılı',
                'user' => [
                    'id' => $user['id'],
                    'username' => $username,
                    'role' => $user['role']
                ]
            ]);
        } else {
            echo json_encode(['error' => 'Hatalı şifre']);
        }
    } else {
        echo json_encode(['error' => 'Admin kullanıcısı bulunamadı']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?> 