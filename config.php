<?php
// config.php - Konfiguracja systemu

// Ustawienia bazy danych
define('DB_HOST', 'localhost');
define('DB_NAME', 'police_system');
define('DB_USER', 'police_system');
define('DB_PASS', 'Aw3JLjefkxxAxjrL');

// Ustawienia systemu
define('SYSTEM_NAME', 'System Policyjny');
define('SYSTEM_VERSION', '3.0.1');
define('DEFAULT_WALLPAPER', '1');
define('UPLOAD_DIR', 'uploads/wallpapers/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Domyślne tapety systemowe
$SYSTEM_WALLPAPERS = [
    '1' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    '2' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    '3' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
    '4' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
    '5' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
    '6' => 'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
    '7' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
    '8' => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
    '9' => 'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)',
    '10' => 'linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)'
];

// Inicjalizacja sesji
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// WAŻNE: Ustawienie domyślnych wartości sesji jeśli nie istnieją
if (!isset($_SESSION['user_role'])) {
    $_SESSION['user_role'] = 'guest';
}
if (!isset($_SESSION['user_data'])) {
    $_SESSION['user_data'] = ['name' => 'Gość', 'badge' => '', 'role' => 'guest'];
}

// Funkcja połączenia z bazą danych
function getDB() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        // Jeśli baza danych nie jest dostępna, używaj systemu demo
        return null;
    }
}

// Funkcja sprawdzenia autoryzacji
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Funkcja sprawdzenia uprawnień administratora
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Funkcja przekierowania
function redirect($url) {
    header("Location: $url");
    exit();
}

// Funkcja zabezpieczania danych wejściowych
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Funkcja hashowania hasła
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Funkcja weryfikacji hasła
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Funkcja logowania użytkownika (z fallback na system demo)
function loginUser($username, $password) {
    global $USERS;
    
    // Najpierw spróbuj z bazą danych
    $pdo = getDB();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, o.first_name, o.last_name, o.badge_number, r.rank_name 
                FROM users u 
                LEFT JOIN officers o ON u.id = o.user_id 
                LEFT JOIN officer_ranks r ON o.rank_id = r.id 
                WHERE u.username = ? AND u.is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Aktualizuj ostatnie logowanie
                $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Ustaw sesję - KLUCZOWE: zawsze ustawiaj wszystkie zmienne sesji
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role']; // WAŻNE!
                $_SESSION['first_login'] = $user['first_login'];
                
                // Dane użytkownika do wyświetlania
                $displayName = $user['first_name'] && $user['last_name'] 
                    ? $user['first_name'] . ' ' . $user['last_name']
                    : $user['username'];
                    
                $_SESSION['user_data'] = [
                    'name' => $displayName,
                    'badge' => $user['badge_number'] ?: 'N/A',
                    'rank' => $user['rank_name'] ?: 'N/A',
                    'role' => $user['role']
                ];
                
                return true;
            }
        } catch (PDOException $e) {
            // Fallback do systemu demo jeśli baza danych nie działa
            error_log("Database error in loginUser: " . $e->getMessage());
        }
    }
    
    // Fallback na system demo
    if (isset($USERS[$username]) && $USERS[$username]['password'] === $password) {
        // WAŻNE: Ustaw WSZYSTKIE zmienne sesji
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = array_search($username, array_keys($USERS)) + 1;
        $_SESSION['username'] = $username;
        $_SESSION['user_role'] = $USERS[$username]['role']; // KLUCZOWE dla admina!
        $_SESSION['first_login'] = false;
        $_SESSION['wallpaper'] = DEFAULT_WALLPAPER;
        $_SESSION['custom_wallpaper'] = null;
        
        $_SESSION['user_data'] = [
            'name' => $USERS[$username]['name'],
            'badge' => $USERS[$username]['badge'],
            'rank' => $USERS[$username]['role'],
            'role' => $USERS[$username]['role']
        ];
        
        return true;
    }
    
    return false;
}

// Funkcja wylogowania
function logoutUser() {
    session_destroy();
    redirect('index.php');
}

// Automatyczne sprawdzenie sesji na każdej chronionej stronie
function requireAuth() {
    if (!isLoggedIn()) {
        redirect('index.php');
    }
    
    // Sprawdź czy użytkownik musi zmienić hasło (tylko dla systemu z bazą danych)
    if (isset($_SESSION['first_login']) && $_SESSION['first_login'] && basename($_SERVER['PHP_SELF']) !== 'force_password_reset.php') {
        redirect('force_password_reset.php');
    }
}

// Funkcja wymagająca uprawnień administratora
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        die('Access denied - Administrator privileges required');
    }
}

// Funkcja do tworzenia katalogu uploads jeśli nie istnieje
function createUploadDir() {
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
}

// Funkcja do walidacji przesłanego pliku
function validateUpload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowed_types)) {
        return "Dozwolone są tylko pliki graficzne (JPG, PNG, GIF, WEBP)";
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return "Plik jest za duży. Maksymalny rozmiar to 5MB";
    }
    
    return true;
}

// Funkcja do generowania unikalnej nazwy pliku
function generateFileName($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    return 'wallpaper_' . uniqid() . '_' . time() . '.' . $extension;
}

// Debug function - usuń w produkcji
function debugSession() {
    echo "<!-- DEBUG SESSION: ";
    echo "logged_in=" . ($_SESSION['logged_in'] ?? 'BRAK') . ", ";
    echo "user_role=" . ($_SESSION['user_role'] ?? 'BRAK') . ", ";
    echo "username=" . ($_SESSION['username'] ?? 'BRAK');
    echo " -->";
}
?>