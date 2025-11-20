<?php
// dashboard.php - Panel główny z MySQL tapetami i efektami
require_once 'config.php';

// Sprawdzenie autoryzacji
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Wylogowanie
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Połączenie z MySQL dla tapet
$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("MySQL connection failed: " . $e->getMessage());
}

// Pobierz dane użytkownika
$user = $_SESSION['user_data'];
$username = $_SESSION['username'];
$current_user_id = $_SESSION['user_id'] ?? 1;

// Pobierz tapetę z MySQL lub fallback do sesji
$wallpaper = DEFAULT_WALLPAPER;
$custom_wallpaper = null;

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_name, setting_value FROM user_settings WHERE user_id = ? AND setting_name IN ('wallpaper', 'custom_wallpaper')");
        $stmt->execute([$current_user_id]);
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $wallpaper = $settings['wallpaper'] ?? $_SESSION['wallpaper'] ?? DEFAULT_WALLPAPER;
        $custom_wallpaper = $settings['custom_wallpaper'] ?? $_SESSION['custom_wallpaper'] ?? null;
    } catch (Exception $e) {
        error_log("Error loading wallpaper settings: " . $e->getMessage());
        $wallpaper = $_SESSION['wallpaper'] ?? DEFAULT_WALLPAPER;
        $custom_wallpaper = $_SESSION['custom_wallpaper'] ?? null;
    }
} else {
    $wallpaper = $_SESSION['wallpaper'] ?? DEFAULT_WALLPAPER;
    $custom_wallpaper = $_SESSION['custom_wallpaper'] ?? null;
}

