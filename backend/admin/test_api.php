<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Debug için tüm request bilgilerini logla
error_log("=== API DEBUG START ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Query String: " . $_SERVER['QUERY_STRING']);
error_log("GET parameters: " . print_r($_GET, true));
error_log("POST parameters: " . print_r($_POST, true));

$action = $_GET['action'] ?? '';

error_log("Action value: '" . $action . "'");
error_log("Action empty check: " . (empty($action) ? 'TRUE' : 'FALSE'));

// Action boşsa hata döndür
if (empty($action)) {
    error_log("ERROR: Action parametresi boş!");
    echo json_encode(['error' => 'Action parametresi gerekli']);
    exit;
}

// Veritabanı bağlantısını oluştur
try {
    $conn = db_connect();
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(['error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()]);
    exit;
}

try {
    switch ($action) {
        case 'users':
            // Tüm kullanıcıları listele
            $sql = "SELECT 
                        u.id, u.username, u.email, u.telefon, u.ad_soyad, u.tc_no,
                        u.balance, u.role, u.is_active, u.created_at, u.son_giris
                    FROM users u
                    WHERE u.role = 'user'
                    ORDER BY u.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $users]);
            break;
            
        case 'withdrawals':
            // Para çekme taleplerini listele
            $sql = "SELECT
                        pct.*,
                        u.username, u.email, u.telefon, u.ad_soyad,
                        a.username as admin_username
                    FROM para_cekme_talepleri pct
                    JOIN users u ON pct.user_id = u.id
                    LEFT JOIN users a ON pct.onaylayan_admin_id = a.id
                    ORDER BY pct.tarih DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $withdrawals = [];
            while ($row = $result->fetch_assoc()) {
                $withdrawals[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $withdrawals]);
            break;
            
        case 'invoices':
            // Faturaları listele
            $sql = "SELECT f.*, u.username, u.email
                    FROM faturalar f
                    JOIN users u ON f.user_id = u.id
                    ORDER BY f.tarih DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $invoices = [];
            while ($row = $result->fetch_assoc()) {
                $invoices[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $invoices]);
            break;
            
        case 'settings':
            // Sistem ayarlarını listele
            $sql = "SELECT * FROM sistem_ayarlari ORDER BY ayar_adi";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $settings = [];
            while ($row = $result->fetch_assoc()) {
                $settings[] = $row;
            }
            
            echo json_encode(['success' => true, 'data' => $settings]);
            break;
            
        case 'dashboard':
            // Dashboard istatistikleri
            $sql = "SELECT COUNT(*) as total_users FROM users WHERE role = 'user'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $total_users = $result->fetch_row()[0];
            
            $sql = "SELECT COUNT(*) as pending_withdrawals FROM para_cekme_talepleri WHERE durum = 'beklemede'";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $pending_withdrawals = $result->fetch_row()[0];
            
            $sql = "SELECT COUNT(*) as total_invoices FROM faturalar";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();
            $total_invoices = $result->fetch_row()[0];
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_users' => $total_users,
                    'pending_withdrawals' => $pending_withdrawals,
                    'total_revenue' => $total_users * 1000,
                    'total_invoices' => $total_invoices
                ]
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Geçersiz işlem: ' . $action]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?> 