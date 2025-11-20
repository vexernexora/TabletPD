<?php
// admin.php - LAPD Officer Management System - Streamlined Version
require_once 'config.php';

requireAdmin();

$admin = $_SESSION['user_data'];

// Funkcja do pobierania fallback stopni
function getFallbackRanks($faction) {
    if ($faction === 'LASD') {
        return [
            ['id' => 1, 'rank_name' => 'Captain', 'rank_abbreviation' => 'Capt.', 'badge_prefix' => '', 'hierarchy_level' => 5, 'faction' => 'LASD'],
            ['id' => 2, 'rank_name' => 'Lieutenant', 'rank_abbreviation' => 'Lt.', 'badge_prefix' => '', 'hierarchy_level' => 4, 'faction' => 'LASD'],
            ['id' => 3, 'rank_name' => 'Sergeant', 'rank_abbreviation' => 'Sgt.', 'badge_prefix' => '', 'hierarchy_level' => 3, 'faction' => 'LASD'],
            ['id' => 4, 'rank_name' => 'Deputy Sheriff Bonus II', 'rank_abbreviation' => 'DSB II', 'badge_prefix' => '', 'hierarchy_level' => 2, 'faction' => 'LASD'],
            ['id' => 5, 'rank_name' => 'Deputy Sheriff Bonus I', 'rank_abbreviation' => 'DSB I', 'badge_prefix' => '', 'hierarchy_level' => 1, 'faction' => 'LASD'],
            ['id' => 6, 'rank_name' => 'Deputy Sheriff Trainee', 'rank_abbreviation' => 'DST', 'badge_prefix' => '', 'hierarchy_level' => 0, 'faction' => 'LASD']
        ];
    } else { // LAPD
        return [
            ['id' => 11, 'rank_name' => 'Chief of Police', 'rank_abbreviation' => 'Chief', 'badge_prefix' => '', 'hierarchy_level' => 10, 'faction' => 'LAPD'],
            ['id' => 12, 'rank_name' => 'Assistant Chief', 'rank_abbreviation' => 'A/Chief', 'badge_prefix' => '', 'hierarchy_level' => 9, 'faction' => 'LAPD'],
            ['id' => 13, 'rank_name' => 'Commander', 'rank_abbreviation' => 'Cmdr.', 'badge_prefix' => '', 'hierarchy_level' => 8, 'faction' => 'LAPD'],
            ['id' => 14, 'rank_name' => 'Captain', 'rank_abbreviation' => 'Capt.', 'badge_prefix' => '', 'hierarchy_level' => 7, 'faction' => 'LAPD'],
            ['id' => 15, 'rank_name' => 'Lieutenant', 'rank_abbreviation' => 'Lt.', 'badge_prefix' => '', 'hierarchy_level' => 6, 'faction' => 'LAPD'],
            ['id' => 16, 'rank_name' => 'Sergeant', 'rank_abbreviation' => 'Sgt.', 'badge_prefix' => '', 'hierarchy_level' => 5, 'faction' => 'LAPD'],
            ['id' => 17, 'rank_name' => 'Detective', 'rank_abbreviation' => 'Det.', 'badge_prefix' => '', 'hierarchy_level' => 4, 'faction' => 'LAPD'],
            ['id' => 18, 'rank_name' => 'Police Officer III', 'rank_abbreviation' => 'P.O. III', 'badge_prefix' => '', 'hierarchy_level' => 3, 'faction' => 'LAPD'],
            ['id' => 19, 'rank_name' => 'Police Officer II', 'rank_abbreviation' => 'P.O. II', 'badge_prefix' => '', 'hierarchy_level' => 2, 'faction' => 'LAPD'],
            ['id' => 20, 'rank_name' => 'Police Officer I', 'rank_abbreviation' => 'P.O. I', 'badge_prefix' => '', 'hierarchy_level' => 1, 'faction' => 'LAPD']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo = getDB();
        
        switch ($_POST['action']) {
            case 'get_officers':
                if ($pdo) {
                    $stmt = $pdo->query("
                        SELECT o.*, u.username, u.is_active as user_active, u.first_login, u.last_login,
                               r.rank_name, r.rank_abbreviation, r.hierarchy_level, u.id as user_id,
                               u.created_at, COALESCE(o.faction, 'LAPD') as faction
                        FROM officers o
                        LEFT JOIN users u ON o.user_id = u.id
                        LEFT JOIN officer_ranks r ON o.rank_id = r.id
                        ORDER BY r.hierarchy_level DESC, o.last_name, o.first_name
                    ");
                    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $officers = [
                        [
                            'id' => 1, 'first_name' => 'Michael', 'last_name' => 'Rodriguez',
                            'birth_year' => 1985, 'rank_name' => 'Capitan', 'badge_number' => '2847',
                            'username' => 'michael.rodriguez', 'department' => 'SEB', 'faction' => 'LAPD',
                            'training' => 'Advanced Command Training, Crisis Management, Community Relations',
                            'user_active' => true, 'first_login' => false, 'hierarchy_level' => 5, 'user_id' => 1,
                            'created_at' => '2024-01-15 10:30:00'
                        ],
                        [
                            'id' => 2, 'first_name' => 'Sarah', 'last_name' => 'Chen',
                            'birth_year' => 1990, 'rank_name' => 'Sergeant', 'badge_number' => '4921',
                            'username' => 'sarah.chen', 'department' => 'Detective Division', 'faction' => 'LASD',
                            'training' => 'Detective Training, Forensic Investigation, Advanced Interrogation',
                            'user_active' => true, 'first_login' => true, 'hierarchy_level' => 3, 'user_id' => 2,
                            'created_at' => '2024-02-20 14:15:00'
                        ]
                    ];
                }
                echo json_encode(['success' => true, 'officers' => $officers]);
                break;
                
            case 'get_department_stats':
                if ($pdo) {
                    $stmt = $pdo->query("
                        SELECT department, COUNT(*) as count,
                               SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) as active_count
                        FROM officers o
                        LEFT JOIN users u ON o.user_id = u.id
                        GROUP BY department
                        ORDER BY count DESC
                    ");
                    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $stats = [
                        ['department' => 'SEB', 'count' => 25, 'active_count' => 22],
                        ['department' => 'Detective Division', 'count' => 18, 'active_count' => 16],
                        ['department' => 'patrol division', 'count' => 15, 'active_count' => 14]
                    ];
                }
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
            case 'get_ranks':
                $faction = $_POST['faction'] ?? 'LAPD';
                
                if ($pdo) {
                    // Sprawdź czy kolumna faction istnieje w tabeli officer_ranks
                    try {
                        $stmt = $pdo->prepare("SHOW COLUMNS FROM officer_ranks LIKE 'faction'");
                        $stmt->execute();
                        $faction_column_exists = $stmt->fetch() ? true : false;
                    } catch (Exception $e) {
                        $faction_column_exists = false;
                    }
                    
                    if ($faction_column_exists) {
                        $stmt = $pdo->prepare("SELECT * FROM officer_ranks WHERE faction = ? OR faction IS NULL ORDER BY hierarchy_level DESC");
                        $stmt->execute([$faction]);
                        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        // Jeśli nie ma kolumny faction, użyj wszystkich stopni
                        $stmt = $pdo->query("SELECT * FROM officer_ranks ORDER BY hierarchy_level DESC");
                        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Jeśli brak stopni w bazie, użyj demo data
                        if (empty($ranks)) {
                            $ranks = getFallbackRanks($faction);
                        }
                    }
                } else {
                    // Demo data - różne stopnie dla różnych frakcji
                    $ranks = getFallbackRanks($faction);
                }
                echo json_encode(['success' => true, 'ranks' => $ranks]);
                break;
                
            case 'get_next_badge':
                $badgeNumber = rand(1000, 9999);
                echo json_encode(['success' => true, 'badge_number' => (string)$badgeNumber]);
                break;
                
            case 'create_officer':
                $firstName = sanitize($_POST['first_name']);
                $lastName = sanitize($_POST['last_name']);
                $birthYear = intval($_POST['birth_year']);
                $rankId = intval($_POST['rank_id']);
                $faction = sanitize($_POST['faction'] ?? 'LAPD');
                $department = sanitize($_POST['department'] ?? 'patrol division');
                $training = sanitize($_POST['training'] ?? '');
                
                if (empty($firstName) || empty($lastName) || $birthYear < 1960 || $birthYear > 2005) {
                    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
                    break;
                }
                
                if ($pdo) {
                    $pdo->beginTransaction();
                    
                    try {
                        $baseUsername = strtolower($firstName . '.' . $lastName);
                        $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername);
                        
                        $username = $baseUsername;
                        $counter = 1;
                        while (true) {
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                            $stmt->execute([$username]);
                            if (!$stmt->fetch()) break;
                            $username = $baseUsername . $counter;
                            $counter++;
                        }
                        
                        $tempPassword = 'temp' . rand(1000, 9999);
                        $hashedPassword = hashPassword($tempPassword);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, password, role, first_login, is_active) 
                            VALUES (?, ?, 'officer', TRUE, TRUE)
                        ");
                        $stmt->execute([$username, $hashedPassword]);
                        $userId = $pdo->lastInsertId();
                        
                        $badgeNumber = rand(1000, 9999);
                        while (true) {
                            $stmt = $pdo->prepare("SELECT id FROM officers WHERE badge_number = ?");
                            $stmt->execute([$badgeNumber]);
                            if (!$stmt->fetch()) break;
                            $badgeNumber = rand(1000, 9999);
                        }
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO officers 
                            (user_id, first_name, last_name, birth_year, rank_id, badge_number, department, training, faction) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $userId, $firstName, $lastName, $birthYear, $rankId, 
                            $badgeNumber, $department, $training, $faction
                        ]);
                        
                        $pdo->commit();
                        
                        if (function_exists('auditLog')) {
                            auditLog('officer_created', $_SESSION['username'] ?? 'admin', 
                                   "Created officer: $firstName $lastName (Badge: $badgeNumber)");
                        }
                        
                        echo json_encode([
                            'success' => true, 
                            'officer_id' => $pdo->lastInsertId(),
                            'username' => $username,
                            'temp_password' => $tempPassword,
                            'badge_number' => $badgeNumber
                        ]);
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                } else {
                    $tempPassword = 'temp' . rand(1000, 9999);
                    echo json_encode([
                        'success' => true,
                        'temp_password' => $tempPassword,
                        'username' => strtolower($firstName . '.' . $lastName),
                        'badge_number' => (string)rand(1000, 9999)
                    ]);
                }
                break;
                
            case 'update_officer':
                $officerId = intval($_POST['officer_id']);
                $firstName = sanitize($_POST['first_name']);
                $lastName = sanitize($_POST['last_name']);
                $birthYear = intval($_POST['birth_year']);
                $rankId = intval($_POST['rank_id']);
                $faction = sanitize($_POST['faction'] ?? 'LAPD');
                $department = sanitize($_POST['department']);
                $training = sanitize($_POST['training'] ?? '');
                
                if ($pdo) {
                    $stmt = $pdo->prepare("
                        UPDATE officers SET 
                        first_name=?, last_name=?, birth_year=?, rank_id=?, 
                        department=?, training=?, faction=?
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $firstName, $lastName, $birthYear, $rankId,
                        $department, $training, $faction, $officerId
                    ]);
                    
                    if (function_exists('auditLog')) {
                        auditLog('officer_updated', $_SESSION['username'] ?? 'admin', 
                               "Updated officer ID: $officerId - $firstName $lastName");
                    }
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'get_officer':
                $officerId = intval($_POST['officer_id']);
                
                if ($pdo) {
                    $stmt = $pdo->prepare("
                        SELECT o.*, r.rank_name, COALESCE(o.faction, 'LAPD') as faction
                        FROM officers o
                        JOIN officer_ranks r ON o.rank_id = r.id
                        WHERE o.id = ?
                    ");
                    $stmt->execute([$officerId]);
                    $officer = $stmt->fetch();
                } else {
                    $officer = [
                        'id' => $officerId, 'first_name' => 'Demo', 'last_name' => 'Officer',
                        'birth_year' => 1985, 'rank_id' => 16, 'department' => 'patrol division',
                        'training' => 'Basic Police Academy Training', 'faction' => 'LAPD'
                    ];
                }
                
                echo json_encode(['success' => true, 'officer' => $officer]);
                break;
                
            case 'delete_officer':
                $officerId = intval($_POST['officer_id']);
                
                if ($pdo) {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare("
                            SELECT o.*, u.username FROM officers o 
                            LEFT JOIN users u ON o.user_id = u.id 
                            WHERE o.id = ?
                        ");
                        $stmt->execute([$officerId]);
                        $officer = $stmt->fetch();
                        
                        if ($officer) {
                            if ($officer['user_id']) {
                                $stmt = $pdo->prepare("DELETE FROM weekly_stats WHERE user_id = ?");
                                $stmt->execute([$officer['user_id']]);
                                
                                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                                $stmt->execute([$officer['user_id']]);
                            }
                            
                            $stmt = $pdo->prepare("DELETE FROM officers WHERE id = ?");
                            $stmt->execute([$officerId]);
                            
                            if (function_exists('auditLog')) {
                                auditLog('officer_deleted', $_SESSION['username'] ?? 'admin', 
                                       "Deleted officer: {$officer['first_name']} {$officer['last_name']} (Badge: {$officer['badge_number']})");
                            }
                            
                            $pdo->commit();
                            echo json_encode(['success' => true, 'message' => 'Officer completely removed from system']);
                        } else {
                            $pdo->rollBack();
                            echo json_encode(['success' => false, 'error' => 'Officer not found']);
                        }
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        error_log("Error deleting officer: " . $e->getMessage());
                        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => true, 'message' => 'Officer removed (demo mode)']);
                }
                break;
                
            case 'toggle_officer_status':
                $officerId = intval($_POST['officer_id']);
                $status = $_POST['status'] === 'true' ? 1 : 0;
                
                if ($pdo) {
                    $stmt = $pdo->prepare("
                        UPDATE users SET is_active = ? 
                        WHERE id = (SELECT user_id FROM officers WHERE id = ?)
                    ");
                    $stmt->execute([$status, $officerId]);
                    
                    if (function_exists('auditLog')) {
                        $statusText = $status ? 'activated' : 'deactivated';
                        auditLog('officer_status_changed', $_SESSION['username'] ?? 'admin', 
                               "Officer ID $officerId $statusText");
                    }
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'reset_password':
                $userId = intval($_POST['user_id']);
                $newPassword = 'reset' . rand(1000, 9999);
                
                if ($pdo) {
                    $hashedPassword = hashPassword($newPassword);
                    $stmt = $pdo->prepare("
                        UPDATE users SET password = ?, first_login = TRUE 
                        WHERE id = ?
                    ");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    if (function_exists('auditLog')) {
                        auditLog('password_reset', $_SESSION['username'] ?? 'admin', 
                               "Reset password for user ID: $userId");
                    }
                }
                
                echo json_encode(['success' => true, 'new_password' => $newPassword]);
                break;
                
            case 'export_officers':
                $officers = [];
                if ($pdo) {
                    $stmt = $pdo->query("
                        SELECT o.*, u.username, u.is_active as user_active, u.first_login, u.last_login,
                               r.rank_name, r.rank_abbreviation, r.hierarchy_level, u.id as user_id,
                               u.created_at, COALESCE(o.faction, 'LAPD') as faction
                        FROM officers o
                        LEFT JOIN users u ON o.user_id = u.id
                        LEFT JOIN officer_ranks r ON o.rank_id = r.id
                        ORDER BY r.hierarchy_level DESC, o.last_name, o.first_name
                    ");
                    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                echo json_encode(['success' => true, 'officers' => $officers]);
                break;
                
            case 'bulk_action':
                $action = $_POST['bulk_action'] ?? '';
                $officerIds = $_POST['officer_ids'] ?? [];
                
                if ($pdo && !empty($officerIds) && in_array($action, ['activate', 'deactivate', 'delete', 'export_selected'])) {
                    if ($action === 'export_selected') {
                        $placeholders = str_repeat('?,', count($officerIds) - 1) . '?';
                        $stmt = $pdo->prepare("
                            SELECT o.*, u.username, u.is_active as user_active, u.first_login,
                                   r.rank_name, r.hierarchy_level, u.created_at, COALESCE(o.faction, 'LAPD') as faction
                            FROM officers o
                            LEFT JOIN users u ON o.user_id = u.id
                            LEFT JOIN officer_ranks r ON o.rank_id = r.id
                            WHERE o.id IN ($placeholders)
                            ORDER BY r.hierarchy_level DESC, o.last_name, o.first_name
                        ");
                        $stmt->execute($officerIds);
                        $selectedOfficers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo json_encode(['success' => true, 'officers' => $selectedOfficers, 'action' => 'export']);
                        break;
                    }
                    
                    $pdo->beginTransaction();
                    
                    try {
                        foreach ($officerIds as $officerId) {
                            $officerId = intval($officerId);
                            
                            if ($action === 'activate' || $action === 'deactivate') {
                                $status = $action === 'activate' ? 1 : 0;
                                $stmt = $pdo->prepare("
                                    UPDATE users SET is_active = ? 
                                    WHERE id = (SELECT user_id FROM officers WHERE id = ?)
                                ");
                                $stmt->execute([$status, $officerId]);
                            } elseif ($action === 'delete') {
                                $stmt = $pdo->prepare("SELECT user_id FROM officers WHERE id = ?");
                                $stmt->execute([$officerId]);
                                $userData = $stmt->fetch();
                                
                                if ($userData && $userData['user_id']) {
                                    $stmt = $pdo->prepare("DELETE FROM weekly_stats WHERE user_id = ?");
                                    $stmt->execute([$userData['user_id']]);
                                    
                                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                                    $stmt->execute([$userData['user_id']]);
                                }
                                
                                $stmt = $pdo->prepare("DELETE FROM officers WHERE id = ?");
                                $stmt->execute([$officerId]);
                            }
                        }
                        
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => 'Bulk action completed successfully']);
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'error' => 'Bulk action failed: ' . $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid bulk action parameters']);
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Admin panel error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_NAME; ?> - Officer Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --surface-3: #f1f5f9;
            --text: #0f172a;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --radius-lg: 16px;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text);
            font-weight: 400;
            line-height: 1.6;
        }
        
        .admin-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .admin-header {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }
        
        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            transform: translate(60px, -60px);
            opacity: 0.1;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 2;
        }
        
        .header-info {
            flex: 1;
        }
        
        .header-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .header-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 16px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-badge i {
            font-size: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--surface);
            padding: 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--stat-color, var(--primary));
            transition: width 0.3s ease;
        }
        
        .stat-card:hover::before {
            width: 8px;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-card.total { --stat-color: var(--primary); }
        .stat-card.active { --stat-color: var(--success); }
        .stat-card.inactive { --stat-color: var(--danger); }
        .stat-card.first-login { --stat-color: var(--warning); }
        .stat-card.departments { --stat-color: var(--purple); }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius);
            background: var(--stat-color);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
        }
        
        .stat-icon i {
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .departments-section {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .departments-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .department-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: var(--surface-2);
            border-radius: var(--radius);
            margin-bottom: 12px;
            transition: all 0.2s;
            border: 1px solid var(--border);
        }
        
        .department-item:hover {
            background: var(--surface-3);
            transform: translateX(4px);
        }
        
        .department-name {
            font-weight: 600;
            color: var(--text);
        }
        
        .department-count {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .count-badge {
            background: var(--primary);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .count-active {
            background: var(--success);
        }
        
        .form-section {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            position: relative;
        }
        
        .section-header {
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--surface-2);
        }
        
        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-description {
            font-size: 1rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .form-row.full-width {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            position: relative;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s ease;
            background: var(--surface);
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .auto-field {
            background: var(--surface-2) !important;
            color: var(--text-secondary);
            font-style: italic;
            border-style: dashed;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        .btn-secondary {
            background: var(--secondary);
            color: white;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        .btn-purple {
            background: linear-gradient(135deg, var(--purple), #7c3aed);
            color: white;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .officers-section {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-header {
            padding: 30px 40px;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .table-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .officers-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--surface);
        }
        
        .officers-table th {
            background: var(--surface-3);
            padding: 20px 24px;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .officers-table td {
            padding: 20px 24px;
            border-bottom: 1px solid var(--surface-2);
            font-size: 0.95rem;
            vertical-align: middle;
        }
        
        .officers-table tr {
            transition: all 0.2s ease;
        }
        
        .officers-table tbody tr:hover {
            background: var(--surface-2);
        }
        
        .officer-name {
            font-weight: 600;
            color: var(--text);
        }
        
        .officer-badge {
            font-family: 'Courier New', monospace;
            color: var(--text-secondary);
            font-weight: 500;
            background: var(--surface-2);
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .officer-faction {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .faction-lapd {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .faction-lasd {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .officer-department {
            color: var(--text-secondary);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-first-login {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .rank-high { 
            color: var(--danger); 
            font-weight: 700;
            background: linear-gradient(135deg, var(--danger), #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .rank-medium { 
            color: var(--warning); 
            font-weight: 600;
        }
        
        .rank-low { 
            color: var(--primary); 
            font-weight: 500;
        }
        
        .actions-cell {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .message {
            background: var(--surface);
            border: 2px solid;
            padding: 20px 24px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            animation: slideIn 0.4s ease-out;
            font-weight: 500;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.success {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.05);
            color: #065f46;
        }
        
        .message.error {
            border-color: var(--danger);
            background: rgba(239, 68, 68, 0.05);
            color: #991b1b;
        }
        
        .message i {
            font-size: 1.25rem;
        }
        
        .credentials-box {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
            border: 2px solid var(--warning);
            border-radius: var(--radius-lg);
            padding: 32px;
            margin-top: 24px;
            box-shadow: var(--shadow);
            display: none;
        }
        
        .credentials-title {
            color: #92400e;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: var(--radius);
            border: 1px solid rgba(217, 119, 6, 0.2);
        }
        
        .credential-label {
            font-weight: 600;
            color: #92400e;
            font-size: 0.95rem;
        }
        
        .credential-value {
            font-family: 'Courier New', monospace;
            color: var(--text);
            font-weight: 700;
            font-size: 1.1rem;
            background: rgba(37, 99, 235, 0.1);
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid rgba(37, 99, 235, 0.2);
        }
        
        .copy-btn {
            background: var(--warning);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .copy-btn:hover {
            background: #d97706;
            transform: scale(1.05);
        }
        
        .credentials-warning {
            margin-top: 20px;
            padding: 16px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: var(--radius);
            border: 1px solid rgba(239, 68, 68, 0.2);
            text-align: center;
            color: #991b1b;
            font-weight: 600;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: modalFadeIn 0.3s ease-out;
        }
        
        .modal.active {
            display: flex;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: var(--surface);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            animation: modalSlideIn 0.4s ease-out;
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-50px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .close-modal {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .modal-body {
            padding: 32px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 24px 32px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            background: var(--surface-2);
        }
        
        .bulk-actions {
            display: none;
            background: var(--surface);
            padding: 16px 24px;
            border-bottom: 2px solid var(--border);
            align-items: center;
            gap: 16px;
        }
        
        .bulk-actions.show {
            display: flex;
        }
        
        .bulk-select-all {
            margin-right: auto;
        }
        
        .selected-count {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .bulk-action-btn {
            padding: 8px 16px;
            font-size: 0.9rem;
        }
        
        .officer-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }
        
        .table-filters {
            padding: 20px 24px;
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-input {
            flex: 1;
            min-width: 200px;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.95rem;
        }
        
        .filter-select {
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.95rem;
            background: var(--surface);
        }
        
        .export-btn {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--radius);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .export-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }
        
        .password-reset-dialog {
            background: rgba(245, 158, 11, 0.05);
            border: 2px solid var(--warning);
            border-radius: var(--radius);
            padding: 20px;
            margin-top: 16px;
            display: none;
        }
        
        .password-reset-dialog.show {
            display: block;
            animation: slideIn 0.3s ease-out;
        }
        
        .new-password-display {
            background: var(--surface);
            padding: 16px;
            border-radius: var(--radius);
            border: 2px solid var(--warning);
            margin: 16px 0;
            text-align: center;
        }
        
        .new-password-value {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            background: rgba(37, 99, 235, 0.1);
            padding: 12px 20px;
            border-radius: var(--radius);
            display: inline-block;
            border: 1px solid rgba(37, 99, 235, 0.2);
        }
        
        @media (max-width: 1200px) {
            .form-row {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .admin-container {
                padding: 12px;
            }
            
            .admin-header {
                padding: 24px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .header-title {
                font-size: 2rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .officers-table {
                font-size: 0.9rem;
            }
            
            .officers-table th,
            .officers-table td {
                padding: 12px 8px;
            }
            
            .table-header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
            
            .table-filters {
                flex-direction: column;
                gap: 12px;
            }
            
            .filter-input {
                min-width: 100%;
            }
        }
        
        .loading {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }
        
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid var(--border);
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .tooltip {
            position: relative;
            cursor: help;
        }
        
        .tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--text);
            color: var(--surface);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 1000;
        }
        
        .tooltip:hover::after {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="header-title"><?php echo SYSTEM_NAME; ?></h1>
                    <p class="header-subtitle">Advanced Officer Management System</p>
                </div>
                <div class="header-badge">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">Administrator</div>
                        <div style="font-size: 1.1rem;"><?php echo htmlspecialchars($admin['name']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="stats-grid" id="statsGrid">
        </div>
        
        <div class="departments-section">
            <h2 class="departments-title">
                <i class="fas fa-building" style="color: var(--purple);"></i>
                Rozkład według Jednostek
            </h2>
            <div id="departmentsList">
            </div>
        </div>
        
        <div class="form-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-user-plus"></i>
                    Dodaj Nowego Funkcjonariusza
                </h2>
                <p class="section-description">Utwórz nowe konto funkcjonariusza w systemie</p>
            </div>
            
            <div class="message success" id="successMessage" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span>Funkcjonariusz został pomyślnie dodany do systemu!</span>
            </div>
            
            <form id="addOfficerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Imię *</label>
                        <input type="text" class="form-input" id="firstName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nazwisko *</label>
                        <input type="text" class="form-input" id="lastName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Rok Urodzenia *</label>
                        <input type="number" class="form-input" id="birthYear" min="1960" max="2005" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Frakcja *</label>
                        <select class="form-select" id="faction" required>
                            <option value="">Wybierz frakcję</option>
                            <option value="LAPD" selected>LAPD</option>
                            <option value="LASD">LASD</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Stopień *
                            <span style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 400;">
                                (zmienia się w zależności od frakcji)
                            </span>
                        </label>
                        <select class="form-select" id="rank" required>
                            <option value="">Najpierw wybierz frakcję</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Numer Odznaki (Auto-generowany)</label>
                        <input type="text" class="form-input auto-field" id="autoBadge" readonly>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Wydział/Jednostka *</label>
                        <select class="form-select" id="department" required>
                            <option value="Operation Safe Street (OSS)">Operation Safe Street (OSS)</option>
                            <option value="SEB">Special Enforcement Bureau (SEB)</option>
                            <option value="patrol division" selected>Patrol Division</option>
                            <option value="Detective Division">Detective Division</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Login (Auto-generowany)</label>
                        <input type="text" class="form-input auto-field" id="autoLogin" readonly>
                    </div>
                    <div class="form-group"></div>
                </div>
                
                <div class="form-row full-width">
                    <div class="form-group">
                        <label class="form-label">Szkolenia i Certyfikaty</label>
                        <textarea class="form-textarea" id="training" placeholder="np. Szkoła Policji, Kurs strzelecki, Szkolenie interwencyjne, Kurs negocjacyjny..."></textarea>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 32px;">
                    <button type="submit" class="btn btn-primary" style="padding: 20px 48px; font-size: 1.1rem;">
                        <i class="fas fa-plus"></i>
                        Dodaj Funkcjonariusza
                    </button>
                </div>
            </form>
            
            <div class="credentials-box" id="credentialsBox">
                <h3 class="credentials-title">
                    <i class="fas fa-key"></i>
                    Dane Logowania Funkcjonariusza
                </h3>
                <div class="credential-item">
                    <span class="credential-label">Login:</span>
                    <span class="credential-value" id="newLogin">-</span>
                    <button class="copy-btn" onclick="copyToClipboard('newLogin')">Kopiuj</button>
                </div>
                <div class="credential-item">
                    <span class="credential-label">Hasło Tymczasowe:</span>
                    <span class="credential-value" id="newPassword">-</span>
                    <button class="copy-btn" onclick="copyToClipboard('newPassword')">Kopiuj</button>
                </div>
                <div class="credential-item">
                    <span class="credential-label">Numer Odznaki:</span>
                    <span class="credential-value" id="newBadge">-</span>
                    <button class="copy-btn" onclick="copyToClipboard('newBadge')">Kopiuj</button>
                </div>
                <div class="credentials-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Funkcjonariusz musi zmienić hasło przy pierwszym logowaniu!</strong>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <button class="btn btn-primary" onclick="copyAllCredentials()">
                        <i class="fas fa-copy"></i>
                        Kopiuj Wszystkie Dane
                    </button>
                </div>
            </div>
        </div>
        
        <div class="officers-section">
            <div class="table-header">
                <h2 class="table-title">
                    <i class="fas fa-users"></i>
                    Lista Funkcjonariuszy
                </h2>
                <div class="table-actions">
                    <button class="export-btn" onclick="exportOfficersData()">
                        <i class="fas fa-download"></i>
                        Eksportuj CSV
                    </button>
                </div>
            </div>
            
            <div class="table-filters">
                <input type="text" id="searchFilter" class="filter-input" placeholder="Szukaj po imieniu, nazwisku lub odznace...">
                <select id="statusFilter" class="filter-select">
                    <option value="">Wszystkie statusy</option>
                    <option value="active">Aktywni</option>
                    <option value="inactive">Nieaktywni</option>
                    <option value="first-login">Pierwsze logowanie</option>
                </select>
                <select id="departmentFilter" class="filter-select">
                    <option value="">Wszystkie jednostki</option>
                </select>
                <select id="factionFilter" class="filter-select">
                    <option value="">Wszystkie frakcje</option>
                    <option value="LAPD">LAPD</option>
                    <option value="LASD">LASD</option>
                </select>
            </div>
            
            <div class="bulk-actions" id="bulkActions">
                <label class="bulk-select-all">
                    <input type="checkbox" id="selectAll" class="officer-checkbox"> Zaznacz wszystkich
                </label>
                <span class="selected-count" id="selectedCount">0 zaznaczonych</span>
                <button class="btn btn-success bulk-action-btn" onclick="bulkAction('activate')">
                    <i class="fas fa-check"></i> Aktywuj
                </button>
                <button class="btn btn-secondary bulk-action-btn" onclick="bulkAction('deactivate')">
                    <i class="fas fa-pause"></i> Dezaktywuj
                </button>
                <button class="btn btn-purple bulk-action-btn" onclick="bulkAction('export_selected')">
                    <i class="fas fa-download"></i> Eksportuj Zaznaczone
                </button>
                <button class="btn btn-danger bulk-action-btn" onclick="bulkAction('delete')">
                    <i class="fas fa-trash"></i> Usuń
                </button>
            </div>
            
            <table class="officers-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="masterCheckbox" class="officer-checkbox"></th>
                        <th>Funkcjonariusz</th>
                        <th>Frakcja</th>
                        <th>Stopień</th>
                        <th>Nr Odznaki</th>
                        <th>Login</th>
                        <th>Jednostka</th>
                        <th>Status</th>
                        <th>Data Dodania</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody id="officersTableBody">
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edytuj Funkcjonariusza</h2>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editOfficerForm">
                    <input type="hidden" id="editOfficerId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Imię *</label>
                            <input type="text" class="form-input" id="editFirstName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nazwisko *</label>
                            <input type="text" class="form-input" id="editLastName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Rok Urodzenia *</label>
                            <input type="number" class="form-input" id="editBirthYear" min="1960" max="2005" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Frakcja *</label>
                            <select class="form-select" id="editFaction" required>
                                <option value="">Wybierz frakcję</option>
                                <option value="LAPD">LAPD</option>
                                <option value="LASD">LASD</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Stopień *
                                <span style="font-size: 0.8rem; color: var(--text-secondary); font-weight: 400;">
                                    (dostępne stopnie zależą od frakcji)
                                </span>
                            </label>
                            <select class="form-select" id="editRank" required>
                                <option value="">Wybierz stopień</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jednostka *</label>
                            <select class="form-select" id="editDepartment" required>
                                <option value="Operation Safe Street">Operation Safe Street</option>
                                <option value="Special Enforcement Bureau">Special Enforcement Bureau</option>
                                <option value="Patrol Division">Patrol Division</option>
                                <option value="Detective Division">Detective Division</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row full-width">
                        <div class="form-group">
                            <label class="form-label">Szkolenia i Certyfikaty</label>
                            <textarea class="form-textarea" id="editTraining" placeholder="np. Szkoła Policji, Kurs strzelecki, Szkolenie interwencyjne..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Anuluj
                </button>
                <button type="button" class="btn btn-primary" onclick="saveOfficerEdit()">
                    <i class="fas fa-save"></i> Zapisz Zmiany
                </button>
            </div>
        </div>
    </div>

    <div class="modal" id="passwordResetModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-key"></i> Reset Hasła</h2>
                <button class="close-modal" onclick="closePasswordResetModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="passwordResetContent">
                    <p style="margin-bottom: 20px; color: var(--text-secondary);">
                        Czy na pewno chcesz zresetować hasło dla tego funkcjonariusza?
                        Zostanie wygenerowane nowe tymczasowe hasło.
                    </p>
                    <div style="text-align: center;">
                        <button class="btn btn-warning" id="confirmPasswordReset">
                            <i class="fas fa-key"></i> Resetuj Hasło
                        </button>
                    </div>
                </div>
                
                <div class="password-reset-dialog" id="newPasswordDisplay">
                    <h3 style="color: #92400e; margin-bottom: 16px; text-align: center;">
                        <i class="fas fa-check-circle"></i> Nowe Hasło Wygenerowane
                    </h3>
                    <div class="new-password-display">
                        <div style="margin-bottom: 12px; color: var(--text-secondary); font-weight: 600;">
                            Nowe tymczasowe hasło:
                        </div>
                        <div class="new-password-value" id="resetPasswordValue">
                            loading...
                        </div>
                        <div style="margin-top: 16px;">
                            <button class="copy-btn" onclick="copyResetPassword()" style="margin-right: 12px;">
                                <i class="fas fa-copy"></i> Kopiuj
                            </button>
                            <button class="btn btn-primary" onclick="copyResetPasswordAndClose()">
                                <i class="fas fa-check"></i> Kopiuj i Zamknij
                            </button>
                        </div>
                    </div>
                    <div class="credentials-warning" style="margin-top: 16px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Funkcjonariusz musi zmienić hasło przy następnym logowaniu!</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentOfficers = [];
        let currentRanks = [];
        let selectedOfficerIds = new Set();
        let currentPasswordResetUserId = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            loadOfficers();
            loadRanks(); 
            loadDepartmentStats();
            setupFormHandlers();
            setupFilters();
            setupBulkActions();
            
            // Poczekaj chwilę na załadowanie DOM i od razu załaduj stopnie dla LAPD
            setTimeout(() => {
                console.log('Loading initial ranks for LAPD...');
                const factionSelect = document.getElementById('faction');
                if (factionSelect) {
                    factionSelect.value = 'LAPD'; // Upewnij się że LAPD jest wybrane
                    loadRanksForFaction(); // Załaduj stopnie
                }
            }, 500);
        });

        function setupFormHandlers() {
            document.getElementById('firstName').addEventListener('input', generateLogin);
            document.getElementById('lastName').addEventListener('input', generateLogin);
            document.getElementById('rank').addEventListener('change', generateBadgeNumber);
            document.getElementById('faction').addEventListener('change', loadRanksForFaction);
            document.getElementById('editFaction').addEventListener('change', loadRanksForEditFaction);
            document.getElementById('addOfficerForm').addEventListener('submit', addOfficer);
        }

        function setupFilters() {
            document.getElementById('searchFilter').addEventListener('input', filterOfficers);
            document.getElementById('statusFilter').addEventListener('change', filterOfficers);
            document.getElementById('departmentFilter').addEventListener('change', filterOfficers);
            document.getElementById('factionFilter').addEventListener('change', filterOfficers);
        }

        function setupBulkActions() {
            document.getElementById('masterCheckbox').addEventListener('change', toggleAllCheckboxes);
            document.getElementById('selectAll').addEventListener('change', toggleAllCheckboxes);
        }

        function loadOfficers() {
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_officers'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentOfficers = data.officers;
                    displayOfficers(currentOfficers);
                    updateStats(currentOfficers);
                    populateDepartmentFilter(currentOfficers);
                } else {
                    console.error('Error loading officers:', data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function loadRanks() {
            // Załaduj domyślnie dla LAPD
            loadRanksForFaction();
        }
        
        function loadRanksForFaction() {
            const faction = document.getElementById('faction').value || 'LAPD';
            console.log('Loading ranks for faction:', faction);
            loadRanksData(faction, 'rank');
        }
        
        function loadRanksForEditFaction() {
            const faction = document.getElementById('editFaction').value || 'LAPD';
            console.log('Loading edit ranks for faction:', faction);
            loadRanksData(faction, 'editRank');
        }
        
        function loadRanksData(faction, selectElementId) {
            console.log('Fetching ranks for faction:', faction, 'target element:', selectElementId);
            return fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_ranks&faction=${faction}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Ranks response:', data);
                if (data.success) {
                    currentRanks = data.ranks;
                    populateRankSelect(selectElementId, data.ranks);
                } else {
                    console.error('Failed to load ranks:', data.error);
                }
            })
            .catch(error => {
                console.error('Error loading ranks:', error);
            });
        }
        
        function populateRankSelect(selectElementId, ranks) {
            const rankSelect = document.getElementById(selectElementId);
            console.log('Populating select', selectElementId, 'with', ranks.length, 'ranks');
            
            if (!rankSelect) {
                console.error('Select element not found:', selectElementId);
                return;
            }
            
            const currentValue = rankSelect.value;
            
            rankSelect.innerHTML = '<option value="">Wybierz stopień</option>';
            
            ranks.forEach(rank => {
                const option = document.createElement('option');
                option.value = rank.id;
                option.textContent = rank.rank_name;
                rankSelect.appendChild(option);
            });
            
            // Przywróć poprzednią wartość jeśli możliwe
            if (currentValue) {
                rankSelect.value = currentValue;
            }
            
            console.log('Populated select with', ranks.length, 'options');
        }

        function loadDepartmentStats() {
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_department_stats'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDepartmentStats(data.stats);
                }
            });
        }

        function displayDepartmentStats(stats) {
            const departmentsList = document.getElementById('departmentsList');
            
            if (stats.length === 0) {
                departmentsList.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">Brak danych o jednostkach</p>';
                return;
            }

            departmentsList.innerHTML = stats.map(dept => `
                <div class="department-item">
                    <span class="department-name">${dept.department}</span>
                    <div class="department-count">
                        <span class="count-badge count-active">${dept.active_count} aktywnych</span>
                        <span class="count-badge">${dept.count} łącznie</span>
                    </div>
                </div>
            `).join('');
        }



        function populateDepartmentFilter(officers) {
            const departmentFilter = document.getElementById('departmentFilter');
            const departments = [...new Set(officers.map(o => o.department))].sort();
            
            departmentFilter.innerHTML = '<option value="">Wszystkie jednostki</option>';
            departments.forEach(dept => {
                departmentFilter.innerHTML += `<option value="${dept}">${dept}</option>`;
            });
        }

        function updateStats(officers) {
            const total = officers.length;
            const active = officers.filter(o => o.user_active).length;
            const inactive = officers.filter(o => !o.user_active).length;
            const firstLogin = officers.filter(o => o.first_login).length;
            const departments = [...new Set(officers.map(o => o.department))].length;

            document.getElementById('statsGrid').innerHTML = `
                <div class="stat-card total">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number">${total}</div>
                            <div class="stat-label">Wszystkich Funkcjonariuszy</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card active">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number">${active}</div>
                            <div class="stat-label">Aktywnych</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card inactive">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number">${inactive}</div>
                            <div class="stat-label">Nieaktywnych</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card first-login">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number">${firstLogin}</div>
                            <div class="stat-label">Pierwsze Logowanie</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card departments">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number">${departments}</div>
                            <div class="stat-label">Jednostek</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>
            `;
        }

        function displayOfficers(officers) {
            const tbody = document.getElementById('officersTableBody');
            tbody.innerHTML = '';
            
            if (officers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 60px; color: var(--text-secondary);">Brak funkcjonariuszy w systemie</td></tr>';
                return;
            }

            officers.forEach((officer, index) => {
                const row = document.createElement('tr');
                row.setAttribute('data-officer-id', officer.id);
                
                const rankLevel = officer.hierarchy_level || 1;
                const rankClass = rankLevel > 10 ? 'rank-high' : rankLevel > 5 ? 'rank-medium' : 'rank-low';
                
                let accountStatusClass = 'status-active';
                let accountStatusText = 'Aktywny';
                
                if (!officer.user_active) {
                    accountStatusClass = 'status-inactive';
                    accountStatusText = 'Nieaktywny';
                } else if (officer.first_login) {
                    accountStatusClass = 'status-first-login';
                    accountStatusText = 'Pierwsze logowanie';
                }

                let createdDate = 'Brak danych';
                if (officer.created_at) {
                    const date = new Date(officer.created_at);
                    createdDate = date.toLocaleDateString('pl-PL');
                }
                
                const faction = officer.faction || 'LAPD';
                
                row.innerHTML = `
                    <td>
                        <input type="checkbox" class="officer-checkbox officer-select" 
                               value="${officer.id}" onchange="updateBulkActions()">
                    </td>
                    <td>
                        <div class="officer-name">${officer.first_name} ${officer.last_name}</div>
                    </td>
                    <td>
                        <div class="officer-faction faction-${faction.toLowerCase()}">
                            ${faction}
                        </div>
                    </td>
                    <td>
                        <span class="${rankClass}">${officer.rank_name}</span>
                    </td>
                    <td>
                        <span class="officer-badge">${officer.badge_number}</span>
                    </td>
                    <td>${officer.username || 'Brak'}</td>
                    <td class="officer-department">${officer.department}</td>
                    <td>
                        <span class="status-badge ${accountStatusClass}">
                            <span class="status-dot"></span>
                            ${accountStatusText}
                        </span>
                    </td>
                    <td>${createdDate}</td>
                    <td>
                        <div class="actions-cell">
                            <button class="btn btn-secondary tooltip" onclick="editOfficer(${officer.id})" 
                                    data-tooltip="Edytuj funkcjonariusza">
                                <i class="fas fa-edit"></i>
                            </button>
                            ${officer.user_id ? `
                                <button class="btn btn-warning tooltip" onclick="resetPassword(${officer.user_id})" 
                                        data-tooltip="Resetuj hasło">
                                    <i class="fas fa-key"></i>
                                </button>
                            ` : ''}
                            <button class="btn ${officer.user_active ? 'btn-secondary' : 'btn-success'} tooltip" 
                                    onclick="toggleOfficerStatus(${officer.id}, ${!officer.user_active})"
                                    data-tooltip="${officer.user_active ? 'Dezaktywuj' : 'Aktywuj'}">
                                <i class="fas ${officer.user_active ? 'fa-pause' : 'fa-play'}"></i>
                            </button>
                            <button class="btn btn-danger tooltip" onclick="deleteOfficer(${officer.id}, '${officer.first_name} ${officer.last_name}')" 
                                    data-tooltip="Usuń funkcjonariusza">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }

        function filterOfficers() {
            const searchTerm = document.getElementById('searchFilter').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const departmentFilter = document.getElementById('departmentFilter').value;
            const factionFilter = document.getElementById('factionFilter').value;
            
            const filteredOfficers = currentOfficers.filter(officer => {
                const matchesSearch = !searchTerm || 
                    officer.first_name.toLowerCase().includes(searchTerm) ||
                    officer.last_name.toLowerCase().includes(searchTerm) ||
                    officer.badge_number.toLowerCase().includes(searchTerm) ||
                    (officer.username && officer.username.toLowerCase().includes(searchTerm));
                
                const matchesStatus = !statusFilter ||
                    (statusFilter === 'active' && officer.user_active) ||
                    (statusFilter === 'inactive' && !officer.user_active) ||
                    (statusFilter === 'first-login' && officer.first_login);
                
                const matchesDepartment = !departmentFilter ||
                    officer.department === departmentFilter;
                
                const matchesFaction = !factionFilter ||
                    (officer.faction || 'LAPD') === factionFilter;
                
                return matchesSearch && matchesStatus && matchesDepartment && matchesFaction;
            });
            
            displayOfficers(filteredOfficers);
        }

        function toggleAllCheckboxes() {
            const isChecked = event.target.checked;
            const checkboxes = document.querySelectorAll('.officer-select');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.officer-select:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedOfficerIds.clear();
            checkboxes.forEach(checkbox => {
                selectedOfficerIds.add(parseInt(checkbox.value));
            });
            
            if (selectedOfficerIds.size > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = `${selectedOfficerIds.size} zaznaczonych`;
            } else {
                bulkActions.classList.remove('show');
            }
            
            const masterCheckbox = document.getElementById('masterCheckbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            const allCheckboxes = document.querySelectorAll('.officer-select');
            
            if (selectedOfficerIds.size === 0) {
                masterCheckbox.indeterminate = false;
                masterCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (selectedOfficerIds.size === allCheckboxes.length) {
                masterCheckbox.indeterminate = false;
                masterCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                masterCheckbox.indeterminate = true;
                selectAllCheckbox.indeterminate = true;
            }
        }

        function bulkAction(action) {
            if (selectedOfficerIds.size === 0) return;
            
            if (action === 'export_selected') {
                exportSelectedOfficers();
                return;
            }
            
            let confirmMessage = '';
            switch (action) {
                case 'activate':
                    confirmMessage = `Czy na pewno chcesz aktywować ${selectedOfficerIds.size} zaznaczonych funkcjonariuszy?`;
                    break;
                case 'deactivate':
                    confirmMessage = `Czy na pewno chcesz dezaktywować ${selectedOfficerIds.size} zaznaczonych funkcjonariuszy?`;
                    break;
                case 'delete':
                    confirmMessage = `Czy na pewno chcesz usunąć ${selectedOfficerIds.size} zaznaczonych funkcjonariuszy? Ta operacja jest nieodwracalna!`;
                    break;
            }
            
            if (!confirm(confirmMessage)) return;
            
            const formData = new FormData();
            formData.append('action', 'bulk_action');
            formData.append('bulk_action', action);
            selectedOfficerIds.forEach(id => {
                formData.append('officer_ids[]', id);
            });
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    loadOfficers();
                    loadDepartmentStats();
                    selectedOfficerIds.clear();
                    updateBulkActions();
                } else {
                    showMessage('Błąd podczas wykonywania operacji: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Błąd połączenia z serwerem', 'error');
            });
        }

        function exportSelectedOfficers() {
            const formData = new FormData();
            formData.append('action', 'bulk_action');
            formData.append('bulk_action', 'export_selected');
            selectedOfficerIds.forEach(id => {
                formData.append('officer_ids[]', id);
            });
            
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.officers) {
                    const officers = data.officers;
                    
                    let csvContent = "Imię,Nazwisko,Frakcja,Stopień,Nr Odznaki,Login,Jednostka,Status,Rok Urodzenia,Data Dodania,Szkolenia\n";
                    
                    officers.forEach(officer => {
                        const accountStatus = !officer.user_active ? 'Nieaktywny' : 
                                            officer.first_login ? 'Pierwsze logowanie' : 'Aktywny';
                        const training = (officer.training || '').replace(/"/g, '""');
                        const createdDate = officer.created_at ? 
                            new Date(officer.created_at).toLocaleDateString('pl-PL') : 'Brak danych';
                        const faction = officer.faction || 'LAPD';
                        
                        csvContent += `"${officer.first_name}","${officer.last_name}","${faction}","${officer.rank_name}","${officer.badge_number}","${officer.username || ''}","${officer.department}","${accountStatus}","${officer.birth_year}","${createdDate}","${training}"\n`;
                    });
                    
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    
                    if (link.download !== undefined) {
                        const url = URL.createObjectURL(blob);
                        link.setAttribute('href', url);
                        link.setAttribute('download', `wybrani_funkcjonariusze_${new Date().toISOString().split('T')[0]}.csv`);
                        link.style.visibility = 'hidden';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        showMessage(`Eksport ${officers.length} zaznaczonych funkcjonariuszy zakończony pomyślnie!`, 'success');
                    } else {
                        showMessage('Eksport nie jest obsługiwany w tej przeglądarce', 'error');
                    }
                } else {
                    showMessage('Błąd podczas eksportu zaznaczonych funkcjonariuszy', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Błąd połączenia z serwerem podczas eksportu', 'error');
            });
        }

        function generateLogin() {
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            
            if (firstName && lastName) {
                let login = (firstName + '.' + lastName).toLowerCase();
                login = login.replace(/[^a-z0-9.]/g, '');
                document.getElementById('autoLogin').value = login;
            } else {
                document.getElementById('autoLogin').value = '';
            }
        }

        function generateBadgeNumber() {
            const badgeNumber = Math.floor(Math.random() * 9000) + 1000;
            document.getElementById('autoBadge').value = badgeNumber;
        }

        function addOfficer(event) {
            event.preventDefault();
            
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalHTML = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dodawanie...';
            
            const formData = new FormData();
            formData.append('action', 'create_officer');
            formData.append('first_name', document.getElementById('firstName').value);
            formData.append('last_name', document.getElementById('lastName').value);
            formData.append('birth_year', document.getElementById('birthYear').value);
            formData.append('rank_id', document.getElementById('rank').value);
            formData.append('faction', document.getElementById('faction').value);
            formData.append('department', document.getElementById('department').value);
            formData.append('training', document.getElementById('training').value);

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage();
                    
                    if (data.temp_password) {
                        document.getElementById('newLogin').textContent = data.username;
                        document.getElementById('newPassword').textContent = data.temp_password;
                        document.getElementById('newBadge').textContent = data.badge_number;
                        document.getElementById('credentialsBox').style.display = 'block';
                        
                        setTimeout(() => {
                            document.getElementById('credentialsBox').style.display = 'none';
                        }, 45000);
                    }
                    
                    document.getElementById('addOfficerForm').reset();
                    document.getElementById('autoLogin').value = '';
                    document.getElementById('autoBadge').value = '';
                    document.getElementById('faction').value = 'LAPD';
                    
                    loadOfficers();
                    loadDepartmentStats();
                } else {
                    showMessage('Błąd dodawania funkcjonariusza: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Błąd połączenia z serwerem', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHTML;
            });
        }

        function resetPassword(userId) {
            currentPasswordResetUserId = userId;
            document.getElementById('passwordResetContent').style.display = 'block';
            document.getElementById('newPasswordDisplay').classList.remove('show');
            document.getElementById('passwordResetModal').classList.add('active');
            
            document.getElementById('confirmPasswordReset').onclick = function() {
                performPasswordReset(userId);
            };
        }

        function performPasswordReset(userId) {
            const confirmBtn = document.getElementById('confirmPasswordReset');
            const originalHTML = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetowanie...';
            
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=reset_password&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('resetPasswordValue').textContent = data.new_password;
                    document.getElementById('passwordResetContent').style.display = 'none';
                    document.getElementById('newPasswordDisplay').classList.add('show');
                    loadOfficers();
                } else {
                    showMessage('Błąd resetowania hasła: ' + (data.error || 'Nieznany błąd'), 'error');
                    closePasswordResetModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Błąd połączenia z serwerem', 'error');
                closePasswordResetModal();
            })
            .finally(() => {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalHTML;
            });
        }

        function copyResetPassword() {
            const password = document.getElementById('resetPasswordValue').textContent;
            navigator.clipboard.writeText(password).then(() => {
                showMessage('Hasło skopiowane do schowka!', 'success');
            });
        }

        function copyResetPasswordAndClose() {
            copyResetPassword();
            setTimeout(() => {
                closePasswordResetModal();
            }, 500);
        }

        function closePasswordResetModal() {
            document.getElementById('passwordResetModal').classList.remove('active');
            currentPasswordResetUserId = null;
        }

        function showSuccessMessage() {
            const successMsg = document.getElementById('successMessage');
            successMsg.style.display = 'flex';
            
            setTimeout(() => {
                successMsg.style.display = 'none';
            }, 5000);
        }

        function showMessage(message, type = 'success') {
            const existingMessages = document.querySelectorAll('.message');
            existingMessages.forEach(msg => msg.remove());
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            const container = document.querySelector('.admin-container');
            container.insertBefore(messageDiv, container.firstChild);
            
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 5000);
        }

        function copyToClipboard(elementId) {
            const text = document.getElementById(elementId).textContent;
            navigator.clipboard.writeText(text).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Skopiowano!';
                btn.style.background = 'var(--success)';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = 'var(--warning)';
                }, 1000);
            });
        }

        function copyAllCredentials() {
            const login = document.getElementById('newLogin').textContent;
            const password = document.getElementById('newPassword').textContent;
            const badge = document.getElementById('newBadge').textContent;
            
            const credentialsText = `Dane logowania funkcjonariusza:
Login: ${login}
Hasło: ${password}
Numer odznaki: ${badge}

WAŻNE: Funkcjonariusz musi zmienić hasło przy pierwszym logowaniu!`;
            
            navigator.clipboard.writeText(credentialsText).then(() => {
                showMessage('Wszystkie dane zostały skopiowane do schowka!', 'success');
            });
        }

        function editOfficer(officerId) {
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_officer&officer_id=${officerId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const officer = data.officer;
                    
                    document.getElementById('editOfficerId').value = officer.id;
                    document.getElementById('editFirstName').value = officer.first_name;
                    document.getElementById('editLastName').value = officer.last_name;
                    document.getElementById('editBirthYear').value = officer.birth_year;
                    document.getElementById('editFaction').value = officer.faction || 'LAPD';
                    document.getElementById('editDepartment').value = officer.department;
                    document.getElementById('editTraining').value = officer.training || '';
                    
                    // Załaduj stopnie dla odpowiedniej frakcji, a potem ustaw wartość
                    const faction = officer.faction || 'LAPD';
                    loadRanksData(faction, 'editRank').then(() => {
                        document.getElementById('editRank').value = officer.rank_id;
                        document.getElementById('editModal').classList.add('active');
                    });
                    
                } else {
                    showMessage('Błąd pobierania danych funkcjonariusza: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Błąd połączenia z serwerem', 'error');
            });
        }

        function saveOfficerEdit() {
            const formData = new FormData();
            formData.append('action', 'update_officer');
            formData.append('officer_id', document.getElementById('editOfficerId').value);
            formData.append('first_name', document.getElementById('editFirstName').value);
            formData.append('last_name', document.getElementById('editLastName').value);
            formData.append('birth_year', document.getElementById('editBirthYear').value);
            formData.append('rank_id', document.getElementById('editRank').value);
            formData.append('faction', document.getElementById('editFaction').value);
            formData.append('department', document.getElementById('editDepartment').value);
            formData.append('training', document.getElementById('editTraining').value);

            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    loadOfficers();
                    loadDepartmentStats();
                    showMessage('Zmiany zostały zapisane pomyślnie!', 'success');
                } else {
                    showMessage('Błąd zapisywania zmian: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Błąd połączenia z serwerem', 'error');
            });
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function deleteOfficer(officerId, name) {
            if (confirm(`Czy na pewno chcesz usunąć funkcjonariusza ${name}?\n\nTa operacja jest nieodwracalna i usunie wszystkie powiązane dane.`)) {
                fetch('admin.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete_officer&officer_id=${officerId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadOfficers();
                        loadDepartmentStats();
                        showMessage('Funkcjonariusz został usunięty z systemu!', 'success');
                    } else {
                        showMessage('Błąd usuwania funkcjonariusza: ' + (data.error || 'Nieznany błąd'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Błąd połączenia z serwerem', 'error');
                });
            }
        }

        function toggleOfficerStatus(officerId, newStatus) {
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=toggle_officer_status&officer_id=${officerId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadOfficers();
                    loadDepartmentStats();
                    const action = newStatus ? 'aktywowany' : 'dezaktywowany';
                    showMessage(`Funkcjonariusz został ${action}`, 'success');
                } else {
                    showMessage('Błąd zmiany statusu: ' + (data.error || 'Nieznany błąd'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Błąd połączenia z serwerem', 'error');
            });
        }

        function exportOfficersData() {
            fetch('admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=export_officers'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.officers) {
                    const officers = data.officers;
                    
                    let csvContent = "Imię,Nazwisko,Frakcja,Stopień,Nr Odznaki,Login,Jednostka,Status,Rok Urodzenia,Data Dodania,Szkolenia\n";
                    
                    officers.forEach(officer => {
                        const accountStatus = !officer.user_active ? 'Nieaktywny' : 
                                            officer.first_login ? 'Pierwsze logowanie' : 'Aktywny';
                        const training = (officer.training || '').replace(/"/g, '""');
                        const createdDate = officer.created_at ? 
                            new Date(officer.created_at).toLocaleDateString('pl-PL') : 'Brak danych';
                        const faction = officer.faction || 'LAPD';
                        
                        csvContent += `"${officer.first_name}","${officer.last_name}","${faction}","${officer.rank_name}","${officer.badge_number}","${officer.username || ''}","${officer.department}","${accountStatus}","${officer.birth_year}","${createdDate}","${training}"\n`;
                    });
                    
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    
                    if (link.download !== undefined) {
                        const url = URL.createObjectURL(blob);
                        link.setAttribute('href', url);
                        link.setAttribute('download', `funkcjonariusze_${new Date().toISOString().split('T')[0]}.csv`);
                        link.style.visibility = 'hidden';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        showMessage('Eksport CSV został pomyślnie zakończony!', 'success');
                    } else {
                        showMessage('Eksport nie jest obsługiwany w tej przeglądarce', 'error');
                    }
                } else {
                    showMessage('Błąd podczas eksportu danych', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Błąd połączenia z serwerem podczas eksportu', 'error');
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                document.getElementById('firstName').focus();
            }
            
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchFilter').focus();
            }
            
            if (e.key === 'Escape') {
                closeModal();
                closePasswordResetModal();
            }
            
            if (e.ctrlKey && e.key === 'a' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                e.preventDefault();
                document.getElementById('masterCheckbox').checked = true;
                toggleAllCheckboxes();
            }
            
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportOfficersData();
            }
            
            if (e.key === 'Delete' && selectedOfficerIds.size > 0 && 
                !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                bulkAction('delete');
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal();
                closePasswordResetModal();
            }
        });

        setInterval(function() {
            loadOfficers();
            loadDepartmentStats();
        }, 30000);

        let searchTimeout;
        document.getElementById('searchFilter').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterOfficers, 300);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            generateBadgeNumber();
        });
    </script>
</body>
</html>