// Sprawdź czy to custom tapeta czy systemowa
$wallpaper_style = '';
if ($custom_wallpaper && file_exists(UPLOAD_DIR . $custom_wallpaper)) {
    $wallpaper_style = "background-image: url('" . UPLOAD_DIR . $custom_wallpaper . "');";
} else {
    $wallpaper_style = "background: " . $SYSTEM_WALLPAPERS[$wallpaper] . ";";
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_NAME; ?> - Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            overflow: hidden;
            background: #000;
        }
        
        /* Desktop with Windows 11 wallpaper */
        .desktop {
            width: 100%;
            height: calc(100vh - 48px);
            <?php echo $wallpaper_style; ?>
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            padding: 20px;
            position: relative;
        }
        
        /* Desktop right-click context menu */
        .context-menu {
            position: fixed;
            background: rgba(32, 32, 32, 0.95);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 6px 0;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 30000;
            min-width: 240px;
        }
        
        .context-menu-item {
            padding: 8px 16px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            cursor: pointer;
            transition: background 0.2s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .context-menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .context-menu-separator {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 6px 0;
        }
        
        .context-menu-item svg {
            width: 16px;
            height: 16px;
            fill: rgba(255, 255, 255, 0.8);
        }
        
        /* Clean Desktop Icons */
        .desktop-icons {
            display: grid;
            grid-template-columns: repeat(auto-fill, 80px);
            grid-auto-rows: 90px;
            gap: 16px;
            padding: 20px;
        }
        
        .desktop-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            position: relative;
        }
        
        .desktop-icon:hover {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .desktop-icon:active {
            transform: scale(0.95);
        }
        
        .desktop-icon.selected {
            background: rgba(0, 120, 212, 0.3);
            border: 1px solid rgba(0, 120, 212, 0.6);
        }
        
        .icon-image {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 6px;
            transition: transform 0.2s ease;
        }
        
        .desktop-icon:hover .icon-image {
            transform: scale(1.05);
        }
        
        /* Windows 11 style icon colors */
        .icon-officers { background: linear-gradient(135deg, #0078d4, #106ebe); }
        .icon-citizens { background: linear-gradient(135deg, #16a085, #1abc9c); }
        .icon-reports { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .icon-cases { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .icon-vehicles { background: linear-gradient(135deg, #3498db, #2980b9); }
        .icon-game { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .icon-settings { background: linear-gradient(135deg, #7f8c8d, #95a5a6); }
        .icon-admin { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .icon-status { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .icon-google { background: linear-gradient(135deg, #4285f4, #1976d2); }
        
        .icon-image svg {
            width: 24px;
            height: 24px;
            fill: white;
        }
        
        .icon-label {
            color: white;
            font-size: 12px;
            text-align: center;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8);
            font-weight: 400;
            max-width: 70px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Windows 11 Taskbar */
        .taskbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 48px;
            background: rgba(32, 32, 32, 0.85);
            backdrop-filter: blur(40px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        /* Centered taskbar container */
        .taskbar-center {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 6px;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Start Button */
        .start-button {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 4px;
        }
        
        .start-button:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .start-button svg {
            width: 16px;
            height: 16px;
            fill: #ffffff;
        }
        
        /* Search Box */
        .search-box {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 4px;
        }
        
        .search-box:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .search-box svg {
            width: 16px;
            height: 16px;
            fill: rgba(255, 255, 255, 0.9);
        }
        
        /* Task View */
        .task-view {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 8px;
        }
        
        .task-view:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .task-view svg {
            width: 16px;
            height: 16px;
            fill: rgba(255, 255, 255, 0.9);
        }
        
        /* Taskbar Apps */
        .taskbar-apps {
            display: flex;
            align-items: center;
            gap: 2px;
        }
        
        .taskbar-app {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .taskbar-app:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .taskbar-app.active::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 3px;
            background: #0078d4;
            border-radius: 2px;
        }
        
        .taskbar-app svg {
            width: 16px;
            height: 16px;
            fill: rgba(255, 255, 255, 0.9);
        }
        
        /* System Tray */
        .system-tray {
            position: absolute;
            right: 8px;
            display: flex;
            align-items: center;
            gap: 2px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 4px;
            padding: 2px;
        }
        
        .tray-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 2px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .tray-icon:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .tray-icon svg {
            width: 14px;
            height: 14px;
            fill: rgba(255, 255, 255, 0.85);
        }
        
        /* Network status indicator */
        .tray-icon.network::after {
            content: '';
            position: absolute;
            top: 6px;
            right: 6px;
            width: 4px;
            height: 4px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 4px rgba(16, 185, 129, 0.7);
        }
        
        /* Battery indicator */
        .battery-icon {
            position: relative;
        }
        
        .battery-level {
            position: absolute;
            bottom: 4px;
            right: 4px;
            font-size: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
        }
        
        /* Clock */
        .clock {
            padding: 0 12px;
            color: white;
            font-size: 13px;
            line-height: 1.2;
            text-align: right;
            cursor: pointer;
            border-radius: 2px;
            transition: all 0.2s ease;
            height: 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 80px;
        }
        
        .clock:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .time {
            font-weight: 400;
            font-size: 13px;
            font-family: 'Segoe UI', monospace;
        }
        
        .date {
            font-size: 11px;
            opacity: 0.9;
        }
        
        /* Start Menu */
        .start-menu {
            position: absolute;
            bottom: 56px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            background: rgba(32, 32, 32, 0.95);
            backdrop-filter: blur(50px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            box-shadow: 0 16px 64px rgba(0, 0, 0, 0.4);
            display: none;
            overflow: hidden;
            z-index: 20000;
        }
        
        .start-menu.active {
            display: block;
            animation: slideUp 0.2s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        .start-header {
            padding: 24px;
        }
        
        .start-search {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            padding: 8px 12px;
            color: white;
            font-size: 14px;
            width: 100%;
            transition: all 0.2s ease;
        }
        
        .start-search:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.18);
            border-color: #0078d4;
            box-shadow: 0 0 0 2px rgba(0, 120, 212, 0.3);
        }
        
        .start-search::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .start-content {
            padding: 0 24px 24px;
        }
        
        .section-title {
            color: rgba(255, 255, 255, 0.8);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 24px;
        }
        
        .app-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .app-tile:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .app-tile.hidden {
            display: none;
        }
        
        .app-tile-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #0078d4, #106ebe);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 6px;
        }
        
        .app-tile-icon svg {
            width: 16px;
            height: 16px;
            fill: white;
        }
        
        .app-tile-name {
            color: rgba(255, 255, 255, 0.9);
            font-size: 11px;
            text-align: center;
            font-weight: 400;
        }
        
        .start-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.1);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .user-profile:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .user-avatar {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #0078d4, #106ebe);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-avatar svg {
            width: 14px;
            height: 14px;
            fill: white;
        }
        
        .user-name {
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            font-weight: 400;
        }
        
        .power-button {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .power-button:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .power-button svg {
            width: 14px;
            height: 14px;
            fill: rgba(255, 255, 255, 0.8);
        }
        
        /* Search overlay */
        .search-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding-top: 100px;
            z-index: 25000;
        }
        
        .search-overlay.active {
            display: flex;
            animation: fadeIn 0.2s ease;
        }
        
        .search-container {
            width: 600px;
            background: rgba(32, 32, 32, 0.95);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            overflow: hidden;
            box-shadow: 0 16px 64px rgba(0, 0, 0, 0.5);
        }
        
        .search-input-container {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .search-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            padding: 12px 16px;
            color: white;
            font-size: 16px;
            transition: all 0.2s ease;
        }
        
        .search-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.18);
            border-color: #0078d4;
            box-shadow: 0 0 0 2px rgba(0, 120, 212, 0.3);
        }
        
        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .search-results {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .search-result {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s ease;
            gap: 12px;
        }
        
        .search-result:hover {
            background: rgba(255, 255, 255, 0.12);
        }
        
        .search-result-icon {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-result-content {
            flex: 1;
        }
        
        .search-result-name {
            color: white;
            font-size: 14px;
            font-weight: 400;
            margin-bottom: 2px;
        }
        
        .search-result-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 20000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 16px 64px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            animation: modalSlide 0.2s ease;
        }
        
        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        /* Window */
        .window {
            position: fixed;
            background: white;
            border-radius: 8px;
            box-shadow: 0 16px 64px rgba(0, 0, 0, 0.4);
            display: none;
            z-index: 15000;
            border: 1px solid rgba(0, 0, 0, 0.08);
            min-width: 400px;
            min-height: 300px;
            overflow: hidden;
        }
        
        .window.active {
            display: block;
            animation: windowOpen 0.2s ease;
        }
        
        .window.maximized {
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: calc(100vh - 48px) !important;
            border-radius: 0 !important;
            transform: none !important;
        }
        
        @keyframes windowOpen {
            from {
                opacity: 0;
                transform: scale(0.95) translate(-50%, -50%);
            }
            to {
                opacity: 1;
                transform: scale(1) translate(-50%, -50%);
            }
        }
        
        @keyframes windowClose {
            to {
                opacity: 0;
                transform: scale(0.95) translate(-50%, -50%);
            }
        }
        
        .window-header {
            background: white;
            color: #333;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0e0e0;
            user-select: none;
            cursor: move;
            height: 40px;
        }
        
        .window-title {
            font-size: 14px;
            font-weight: 400;
        }
        
        .window-controls {
            display: flex;
            gap: 8px;
        }
        
        .window-btn {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .window-btn:hover {
            transform: scale(1.15);
        }
        
        .window-close {
            background: #ff5f57;
        }
        
        .window-minimize {
            background: #ffbd2e;
        }
        
        .window-maximize {
            background: #28ca42;
        }
        
        .window-body {
            background: white;
            overflow: hidden;
            height: calc(100% - 40px);
        }
        
        .window iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* Loading screen */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50000;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .loading-screen.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .loading-content {
            text-align: center;
            color: white;
        }
        
        .loading-spinner {
            width: 32px;
            height: 32px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-top: 2px solid #0078d4;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            font-size: 16px;
            font-weight: 400;
            margin-bottom: 4px;
        }
        
        .loading-subtext {
            font-size: 12px;
            opacity: 0.7;
        }
        
        /* Notifications */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(32, 32, 32, 0.95);
            backdrop-filter: blur(20px);
            color: white;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 16px 64px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.12);
            max-width: 350px;
            z-index: 40000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .notification-icon {
            width: 20px;
            height: 20px;
            background: #0078d4;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 14px;
        }
        
        .notification-body {
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.4;
        }
        
        /* Tooltip */
        .tooltip {
            position: fixed;
            background: rgba(32, 32, 32, 0.95);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
            z-index: 50000;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .tooltip.show {
            opacity: 1;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .desktop-icons {
                grid-template-columns: repeat(auto-fill, 70px);
                gap: 12px;
            }
            
            .start-menu {
                width: 90vw;
            }
            
            .apps-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .search-container {
                width: 90vw;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Ładowanie systemu</div>
            <div class="loading-subtext">Przygotowywanie środowiska pracy</div>
        </div>
    </div>
    
    <div class="desktop" oncontextmenu="showContextMenu(event)">
        <div class="desktop-icons">
            <!-- Status -->
            <div class="desktop-icon" onclick="selectIcon(this)" ondblclick="openStatusWindow()" data-name="Status" data-description="Status funkcjonariuszy systemu">
                <div class="icon-image icon-status">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <span class="icon-label">Duty Shift</span>
            </div>
            
            <!-- Funkcjonariusze -->
            <div class="desktop-icon" onclick="selectIcon(this)" ondblclick="openOfficersWindow()" data-name="Funkcjonariusze" data-description="Zarządzanie funkcjonariuszami">
                <div class="icon-image icon-officers">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2L3.5 7v6c0 5.55 3.84 10.74 8.5 12 4.66-1.26 8.5-6.45 8.5-12V7L12 2zm0 10h7c-.53 4.12-3.28 7.79-7 8.94V12H5V8.3l7-3.11v6.81z"/>
                    </svg>
                </div>
                <span class="icon-label">Peace Officer</span>
            </div>
            
            <!-- Obywatele -->
            <div class="desktop-icon" onclick="selectIcon(this)" ondblclick="openCitizensWindow()" data-name="Obywatele" data-description="Baza danych obywateli">
                <div class="icon-image icon-citizens">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                    </svg>
                </div>
                <span class="icon-label">Obywatele</span>
            </div>
            
            <!-- Raporty -->
            <div class="desktop-icon" onclick="selectIcon(this)" ondblclick="openReportsWindow()" data-name="Raporty" data-description="System raportowania">
                <div class="icon-image icon-reports">
                    <svg viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                </div>
                <span class="icon-label">Raporty</span>
            </div>
            
            <!-- Sprawy -->
            <div class="desktop-icon" onclick="selectIcon(this)" data-app="cases" ondblclick="showWorkInProgress()" data-name="Sprawy" data-description="Zarządzanie sprawami">
                <div class="icon-image icon-cases">
                    <svg viewBox="0 0 24 24">
                        <path d="M10 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2h-8l-2-2z"/>
                    </svg>
                </div>
                <span class="icon-label">Sprawy</span>
            </div>

            <!-- Pojazdy -->
            <div class="desktop-icon" onclick="selectIcon(this)" ondblclick="openVehiclesWindow()" data-name="Pojazdy" data-description="Zarządzanie flotą pojazdów">
                <div class="icon-image icon-vehicles">
                    <svg viewBox="0 0 24 24">
                        <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.22.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
                    </svg>
                </div>
                <span class="icon-label">Pojazdy</span>
            </div>
            
            <!-- Game Center -->
            <div class="desktop-icon" onclick="selectIcon(this)" ondblclick="openGameWindow()" data-name="Game Center" data-description="Centrum gier i rozrywki">
                <div class="icon-image icon-game">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-10 7H8v3H6v-3H3v-2h3V8h2v3h3v2zm4.5 2c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4-3c-.83 0-1.5-.67-1.5-1.5S18.67 9 19.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                    </svg>
                </div>
                <span class="icon-label">Game Center</span>
            </div>
            
            <!-- Administracja (tylko dla adminów) -->
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
            <div class="desktop-icon" onclick="selectIcon(this)" ondblclick="openAdminPanel()" data-name="Administracja" data-description="Panel administracyjny">
                <div class="icon-image icon-admin">
                    <svg viewBox="0 0 24 24">
                        <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.6 14.8,10V11.5C15.4,12.1 16,12.8 16,14V18C16,19.1 15.1,20 14,20H10C8.9,20 8,19.1 8,18V14C8,12.8 8.6,12.1 9.2,11.5V10C9.2,8.6 10.6,7 12,7M12,8.2C11.2,8.2 10.5,8.7 10.5,10V11.5H13.5V10C13.5,8.7 12.8,8.2 12,8.2Z"/>
                    </svg>
                </div>
                <span class="icon-label">Administracja</span>
            </div>
            <?php endif; ?>
            
            <!-- Ustawienia -->
            <div class="desktop-icon" onclick="selectIcon(this)" ondblclick="openSettings()" data-name="Ustawienia" data-description="Ustawienia systemu">
                <div class="icon-image icon-settings">
                    <svg viewBox="0 0 24 24">
                        <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                    </svg>
                </div>
                <span class="icon-label">Ustawienia</span>
            </div>
            
            <!-- Recycle Bin -->
            <div class="desktop-icon" style="position: absolute; bottom: 40px; right: 20px;" onclick="selectIcon(this)" ondblclick="showWorkInProgress()" data-name="Kosz" data-description="Kosz systemu">
                <div class="icon-image" style="background: linear-gradient(135deg, #6b7280, #4b5563);">
                    <svg viewBox="0 0 24 24">
                        <path d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"/>
                    </svg>
                </div>
                <span class="icon-label">Kosz</span>
            </div>
        </div>
    </div>
    
    <!-- Context Menu -->
    <div class="context-menu" id="contextMenu">
        <div class="context-menu-item" onclick="openSettings(); hideContextMenu();">
            <svg viewBox="0 0 24 24">
                <path d="M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8M12,10A2,2 0 0,0 10,12A2,2 0 0,0 12,14A2,2 0 0,0 14,12A2,2 0 0,0 12,10M10,22C9.75,22 9.54,21.82 9.5,21.58L9.13,18.93C8.5,18.68 7.96,18.34 7.44,17.94L4.95,18.95C4.73,19.03 4.46,18.95 4.34,18.73L2.34,15.27C2.21,15.05 2.27,14.78 2.46,14.63L4.57,12.97L4.5,12L4.57,11.03L2.46,9.37C2.27,9.22 2.21,8.95 2.34,8.73L4.34,5.27C4.46,5.05 4.73,4.96 4.95,5.05L7.44,6.05C7.96,5.66 8.5,5.32 9.13,5.07L9.5,2.42C9.54,2.18 9.75,2 10,2H14C14.25,2 14.46,2.18 14.5,2.42L14.87,5.07C15.5,5.32 16.04,5.66 16.56,6.05L19.05,5.05C19.27,4.96 19.54,5.05 19.66,5.27L21.66,8.73C21.79,8.95 21.73,9.22 21.54,9.37L19.43,11.03L19.5,12L19.43,12.97L21.54,14.63C21.73,14.78 21.79,15.05 21.66,15.27L19.66,18.73C19.54,18.95 19.27,19.04 19.05,18.95L16.56,17.95C16.04,18.34 15.5,18.68 14.87,18.93L14.5,21.58C14.46,21.82 14.25,22 14,22H10M11.25,4L10.88,6.61C9.68,6.86 8.62,7.5 7.85,8.39L5.44,7.35L4.69,8.65L6.8,10.2C6.4,11.37 6.4,12.64 6.8,13.8L4.68,15.36L5.43,16.66L7.86,15.62C8.63,16.5 9.68,17.14 10.87,17.38L11.24,20H12.76L13.13,17.39C14.32,17.14 15.37,16.5 16.14,15.62L18.57,16.66L19.32,15.36L17.2,13.81C17.6,12.64 17.6,11.37 17.2,10.2L19.31,8.65L18.56,7.35L16.15,8.39C15.38,7.5 14.32,6.86 13.12,6.62L12.75,4H11.25Z"/>
            </svg>
            Ustawienia wyświetlania
        </div>
        <div class="context-menu-item" onclick="showNotification('System', 'Nowy dokument utworzony'); hideContextMenu();">
            <svg viewBox="0 0 24 24">
                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
            </svg>
            Nowy dokument
        </div>
        <div class="context-menu-separator"></div>
        <div class="context-menu-item" onclick="refreshDesktop(); hideContextMenu();">
            <svg viewBox="0 0 24 24">
                <path d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z"/>
            </svg>
            Odśwież
        </div>
        <div class="context-menu-separator"></div>
        <div class="context-menu-item" onclick="openSearchOverlay(); hideContextMenu();">
            <svg viewBox="0 0 24 24">
                <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
            </svg>
            Wyszukaj
        </div>
    </div>
    
    <!-- Search Overlay -->
    <div class="search-overlay" id="searchOverlay">
        <div class="search-container">
            <div class="search-input-container">
                <input type="text" class="search-input" id="searchInput" placeholder="Wpisz "Peace Officer" aby zobaczyć dane funkcnonariuszy>
            </div>
            <div class="search-results" id="searchResults">
                <!-- Results will be populated here -->
            </div>
        </div>
    </div>
    
    <!-- Windows 11 Taskbar -->
    <div class="taskbar">
        <div class="taskbar-center">
            <!-- Start Button -->
            <div class="start-button" onclick="toggleStartMenu()" onmouseenter="showTooltip(this, 'Start')">
                <svg viewBox="0 0 24 24">
                    <path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>
                </svg>
            </div>
            
            <!-- Search -->
            <div class="search-box" onclick="openSearchOverlay()" onmouseenter="showTooltip(this, 'Wyszukaj')">
                <svg viewBox="0 0 24 24">
                    <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
            </div>
            
            <!-- Task View -->
            <div class="task-view" onmouseenter="showTooltip(this, 'Widok zadań')">
                <svg viewBox="0 0 24 24">
                    <path d="M4 6h7V4H4c-1.1 0-2 .9-2 2v7h2V6zm0 11h7v2H4c-1.1 0-2-.9-2-2v-7h2v7zm16-11h-7V4h7c1.1 0 2 .9 2 2v7h-2V6zm0 11h-7v2h7c1.1 0 2-.9 2-2v-7h-2v7z"/>
                </svg>
            </div>
            
            <!-- Taskbar Apps -->
            <div class="taskbar-apps" id="taskbarApps">
                <!-- Dynamically added apps will go here -->
            </div>
        </div>
        
        <!-- System Tray -->
        <div class="system-tray">
            <!-- Network -->
            <div class="tray-icon network" onmouseenter="showTooltip(this, 'Sieć: Połączono')">
                <svg viewBox="0 0 24 24">
                    <path d="M1 9l2 2c4.97-4.97 13.03-4.97 18 0l2-2C16.93 2.93 7.08 2.93 1 9zm8 8l3 3 3-3a4.237 4.237 0 0 0-6 0zm-4-4l2 2a7.074 7.074 0 0 1 10 0l2-2C15.14 9.14 8.87 9.14 5 13z"/>
                </svg>
            </div>
            
            <!-- Volume -->
            <div class="tray-icon" onmouseenter="showTooltip(this, 'Głośność: 85%')" onclick="playSystemSound()">
                <svg viewBox="0 0 24 24">
                    <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/>
                </svg>
            </div>
            
            <!-- Battery -->
            <div class="tray-icon battery-icon" onmouseenter="showTooltip(this, 'Bateria: 67% (Ładowanie)')">
                <svg viewBox="0 0 24 24">
                    <path d="M15.67 4H14V2h-4v2H8.33C7.6 4 7 4.6 7 5.33v15.33C7 21.4 7.6 22 8.33 22h7.33c.74 0 1.34-.6 1.34-1.33V5.33C17 4.6 16.4 4 15.67 4z"/>
                </svg>
                <span class="battery-level" id="batteryLevel">67</span>
            </div>
            
            <!-- Action Center -->
            <div class="tray-icon" onmouseenter="showTooltip(this, 'Centrum akcji')" onclick="showNotification('System', 'Centrum akcji zostanie wkrótce dodane')">
                <svg viewBox="0 0 24 24">
                    <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                </svg>
            </div>
            
            <!-- Clock -->
            <div class="clock" onclick="showNotification('Kalendarz', 'Dzisiaj jest ' + new Date().toLocaleDateString('pl-PL', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'}))">
                <div class="time" id="time">00:00</div>
                <div class="date" id="date">01.01.2024</div>
            </div>
        </div>
    </div>
    
    <!-- Start Menu -->
    <div class="start-menu" id="startMenu">
        <div class="start-header">
            <input type="text" class="start-search" placeholder="Wpisz tutaj, aby wyszukać" id="startSearch">
        </div>
        
        <div class="start-content">
            <div class="pinned-apps">
                <div class="section-title">Przypięte</div>
                <div class="apps-grid" id="appsGrid">
                    <div class="app-tile" onclick="openStatusWindow(); toggleStartMenu();" data-search="status funkcjonariusze">
                        <div class="app-tile-icon" style="background: linear-gradient(135deg, #27ae60, #2ecc71);">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </div>
                        <span class="app-tile-name">Duty Shift</span>
                    </div>
                    
                    <div class="app-tile" onclick="openSettings(); toggleStartMenu();" data-search="ustawienia konfiguracja">
                        <div class="app-tile-icon" style="background: linear-gradient(135deg, #7f8c8d, #95a5a6);">
                            <svg viewBox="0 0 24 24">
                                <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                            </svg>
                        </div>
                        <span class="app-tile-name">Ustawienia</span>
                    </div>
                    
                    <div class="app-tile" onclick="openGameWindow(); toggleStartMenu();" data-search="gra game centrum rozrywka">
                        <div class="app-tile-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <svg viewBox="0 0 24 24">
                                <path d="M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-10 7H8v3H6v-3H3v-2h3V8h2v3h3v2zm4.5 2c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4-3c-.83 0-1.5-.67-1.5-1.5S18.67 9 19.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                            </svg>
                        </div>
                        <span class="app-tile-name">Game Center</span>
                    </div>
                    
                    <div class="app-tile" onclick="openOfficersWindow(); toggleStartMenu();" data-search="funkcjonariusze policja">
                        <div class="app-tile-icon" style="background: linear-gradient(135deg, #0078d4, #106ebe);">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 2L3.5 7v6c0 5.55 3.84 10.74 8.5 12 4.66-1.26 8.5-6.45 8.5-12V7L12 2zm0 10h7c-.53 4.12-3.28 7.79-7 8.94V12H5V8.3l7-3.11v6.81z"/>
                            </svg>
                        </div>
                        <span class="app-tile-name">Peace Officer</span>
                    </div>
                    
                    <div class="app-tile" onclick="openCitizensWindow(); toggleStartMenu();" data-search="obywatele ludzie baza">
                        <div class="app-tile-icon" style="background: linear-gradient(135deg, #16a085, #1abc9c);">
                            <svg viewBox="0 0 24 24">
                                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                            </svg>
                        </div>
                        <span class="app-tile-name">Obywatele</span>
                    </div>
                    
                    <div class="app-tile" onclick="openReportsWindow(); toggleStartMenu();" data-search="raporty sprawozdania dokumenty">
                        <div class="app-tile-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <svg viewBox="0 0 24 24">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                            </svg>
                        </div>
                        <span class="app-tile-name">Raporty</span>
                    </div>
                    
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <div class="app-tile" onclick="openAdminPanel(); toggleStartMenu();" data-search="administracja admin zarządzanie">
                        <div class="app-tile-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <svg viewBox="0 0 24 24">
                                <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.6 14.8,10V11.5C15.4,12.1 16,12.8 16,14V18C16,19.1 15.1,20 14,20H10C8.9,20 8,19.1 8,18V14C8,12.8 8.6,12.1 9.2,11.5V10C9.2,8.6 10.6,7 12,7M12,8.2C11.2,8.2 10.5,8.7 10.5,10V11.5H13.5V10C13.5,8.7 12.8,8.2 12,8.2Z"/>
                            </svg>
                        </div>
                        <span class="app-tile-name">Administracja</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="start-footer">
            <div class="user-profile">
                <div class="user-avatar">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
            </div>
            
            <a href="?logout=1" class="power-button" onmouseenter="showTooltip(this, 'Wyloguj')">
                <svg viewBox="0 0 24 24">
                    <path d="M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z"/>
                </svg>
            </a>
        </div>
    </div>
    
    <!-- All Windows -->
    <div class="window" id="statusWindow" style="width: 1100px; height: 700px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Duty Shift</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('statusWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('statusWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('statusWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="status.php"></iframe>
        </div>
    </div>

    <div class="window" id="officersWindow" style="width: 1200px; height: 800px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Peace Officer</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('officersWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('officersWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('officersWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="officers.php"></iframe>
        </div>
    </div>
    
    <div class="window" id="citizensWindow" style="width: 1200px; height: 800px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Obywatele</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('citizensWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('citizensWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('citizensWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="obywatele.php"></iframe>
        </div>
    </div>

    <div class="window" id="vehiclesWindow" style="width: 1200px; height: 800px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Pojazdy</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('vehiclesWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('vehiclesWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('vehiclesWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="pojazdy.php"></iframe>
        </div>
    </div>
    
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
    <div class="window" id="adminWindow" style="width: 900px; height: 600px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Panel Administracyjny</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('adminWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('adminWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('adminWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="admin.php"></iframe>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="window" id="reportsWindow" style="width: 900px; height: 600px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Raporty</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('reportsWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('reportsWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('reportsWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="reports.php"></iframe>
        </div>
    </div>
    
    <div class="window" id="settingsWindow" style="width: 900px; height: 600px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Ustawienia</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('settingsWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('settingsWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('settingsWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="settings.php"></iframe>
        </div>
    </div>
    
    <div class="window" id="gameWindow" style="width: 800px; height: 600px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Game Center</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('gameWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('gameWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('gameWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="gra.php"></iframe>
        </div>
    </div>
    
    <!-- Hidden Google Browser Window -->
    <div class="window" id="googleWindow" style="width: 1200px; height: 800px; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="window-header">
            <span class="window-title">Ukryta Przeglądarka</span>
            <div class="window-controls">
                <button class="window-btn window-minimize" onclick="minimizeWindow('googleWindow')"></button>
                <button class="window-btn window-maximize" onclick="maximizeWindow('googleWindow')"></button>
                <button class="window-btn window-close" onclick="closeWindow('googleWindow')"></button>
            </div>
        </div>
        <div class="window-body">
            <iframe src="google.php"></iframe>
        </div>
    </div>
    
    <!-- Modal for Work in Progress -->
    <div class="modal" id="workInProgressModal">
        <div class="modal-content">
            <div style="background: #0078d4; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
                <h2 style="margin: 0; font-size: 18px; font-weight: 400;">Aplikacja w budowie</h2>
            </div>
            <div style="padding: 30px; text-align: center;">
                <div style="width: 64px; height: 64px; margin: 0 auto 20px; background: #f39c12; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 24 24" style="width: 32px; height: 32px; fill: white;">
                        <path d="M13.7,15.3l1.3-1.3V8h3l-4-4l-4,4h3v5.6l1.7,1.7M20,18v-7.81c1.16-0.41,2-1.51,2-2.82c0-1.66-1.34-3-3-3s-3,1.34-3,3 c0,1.31,0.84,2.41,2,2.82V18h-4.47l-2.35-2.35l-1.41,1.41L11.7,19H4v2h16V18z"/>
                    </svg>
                </div>
                <h3 style="color: #333; margin-bottom: 12px; font-size: 16px; font-weight: 400;">Ta funkcja jest w rozwoju</h3>
                <p style="color: #666; font-size: 14px; line-height: 1.5;">Pracujemy nad wdrożeniem tej aplikacji.<br>Spróbuj ponownie później.</p>
            </div>
            <div style="padding: 16px 20px; border-top: 1px solid #e0e0e0; text-align: center;">
                <button onclick="closeModal()" style="background: #0078d4; color: white; border: none; padding: 8px 24px; border-radius: 4px; cursor: pointer; font-weight: 400; font-size: 14px; transition: all 0.2s ease;">OK</button>
            </div>
        </div>
    </div>
    
    <script>
        // System sounds
        const systemSounds = {
            startup: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhByqCz/LNe',
            click: 'data:audio/wav;base64,UklGRh4AAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YfcAAAC3t7e3t7e3t7e3t7e3t7e3',
            error: 'data:audio/wav;base64,UklGRtABAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YawBAACzsrKy'
        };
        
        // Search functionality with hidden browser easter egg
        const searchData = [
            { name: 'Duty Shift', description: 'Status funkcjonariuszy systemu', type: 'app', action: 'openStatusWindow', icon: 'status' },
            { name: 'Peace Officer', description: 'Zarządzanie funkcjonariuszami', type: 'app', action: 'openOfficersWindow', icon: 'officers' },
            { name: 'Obywatele', description: 'Baza danych obywateli', type: 'app', action: 'openCitizensWindow', icon: 'citizens' },
            { name: 'Raporty', description: 'System raportowania', type: 'app', action: 'openReportsWindow', icon: 'reports' },
            { name: 'Game Center', description: 'Centrum gier i rozrywki', type: 'app', action: 'openGameWindow', icon: 'game' },
            { name: 'Ustawienia', description: 'Ustawienia systemu', type: 'app', action: 'openSettings', icon: 'settings' },
            { name: 'Kosz', description: 'Kosz systemu', type: 'system', action: 'showWorkInProgress', icon: 'trash' }
        ];
        
        // Add admin if available
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        searchData.push({ name: 'Administracja', description: 'Panel administracyjny', type: 'app', action: 'openAdminPanel', icon: 'admin' });
        <?php endif; ?>
        
        let selectedIcon = null;
        let windowStates = {};
        
        // Initialize system
        function initializeSystem() {
            hideLoadingScreen();
            updateClock();
            setInterval(updateClock, 1000);
            updateBatteryStatus();
            setInterval(updateBatteryStatus, 60000);
            
            // Add entrance animations to desktop icons
            const icons = document.querySelectorAll('.desktop-icon');
            icons.forEach((icon, index) => {
                icon.style.opacity = '0';
                icon.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    icon.style.transition = 'all 0.3s ease';
                    icon.style.opacity = '1';
                    icon.style.transform = 'translateY(0)';
                }, 1700 + (index * 50));
            });
            
            // Show welcome notification
            setTimeout(() => {
                showNotification('System', 'Witaj w systemie zarządzania policji!');
            }, 2500);
            
            // Initialize search
            initializeSearch();
        }
        
        // Sound system
        function playSystemSound(type = 'click') {
            try {
                const audio = new Audio(systemSounds[type] || systemSounds.click);
                audio.volume = 0.3;
                audio.play().catch(() => {}); // Silent fail for better UX
            } catch (e) {
                // Silent fail
            }
        }
        
        // Enhanced search functionality with hidden browser easter egg
        function initializeSearch() {
            const searchInput = document.getElementById('searchInput');
            const startSearch = document.getElementById('startSearch');
            const searchResults = document.getElementById('searchResults');
            
            function performSearch(query) {
                if (!query || query.length < 1) {
                    searchResults.innerHTML = '';
                    return;
                }
                
                // Hidden browser easter egg
                if (query.toLowerCase() === 'browser' || query.toLowerCase() === 'przeglądarka' || query.toLowerCase() === 'google') {
                    searchResults.innerHTML = `
                        <div class="search-result" onclick="openGoogleWindow(); closeSearchOverlay(); showNotification('Easter Egg', 'Gratulacje! Odkryłeś ukrytą przeglądarkę!');">
                            <div class="search-result-icon icon-google" style="background: linear-gradient(135deg, #4285f4, #1976d2);">
                                <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: white;">
                                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                </svg>
                            </div>
                            <div class="search-result-content">
                                <div class="search-result-name">🎉 Ukryta przeglądarka!</div>
                                <div class="search-result-description">Gratulacje! Znalazłeś ukryty easter egg</div>
                            </div>
                        </div>
                    `;
                    return;
                }
                
                const results = searchData.filter(item => 
                    item.name.toLowerCase().includes(query.toLowerCase()) ||
                    item.description.toLowerCase().includes(query.toLowerCase())
                );
                
                searchResults.innerHTML = results.map(item => `
                    <div class="search-result" onclick="${item.action}(); closeSearchOverlay();">
                        <div class="search-result-icon icon-${item.icon}" style="background: ${getIconBackground(item.icon)};">
                            ${getAppIcon(item.icon)}
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-name">${item.name}</div>
                            <div class="search-result-description">${item.description}</div>
                        </div>
                    </div>
                `).join('');
                
                if (results.length === 0) {
                    searchResults.innerHTML = `
                        <div class="search-result">
                            <div class="search-result-content">
                                <div class="search-result-name">Brak wyników</div>
                                <div class="search-result-description">Spróbuj "Peace Officer"</div>
                            </div>
                        </div>
                    `;
                }
            }
            
            searchInput.addEventListener('input', (e) => performSearch(e.target.value));
            
            // Start menu search
            startSearch.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const appTiles = document.querySelectorAll('.app-tile');
                
                appTiles.forEach(tile => {
                    const appName = tile.querySelector('.app-tile-name').textContent.toLowerCase();
                    const searchTerms = tile.getAttribute('data-search') || '';
                    
                    if (appName.includes(searchTerm) || searchTerms.includes(searchTerm)) {
                        tile.classList.remove('hidden');
                    } else {
                        tile.classList.add('hidden');
                    }
                });
            });
        }
        
        function getIconBackground(iconType) {
            const backgrounds = {
                'status': 'linear-gradient(135deg, #27ae60, #2ecc71)',
                'officers': 'linear-gradient(135deg, #0078d4, #106ebe)',
                'citizens': 'linear-gradient(135deg, #16a085, #1abc9c)',
                'reports': 'linear-gradient(135deg, #f39c12, #e67e22)',
                'game': 'linear-gradient(135deg, #9b59b6, #8e44ad)',
                'google': 'linear-gradient(135deg, #4285f4, #1976d2)',
                'settings': 'linear-gradient(135deg, #7f8c8d, #95a5a6)',
                'admin': 'linear-gradient(135deg, #e74c3c, #c0392b)',
                'trash': 'linear-gradient(135deg, #6b7280, #4b5563)'
            };
            return backgrounds[iconType] || 'linear-gradient(135deg, #0078d4, #106ebe)';
        }
        
        // Search overlay
        function openSearchOverlay() {
            playSystemSound();
            document.getElementById('searchOverlay').classList.add('active');
            document.getElementById('searchInput').focus();
        }
        
        function closeSearchOverlay() {
            document.getElementById('searchOverlay').classList.remove('active');
            document.getElementById('searchInput').value = '';
            document.getElementById('searchResults').innerHTML = '';
        }
        
        // Notification system
        function showNotification(title, message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = `
                <div class="notification-header">
                    <div class="notification-icon">
                        <svg viewBox="0 0 24 24" style="width: 12px; height: 12px; fill: white;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                    <div class="notification-title">${title}</div>
                </div>
                <div class="notification-body">${message}</div>
            `;
            
            document.body.appendChild(notification);
            
            // Show animation
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Auto remove
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 4000);
            
            playSystemSound();
        }
        
        // Enhanced tooltip system
        function showTooltip(element, text) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = text;
            
            const rect = element.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) + 'px';
            tooltip.style.bottom = (window.innerHeight - rect.top + 10) + 'px';
            tooltip.style.transform = 'translateX(-50%)';
            
            document.body.appendChild(tooltip);
            
            setTimeout(() => tooltip.classList.add('show'), 10);
            
            element.addEventListener('mouseleave', () => {
                tooltip.classList.remove('show');
                setTimeout(() => tooltip.remove(), 200);
            }, { once: true });
        }
        
        // Hide loading screen
        function hideLoadingScreen() {
            setTimeout(() => {
                document.getElementById('loadingScreen').classList.add('hidden');
            }, 1500);
        }
        
        // Clock
        function updateClock() {
            const now = new Date();
            document.getElementById('time').textContent = now.toLocaleTimeString('pl-PL', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            document.getElementById('date').textContent = now.toLocaleDateString('pl-PL');
        }
        
        // Battery status
        function updateBatteryStatus() {
            const batteryLevel = document.getElementById('batteryLevel');
            if (batteryLevel) {
                // Simulate battery changes
                const level = 85 + Math.floor(Math.random() * 10);
                batteryLevel.textContent = level;
                
                if (level < 20) {
                    batteryLevel.style.color = '#ef4444';
                } else if (level < 50) {
                    batteryLevel.style.color = '#f59e0b';
                } else {
                    batteryLevel.style.color = '#10b981';
                }
            }
        }
        
        // Start Menu
        function toggleStartMenu() {
            playSystemSound();
            const menu = document.getElementById('startMenu');
            menu.classList.toggle('active');
        }
        
        // Icon selection
        function selectIcon(icon) {
            playSystemSound('click');
            
            // Deselect previous
            if (selectedIcon) {
                selectedIcon.classList.remove('selected');
            }
            
            // Select new
            selectedIcon = icon;
            icon.classList.add('selected');
        }
        
        // Window Management Functions
        function openStatusWindow() { openWindow('statusWindow', 'status', 'Duty Shift'); }
        function openSettings() { openWindow('settingsWindow', 'settings', 'Ustawienia'); }
        function openGameWindow() { openWindow('gameWindow', 'game', 'Game Center'); }
        function openGoogleWindow() { openWindow('googleWindow', 'google', 'Ukryta Przeglądarka'); }
        function openAdminPanel() { openWindow('adminWindow', 'admin', 'Panel Administracyjny'); }
        function openReportsWindow() { openWindow('reportsWindow', 'reports', 'Raporty'); }
        function openOfficersWindow() { openWindow('officersWindow', 'officers', 'Funkcjonariusze'); }
        function openVehiclesWindow() { openWindow('vehiclesWindow', 'vehicles', 'Pojazdy'); }
        function openCitizensWindow() { openWindow('citizensWindow', 'citizens', 'Obywatele'); }
        
        function openWindow(windowId, appId, appName) {
            playSystemSound();
            const window = document.getElementById(windowId);
            if (!window) return;
            
            window.classList.add('active');
            document.getElementById('startMenu').classList.remove('active');
            
            if (!windowStates[windowId]) {
                windowStates[windowId] = {
                    minimized: false,
                    maximized: false,
                    originalStyle: {
                        width: window.style.width,
                        height: window.style.height,
                        top: window.style.top,
                        left: window.style.left,
                        transform: window.style.transform
                    }
                };
            }
            
            addToTaskbar(appId, appName, getAppIcon(appId));
            showNotification('System', `Uruchamianie aplikacji: ${appName}`);
        }
        
        function closeWindow(windowId) {
            playSystemSound();
            const window = document.getElementById(windowId);
            window.style.animation = 'windowClose 0.2s ease forwards';
            
            setTimeout(() => {
                window.classList.remove('active');
                window.classList.remove('maximized');
                window.style.animation = '';
                
                if (windowStates[windowId]) {
                    const state = windowStates[windowId];
                    window.style.width = state.originalStyle.width;
                    window.style.height = state.originalStyle.height;
                    window.style.top = state.originalStyle.top;
                    window.style.left = state.originalStyle.left;
                    window.style.transform = state.originalStyle.transform;
                    windowStates[windowId] = null;
                }
                
                removeFromTaskbar(windowId);
            }, 200);
        }
        
        function minimizeWindow(windowId) {
            playSystemSound();
            const window = document.getElementById(windowId);
            const appId = windowId.replace('Window', '');
            const taskbarApp = document.getElementById(`taskbar-${appId}`);
            
            if (window.classList.contains('active')) {
                window.classList.remove('active');
                if (taskbarApp) taskbarApp.classList.remove('active');
                if (windowStates[windowId]) windowStates[windowId].minimized = true;
            } else {
                window.classList.add('active');
                if (taskbarApp) taskbarApp.classList.add('active');
                if (windowStates[windowId]) windowStates[windowId].minimized = false;
            }
        }
        
        function maximizeWindow(windowId) {
            playSystemSound();
            const window = document.getElementById(windowId);
            if (!windowStates[windowId]) return;
            
            if (window.classList.contains('maximized')) {
                window.classList.remove('maximized');
                const state = windowStates[windowId];
                window.style.width = state.originalStyle.width;
                window.style.height = state.originalStyle.height;
                window.style.top = state.originalStyle.top;
                window.style.left = state.originalStyle.left;
                window.style.transform = state.originalStyle.transform;
                windowStates[windowId].maximized = false;
            } else {
                window.classList.add('maximized');
                windowStates[windowId].maximized = true;
            }
        }
        
        function getAppIcon(appId) {
            const icons = {
                'status': '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
                'settings': '<svg viewBox="0 0 24 24"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>',
                'game': '<svg viewBox="0 0 24 24"><path d="M21 6H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-10 7H8v3H6v-3H3v-2h3V8h2v3h3v2zm4.5 2c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4-3c-.83 0-1.5-.67-1.5-1.5S18.67 9 19.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
                'google': '<svg viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="white"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="white"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="white"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="white"/></svg>',
                'admin': '<svg viewBox="0 0 24 24"><path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.6 14.8,10V11.5C15.4,12.1 16,12.8 16,14V18C16,19.1 15.1,20 14,20H10C8.9,20 8,19.1 8,18V14C8,12.8 8.6,12.1 9.2,11.5V10C9.2,8.6 10.6,7 12,7M12,8.2C11.2,8.2 10.5,8.7 10.5,10V11.5H13.5V10C13.5,8.7 12.8,8.2 12,8.2Z"/></svg>',
                'reports': '<svg viewBox="0 0 24 24"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/></svg>',
                'officers': '<svg viewBox="0 0 24 24"><path d="M12 2L3.5 7v6c0 5.55 3.84 10.74 8.5 12 4.66-1.26 8.5-6.45 8.5-12V7L12 2zm0 10h7c-.53 4.12-3.28 7.79-7 8.94V12H5V8.3l7-3.11v6.81z"/></svg>',
                'citizens': '<svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>',
                'trash': '<svg viewBox="0 0 24 24"><path d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"/></svg>'
            };
            return icons[appId] || '';
        }
        
        // Taskbar management
        function addToTaskbar(appId, appName, iconSvg) {
            const taskbarApps = document.getElementById('taskbarApps');
            
            if (document.getElementById(`taskbar-${appId}`)) {
                document.getElementById(`taskbar-${appId}`).classList.add('active');
                return;
            }
            
            const taskbarApp = document.createElement('div');
            taskbarApp.className = 'taskbar-app active';
            taskbarApp.id = `taskbar-${appId}`;
            taskbarApp.innerHTML = iconSvg;
            taskbarApp.onclick = function() {
                const windowId = appId + 'Window';
                const windowElement = document.getElementById(windowId);
                if (windowElement) {
                    if (windowElement.classList.contains('active')) {
                        minimizeWindow(windowId);
                    } else {
                        windowElement.classList.add('active');
                        this.classList.add('active');
                        if (windowStates[windowId]) {
                            windowStates[windowId].minimized = false;
                        }
                    }
                }
            };
            
            taskbarApp.addEventListener('mouseenter', () => showTooltip(taskbarApp, appName));
            
            taskbarApps.appendChild(taskbarApp);
        }
        
        function removeFromTaskbar(windowId) {
            const appId = windowId.replace('Window', '');
            const taskbarApp = document.getElementById(`taskbar-${appId}`);
            if (taskbarApp) {
                taskbarApp.remove();
            }
        }
        
        // Modal
        function showWorkInProgress() {
            playSystemSound();
            document.getElementById('workInProgressModal').classList.add('active');
        }
        
        function closeModal() {
            playSystemSound();
            document.getElementById('workInProgressModal').classList.remove('active');
        }
        
        // Context Menu Functions
        function showContextMenu(e) {
            e.preventDefault();
            playSystemSound();
            const contextMenu = document.getElementById('contextMenu');
            contextMenu.style.display = 'block';
            contextMenu.style.left = e.clientX + 'px';
            contextMenu.style.top = e.clientY + 'px';
            
            // Adjust position if menu would go off screen
            const rect = contextMenu.getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                contextMenu.style.left = (e.clientX - rect.width) + 'px';
            }
            if (rect.bottom > window.innerHeight) {
                contextMenu.style.top = (e.clientY - rect.height) + 'px';
            }
        }
        
        function hideContextMenu() {
            document.getElementById('contextMenu').style.display = 'none';
        }
        
        function refreshDesktop() {
            playSystemSound();
            showNotification('System', 'Odświeżanie pulpitu...');
            
            // Desktop refresh animation
            const icons = document.querySelectorAll('.desktop-icon');
            icons.forEach((icon, index) => {
                icon.style.opacity = '0';
                setTimeout(() => {
                    icon.style.transition = 'opacity 0.3s ease';
                    icon.style.opacity = '1';
                }, index * 50);
            });
        }
        
        // Window dragging
        let isDragging = false;
        let currentWindow = null;
        let offsetX = 0;
        let offsetY = 0;
        
        document.addEventListener('mousedown', function(e) {
            if (e.target.classList.contains('window-header') || e.target.parentElement?.classList.contains('window-header')) {
                if (!e.target.classList.contains('window-btn') && !e.target.parentElement?.classList.contains('window-btn')) {
                    const window = e.target.closest('.window');
                    if (window && !window.classList.contains('maximized')) {
                        isDragging = true;
                        currentWindow = window;
                        const rect = window.getBoundingClientRect();
                        offsetX = e.clientX - rect.left;
                        offsetY = e.clientY - rect.top;
                        window.style.transform = 'none';
                        
                        window.style.zIndex = 15001;
                        setTimeout(() => {
                            window.style.zIndex = 15000;
                        }, 100);
                    }
                }
            }
        });
        
        document.addEventListener('mousemove', function(e) {
            if (isDragging && currentWindow && !currentWindow.classList.contains('maximized')) {
                const newLeft = e.clientX - offsetX;
                const newTop = e.clientY - offsetY;
                
                const maxLeft = window.innerWidth - 100;
                const maxTop = window.innerHeight - 100;
                
                currentWindow.style.left = Math.max(0, Math.min(maxLeft, newLeft)) + 'px';
                currentWindow.style.top = Math.max(0, Math.min(maxTop, newTop)) + 'px';
            }
        });
        
        document.addEventListener('mouseup', function() {
            isDragging = false;
            currentWindow = null;
        });
        
        // Double click to maximize/restore
        document.addEventListener('dblclick', function(e) {
            if (e.target.classList.contains('window-header') || e.target.parentElement?.classList.contains('window-header')) {
                if (!e.target.classList.contains('window-btn') && !e.target.parentElement?.classList.contains('window-btn')) {
                    const window = e.target.closest('.window');
                    if (window) {
                        maximizeWindow(window.id);
                    }
                }
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Meta' || e.key === 'OS') {
                e.preventDefault();
                toggleStartMenu();
            }
            
            if (e.ctrlKey && e.key === ' ') {
                e.preventDefault();
                openSearchOverlay();
            }
            
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => {
                    modal.classList.remove('active');
                });
                document.getElementById('startMenu').classList.remove('active');
                closeSearchOverlay();
            }
            
            if (e.altKey && e.key === 'F4') {
                e.preventDefault();
                const activeWindow = document.querySelector('.window.active');
                if (activeWindow) {
                    closeWindow(activeWindow.id);
                }
            }
        });
        
        // Focus management
        document.addEventListener('mousedown', function(e) {
            const clickedWindow = e.target.closest('.window');
            if (clickedWindow && clickedWindow.classList.contains('active')) {
                document.querySelectorAll('.window').forEach(w => {
                    w.style.zIndex = 15000;
                });
                clickedWindow.style.zIndex = 15001;
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            const startMenu = document.getElementById('startMenu');
            const startButton = document.querySelector('.start-button');
            const contextMenu = document.getElementById('contextMenu');
            const searchOverlay = document.getElementById('searchOverlay');
            
            if (!startMenu.contains(e.target) && !startButton.contains(e.target)) {
                startMenu.classList.remove('active');
            }
            
            if (!contextMenu.contains(e.target)) {
                hideContextMenu();
            }
            
            if (e.target === searchOverlay) {
                closeSearchOverlay();
            }
            
            // Deselect icon when clicking on empty space
            if (e.target === document.querySelector('.desktop') && selectedIcon) {
                selectedIcon.classList.remove('selected');
                selectedIcon = null;
            }
        });
        
        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', initializeSystem);
        
        console.log('Windows 11 Style Dashboard with hidden Google browser loaded successfully');
        console.log('Hidden feature: Type "browser" in search to discover the secret!');
        console.log('Current wallpaper: <?php echo $wallpaper; ?>');
        <?php if ($custom_wallpaper): ?>
        console.log('Custom wallpaper: <?php echo $custom_wallpaper; ?>');
        <?php endif; ?>
    </script>
</body>
</html>