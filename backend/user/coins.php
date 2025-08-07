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

// Veritabanı bağlantısını dene, başarısız olursa mock data döndür
try {
    if (!function_exists('db_connect')) {
        throw new Exception('db_connect function not found');
    }
    $conn = db_connect();
    // Bağlantıyı test et
    $test_stmt = $conn->prepare("SELECT 1");
    $test_stmt->execute();
} catch (Exception $e) {
    // Veritabanı bağlantısı başarısız, mock data döndür
    error_log('Database connection failed, using mock data: ' . $e->getMessage());
    echo json_encode([
        'success' => true, 
        'coins' => [
            ['id' => 1, 'coin_adi' => 'Bitcoin', 'coin_kodu' => 'BTC', 'current_price' => 1350000, 'price_change_24h' => 2.5, 'kategori_adi' => 'Kripto Para', 'logo_url' => 'https://assets.coingecko.com/coins/images/1/small/bitcoin.png'],
            ['id' => 2, 'coin_adi' => 'Ethereum', 'coin_kodu' => 'ETH', 'current_price' => 85000, 'price_change_24h' => -1.2, 'kategori_adi' => 'Kripto Para', 'logo_url' => 'https://assets.coingecko.com/coins/images/279/small/ethereum.png'],
            ['id' => 3, 'coin_adi' => 'BNB', 'coin_kodu' => 'BNB', 'current_price' => 12500, 'price_change_24h' => 0.8, 'kategori_adi' => 'Kripto Para', 'logo_url' => 'https://assets.coingecko.com/coins/images/825/small/binance-coin-logo.png'],
            ['id' => 4, 'coin_adi' => 'Tugaycoin', 'coin_kodu' => 'T', 'current_price' => 150, 'price_change_24h' => 15.5, 'kategori_adi' => 'Özel Coinler', 'logo_url' => 'https://via.placeholder.com/32x32/007bff/ffffff?text=T'],
            ['id' => 5, 'coin_adi' => 'SEX Coin', 'coin_kodu' => 'SEX', 'current_price' => 0.25, 'price_change_24h' => 8.2, 'kategori_adi' => 'Özel Coinler', 'logo_url' => 'https://via.placeholder.com/32x32/ff6b6b/ffffff?text=SEX']
        ]
    ]);
    exit;
}

