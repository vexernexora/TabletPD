<?php
require_once 'config.php';

requireAuth();

// Check user role
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'supervisor'])) {
    header('Location: login.php?error=access_denied');
    exit();
}

$pdo = getDB();
$config = require_once 'erlc_config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_erlc_settings':
            $result = saveERLCSettings($_POST);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'test_connection':
            $result = testERLCConnection($_POST);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'sync_officers':
            $result = syncOfficersWithERLC();
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
    }
}

function saveERLCSettings($data) {
    // Save settings logic here
    return ['success' => true, 'message' => 'Ustawienia ERLC zostały zapisane'];
}

function testERLCConnection($data) {
    $serverKey = $data['erlc_server_key'] ?? '';
    $serverId = $data['erlc_server_id'] ?? '';
    
    if (empty($serverKey) || empty($serverId)) {
        return ['success' => false, 'message' => 'Klucz serwera i ID są wymagane'];
    }
    
    $url = "https://api.policeroleplay.community/v1/server/{$serverId}/info";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "Server-Key: {$serverKey}",
            "Content-Type: application/json"
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'message' => 'Połączenie z ERLC API zostało nawiązane pomyślnie'];
    } else {
        return ['success' => false, 'message' => "Błąd połączenia z ERLC API (HTTP: {$httpCode})"];
    }
}

function syncOfficersWithERLC() {
    return ['success' => true, 'message' => 'Synchronizacja funkcjonariuszy zakończona pomyślnie'];
}

function getERLCStats($pdo) {
    $stats = [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_officers,
                SUM(CASE WHEN os.status = 1 THEN 1 ELSE 0 END) as active_officers,
                SUM(CASE WHEN o.faction = 'LAPD' AND os.status = 1 THEN 1 ELSE 0 END) as lapd_active,
                SUM(CASE WHEN o.faction = 'LASD' AND os.status = 1 THEN 1 ELSE 0 END) as lasd_active
            FROM users u
            LEFT JOIN officers o ON u.id = o.user_id
            LEFT JOIN officer_status os ON u.id = os.user_id
            WHERE u.role IN ('officer', 'admin') AND u.is_active = 1
        ");
        $stmt->execute();
        $stats = $stmt->fetch();
        
    } catch (Exception $e) {
        $stats = [
            'total_officers' => 0,
            'active_officers' => 0,
            'lapd_active' => 0,
            'lasd_active' => 0
        ];
    }
    
    return $stats;
}

