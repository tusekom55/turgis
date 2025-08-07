<?php
// API hata debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 API Hata Debug</h1>";

// 1. Config dosyası kontrolü
echo "<h2>1. Config Dosyası Kontrolü</h2>";
try {
    require_once 'backend/config.php';
    echo "✅ Config dosyası yüklendi<br>";
    echo "DB Host: " . $DB_HOST . "<br>";
    echo "DB Name: " . $DB_NAME . "<br>";
    echo "DB User: " . $DB_USER . "<br>";
} catch (Exception $e) {
    echo "❌ Config hatası: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Veritabanı bağlantısı kontrolü
echo "<h2>2. Veritabanı Bağlantısı</h2>";
try {
    $conn = db_connect();
    echo "✅ PDO bağlantısı başarılı<br>";
} catch (Exception $e) {
    echo "❌ PDO bağlantı hatası: " . $e->getMessage() . "<br>";
    
    // MySQLi ile deneme
    try {
        $mysqli_conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if ($mysqli_conn->connect_error) {
            echo "❌ MySQLi bağlantı hatası: " . $mysqli_conn->connect_error . "<br>";
        } else {
            echo "✅ MySQLi bağlantısı başarılı<br>";
        }
    } catch (Exception $e2) {
        echo "❌ MySQLi hatası: " . $e2->getMessage() . "<br>";
    }
    exit;
}

// 3. Coins tablosu kontrolü
echo "<h2>3. Coins Tablosu Kontrolü</h2>";
try {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'coins'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Coins tablosu mevcut<br>";
        
        // Tablo yapısını kontrol et
        $stmt = $conn->prepare("DESCRIBE coins");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Tablo Yapısı:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Sütun</th><th>Tip</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Kayıt sayısını kontrol et
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM coins");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<br>📊 Toplam coin sayısı: " . $count['count'] . "<br>";
        
        // Örnek kayıtları göster
        if ($count['count'] > 0) {
            $stmt = $conn->prepare("SELECT * FROM coins LIMIT 3");
            $stmt->execute();
            $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>Örnek Kayıtlar:</h3>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr>";
            foreach (array_keys($coins[0]) as $key) {
                echo "<th>" . $key . "</th>";
            }
            echo "</tr>";
            
            foreach ($coins as $coin) {
                echo "<tr>";
                foreach ($coin as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "❌ Coins tablosu bulunamadı<br>";
        
        // Mevcut tabloları listele
        $stmt = $conn->prepare("SHOW TABLES");
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Mevcut Tablolar:</h3>";
        foreach ($tables as $table) {
            echo "- " . $table . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Tablo kontrol hatası: " . $e->getMessage() . "<br>";
}

// 4. Price Manager kontrolü
echo "<h2>4. Price Manager Kontrolü</h2>";
try {
    require_once 'backend/utils/price_manager.php';
    echo "✅ Price Manager dosyası yüklendi<br>";
    
    $priceManager = new PriceManager();
    echo "✅ Price Manager sınıfı başlatıldı<br>";
    
} catch (Exception $e) {
    echo "❌ Price Manager hatası: " . $e->getMessage() . "<br>";
}

// 5. Coins API simülasyonu
echo "<h2>5. Coins API Simülasyonu</h2>";
try {
    // Coins API'sinin yaptığı işlemi simüle et
    $sql = 'SELECT 
                coins.id, 
                coins.coin_adi, 
                coins.coin_kodu, 
                coins.current_price, 
                coins.price_change_24h, 
                coins.coin_type,
                coins.price_source,
                "Kripto Para" as kategori_adi
            FROM coins 
            WHERE coins.is_active = 1
            ORDER BY coins.coin_kodu ASC
            LIMIT 5';
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ API sorgusu başarılı<br>";
    echo "📊 Dönen kayıt sayısı: " . count($coins) . "<br>";
    
    if (count($coins) > 0) {
        echo "<h3>API Sonucu:</h3>";
        echo "<pre>" . json_encode($coins, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "❌ API simülasyon hatası: " . $e->getMessage() . "<br>";
}

echo "<h2>✅ Debug Tamamlandı</h2>";
?>
