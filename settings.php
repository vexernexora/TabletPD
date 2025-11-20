<?php
// settings.php - Ulepszone ustawienia z MySQL i fallbackiem
require_once 'config.php';

// Sprawdzenie autoryzacji
requireAuth();

$message = '';
$error = '';

// Debug połączenia MySQL
$connection_debug = [];
try {
    $connection_debug['host'] = DB_HOST;
    $connection_debug['db'] = DB_NAME;
    $connection_debug['user'] = DB_USER;
    
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $connection_debug['status'] = 'connected';
    
    // Test prostego zapytania
    $test_query = $pdo->query("SELECT 1 as test");
    $connection_debug['test'] = $test_query ? 'ok' : 'failed';
    
} catch (PDOException $e) {
    $connection_debug['status'] = 'failed';
    $connection_debug['error'] = $e->getMessage();
    $pdo = null;
}

// Pobierz ID użytkownika 
$current_user_id = $_SESSION['user_id'] ?? 1;

// Próbuj pobrać dane z MySQL
$mysql_connected = false;
$user_data = null;
$user_stats = null;
$recent_activities = [];
$user_settings = [];

if ($pdo) {
    try {
        // Sprawdź czy tabele istnieją
        $tables_exist = [];
        foreach(['users', 'officers', 'officer_status', 'weekly_stats', 'status_history', 'user_settings'] as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $tables_exist[$table] = $stmt->fetch() ? true : false;
        }
        
        if ($tables_exist['users']) {
            // Pobierz dane użytkownika z MySQL
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       COALESCE(CONCAT(o.first_name, ' ', o.last_name), u.username) as full_name,
                       o.first_name, o.last_name, o.badge_number, o.email,
                       r.rank_name
                FROM users u
                LEFT JOIN officers o ON u.id = o.user_id
                LEFT JOIN officer_ranks r ON o.rank_id = r.id
                WHERE u.id = ?
            ");
            $stmt->execute([$current_user_id]);
            $user_data = $stmt->fetch();
            
            if ($user_data) {
                // Pobierz ustawienia użytkownika z MySQL
                if ($tables_exist['user_settings']) {
                    $stmt = $pdo->prepare("SELECT setting_name, setting_value FROM user_settings WHERE user_id = ?");
                    $stmt->execute([$current_user_id]);
                    $mysql_settings = $stmt->fetchAll();
                    foreach ($mysql_settings as $setting) {
                        $user_settings[$setting['setting_name']] = $setting['setting_value'];
                    }
                }
                
                // Pobierz statystyki z MySQL
                if ($tables_exist['officer_status'] && $tables_exist['weekly_stats']) {
                    $stmt = $pdo->prepare("
                        SELECT 
                            COALESCE(ws.total_hours, 0) as week_hours,
                            os.status as current_status,
                            os.start_time,
                            COUNT(sh.id) as status_changes
                        FROM users u
                        LEFT JOIN officer_status os ON u.id = os.user_id
                        LEFT JOIN weekly_stats ws ON u.id = ws.user_id AND ws.week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                        LEFT JOIN status_history sh ON u.id = sh.user_id
                        WHERE u.id = ?
                        GROUP BY u.id, os.status, os.start_time, ws.total_hours
                    ");
                    $stmt->execute([$current_user_id]);
                    $stats = $stmt->fetch();
                    
                    // Pobierz ostatnią zmianę statusu
                    $stmt = $pdo->prepare("
                        SELECT MAX(change_time) as last_change
                        FROM status_history 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$current_user_id]);
                    $last_change = $stmt->fetchColumn();
                    
                    $user_stats = [
                        'week_hours' => $stats['week_hours'] ?? 0,
                        'min_weekly_hours' => 3,
                        'current_status' => $stats['current_status'] ?? 3,
                        'last_status_change' => $last_change ?: date('Y-m-d H:i:s'),
                        'status_changes' => $stats['status_changes'] ?? 0,
                        'start_time' => $stats['start_time']
                    ];
                }
                
                // Pobierz historię aktywności
                if ($tables_exist['status_history']) {
                    $stmt = $pdo->prepare("
                        SELECT status_from, status_to, change_time, duration_minutes, notes
                        FROM status_history 
                        WHERE user_id = ? 
                        ORDER BY change_time DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$current_user_id]);
                    $recent_activities = $stmt->fetchAll();
                }
                
                $mysql_connected = true;
                $connection_debug['user_found'] = true;
                $connection_debug['tables'] = $tables_exist;
            } else {
                $connection_debug['user_found'] = false;
            }
        } else {
            $connection_debug['tables_missing'] = true;
        }
        
    } catch (Exception $e) {
        $connection_debug['query_error'] = $e->getMessage();
        error_log("MySQL error in settings: " . $e->getMessage());
    }
}

