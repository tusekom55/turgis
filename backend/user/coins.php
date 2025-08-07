<?php
// Hata raporlamayı kapat (JSON çıktısını bozmasın)
error_reporting(0);
ini_set('display_errors', 0);

// Output buffering başlat (beklenmeyen çıktıları yakala)
ob_start();

// Session yönetimi - çakışma önleme
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buffer'ı temizle ve header'ları ayarla
ob_clean();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Config dosyası path'ini esnek şekilde bulma
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/config.php',
    dirname(__DIR__) . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Config dosyası bulunamadı']));
}

try {
    $conn = db_connect();
    
    // Artık fiyatlar TL cinsinden saklanıyor
    
    // Tekli coin bilgisi isteniyor mu?
    $coin_id = $_GET['coin_id'] ?? null;
    
    if ($coin_id) {
        // Tekli coin bilgisi getir
        $sql = 'SELECT coins.id, coins.coin_adi, coins.coin_kodu, coins.current_price, coins.price_change_24h, coins.logo_url, COALESCE(coin_kategorileri.kategori_adi, "Diğer") as kategori_adi FROM coins LEFT JOIN coin_kategorileri ON coins.kategori_id = coin_kategorileri.id WHERE coins.id = ? AND coins.is_active = 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$coin_id]);
        $coin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coin) {
            // Artık fiyatlar TL cinsinden saklanıyor
            $coin['current_price'] = floatval($coin['current_price']);
            $coin['currency'] = 'TRY';
            
            echo json_encode(['success' => true, 'coin' => $coin]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Coin bulunamadı']);
        }
        exit;
    }
    
    // Coins tablosunun var olup olmadığını kontrol et
    $table_check = $conn->prepare("SHOW TABLES LIKE 'coins'");
    $table_check->execute();
    
    if ($table_check->rowCount() == 0) {
        // Mock data döndür
        echo json_encode([
            'success' => true, 
            'coins' => [
                ['id' => 1, 'coin_adi' => 'Bitcoin', 'coin_kodu' => 'BTC', 'current_price' => 1350000, 'price_change_24h' => 2.5, 'kategori_adi' => 'Kripto Para'],
                ['id' => 2, 'coin_adi' => 'Ethereum', 'coin_kodu' => 'ETH', 'current_price' => 85000, 'price_change_24h' => -1.2, 'kategori_adi' => 'Kripto Para'],
                ['id' => 3, 'coin_adi' => 'BNB', 'coin_kodu' => 'BNB', 'current_price' => 12500, 'price_change_24h' => 0.8, 'kategori_adi' => 'Kripto Para']
            ]
        ]);
        exit;
    }
    
    $sql = 'SELECT coins.id, coins.coin_adi, coins.coin_kodu, coins.current_price, coins.price_change_24h, coins.logo_url, COALESCE(coin_kategorileri.kategori_adi, "Diğer") as kategori_adi FROM coins LEFT JOIN coin_kategorileri ON coins.kategori_id = coin_kategorileri.id WHERE coins.is_active = 1 ORDER BY coins.sira ASC, coins.id ASC';
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Artık fiyatlar TL cinsinden saklanıyor
    foreach ($coins as &$coin) {
        $coin['current_price'] = floatval($coin['current_price']);
        $coin['currency'] = 'TRY';
    }
    
    echo json_encode(['success' => true, 'coins' => $coins]);
    
} catch (PDOException $e) {
    error_log('Database error in coins.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası']);
} catch (Exception $e) {
    error_log('General error in coins.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sistem hatası']);
}