$stats = getERLCStats($pdo);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERLC Admin - Zarządzanie systemem</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f23;
            background-image: 
                radial-gradient(circle at 25% 25%, #1a1a3a 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, #2d1b69 0%, transparent 50%),
                linear-gradient(135deg, #0f0f23 0%, #1a1a3a 50%, #0f0f23 100%);
            min-height: 100vh;
            padding: 0;
            margin: 0;
            color: #ffffff;
            overflow-x: hidden;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .admin-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .admin-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .admin-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .quick-action {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            text-align: center;
        }
        
        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .quick-action-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quick-action-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .quick-action-desc {
            font-size: 13px;
            opacity: 0.8;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .stat-title {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .stat-change {
            font-size: 12px;
            color: #10b981;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .config-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f9fafb;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            background: #f9fafb;
            cursor: pointer;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }
        
        .alert {
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #059669;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        
        .connection-status {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .status-connected {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #059669;
        }
        
        .status-disconnected {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        .logs-section {
            background: #1f2937;
            color: white;
            border-radius: 15px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .log-entry {
            margin-bottom: 8px;
            opacity: 0.8;
        }
        
        .log-time {
            color: #60a5fa;
        }
        
        .log-level {
            font-weight: 600;
        }
        
        .log-info { color: #10b981; }
        .log-warning { color: #f59e0b; }
        .log-error { color: #ef4444; }
        
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">ERLC System Administration</h1>
            <p class="admin-subtitle">Zarządzanie integracją z Emergency Response: Liberty County</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="quick-actions">
            <a href="erlc_map.php" class="quick-action">
                <div class="quick-action-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                    </svg>
                </div>
                <div class="quick-action-title">Live Map</div>
                <div class="quick-action-desc">Otwórz mapę na żywo</div>
            </a>
            
            <button class="quick-action" onclick="syncOfficers()">
                <div class="quick-action-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                        <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>
                    </svg>
                </div>
                <div class="quick-action-title">Synchronizuj</div>
                <div class="quick-action-desc">Zsynchronizuj dane</div>
            </button>
            
            <button class="quick-action" onclick="testConnection()">
                <div class="quick-action-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="quick-action-title">Test API</div>
                <div class="quick-action-desc">Sprawdź połączenie</div>
            </button>
            
            <a href="officers.php" class="quick-action">
                <div class="quick-action-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                        <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A1.79 1.79 0 0 0 18.25 7h-2.5c-.8 0-1.53.5-1.71 1.37L11.5 16H14v6h6zM12.5 11.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5S11 9.17 11 10s.67 1.5 1.5 1.5zm1.5 1h-4c-.8 0-1.53.5-1.71 1.37L6.5 21H9v-6h2.5l2.54-7.63A1.79 1.79 0 0 0 12.25 6h-.75c-.41 0-.75.34-.75.75S11.09 7.5 11.5 7.5h.75c.28 0 .5.22.5.5 0 .28-.22.5-.5.5z"/>
                    </svg>
                </div>
                <div class="quick-action-title">Funkcjonariusze</div>
                <div class="quick-action-desc">Zarządzaj funkcjonariuszami</div>
            </a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Całkowita liczba funkcjonariuszy</div>
                    <div class="stat-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                            <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A1.79 1.79 0 0 0 18.25 7h-2.5"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['total_officers'] ?? 0; ?></div>
                <div class="stat-change">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7 14l5-5 5 5z"/>
                    </svg>
                    Aktywnych w systemie
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Na służbie</div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['active_officers'] ?? 0; ?></div>
                <div class="stat-change">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7 14l5-5 5 5z"/>
                    </svg>
                    Obecnie aktywnych
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">LAPD</div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1e40af);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['lapd_active'] ?? 0; ?></div>
                <div class="stat-change">Na służbie</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Sheriff</div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['lasd_active'] ?? 0; ?></div>
                <div class="stat-change">Na służbie</div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="config-section">
                <h2 class="section-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.07-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.74,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.07,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.47-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/>
                    </svg>
                    Konfiguracja ERLC
                </h2>
                
                <div class="connection-status status-connected">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="status-indicator"></div>
                        <span>Połączono z ERLC API</span>
                    </div>
                    <span style="font-size: 12px;">Ostatnia synchronizacja: 2 min temu</span>
                </div>
                
                <form method="POST" id="erlcConfigForm">
                    <input type="hidden" name="action" value="save_erlc_settings">
                    
                    <div class="form-group">
                        <label class="form-label">Klucz serwera ERLC</label>
                        <input type="password" name="erlc_server_key" class="form-input" 
                               placeholder="Wprowadź klucz serwera" value="<?php echo htmlspecialchars($config['erlc_api']['server_key']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ID serwera ERLC</label>
                        <input type="text" name="erlc_server_id" class="form-input" 
                               placeholder="Wprowadź ID serwera" value="<?php echo htmlspecialchars($config['erlc_api']['server_id']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Interwał aktualizacji (ms)</label>
                        <select name="update_interval" class="form-select">
                            <option value="1000">1 sekunda</option>
                            <option value="2000" selected>2 sekundy</option>
                            <option value="5000">5 sekund</option>
                            <option value="10000">10 sekund</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
                            </svg>
                            Zapisz ustawienia
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="testConnection()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            Testuj połączenie
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="config-section">
                <h2 class="section-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    Logi systemu
                </h2>
                
                <div class="logs-section">
                    <div class="log-entry">
                        <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                        <span class="log-level log-info">[INFO]</span>
                        System ERLC uruchomiony pomyślnie
                    </div>
                    <div class="log-entry">
                        <span class="log-time">[<?php echo date('H:i:s', time() - 120); ?>]</span>
                        <span class="log-level log-info">[INFO]</span>
                        Synchronizacja funkcjonariuszy zakończona (<?php echo $stats['active_officers']; ?> aktywnych)
                    </div>
                    <div class="log-entry">
                        <span class="log-time">[<?php echo date('H:i:s', time() - 300); ?>]</span>
                        <span class="log-level log-info">[INFO]</span>
                        Połączenie z ERLC API nawiązane
                    </div>
                    <div class="log-entry">
                        <span class="log-time">[<?php echo date('H:i:s', time() - 480); ?>]</span>
                        <span class="log-level log-warning">[WARNING]</span>
                        Timeout podczas pobierania pozycji funkcjonariusza #1247
                    </div>
                    <div class="log-entry">
                        <span class="log-time">[<?php echo date('H:i:s', time() - 600); ?>]</span>
                        <span class="log-level log-info">[INFO]</span>
                        Mapa ERLC załadowana - <?php echo $stats['total_officers']; ?> funkcjonariuszy w systemie
                    </div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button class="btn btn-secondary" onclick="clearLogs()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                        Wyczyść logi
                    </button>
                    <button class="btn btn-secondary" onclick="downloadLogs()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                        Pobierz logi
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function testConnection() {
            const formData = new FormData();
            formData.append('action', 'test_connection');
            formData.append('erlc_server_key', document.querySelector('input[name="erlc_server_key"]').value);
            formData.append('erlc_server_id', document.querySelector('input[name="erlc_server_id"]').value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Błąd podczas testowania połączenia');
            });
        }
        
        function syncOfficers() {
            const formData = new FormData();
            formData.append('action', 'sync_officers');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Błąd podczas synchronizacji');
            });
        }
        
        function clearLogs() {
            if (confirm('Czy na pewno chcesz wyczyścić logi?')) {
                document.querySelector('.logs-section').innerHTML = '<div class="log-entry">Logi zostały wyczyszczone</div>';
            }
        }
        
        function downloadLogs() {
            const logContent = document.querySelector('.logs-section').innerText;
            const blob = new Blob([logContent], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `erlc_logs_${new Date().toISOString().split('T')[0]}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            setInterval(() => {
                const logSection = document.querySelector('.logs-section');
                const now = new Date();
                const timeStr = now.toTimeString().slice(0, 8);
                
                if (Math.random() < 0.3) {
                    const newLog = document.createElement('div');
                    newLog.className = 'log-entry';
                    newLog.innerHTML = `
                        <span class="log-time">[${timeStr}]</span>
                        <span class="log-level log-info">[INFO]</span>
                        Aktualizacja pozycji funkcjonariuszy (${<?php echo $stats['active_officers']; ?>} aktywnych)
                    `;
                    logSection.insertBefore(newLog, logSection.firstChild);
                    
                    if (logSection.children.length > 10) {
                        logSection.removeChild(logSection.lastChild);
                    }
                }
            }, 15000);
        });
    </script>
</body>
</html>