// Fallback - użyj danych z sesji/config jeśli MySQL nie działa
if (!$mysql_connected || !$user_data) {
    $session_user_data = $_SESSION['user_data'] ?? ['name' => 'Użytkownik', 'badge' => 'N/A', 'role' => 'guest'];
    
    $user_data = [
        'username' => $_SESSION['username'] ?? 'guest',
        'role' => $_SESSION['user_role'] ?? $session_user_data['role'] ?? 'guest',
        'full_name' => $session_user_data['name'] ?? 'Użytkownik',
        'badge_number' => $session_user_data['badge'] ?? 'N/A',
        'first_name' => explode(' ', $session_user_data['name'] ?? 'Użytkownik')[0],
        'last_name' => isset(explode(' ', $session_user_data['name'] ?? 'Użytkownik')[1]) ? explode(' ', $session_user_data['name'] ?? 'Użytkownik')[1] : '',
        'email' => '',
        'rank_name' => $session_user_data['rank'] ?? 'N/A'
    ];
    
    $user_stats = [
        'week_hours' => 8.3,
        'min_weekly_hours' => 3,
        'current_status' => 3,
        'last_status_change' => '2024-12-08 14:30:00',
        'status_changes' => 47,
        'start_time' => null
    ];
    
    $recent_activities = [
        [
            'status_from' => 1,
            'status_to' => 3,
            'change_time' => '2024-12-08 14:30:00',
            'duration_minutes' => 480,
            'notes' => null
        ]
    ];
}

// Funkcja formatowania godzin
function formatHoursReadable($hours) {
    $h = floor($hours);
    $m = round(($hours - $h) * 60);
    
    if ($h > 0 && $m > 0) {
        return $h . 'h ' . $m . 'm';
    } elseif ($h > 0) {
        return $h . 'h';
    } elseif ($m > 0) {
        return $m . 'm';
    } else {
        return '0h';
    }
}

// Funkcja zapisywania ustawień do MySQL
function saveUserSetting($pdo, $user_id, $setting_name, $setting_value) {
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, setting_name, setting_value, updated_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            updated_at = NOW()
        ");
        return $stmt->execute([$user_id, $setting_name, $setting_value]);
    } catch (Exception $e) {
        error_log("Error saving user setting: " . $e->getMessage());
        return false;
    }
}

// Obsługa zmiany tapety
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallpaper'])) {
    $wallpaper_value = $_POST['wallpaper'];
    
    if ($pdo) {
        if (saveUserSetting($pdo, $current_user_id, 'wallpaper', $wallpaper_value)) {
            saveUserSetting($pdo, $current_user_id, 'custom_wallpaper', '');
            $message = 'Tapeta została zmieniona i zapisana!';
        } else {
            $_SESSION['wallpaper'] = $wallpaper_value;
            $_SESSION['custom_wallpaper'] = null;
            $message = 'Tapeta została zmieniona (sesja)!';
        }
    } else {
        $_SESSION['wallpaper'] = $wallpaper_value;
        $_SESSION['custom_wallpaper'] = null;
        $message = 'Tapeta została zmieniona (sesja)!';
    }
}

// Obsługa przesyłania własnej tapety
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['custom_wallpaper'])) {
    createUploadDir();
    
    $file = $_FILES['custom_wallpaper'];
    $validation = validateUpload($file);
    
    if ($validation === true) {
        $new_filename = generateFileName($file['name']);
        $upload_path = UPLOAD_DIR . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            if ($pdo) {
                if (saveUserSetting($pdo, $current_user_id, 'custom_wallpaper', $new_filename) && 
                    saveUserSetting($pdo, $current_user_id, 'wallpaper', 'custom')) {
                    $message = 'Własna tapeta została ustawiona i zapisana!';
                } else {
                    $_SESSION['custom_wallpaper'] = $new_filename;
                    $_SESSION['wallpaper'] = 'custom';
                    $message = 'Własna tapeta została ustawiona (sesja)!';
                }
            } else {
                $_SESSION['custom_wallpaper'] = $new_filename;
                $_SESSION['wallpaper'] = 'custom';
                $message = 'Własna tapeta została ustawiona (sesja)!';
            }
        } else {
            $error = 'Błąd podczas przesyłania pliku.';
        }
    } else {
        $error = $validation;
    }
}