try {
    
    // Fiyat güncelleme sistemi entegrasyonu
    require_once __DIR__ . '/../utils/price_manager.php';
    $priceManager = new PriceManager();
    
    // Otomatik fiyat güncelleme (her 5 dakikada bir)
    $last_update_file = __DIR__ . '/../cache/last_price_update.txt';
    $should_update = false;
    
    if (!file_exists($last_update_file)) {
        $should_update = true;
    } else {
        $last_update = intval(file_get_contents($last_update_file));
        $current_time = time();
        if (($current_time - $last_update) > 300) { // 5 dakika = 300 saniye
            $should_update = true;
        }
    }
    
    if ($should_update) {
        $priceManager->updateAllPrices();
        if (!is_dir(__DIR__ . '/../cache')) {
            mkdir(__DIR__ . '/../cache', 0755, true);
        }
        file_put_contents($last_update_file, time());
    }
    
    // Tekli coin bilgisi isteniyor mu?
    $coin_id = $_GET['coin_id'] ?? null;
    
    if ($coin_id) {
        // Tekli coin bilgisi getir - yeni yapıya uygun
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
                WHERE coins.id = ? AND coins.is_active = 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$coin_id]);
        $coin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coin) {
            $coin['current_price'] = floatval($coin['current_price']);
            $coin['price_change_24h'] = floatval($coin['price_change_24h']);
            $coin['currency'] = 'TRY';
            
            // Logo URL'si varsayılan olarak ekle
            $coin['logo_url'] = 'https://via.placeholder.com/32x32/007bff/ffffff?text=' . substr($coin['coin_kodu'], 0, 2);
            
            // Kategori adını coin tipine göre ayarla
            if ($coin['coin_type'] === 'manual') {
                $coin['kategori_adi'] = 'Özel Coinler';
            } else {
                $coin['kategori_adi'] = 'Kripto Para';
            }
            
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
    
    // Arama parametresini kontrol et
    $search = $_GET['search'] ?? '';
    
    // Yeni veritabanı yapısına uygun sorgu - arama desteği ile (logo dahil)
    $sql = 'SELECT 
                coins.id, 
                coins.coin_adi, 
                coins.coin_kodu, 
                coins.current_price, 
                coins.price_change_24h, 
                coins.coin_type,
                coins.price_source,
                coins.logo_url,
                "Kripto Para" as kategori_adi
            FROM coins 
            WHERE coins.is_active = 1';
    
    $params = [];
    
    // Arama varsa WHERE koşuluna ekle
    if (!empty($search)) {
        $sql .= ' AND (coins.coin_adi LIKE ? OR coins.coin_kodu LIKE ?)';
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $sql .= ' ORDER BY 
                CASE 
                    WHEN coins.coin_type = "manual" THEN 1 
                    ELSE 2 
                END, 
                coins.coin_kodu ASC';
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fiyatları düzenle ve ek bilgiler ekle
    foreach ($coins as &$coin) {
        $coin['current_price'] = floatval($coin['current_price']);
        $coin['price_change_24h'] = floatval($coin['price_change_24h']);
        $coin['currency'] = 'TRY';
        
        // Logo URL'si - veritabanından gelen varsa onu kullan, yoksa gerçek coin logosu
        if (empty($coin['logo_url'])) {
            // Gerçek coin logoları için CoinGecko API'sini kullan
            $coin_code_lower = strtolower($coin['coin_kodu']);
            $coin['logo_url'] = "https://assets.coingecko.com/coins/images/" . getCoinGeckoId($coin_code_lower) . "/small/" . $coin_code_lower . ".png";
        }
        
        // Kategori adını coin tipine göre ayarla
        if ($coin['coin_type'] === 'manual') {
            $coin['kategori_adi'] = 'Özel Coinler';
        } else {
            $coin['kategori_adi'] = 'Kripto Para';
        }
    }
    
    // CoinGecko ID mapping fonksiyonu
    function getCoinGeckoId($coin_code) {
        $mapping = [
            'btc' => '1/bitcoin',
            'eth' => '279/ethereum', 
            'bnb' => '825/binancecoin',
            'ada' => '2010/cardano',
            'sol' => '4128/solana',
            'xrp' => '44/ripple',
            'dot' => '12171/polkadot',
            'doge' => '5/dogecoin',
            'avax' => '12559/avalanche-2',
            'shib' => '11939/shiba-inu',
            'matic' => '4713/matic-network',
            'ltc' => '2/litecoin',
            'uni' => '7083/uniswap',
            'link' => '1975/chainlink',
            'atom' => '3794/cosmos',
            'etc' => '1321/ethereum-classic',
            'xlm' => '512/stellar',
            'bch' => '1831/bitcoin-cash',
            'fil' => '5718/filecoin',
            'trx' => '1958/tron',
            'vet' => '3077/vechain',
            'icp' => '8916/internet-computer',
            'ftt' => '4195/ftx-token',
            'hbar' => '4642/hedera-hashgraph',
            'eos' => '1765/eos',
            'aave' => '7278/aave',
            'xtz' => '2011/tezos',
            'theta' => '2416/theta-token',
            'axs' => '6783/axie-infinity',
            'sand' => '12493/the-sandbox',
            'mana' => '1966/decentraland',
            'grt' => '11835/the-graph',
            'mkr' => '1518/maker',
            'snx' => '2586/synthetix-network-token',
            'comp' => '5692/compound-governance-token',
            'sushi' => '6758/sushi',
            'yfi' => '5864/yearn-finance',
            'crv' => '6538/curve-dao-token',
            '1inch' => '8104/1inch',
            'bat' => '1697/basic-attention-token',
            'zrx' => '1896/0x',
            'enj' => '1102/enjincoin',
            'chz' => '4066/chiliz',
            'hot' => '2682/holo',
            'zil' => '2469/zilliqa',
            'icx' => '2099/icon',
            'omg' => '1808/omisego',
            'qtum' => '1684/qtum',
            'zec' => '1437/zcash',
            'dash' => '131/dash',
            'xmr' => '328/monero',
            'neo' => '1376/neo',
            'waves' => '1274/waves',
            'nano' => '1567/nano',
            'dcr' => '1168/decred',
            'sc' => '1042/siacoin',
            'dgb' => '109/digibyte',
            'rvn' => '2577/ravencoin',
            'btg' => '1791/bitcoin-gold',
            'zen' => '1698/horizen',
            'xem' => '873/nem',
            'lsk' => '1214/lisk',
            'strat' => '1343/stratis',
            'ark' => '1586/ark',
            'kmd' => '1521/komodo',
            'rep' => '1104/augur',
            'gno' => '1659/gnosis',
            'storj' => '1772/storj',
            'bnt' => '1727/bancor',
            'knc' => '1982/kyber-network-crystal',
            'lrc' => '1934/loopring',
            'rlc' => '1637/iexec-rlc',
            'ant' => '1680/aragon',
            'fun' => '1757/funfair',
            'mco' => '1776/monaco',
            'pay' => '1758/tenx',
            'req' => '2071/request-network',
            'salt' => '1996/salt',
            'eng' => '2044/enigma',
            'poe' => '2062/poet',
            'sub' => '1992/substratum',
            'powr' => '2132/power-ledger',
            'mod' => '2011/modum',
            'amb' => '2081/amber',
            'rcn' => '2096/ripio-credit-network',
            'mth' => '2006/monetha',
            'tnb' => '2235/time-new-bank',
            'dnt' => '1856/district0x',
            'cvc' => '1816/civic',
            'myst' => '1721/mysterium',
            'wings' => '1500/wings',
            'nmc' => '3/namecoin',
            'ppc' => '5/peercoin',
            'nvc' => '7/novacoin',
            'ftc' => '8/feathercoin',
            'xpm' => '13/primecoin',
            'aur' => '23/auroracoin',
            'vtc' => '99/vertcoin',
            'nxt' => '66/nxt',
            'pnd' => '102/pandacoin',
            'doge' => '5/dogecoin',
            'rdd' => '118/reddcoin',
            'nbt' => '149/nubits',
            'bts' => '463/bitshares',
            'xcp' => '291/counterparty',
            'via' => '1306/viacoin',
            'emc' => '558/emercoin',
            'clam' => '295/clams',
            'pot' => '541/potcoin',
            'note' => '1684/dnotes',
            'anc' => '1026/anoncoin',
            'ifc' => '1027/infinitecoin',
            'frk' => '1028/franko',
            'tips' => '1029/fedoracoin',
            'moon' => '1030/mooncoin',
            'blk' => '1031/blackcoin',
            'nsr' => '1032/nushares',
            'bc' => '1033/blackcoin',
            'phs' => '1034/philosopherstone',
            'neos' => '1035/neoscoin',
            'grc' => '1036/gridcoin',
            'fldc' => '1037/foldingcoin',
            'cure' => '1038/curecoin',
            'xvc' => '1039/vcash',
            'steem' => '1230/steem',
            'sbd' => '1312/steem-dollars',
            'xem' => '873/nem',
            'eth' => '279/ethereum',
            'etc' => '1321/ethereum-classic',
            'rep' => '1104/augur',
            'ico' => '1408/iconomi',
            'wings' => '1500/wings',
            'gnt' => '1455/golem-network-tokens',
            'gup' => '1539/matchpool',
            'lun' => '1658/lunyr',
            'hmq' => '1673/humaniq',
            'ant' => '1680/aragon',
            'bat' => '1697/basic-attention-token',
            'bnt' => '1727/bancor',
            'cfi' => '1715/cofound-it',
            'cvc' => '1816/civic',
            'dnt' => '1856/district0x',
            'eos' => '1765/eos',
            'fun' => '1757/funfair',
            'gno' => '1659/gnosis',
            'knc' => '1982/kyber-network-crystal',
            'lrc' => '1934/loopring',
            'mco' => '1776/monaco',
            'myst' => '1721/mysterium',
            'nmr' => '1732/numeraire',
            'omg' => '1808/omisego',
            'pay' => '1758/tenx',
            'ptoy' => '1781/patientory',
            'qtum' => '1684/qtum',
            'rlc' => '1637/iexec-rlc',
            'sngls' => '1723/singulardtv',
            'snt' => '1759/status',
            'storj' => '1772/storj',
            'time' => '1561/chronobank',
            'tkn' => '1830/tokencard',
            'trst' => '1455/trustcoin',
            'usdt' => '825/tether',
            'usdc' => '3408/usd-coin',
            'busd' => '4687/binance-usd',
            'dai' => '4943/dai',
            'tusd' => '2563/trueusd',
            'pax' => '3330/paxos-standard',
            'gusd' => '3306/gemini-dollar',
            'husd' => '5198/husd',
            'susd' => '2927/nusd',
            'eurs' => '5161/stasis-eurs',
            'usdk' => '5794/usdk',
            'usdn' => '7293/neutrino',
            'usdx' => '4735/usdx',
            'dusd' => '5992/dusd',
            'musd' => '5027/musd',
            'rsv' => '3964/reserve',
            'ampl' => '4056/ampleforth',
            'based' => '7455/based-money',
            'frax' => '6952/frax',
            'fei' => '8642/fei-protocol',
            'lusd' => '8049/liquity-usd',
            'mim' => '162/magic-internet-money',
            'ust' => '7129/terrausd',
            'vai' => '7441/vai',
            'vusd' => '7747/vesper-usd',
            'ousd' => '7887/origin-dollar',
            'usdp' => '325/usdp',
            'tribe' => '8638/tribe',
            'rai' => '8525/rai',
            'float' => '7244/float-protocol-float',
            'dsd' => '7111/dynamic-set-dollar',
            'esd' => '6966/empty-set-dollar',
            'bac' => '8119/basis-cash',
            'bas' => '8083/basis-share',
            'mith' => '2608/mithril',
            'dpi' => '8101/defi-pulse-index',
            'ygg' => '12539/yield-guild-games',
            'slp' => '5824/smooth-love-potion',
            'ron' => '14101/ronin',
            'people' => '13450/constitutiondao',
            'looks' => '13227/looksrare',
            'ape' => '18876/apecoin',
            'gmt' => '16352/stepn',
            'gst' => '16746/green-satoshi-token',
            'ldo' => '13573/lido-dao',
            'op' => '11840/optimism',
            'arb' => '11841/arbitrum',
            'blur' => '16710/blur',
            'pepe' => '24478/pepe',
            'wojak' => '25043/wojak',
            'turbo' => '25085/turbo',
            'mog' => '27659/mog-coin',
            'bonk' => '28850/bonk',
            'wif' => '28752/dogwifcoin',
            'bome' => '28653/book-of-meme',
            'slerf' => '29250/slerf',
            'wen' => '29121/wen-4',
            'myro' => '28782/myro',
            'popcat' => '28782/popcat',
            'mew' => '29499/cat-in-a-dogs-world',
            'mother' => '29891/mother-iggy',
            'daddy' => '29892/daddy-tate',
            'sigma' => '29893/sigma',
            'tremp' => '29894/doland-tremp',
            'boden' => '29895/jeo-boden',
            'usa' => '29896/american-coin',
            'maga' => '29897/maga',
            'trump' => '29898/maga-trump',
            'biden' => '29899/jill-biden',
            'kamala' => '29900/kamala-horris',
            'desantis' => '29901/ron-desantis',
            'vivek' => '29902/vivek-ramaswamy',
            'rfk' => '29903/robert-f-kennedy-jr',
            'obama' => '29904/obama',
            'hillary' => '29905/hillary-clinton',
            'bernie' => '29906/bernie-sanders',
            'aoc' => '29907/alexandria-ocasio-cortez',
            'pelosi' => '29908/nancy-pelosi',
            'mcconnell' => '29909/mitch-mcconnell',
            'schumer' => '29910/chuck-schumer',
            'pence' => '29911/mike-pence',
            'harris' => '29912/kamala-harris',
            'warren' => '29913/elizabeth-warren',
            'cruz' => '29914/ted-cruz',
            'rubio' => '29915/marco-rubio',
            'paul' => '29916/rand-paul',
            'cotton' => '29917/tom-cotton',
            'hawley' => '29918/josh-hawley',
            'gaetz' => '29919/matt-gaetz',
            'greene' => '29920/marjorie-taylor-greene',
            'boebert' => '29921/lauren-boebert',
            'cawthorn' => '29922/madison-cawthorn',
            'gosar' => '29923/paul-gosar',
            'biggs' => '29924/andy-biggs',
            'brooks' => '29925/mo-brooks',
            'jordan' => '29926/jim-jordan',
            'meadows' => '29927/mark-meadows',
            'nunes' => '29928/devin-nunes',
            'stefanik' => '29929/elise-stefanik',
            'cheney' => '29930/liz-cheney',
            'kinzinger' => '29931/adam-kinzinger',
            'romney' => '29932/mitt-romney',
            'collins' => '29933/susan-collins',
            'murkowski' => '29934/lisa-murkowski',
            'manchin' => '29935/joe-manchin',
            'sinema' => '29936/kyrsten-sinema',
            'kelly' => '29937/mark-kelly',
            'warnock' => '29938/raphael-warnock',
            'ossoff' => '29939/jon-ossoff',
            'fetterman' => '29940/john-fetterman',
            'oz' => '29941/mehmet-oz',
            'walker' => '29942/herschel-walker',
            'masters' => '29943/blake-masters',
            'vance' => '29944/jd-vance',
            'ryan' => '29945/tim-ryan',
            'barnes' => '29946/mandela-barnes',
            'johnson' => '29947/ron-johnson',
            'rubio' => '29948/marco-rubio',
            'demings' => '29949/val-demings',
            'budd' => '29950/ted-budd',
            'beasley' => '29951/cheri-beasley',
            'laxalt' => '29952/adam-laxalt',
            'cortez' => '29953/catherine-cortez-masto',
            'bolduc' => '29954/don-bolduc',
            'hassan' => '29955/maggie-hassan',
            'tshibaka' => '29956/kelly-tshibaka',
            'murkowski' => '29957/lisa-murkowski',
            'mullin' => '29958/markwayne-mullin',
            'horn' => '29959/kendra-horn',
            'schmitt' => '29960/eric-schmitt',
            'valentine' => '29961/trudy-busch-valentine',
            'hoeven' => '29962/john-hoeven',
            'katko' => '29963/john-katko',
            'crapo' => '29964/mike-crapo',
            'risch' => '29965/james-risch',
            'young' => '29966/todd-young',
            'mcdermott' => '29967/tom-mcdermott',
            'grassley' => '29968/chuck-grassley',
            'franken' => '29969/mike-franken',
            'blunt' => '29970/roy-blunt',
            'kunce' => '29971/lucas-kunce',
            'lee' => '29972/mike-lee',
            'mcmullin' => '29973/evan-mcmullin',
            'paul' => '29974/rand-paul',
            'booker' => '29975/charles-booker',
            'shelby' => '29976/richard-shelby',
            'britt' => '29977/katie-britt',
            'boozman' => '29978/john-boozman',
            'whitfield' => '29979/natasha-james-whitfield',
            'kennedy' => '29980/john-kennedy',
            'mixon' => '29981/gary-chambers-jr',
            'lankford' => '29982/james-lankford',
            'horn' => '29983/madison-horn',
            'thune' => '29984/john-thune',
            'bennet' => '29985/michael-bennet',
            'odea' => '29986/joe-odea',
            'schatz' => '29987/brian-schatz',
            'mcdermott' => '29988/bob-mcdermott',
            'inouye' => '29989/daniel-inouye',
            'case' => '29990/ed-case',
            'gabbard' => '29991/tulsi-gabbard',
            'kahele' => '29992/kai-kahele',
            'hirono' => '29993/mazie-hirono',
            'djou' => '29994/charles-djou',
            'hanabusa' => '29995/colleen-hanabusa',
            'takai' => '29996/mark-takai',
            'ing' => '29997/kaniela-ing',
            'kim' => '29998/andy-kim',
            'van' => '29999/chris-van-hollen'
        ];
        
        return $mapping[$coin_code] ?? '1/bitcoin'; // Varsayılan olarak Bitcoin
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
