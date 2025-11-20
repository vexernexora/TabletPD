<?php
// diagnoza.php - Znajdź różnicę między localhost a hostingiem
require_once 'config.php';

// Sprawdzenie autoryzacji
requireAuth();

if (!isAdmin()) {
    die("Tylko dla adminów");
}

$pdo = getDB();

echo "<h1>DIAGNOSTYKA PROBLEMU Z CZASEM</h1>";
echo "<pre>";

// 1. Sprawdź strefy czasowe
echo "=== STREFY CZASOWE ===\n";
echo "PHP timezone: " . date_default_timezone_get() . "\n";
echo "PHP date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP time(): " . time() . "\n";

// Sprawdź strefę czasową MySQL
$stmt = $pdo->query("SELECT @@global.time_zone, @@session.time_zone, NOW() as mysql_now, UNIX_TIMESTAMP() as mysql_timestamp");
$mysql_info = $stmt->fetch();
echo "MySQL global timezone: " . $mysql_info['@@global.time_zone'] . "\n";
echo "MySQL session timezone: " . $mysql_info['@@session.time_zone'] . "\n";
echo "MySQL NOW(): " . $mysql_info['mysql_now'] . "\n";
echo "MySQL UNIX_TIMESTAMP(): " . $mysql_info['mysql_timestamp'] . "\n";

// Porównaj czasy
$diff = time() - $mysql_info['mysql_timestamp'];
echo "\nRóżnica PHP time() - MySQL UNIX_TIMESTAMP(): " . $diff . " sekund\n";
if (abs($diff) > 60) {
    echo "⚠️ UWAGA: Różnica większa niż 1 minuta! To może być problem!\n";
}

echo "\n=== DANE W BAZIE ===\n";

// Pobierz dane użytkownika
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM officer_status WHERE user_id = ?");
$stmt->execute([$user_id]);
$status = $stmt->fetch();

if ($status) {
    echo "Twój rekord w officer_status:\n";
    print_r($status);
    
    if ($status['start_time']) {
        echo "\nAnaliza start_time:\n";
        echo "start_time z bazy: " . $status['start_time'] . "\n";
        $start_ts = strtotime($status['start_time']);
        echo "strtotime(start_time): " . $start_ts . "\n";
        echo "Obecny time(): " . time() . "\n";
        $diff_minutes = round((time() - $start_ts) / 60);
        echo "Różnica w minutach: " . $diff_minutes . "\n";
        
        if ($diff_minutes < 0) {
            echo "⚠️ BŁĄD: Różnica ujemna! start_time jest w przyszłości!\n";
        } elseif ($diff_minutes > 1440) {
            echo "⚠️ BŁĄD: Różnica > 24h (" . round($diff_minutes/60, 1) . " godzin)!\n";
        }
    }
}

echo "\n=== TEST ZAPISU I ODCZYTU ===\n";

// Test zapisu i odczytu czasu
$test_table = "CREATE TEMPORARY TABLE test_time (
    id INT PRIMARY KEY,
    php_time INT,
    mysql_now DATETIME,
    mysql_timestamp TIMESTAMP
)";

try {
    $pdo->exec($test_table);
    
    // Zapisz testowe dane
    $stmt = $pdo->prepare("INSERT INTO test_time VALUES (1, ?, NOW(), CURRENT_TIMESTAMP)");
    $stmt->execute([time()]);
    
    // Odczytaj
    $stmt = $pdo->query("SELECT *, UNIX_TIMESTAMP(mysql_now) as unix_now, UNIX_TIMESTAMP(mysql_timestamp) as unix_ts FROM test_time WHERE id = 1");
    $test = $stmt->fetch();
    
    echo "Zapisano PHP time(): " . $test['php_time'] . "\n";
    echo "MySQL NOW(): " . $test['mysql_now'] . " (UNIX: " . $test['unix_now'] . ")\n";
    echo "MySQL TIMESTAMP: " . $test['mysql_timestamp'] . " (UNIX: " . $test['unix_ts'] . ")\n";
    
    $diff1 = $test['php_time'] - $test['unix_now'];
    $diff2 = $test['php_time'] - $test['unix_ts'];
    
    echo "\nRóżnice:\n";
    echo "PHP time - MySQL NOW: " . $diff1 . " sekund (" . round($diff1/3600, 2) . " godzin)\n";
    echo "PHP time - MySQL TIMESTAMP: " . $diff2 . " sekund (" . round($diff2/3600, 2) . " godzin)\n";
    
    if (abs($diff1) >= 3600) {
        echo "\n🔴 ZNALEZIONO PROBLEM! Różnica " . round($diff1/3600) . " godzin między PHP a MySQL!\n";
        echo "To wyjaśnia dlaczego masz problem z 6 godzinami (360 minut)!\n";
    }
    
} catch (Exception $e) {
    echo "Błąd testu: " . $e->getMessage() . "\n";
}

echo "\n=== ROZWIĄZANIE ===\n";

if (abs($diff1) >= 3600 || abs($diff) > 60) {
    echo "Wykryto problem z synchronizacją czasu. Opcje rozwiązania:\n\n";
    
    echo "OPCJA 1 - Ustaw strefę czasową w PHP (dodaj na początku config.php):\n";
    echo "date_default_timezone_set('Europe/Warsaw');\n\n";
    
    echo "OPCJA 2 - Synchronizuj MySQL z PHP (wykonaj to zapytanie po połączeniu):\n";
    echo "\$pdo->exec(\"SET time_zone = '\".date('P').\"'\");\n\n";
    
    echo "OPCJA 3 - Używaj tylko PHP time() zamiast MySQL NOW():\n";
    echo "Zamiast NOW() używaj FROM_UNIXTIME(" . time() . ")\n";
    
} else {
    echo "Nie wykryto problemu z synchronizacją czasu.\n";
    echo "Problem może być gdzie indziej - sprawdź logi błędów.\n";
}

echo "\n=== INFORMACJE O SYSTEMIE ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "MySQL Version: " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
echo "Operating System: " . PHP_OS . "\n";

echo "</pre>";

// Przycisk do naprawy
if (abs($diff1) >= 3600 || abs($diff) > 60) {
    ?>
    <h2>Szybka naprawa</h2>
    <form method="POST">
        <button name="fix_time" type="submit" style="padding: 10px 20px; background: #ef4444; color: white; border: none; border-radius: 5px; cursor: pointer;">
            NAPRAW PROBLEM Z CZASEM (resetuje duration_minutes do 0)
        </button>
    </form>
    <?php
    
    if (isset($_POST['fix_time'])) {
        $stmt = $pdo->prepare("UPDATE officer_status SET duration_minutes = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        echo "<p style='color: green;'><strong>Zresetowano duration_minutes do 0!</strong></p>";
    }
}
?>

<hr>
<a href="status.php">Powrót do statusu</a>