// Obsługa zapisywania preferencji
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    $preferences = [
        'sound_notifications' => isset($_POST['sound_notifications']),
        'interface_animations' => isset($_POST['interface_animations']),
        'auto_lock' => isset($_POST['auto_lock']),
        'dark_mode' => isset($_POST['dark_mode']),
        'show_tooltips' => isset($_POST['show_tooltips'])
    ];
    
    if ($pdo) {
        $saved_to_mysql = true;
        foreach ($preferences as $pref_name => $pref_value) {
            if (!saveUserSetting($pdo, $current_user_id, $pref_name, $pref_value ? '1' : '0')) {
                $saved_to_mysql = false;
                break;
            }
        }
        
        if ($saved_to_mysql) {
            $message = 'Preferencje zostały zapisane w bazie danych!';
        } else {
            $_SESSION['preferences'] = $preferences;
            $message = 'Preferencje zostały zapisane (sesja)!';
        }
    } else {
        $_SESSION['preferences'] = $preferences;
        $message = 'Preferencje zostały zapisane (sesja)!';
    }
}

// Pobierz aktualne ustawienia
$current_wallpaper = $user_settings['wallpaper'] ?? $_SESSION['wallpaper'] ?? DEFAULT_WALLPAPER;
$custom_wallpaper = $user_settings['custom_wallpaper'] ?? $_SESSION['custom_wallpaper'] ?? null;

$preferences = [
    'sound_notifications' => ($user_settings['sound_notifications'] ?? '1') === '1',
    'interface_animations' => ($user_settings['interface_animations'] ?? '1') === '1',
    'auto_lock' => ($user_settings['auto_lock'] ?? '0') === '1',
    'dark_mode' => ($user_settings['dark_mode'] ?? '0') === '1',
    'show_tooltips' => ($user_settings['show_tooltips'] ?? '1') === '1'
];

