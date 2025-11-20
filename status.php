<?php
// status.php - Aplikacja Status Funkcjonariuszy z MySQL
require_once 'config.php';

// Sprawdzenie autoryzacji
requireAuth();

$message = '';
$error = '';
$pdo = getDB();

if (!$pdo) {
    die("Błąd połączenia z bazą danych");
}

// Pobierz ID aktualnego użytkownika
$current_user_id = $_SESSION['user_id'];

// Funkcja do pobierania wszystkich funkcjonariuszy ze statusem
function getAllOfficersWithStatus($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            COALESCE(CONCAT(o.first_name, ' ', o.last_name), u.username) as full_name,
            COALESCE(o.badge_number, 'N/A') as badge_number,
            COALESCE(r.rank_name, 'N/A') as rank_name,
            os.status,
            os.start_time,
            os.duration_minutes,
            COALESCE(ws.total_hours, 0) as week_hours
        FROM users u
        LEFT JOIN officers o ON u.id = o.user_id
        LEFT JOIN officer_ranks r ON o.rank_id = r.id
        LEFT JOIN officer_status os ON u.id = os.user_id
        LEFT JOIN weekly_stats ws ON u.id = ws.user_id AND ws.week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
        WHERE u.role IN ('officer', 'admin') AND u.is_active = 1
        ORDER BY os.status ASC, full_name ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Funkcja do pobierania statystyk wszystkich funkcjonariuszy dla kopiowania
function getAllOfficersStatsForCopy($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            COALESCE(CONCAT(o.first_name, ' ', o.last_name), u.username) as full_name,
            COALESCE(o.badge_number, 'N/A') as badge_number,
            COALESCE(r.rank_name, 'N/A') as rank_name,
            COALESCE(os.duration_minutes, 0) as total_minutes,
            COALESCE(ws.total_hours, 0) as week_hours,
            os.status
        FROM users u
        LEFT JOIN officers o ON u.id = o.user_id
        LEFT JOIN officer_ranks r ON o.rank_id = r.id
        LEFT JOIN officer_status os ON u.id = os.user_id
        LEFT JOIN weekly_stats ws ON u.id = ws.user_id AND ws.week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
        WHERE u.role IN ('officer', 'admin') AND u.is_active = 1
        ORDER BY full_name ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Funkcja do formatowania godzin na format czytelny (np. 2h 30m zamiast 2.5h)
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

// Funkcja do obliczenia czasu pracy
function calculateWorkingHours($start_time) {
    if (!$start_time) return 0;
    return round((time() - strtotime($start_time)) / 3600, 1);
}

// Funkcja do formatowania czasu pracy (godziny i minuty)
function formatWorkingTime($start_time) {
    if (!$start_time) return '0h';
    
    $seconds = time() - strtotime($start_time);
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0 && $minutes > 0) {
        return $hours . 'h ' . $minutes . 'm';
    } elseif ($hours > 0) {
        return $hours . 'h';
    } elseif ($minutes > 0) {
        return $minutes . 'm';
    } else {
        return '0m';
    }
}

// Funkcja do resetowania godzin pojedynczego użytkownika
function resetUserHours($pdo, $user_id, $admin_id) {
    try {
        $pdo->beginTransaction();
        
        // Wyzeruj godziny w officer_status
        $stmt = $pdo->prepare("UPDATE officer_status SET duration_minutes = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Usuń statystyki tygodniowe
        $stmt = $pdo->prepare("DELETE FROM weekly_stats WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Dodaj do historii
        $stmt = $pdo->prepare("
            INSERT INTO status_history (user_id, status_from, status_to, change_time, notes) 
            VALUES (?, 0, 0, NOW(), 'Reset godzin przez administratora')
        ");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Błąd resetowania godzin użytkownika: " . $e->getMessage());
        return false;
    }
}

// Funkcja do zmiany statusu
function changeUserStatus($pdo, $user_id, $new_status, $admin_action = false) {
    try {
        $pdo->beginTransaction();
        
        // Pobierz aktualny status
        $stmt = $pdo->prepare("SELECT * FROM officer_status WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current = $stmt->fetch();
        
        if ($new_status == 1) { // Na służbie
            if ($current) {
                // Zaktualizuj na status 1
                $stmt = $pdo->prepare("
                    UPDATE officer_status 
                    SET status = 1, start_time = NOW(), end_time = NULL
                    WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);
            } else {
                // Wstaw nowy rekord
                $stmt = $pdo->prepare("
                    INSERT INTO officer_status (user_id, status, start_time) 
                    VALUES (?, 1, NOW())
                ");
                $stmt->execute([$user_id]);
            }
            
            // Dodaj do historii
            $stmt = $pdo->prepare("
                INSERT INTO status_history (user_id, status_from, status_to, change_time, notes) 
                VALUES (?, ?, 1, NOW(), ?)
            ");
            $notes = $admin_action ? 'Zmiana przez administratora' : null;
            $stmt->execute([$user_id, $current ? $current['status'] : 3, $notes]);
            
        } else { // Poza służbą (status 3)
            if ($current && $current['status'] == 1 && $current['start_time']) {
                // Oblicz czas pracy
                $duration = round((time() - strtotime($current['start_time'])) / 60); // w minutach
                
                // Zaktualizuj status
                $stmt = $pdo->prepare("
                    UPDATE officer_status 
                    SET status = 3, end_time = NOW(), duration_minutes = duration_minutes + ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$duration, $user_id]);
                
                // Zaktualizuj statystyki tygodniowe
                $week_start = date('Y-m-d', strtotime('monday this week'));
                $hours = $duration / 60;
                
                $stmt = $pdo->prepare("
                    INSERT INTO weekly_stats (user_id, week_start, total_minutes, total_hours) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        total_minutes = total_minutes + ?,
                        total_hours = total_hours + ?
                ");
                $stmt->execute([$user_id, $week_start, $duration, $hours, $duration, $hours]);
                
                // Dodaj do historii
                $stmt = $pdo->prepare("
                    INSERT INTO status_history (user_id, status_from, status_to, change_time, duration_minutes, notes) 
                    VALUES (?, 1, 3, NOW(), ?, ?)
                ");
                $notes = $admin_action ? 'Zdjęcie ze służby przez administratora' : null;
                $stmt->execute([$user_id, $duration, $notes]);
            } else {
                // Po prostu ustaw status na 3
                if ($current) {
                    $stmt = $pdo->prepare("
                        UPDATE officer_status 
                        SET status = 3, start_time = NULL, end_time = NOW()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO officer_status (user_id, status, end_time) 
                        VALUES (?, 3, NOW())
                    ");
                    $stmt->execute([$user_id]);
                }
                
                // Dodaj do historii
                $stmt = $pdo->prepare("
                    INSERT INTO status_history (user_id, status_from, status_to, change_time, notes) 
                    VALUES (?, ?, 3, NOW(), ?)
                ");
                $notes = $admin_action ? 'Zmiana przez administratora' : null;
                $stmt->execute([$user_id, $current ? $current['status'] : 3, $notes]);
            }
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Błąd zmiany statusu: " . $e->getMessage());
        return false;
    }
}

// Obsługa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_status'])) {
        $new_status = (int)$_POST['new_status'];
        
        if (changeUserStatus($pdo, $current_user_id, $new_status)) {
            $message = $new_status == 1 ? 'Status zmieniony na "Na służbie"' : 'Status zmieniony na "Poza służbą"';
            // Przekieruj aby uniknąć ponownego wysłania
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $error = 'Błąd podczas zmiany statusu';
        }
    }
    
    // Admin: zmiana statusu innego użytkownika
    if (isset($_POST['admin_change_status']) && isAdmin()) {
        $target_user_id = (int)$_POST['target_user_id'];
        $new_status = (int)$_POST['new_status'];
        
        if ($target_user_id && changeUserStatus($pdo, $target_user_id, $new_status, true)) {
            $action_text = $new_status == 1 ? 'postawiono na służbie' : 'zdjęto ze służby';
            $message = "Użytkownik został {$action_text} przez administratora";
            header("Location: " . $_SERVER['PHP_SELF'] . "?admin_success=1");
            exit();
        } else {
            $error = 'Błąd podczas zmiany statusu użytkownika';
        }
    }
    
    // Admin: reset godzin pojedynczego użytkownika
    if (isset($_POST['reset_user_hours']) && isAdmin()) {
        $target_user_id = (int)$_POST['target_user_id'];
        
        if ($target_user_id && resetUserHours($pdo, $target_user_id, $current_user_id)) {
            $message = 'Godziny użytkownika zostały wyzerowane';
            header("Location: " . $_SERVER['PHP_SELF'] . "?reset_user=1");
            exit();
        } else {
            $error = 'Błąd podczas zerowania godzin użytkownika';
        }
    }
    
    // Funkcja zerowania godzin (tylko dla adminów)
    if (isset($_POST['reset_hours']) && isAdmin()) {
        try {
            $pdo->beginTransaction();
            
            // Wyzeruj wszystkie godziny w officer_status
            $stmt = $pdo->prepare("UPDATE officer_status SET duration_minutes = 0");
            $stmt->execute();
            
            // Wyzeruj wszystkie godziny w weekly_stats
            $stmt = $pdo->prepare("DELETE FROM weekly_stats");
            $stmt->execute();
            
            // Dodaj do historii informację o resecie (przez admina)
            $stmt = $pdo->prepare("
                INSERT INTO status_history (user_id, status_from, status_to, change_time, notes) 
                VALUES (?, 0, 0, NOW(), 'Reset godzin przez administratora')
            ");
            $stmt->execute([$current_user_id]);
            
            $pdo->commit();
            $message = 'Wszystkie godziny zostały wyzerowane przez administratora';
            header("Location: " . $_SERVER['PHP_SELF'] . "?reset=1");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Błąd podczas zerowania godzin: ' . $e->getMessage();
        }
    }
}

// Sprawdź czy to przekierowanie po sukcesie
if (isset($_GET['success'])) {
    $message = 'Status został zmieniony pomyślnie';
}
if (isset($_GET['admin_success'])) {
    $message = 'Status użytkownika został zmieniony przez administratora';
}
if (isset($_GET['reset'])) {
    $message = 'Wszystkie godziny zostały wyzerowane przez administratora';
}
if (isset($_GET['reset_user'])) {
    $message = 'Godziny użytkownika zostały wyzerowane';
}

// Pobierz dane
$officers = getAllOfficersWithStatus($pdo);
$current_user_status = null;

foreach ($officers as $officer) {
    if ($officer['id'] == $current_user_id) {
        $current_user_status = $officer;
        break;
    }
}

// Statystyki
$on_duty = array_filter($officers, function($o) { return $o['status'] == 1; });
$off_duty = array_filter($officers, function($o) { return $o['status'] == 3; });

// Pobierz statystyki użytkownika
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(duration_minutes), 0) as total_minutes,
        COALESCE(ws.total_hours, 0) as week_hours
    FROM officer_status os
    LEFT JOIN weekly_stats ws ON os.user_id = ws.user_id AND ws.week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    WHERE os.user_id = ?
");
$stmt->execute([$current_user_id]);
$user_stats = $stmt->fetch();

$total_hours = round(($user_stats['total_minutes'] ?? 0) / 60, 1);
$week_hours = $user_stats['week_hours'] ?? 0;
$min_weekly = 3; // minimum 3h tygodniowo

// Pobierz dane do kopiowania (tylko dla adminów)
$officers_stats_for_copy = [];
if (isAdmin()) {
    $officers_stats_for_copy = getAllOfficersStatsForCopy($pdo);
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Funkcjonariuszy</title>
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
        
        .status-container {
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Header */
        .status-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
        
        .status-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .status-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .status-header p {
            font-size: 16px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }
        
        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
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
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon.on-duty { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.off-duty { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-icon.total { background: linear-gradient(135deg, #3b82f6, #1e40af); }
        .stat-icon.week { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        
        .stat-icon svg {
            width: 20px;
            height: 20px;
            fill: white;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .stat-value.good { color: #10b981; }
        .stat-value.bad { color: #ef4444; }
        
        .stat-description {
            font-size: 13px;
            color: #64748b;
        }
        
        /* Personal Status Panel */
        .personal-status {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .personal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .personal-info h2 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .personal-info p {
            color: #64748b;
            font-size: 15px;
        }
        
        .current-status {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .status-1 {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .status-3 {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .status-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .status-btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-on-duty {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-off-duty {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .status-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .status-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Admin Panel */
        .admin-panel {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 2px solid #ef4444;
        }
        
        .admin-header {
            margin-bottom: 20px;
        }
        
        .admin-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 5px;
        }
        
        .admin-header p {
            color: #64748b;
            font-size: 15px;
        }
        
        .admin-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .admin-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 25px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .reset-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .copy-btn {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
        }
        
        .admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        /* Officers Management - Improved compact design */
        .officers-management {
            margin-top: 30px;
        }
        
        .management-title {
            font-size: 20px;
            font-weight: 600;
            color: #dc2626;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .officers-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .officers-table th {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .officers-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        
        .officers-table tr:hover {
            background: #f8fafc;
        }
        
        .officer-name-cell {
            font-weight: 600;
            color: #1e293b;
        }
        
        .officer-badge-cell {
            color: #64748b;
            font-size: 13px;
        }
        
        .officer-hours-cell {
            text-align: center;
        }
        
        .hours-week { color: #3b82f6; font-weight: 600; }
        .hours-total { color: #8b5cf6; font-weight: 600; }
        
        .officer-status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .officer-status-badge.on-duty {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .officer-status-badge.off-duty {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .officer-actions-cell {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .action-btn.on-duty {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .action-btn.off-duty {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .action-btn.reset {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .action-btn svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }
        
        /* Officers List */
        .officers-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .section-subtitle {
            color: #64748b;
            margin-bottom: 30px;
        }
        
        .officers-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn.active, .filter-btn:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .officers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .officer-card {
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .officer-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .officer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .officer-info h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .officer-info p {
            font-size: 13px;
            color: #64748b;
        }
        
        .officer-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .officer-status.status-1 {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .officer-status.status-3 {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .officer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #64748b;
        }
        
        .working-hours {
            color: #10b981;
        }
        
        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideDown 0.4s ease-out;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .message.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .message svg {
            width: 24px;
            height: 24px;
            fill: currentColor;
        }
        
        /* Copy notification */
        .copy-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease-out;
        }
        
        .copy-notification.show {
            opacity: 1;
            transform: translateX(0);
        }
    </style>
</head>
<body>
    <div class="status-container">
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
        
        <div class="status-header">
            <h1>Status Funkcjonariuszy</h1>
            <p>Zarządzaj swoim statusem i przeglądaj aktywność innych funkcjonariuszy</p>
        </div>
        
        <!-- Quick Statistics -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Na służbie</span>
                    <div class="stat-icon on-duty">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($on_duty); ?></div>
                <div class="stat-description">Funkcjonariuszy aktywnych</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Poza służbą</span>
                    <div class="stat-icon off-duty">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo count($off_duty); ?></div>
                <div class="stat-description">Funkcjonariuszy nieaktywnych</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Twoje godziny</span>
                    <div class="stat-icon total">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.5 8.5l-4.5 2.7-.8-1.3L11.5 9.5V6h1v3.5z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo formatHoursReadable($total_hours); ?></div>
                <div class="stat-description">Łącznie przepracowanych</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Ten tydzień</span>
                    <div class="stat-icon week">
                        <svg viewBox="0 0 24 24">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value <?php echo $week_hours >= $min_weekly ? 'good' : 'bad'; ?>">
                    <?php echo formatHoursReadable($week_hours); ?>
                </div>
                <div class="stat-description">
                    Min. <?php echo formatHoursReadable($min_weekly); ?> tygodniowo
                    <?php if ($week_hours >= $min_weekly): ?>
                        <span style="color: #10b981;">✓ Cel osiągnięty</span>
                    <?php else: ?>
                        <span style="color: #ef4444;">⚠ <?php echo formatHoursReadable($min_weekly - $week_hours); ?> do celu</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Personal Status Panel -->
        <div class="personal-status">
            <div class="personal-header">
                <div class="personal-info">
                    <h2>Twój status</h2>
                    <p>Zarządzaj swoim stanem służby</p>
                </div>
                <div class="current-status status-<?php echo $current_user_status['status'] ?? 3; ?>">
                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: currentColor;">
                        <?php if (($current_user_status['status'] ?? 3) == 1): ?>
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        <?php else: ?>
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                        <?php endif; ?>
                    </svg>
                    <?php echo ($current_user_status['status'] ?? 3) == 1 ? 'Na służbie' : 'Poza służbą'; ?>
                    <?php if (($current_user_status['status'] ?? 3) == 1 && isset($current_user_status['start_time'])): ?>
                        <span id="workingTime" data-start="<?php echo strtotime($current_user_status['start_time']); ?>">
                            (<?php echo formatWorkingTime($current_user_status['start_time']); ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="new_status" value="">
                <div class="status-buttons">
                    <button type="submit" name="change_status" value="1" 
                            class="status-btn btn-on-duty"
                            onclick="this.form.querySelector('input[name=new_status]').value=1"
                            <?php echo ($current_user_status['status'] ?? 3) == 1 ? 'disabled' : ''; ?>>
                        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: currentColor;">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        Rozpocznij służbę
                    </button>
                    
                    <button type="submit" name="change_status" value="3" 
                            class="status-btn btn-off-duty"
                            onclick="this.form.querySelector('input[name=new_status]').value=3"
                            <?php echo ($current_user_status['status'] ?? 3) == 3 ? 'disabled' : ''; ?>>
                        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: currentColor;">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                        </svg>
                        Zakończ służbę
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Admin Panel (tylko dla adminów) -->
        <?php if (isAdmin()): ?>
        <div class="admin-panel">
            <div class="admin-header">
                <h2>Panel Administratora</h2>
                <p>Zarządzanie systemem statusów i funkcjonariuszami</p>
            </div>
            
            <div class="admin-actions">
                <button type="button" class="admin-btn copy-btn" onclick="copyAllHours()">
                    <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: currentColor;">
                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                    </svg>
                    Kopiuj godziny wszystkich
                </button>
                
                <form method="POST" action="" onsubmit="return confirm('Czy na pewno chcesz wyzerować wszystkie godziny? Ta operacja jest nieodwracalna!');" style="display: contents;">
                    <button type="submit" name="reset_hours" class="admin-btn reset-btn">
                        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: currentColor;">
                            <path d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z"/>
                        </svg>
                        Wyzeruj wszystkie godziny
                    </button>
                </form>
            </div>
            
            <!-- Officers Management Table -->
            <div class="officers-management">
                <div class="management-title">
                    <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: currentColor;">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    Zarządzanie funkcjonariuszami
                </div>
                
                <table class="officers-table">
                    <thead>
                        <tr>
                            <th>Funkcjonariusz</th>
                            <th>Odznaka</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: center;">Godziny (tydzień)</th>
                            <th style="text-align: center;">Godziny (razem)</th>
                            <th style="text-align: right;">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($officers as $officer): ?>
                            <?php if ($officer['id'] != $current_user_id): // Nie pokazuj siebie ?>
                            <tr>
                                <td class="officer-name-cell"><?php echo htmlspecialchars($officer['full_name']); ?></td>
                                <td class="officer-badge-cell"><?php echo htmlspecialchars($officer['badge_number']); ?></td>
                                <td style="text-align: center;">
                                    <span class="officer-status-badge <?php echo ($officer['status'] ?? 3) == 1 ? 'on-duty' : 'off-duty'; ?>">
                                        <svg viewBox="0 0 8 8" style="width: 8px; height: 8px; fill: currentColor;">
                                            <circle cx="4" cy="4" r="4"/>
                                        </svg>
                                        <?php echo ($officer['status'] ?? 3) == 1 ? 'Na służbie' : 'Poza służbą'; ?>
                                    </span>
                                </td>
                                <td class="officer-hours-cell">
                                    <span class="hours-week"><?php echo formatHoursReadable($officer['week_hours']); ?></span>
                                </td>
                                <td class="officer-hours-cell">
                                    <span class="hours-total"><?php echo formatHoursReadable(($officer['duration_minutes'] ?? 0) / 60); ?></span>
                                </td>
                                <td class="officer-actions-cell">
                                    <form method="POST" action="" style="display: contents;">
                                        <input type="hidden" name="target_user_id" value="<?php echo $officer['id']; ?>">
                                        <input type="hidden" name="new_status" value="">
                                        
                                        <button type="button" 
                                                class="action-btn on-duty"
                                                onclick="adminChangeStatus(this, <?php echo $officer['id']; ?>, 1)"
                                                <?php echo ($officer['status'] ?? 3) == 1 ? 'disabled' : ''; ?>
                                                title="Postaw na służbę">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                            </svg>
                                        </button>
                                        
                                        <button type="button" 
                                                class="action-btn off-duty"
                                                onclick="adminChangeStatus(this, <?php echo $officer['id']; ?>, 3)"
                                                <?php echo ($officer['status'] ?? 3) == 3 ? 'disabled' : ''; ?>
                                                title="Zdejmij ze służby">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                                            </svg>
                                        </button>
                                        
                                        <button type="button" 
                                                class="action-btn reset"
                                                onclick="resetUserHours(<?php echo $officer['id']; ?>)"
                                                title="Wyzeruj godziny">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M12.5,8C9.85,8 7.45,9 5.6,10.6L2,7V16H11L7.38,12.38C8.77,11.22 10.54,10.5 12.5,10.5C16.04,10.5 19.05,12.81 20.1,16L22.47,15.22C21.08,11.03 17.15,8 12.5,8Z"/>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Officers List -->
        <div class="officers-section">
            <h2 class="section-title">Lista funkcjonariuszy</h2>
            <p class="section-subtitle">Aktualny status wszystkich funkcjonariuszy w systemie</p>
            
            <div class="officers-filter">
                <button class="filter-btn active" onclick="filterOfficers('all')">Wszyscy</button>
                <button class="filter-btn" onclick="filterOfficers('1')">Na służbie</button>
                <button class="filter-btn" onclick="filterOfficers('3')">Poza służbą</button>
            </div>
            
            <div class="officers-grid" id="officersGrid">
                <?php foreach ($officers as $officer): ?>
                <div class="officer-card" data-status="<?php echo $officer['status'] ?? 3; ?>">
                    <div class="officer-header">
                        <div class="officer-info">
                            <h3><?php echo htmlspecialchars($officer['full_name']); ?></h3>
                            <p><?php echo htmlspecialchars($officer['badge_number']); ?> • <?php echo htmlspecialchars($officer['rank_name']); ?></p>
                        </div>
                        <div class="officer-status status-<?php echo $officer['status'] ?? 3; ?>">
                            <svg viewBox="0 0 8 8" style="width: 8px; height: 8px; fill: currentColor;">
                                <circle cx="4" cy="4" r="4"/>
                            </svg>
                            <?php echo ($officer['status'] ?? 3) == 1 ? 'Na służbie' : 'Poza służbą'; ?>
                        </div>
                    </div>
                    
                    <div class="officer-details">
                        <div class="detail-item">
                            <div class="detail-value">
                                <?php if (($officer['status'] ?? 3) == 1 && $officer['start_time']): ?>
                                    <span class="working-hours" data-start="<?php echo strtotime($officer['start_time']); ?>">
                                        <?php echo formatWorkingTime($officer['start_time']); ?>
                                    </span>
                                <?php else: ?>
                                    0h
                                <?php endif; ?>
                            </div>
                            <div class="detail-label">Dziś na służbie</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-value <?php echo $officer['week_hours'] >= $min_weekly ? 'good' : 'bad'; ?>">
                                <?php echo formatHoursReadable($officer['week_hours']); ?>
                            </div>
                            <div class="detail-label">Ten tydzień</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Copy notification -->
    <div id="copyNotification" class="copy-notification">
        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: currentColor; margin-right: 10px;">
            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
        </svg>
        Godziny skopiowane do schowka!
    </div>
    
    <script>
        // Dane do kopiowania (tylko dla adminów)
        <?php if (isAdmin()): ?>
        const officersStatsData = <?php echo json_encode($officers_stats_for_copy); ?>;
        <?php endif; ?>
        
        // Funkcja do kopiowania godzin wszystkich funkcjonariuszy
        function copyAllHours() {
            <?php if (isAdmin()): ?>
            let copyText = "";
            
            officersStatsData.forEach((officer, index) => {
                const totalMinutes = officer.total_minutes || 0;
                const weekHours = officer.week_hours || 0;
                
                // Formatowanie godzin
                const totalH = Math.floor(totalMinutes / 60);
                const totalM = totalMinutes % 60;
                const weekH = Math.floor(weekHours);
                const weekM = Math.round((weekHours - weekH) * 60);
                
                let totalFormatted = totalH > 0 ? (totalH + 'h') : '';
                if (totalM > 0) {
                    totalFormatted += (totalH > 0 ? ' ' : '') + totalM + 'm';
                }
                if (totalFormatted === '') {
                    totalFormatted = '0h';
                }
                
                let weekFormatted = weekH > 0 ? (weekH + 'h') : '';
                if (weekM > 0) {
                    weekFormatted += (weekH > 0 ? ' ' : '') + weekM + 'm';
                }
                if (weekFormatted === '') {
                    weekFormatted = '0h';
                }
                
                if (index > 0) {
                    copyText += " ";
                }
                copyText += officer.full_name + ' - ' + weekFormatted + ' (ten tydzien) ' + totalFormatted + ' (razem)';
            });
            
            navigator.clipboard.writeText(copyText).then(() => {
                showCopyNotification();
            }).catch(err => {
                console.error('Błąd kopiowania:', err);
                // Fallback dla starszych przeglądarek
                const textArea = document.createElement('textarea');
                textArea.value = copyText;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showCopyNotification();
            });
            <?php endif; ?>
        }
        
        // Admin functions
        function adminChangeStatus(button, userId, newStatus) {
            const form = button.closest('form');
            const statusInput = form.querySelector('input[name="new_status"]');
            statusInput.value = newStatus;
            
            const actionText = newStatus === 1 ? 'postawić na służbie' : 'zdjąć ze służby';
            const row = button.closest('tr');
            const name = row.querySelector('.officer-name-cell').textContent;
            
            if (confirm(`Czy na pewno chcesz ${actionText} funkcjonariusza ${name}?`)) {
                // Dodaj ukryte pola do formularza
                const adminInput = document.createElement('input');
                adminInput.type = 'hidden';
                adminInput.name = 'admin_change_status';
                adminInput.value = '1';
                form.appendChild(adminInput);
                
                form.submit();
            }
        }
        
        // Funkcja do resetowania godzin użytkownika
        function resetUserHours(userId) {
            const row = event.target.closest('tr');
            const name = row.querySelector('.officer-name-cell').textContent;
            
            if (confirm(`Czy na pewno chcesz wyzerować godziny funkcjonariusza ${name}? Ta operacja jest nieodwracalna!`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const userInput = document.createElement('input');
                userInput.type = 'hidden';
                userInput.name = 'target_user_id';
                userInput.value = userId;
                
                const resetInput = document.createElement('input');
                resetInput.type = 'hidden';
                resetInput.name = 'reset_user_hours';
                resetInput.value = '1';
                
                form.appendChild(userInput);
                form.appendChild(resetInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Pokaż powiadomienie o kopiowaniu
        function showCopyNotification() {
            const notification = document.getElementById('copyNotification');
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        // Funkcja do formatowania czasu
        function formatWorkingTime(startTimestamp) {
            const now = Math.floor(Date.now() / 1000);
            const seconds = now - startTimestamp;
            
            if (seconds < 0) return '0m';
            
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            
            if (hours > 0 && minutes > 0) {
                return hours + 'h ' + minutes + 'm';
            } else if (hours > 0) {
                return hours + 'h';
            } else {
                return minutes + 'm';
            }
        }
        
        // Aktualizuj czas pracy na żywo
        function updateWorkingTimes() {
            const workingElements = document.querySelectorAll('[data-start]');
            
            workingElements.forEach(element => {
                const startTime = parseInt(element.getAttribute('data-start'));
                if (startTime && startTime > 0) {
                    const formattedTime = formatWorkingTime(startTime);
                    
                    if (element.id === 'workingTime') {
                        element.textContent = '(' + formattedTime + ')';
                    } else {
                        element.textContent = formattedTime;
                    }
                }
            });
        }
        
        // Obsługa formularzy
        document.addEventListener('DOMContentLoaded', function() {
            // NIE blokujemy formularza statusu - pozwalamy na normalne wysłanie
            // Formularz ma już wszystkie potrzebne dane w HTML
        });
        
        // Officers filter function
        function filterOfficers(status) {
            const cards = document.querySelectorAll('.officer-card');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update button states
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter cards
            cards.forEach(card => {
                if (status === 'all' || card.getAttribute('data-status') === status) {
                    card.style.display = 'block';
                    card.style.animation = 'fadeIn 0.3s ease-out';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '1') {
                e.preventDefault();
                const btn = document.querySelector('.btn-on-duty:not([disabled])');
                if (btn) btn.click();
            }
            if (e.ctrlKey && e.key === '3') {
                e.preventDefault();
                const btn = document.querySelector('.btn-off-duty:not([disabled])');
                if (btn) btn.click();
            }
            <?php if (isAdmin()): ?>
            if (e.ctrlKey && e.key === 'c') {
                e.preventDefault();
                copyAllHours();
            }
            <?php endif; ?>
        });
        
        // Hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 300);
            });
        }, 5000);
        
        // Start live time updates
        updateWorkingTimes();
        setInterval(updateWorkingTimes, 60000); // Update every minute
        
        console.log('Status page loaded successfully');
    </script>
</body>
</html>