// Fallback z sesji jeśli MySQL nie działa
if (!$mysql_connected) {
    $preferences = $_SESSION['preferences'] ?? $preferences;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia systemu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .settings-header::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 300px; height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .settings-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .settings-header p {
            font-size: 16px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        .settings-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: white;
            padding: 10px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .nav-tab {
            flex: 1;
            padding: 15px 20px;
            background: transparent;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .nav-tab:hover { background: #f8fafc; }
        
        .nav-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .nav-tab svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
        
        .settings-content {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .tab-content {
            display: none;
            padding: 40px;
            animation: slideIn 0.4s ease-out;
        }
        
        .tab-content.active { display: block; }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .section-subtitle {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 30px;
        }
        
        .wallpaper-categories { margin-bottom: 40px; }
        
        .category-title {
            font-size: 18px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-title::before {
            content: '';
            width: 4px; height: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
        
        .wallpaper-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .wallpaper-option {
            position: relative;
            aspect-ratio: 16/9;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 3px solid transparent;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .wallpaper-option:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .wallpaper-option.active {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }
        
        .wallpaper-option input {
            position: absolute;
            opacity: 0;
            width: 100%; height: 100%;
            cursor: pointer;
        }
        
        .wallpaper-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .wallpaper-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .wallpaper-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .wallpaper-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .wallpaper-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .wallpaper-6 { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
        .wallpaper-7 { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .wallpaper-8 { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); }
        .wallpaper-9 { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }
        .wallpaper-10 { background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%); }
        
        .checkmark {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 50px; height: 50px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .wallpaper-option.active .checkmark {
            display: flex;
            animation: checkmarkPop 0.3s ease-out;
        }
        
        @keyframes checkmarkPop {
            from { transform: translate(-50%, -50%) scale(0); }
            to { transform: translate(-50%, -50%) scale(1); }
        }
        
        .checkmark svg {
            width: 30px; height: 30px;
            fill: #10b981;
        }
        
        .upload-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            border: 2px dashed #cbd5e1;
            transition: all 0.3s;
        }
        
        .upload-section:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }
        
        .upload-icon {
            width: 80px; height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .upload-icon svg {
            width: 40px; height: 40px;
            fill: white;
        }
        
        .upload-button {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .upload-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .upload-input { display: none; }
        
        .profile-card {
            display: flex;
            align-items: center;
            gap: 30px;
            padding: 30px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px; height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .profile-avatar svg {
            width: 50px; height: 50px;
            fill: white;
        }
        
        .profile-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: #64748b;
            font-size: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .info-label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .status-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .stat-title {
            font-size: 14px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon.week { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.status { background: linear-gradient(135deg, #f59e0b, #d97706); }
        
        .stat-icon svg {
            width: 20px; height: 20px;
            fill: white;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .stat-description {
            font-size: 13px;
            color: #64748b;
        }
        
        .week-hours.good { color: #10b981; }
        .week-hours.bad { color: #ef4444; }
        
        .current-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-1 {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-3 {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: messageSlide 0.4s ease-out;
        }
        
        @keyframes messageSlide {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .message.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
        
        .message svg {
            width: 24px; height: 24px;
            fill: currentColor;
        }
        
        .switch-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        
        .switch-label {
            font-size: 15px;
            font-weight: 500;
            color: #334155;
        }
        
        .switch {
            position: relative;
            width: 60px; height: 30px;
            background: #cbd5e1;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .switch.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .switch-slider {
            position: absolute;
            top: 3px; left: 3px;
            width: 24px; height: 24px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .switch.active .switch-slider {
            transform: translateX(30px);
        }
        
        .save-preferences {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .save-preferences:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <?php if ($message): ?>
        <div class="message success">
            <svg viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
            </svg>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="message error">
            <svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="settings-header">
            <h1>Ustawienia systemu</h1>
            <p>Spersonalizuj swoje środowisko pracy i zarządzaj ustawieniami systemu
            <?php if (isset($mysql_connected)): ?>
                <span style="margin-left: 15px; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; <?php echo $mysql_connected ? 'background: rgba(16, 185, 129, 0.2); color: rgba(16, 185, 129, 0.9);' : 'background: rgba(239, 68, 68, 0.2); color: rgba(239, 68, 68, 0.9);'; ?>">
                    <?php echo $mysql_connected ? '● MySQL Połączono' : '● MySQL Brak połączenia (dane z sesji)'; ?>
                </span>
            <?php endif; ?>
            </p>
        </div>
        
        <div class="settings-nav">
            <button class="nav-tab active" onclick="switchTab('appearance')">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                Wygląd
            </button>
            <button class="nav-tab" onclick="switchTab('profile')">
                <svg viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                Profil
            </button>
            <button class="nav-tab" onclick="switchTab('preferences')">
                <svg viewBox="0 0 24 24">
                    <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                </svg>
                Preferencje
            </button>
        </div>
        
        <div class="settings-content">
            <!-- Appearance Tab -->
            <div class="tab-content active" id="appearance-tab">
                <h2 class="section-title">Personalizacja wyglądu</h2>
                <p class="section-subtitle">Wybierz tapetę i dostosuj interfejs do swoich preferencji</p>
                
                <div class="wallpaper-categories">
                    <h3 class="category-title">Tapety gradientowe</h3>
                    <form method="POST" action="" id="wallpaperForm">
                        <div class="wallpaper-grid">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                            <label class="wallpaper-option wallpaper-<?php echo $i; ?> <?php echo (!$custom_wallpaper && $current_wallpaper == $i) ? 'active' : ''; ?>">
                                <input type="radio" name="wallpaper" value="<?php echo $i; ?>" 
                                       <?php echo (!$custom_wallpaper && $current_wallpaper == $i) ? 'checked' : ''; ?>
                                       onchange="this.form.submit()">
                                <div class="checkmark">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                    </svg>
                                </div>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </form>
                </div>
                
                <div class="wallpaper-categories">
                    <h3 class="category-title">Własna tapeta</h3>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="upload-section">
                            <div class="upload-icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/>
                                </svg>
                            </div>
                            <h3 style="font-size: 20px; margin-bottom: 10px; color: #1e293b;">Prześlij własny obraz</h3>
                            <p style="color: #64748b; margin-bottom: 20px;">Obsługiwane formaty: JPG, PNG, GIF, WEBP (max 5MB)</p>
                            <label for="customWallpaper" class="upload-button">
                                Wybierz plik
                                <input type="file" name="custom_wallpaper" id="customWallpaper" 
                                       class="upload-input" accept="image/*" onchange="this.form.submit()">
                            </label>
                            
                            <?php if ($custom_wallpaper && file_exists(UPLOAD_DIR . $custom_wallpaper)): ?>
                            <div style="margin-top: 30px;">
                                <p style="color: #10b981; font-weight: 600;">✓ Własna tapeta jest aktywna</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Profile Tab -->
            <div class="tab-content" id="profile-tab">
                <h2 class="section-title">Informacje o profilu</h2>
                <p class="section-subtitle">Dane użytkownika i statystyki konta</p>
                
                <div class="profile-card">
                    <div class="profile-avatar">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($user_data['full_name']); ?></h3>
                        <p><?php echo htmlspecialchars($user_data['role']); ?> • <?php echo htmlspecialchars($user_data['badge_number'] ?? 'N/A'); ?></p>
                    </div>
                </div>
                
                <div class="status-stats">
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Ten tydzień</span>
                            <div class="stat-icon week">
                                <svg viewBox="0 0 24 24">
                                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-value week-hours <?php echo $user_stats['week_hours'] >= $user_stats['min_weekly_hours'] ? 'good' : 'bad'; ?>">
                            <?php echo round($user_stats['week_hours'], 1); ?>h
                        </div>
                        <div class="stat-description">
                            Min. <?php echo $user_stats['min_weekly_hours']; ?>h tygodniowo
                            <?php if ($user_stats['week_hours'] >= $user_stats['min_weekly_hours']): ?>
                                <span style="color: #10b981;">✓ Cel osiągnięty</span>
                            <?php else: ?>
                                <span style="color: #ef4444;">⚠ Poniżej minimum</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <span class="stat-title">Aktualny status</span>
                            <div class="stat-icon status">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="current-status status-<?php echo $user_stats['current_status']; ?>">
                            <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                                <?php if ($user_stats['current_status'] == 1): ?>
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                <?php else: ?>
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                                <?php endif; ?>
                            </svg>
                            <?php echo $user_stats['current_status'] == 1 ? 'Na służbie' : 'Poza służbą'; ?>
                        </div>
                        <div class="stat-description">
                            Ostatnia zmiana: <?php echo date('d.m.Y H:i', strtotime($user_stats['last_status_change'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nazwa użytkownika</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['username']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Rola w systemie</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['role']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Numer odznaki</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['badge_number'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Stopień</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['rank_name'] ?? 'Brak'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_data['email'] ?? 'Brak'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Liczba zmian statusu</div>
                        <div class="info-value"><?php echo $user_stats['status_changes'] ?? 0; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Źródło danych</div>
                        <div class="info-value" style="<?php echo $mysql_connected ? 'color: #10b981;' : 'color: #f59e0b;'; ?>">
                            <?php echo $mysql_connected ? 'MySQL Database' : 'Session Data'; ?>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 40px;">
                    <h3 style="font-size: 20px; font-weight: 600; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: #667eea;">
                            <path d="M13,3A9,9 0 0,0 4,12H1L4.89,15.89L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3Z"/>
                        </svg>
                        Historia aktywności
                    </h3>
                    
                    <div style="background: #f8fafc; border-radius: 12px; padding: 20px; max-height: 400px; overflow-y: auto;">
                        <?php if (empty($recent_activities)): ?>
                            <div style="text-align: center; padding: 40px; color: #64748b;">
                                <svg viewBox="0 0 24 24" style="width: 48px; height: 48px; fill: currentColor; margin-bottom: 20px; opacity: 0.5;">
                                    <path d="M13,3A9,9 0 0,0 4,12H1L4.89,15.89L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3Z"/>
                                </svg><br>
                                Brak historii aktywności
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                            <div style="display: flex; align-items: center; padding: 12px; background: white; border-radius: 8px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #2563eb); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: white;">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #1e293b; margin-bottom: 2px;">
                                        Zmiana statusu z <?php echo $activity['status_from'] == 1 ? 'na służbie' : 'poza służbą'; ?> 
                                        na <?php echo $activity['status_to'] == 1 ? 'na służbie' : 'poza służbą'; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?php echo date('d.m.Y H:i', strtotime($activity['change_time'])); ?>
                                        <?php if ($activity['duration_minutes']): ?>
                                            - Czas pracy: <?php echo formatHoursReadable($activity['duration_minutes'] / 60); ?>
                                        <?php endif; ?>
                                        <?php if ($activity['notes']): ?>
                                            <br><em><?php echo htmlspecialchars($activity['notes']); ?></em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Preferences Tab -->
            <div class="tab-content" id="preferences-tab">
                <h2 class="section-title">Preferencje systemowe</h2>
                <p class="section-subtitle">Dostosuj zachowanie systemu</p>
                
                <form method="POST" action="">
                    <div class="switch-group">
                        <span class="switch-label">Powiadomienia dźwiękowe</span>
                        <div class="switch <?php echo $preferences['sound_notifications'] ? 'active' : ''; ?>" onclick="toggleSwitch(this)">
                            <div class="switch-slider"></div>
                            <input type="hidden" name="sound_notifications" value="<?php echo $preferences['sound_notifications'] ? '1' : '0'; ?>">
                        </div>
                    </div>
                    
                    <div class="switch-group">
                        <span class="switch-label">Animacje interfejsu</span>
                        <div class="switch <?php echo $preferences['interface_animations'] ? 'active' : ''; ?>" onclick="toggleSwitch(this)">
                            <div class="switch-slider"></div>
                            <input type="hidden" name="interface_animations" value="<?php echo $preferences['interface_animations'] ? '1' : '0'; ?>">
                        </div>
                    </div>
                    
                    <div class="switch-group">
                        <span class="switch-label">Automatyczne blokowanie</span>
                        <div class="switch <?php echo $preferences['auto_lock'] ? 'active' : ''; ?>" onclick="toggleSwitch(this)">
                            <div class="switch-slider"></div>
                            <input type="hidden" name="auto_lock" value="<?php echo $preferences['auto_lock'] ? '1' : '0'; ?>">
                        </div>
                    </div>
                    
                    <div class="switch-group">
                        <span class="switch-label">Tryb ciemny</span>
                        <div class="switch <?php echo $preferences['dark_mode'] ? 'active' : ''; ?>" onclick="toggleSwitch(this)">
                            <div class="switch-slider"></div>
                            <input type="hidden" name="dark_mode" value="<?php echo $preferences['dark_mode'] ? '1' : '0'; ?>">
                        </div>
                    </div>
                    
                    <div class="switch-group">
                        <span class="switch-label">Pokazuj podpowiedzi</span>
                        <div class="switch <?php echo $preferences['show_tooltips'] ? 'active' : ''; ?>" onclick="toggleSwitch(this)">
                            <div class="switch-slider"></div>
                            <input type="hidden" name="show_tooltips" value="<?php echo $preferences['show_tooltips'] ? '1' : '0'; ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="save_preferences" class="save-preferences">
                        Zapisz preferencje
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function toggleSwitch(switchElement) {
            switchElement.classList.toggle('active');
            const hiddenInput = switchElement.querySelector('input[type="hidden"]');
            if (hiddenInput) {
                hiddenInput.value = switchElement.classList.contains('active') ? '1' : '0';
            }
        }
        
        window.addEventListener('load', function() {
            if (window.parent && window.parent !== window) {
                <?php if ($message && (strpos($message, 'tapeta') !== false || strpos($message, 'Tapeta') !== false)): ?>
                setTimeout(() => {
                    window.parent.location.reload();
                }, 1500);
                <?php endif; ?>
            }
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            const wallpaperInputs = document.querySelectorAll('input[name="wallpaper"]');
            wallpaperInputs.forEach(input => {
                input.addEventListener('change', function() {
                    document.querySelectorAll('.wallpaper-option').forEach(option => {
                        option.classList.remove('active');
                    });
                    this.parentElement.classList.add('active');
                });
            });
        });
        
        console.log('Settings system loaded - MySQL integration with session fallback');
        console.log('Database status: <?php echo $mysql_connected ? "Connected" : "Fallback to session"; ?>');
    </script>
</body>
</html>

<?php
// SQL do utworzenia tabeli user_settings (odkomentuj i wykonaj raz w bazie danych)
/*
CREATE TABLE `user_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `setting_name` varchar(50) NOT NULL,
    `setting_value` text,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_setting_unique` (`user_id`, `setting_name`),
    KEY `user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
?>