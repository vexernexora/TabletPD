<?php
// officers_api.php - API dla aplikacji funkcjonariuszy
require_once 'config.php';

// Sprawdzenie autoryzacji
requireAuth();

// Ustawienia dla JSON response
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';
$officer_id = (int)($_GET['id'] ?? 0);

switch ($action) {
    case 'get_officer':
        getOfficerDetails($pdo, $officer_id);
        break;
    
    case 'get_activities':
        getOfficerActivities($pdo, $officer_id);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function getOfficerDetails($pdo, $officer_id) {
    if (!$officer_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Officer ID is required']);
        return;
    }
    
    try {
        // Sprawdź czy tabela departments istnieje
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'departments'");
        $stmt->execute();
        $departments_exists = $stmt->fetch() ? true : false;
        
        if ($departments_exists) {
            $sql = "
                SELECT 
                    u.id,
                    u.username,
                    COALESCE(CONCAT(o.first_name, ' ', o.last_name), u.username) as full_name,
                    o.first_name,
                    o.last_name,
                    COALESCE(o.badge_number, 'N/A') as badge_number,
                    COALESCE(d.department_name, 'N/A') as department_name,
                    COALESCE(r.rank_name, 'N/A') as rank_name,
                    o.email,
                    o.phone,
                    u.last_login,
                    u.created_at,
                    os.status as current_status,
                    os.start_time,
                    COALESCE(ws.total_hours, 0) as week_hours,
                    COALESCE(os.duration_minutes, 0) as total_minutes
                FROM users u
                LEFT JOIN officers o ON u.id = o.user_id
                LEFT JOIN departments d ON o.department_id = d.id
                LEFT JOIN officer_ranks r ON o.rank_id = r.id
                LEFT JOIN officer_status os ON u.id = os.user_id
                LEFT JOIN weekly_stats ws ON u.id = ws.user_id AND ws.week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                WHERE u.id = ? AND u.is_active = 1
            ";
        } else {
            $sql = "
                SELECT 
                    u.id,
                    u.username,
                    COALESCE(CONCAT(o.first_name, ' ', o.last_name), u.username) as full_name,
                    o.first_name,
                    o.last_name,
                    COALESCE(o.badge_number, 'N/A') as badge_number,
                    'Patrol Department' as department_name,
                    COALESCE(r.rank_name, 'Officer') as rank_name,
                    o.email,
                    o.phone,
                    u.last_login,
                    u.created_at,
                    os.status as current_status,
                    os.start_time,
                    COALESCE(ws.total_hours, 0) as week_hours,
                    COALESCE(os.duration_minutes, 0) as total_minutes
                FROM users u
                LEFT JOIN officers o ON u.id = o.user_id
                LEFT JOIN officer_ranks r ON o.rank_id = r.id
                LEFT JOIN officer_status os ON u.id = os.user_id
                LEFT JOIN weekly_stats ws ON u.id = ws.user_id AND ws.week_start = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                WHERE u.id = ? AND u.is_active = 1
            ";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$officer_id]);
        $officer = $stmt->fetch();
        
        if (!$officer) {
            http_response_code(404);
            echo json_encode(['error' => 'Officer not found']);
            return;
        }
        
        // Pobierz aktywności
        $activities = getActivitiesData($pdo, $officer_id);
        
        $response = [
            'officer' => $officer,
            'activities' => $activities,
            'formatted' => [
                'week_hours' => formatHours($officer['week_hours']),
                'total_hours' => formatHours($officer['total_minutes'] / 60),
                'last_login' => $officer['last_login'] ? date('d.m.Y H:i', strtotime($officer['last_login'])) : 'Nigdy',
                'status_text' => $officer['current_status'] == 1 ? 'Na służbie' : 'Poza służbą',
                'working_time' => $officer['current_status'] == 1 && $officer['start_time'] ? 
                    formatWorkingTime($officer['start_time']) : null
            ]
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getOfficerActivities($pdo, $officer_id) {
    if (!$officer_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Officer ID is required']);
        return;
    }
    
    $activities = getActivitiesData($pdo, $officer_id);
    echo json_encode($activities, JSON_UNESCAPED_UNICODE);
}

function getActivitiesData($pdo, $officer_id) {
    $all_activities = [];
    
    try {
        // 1. Pobierz zmiany statusu
        $stmt = $pdo->prepare("
            SELECT 
                'status' as type,
                CASE 
                    WHEN status_to = 1 THEN 'Wejście na służbę'
                    WHEN status_to = 3 THEN 'Zejście ze służby'
                    ELSE 'Zmiana statusu'
                END as description,
                change_time as time,
                duration_minutes,
                notes,
                CASE 
                    WHEN status_to = 1 THEN '#10b981'
                    WHEN status_to = 3 THEN '#ef4444'
                    ELSE '#3b82f6'
                END as color,
                'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z' as icon
            FROM status_history 
            WHERE user_id = ?
            ORDER BY change_time DESC
            LIMIT 10
        ");
        
        $stmt->execute([$officer_id]);
        $status_changes = $stmt->fetchAll();
        
        foreach ($status_changes as $activity) {
            $formatted_activity = [
                'activity_type' => $activity['type'],
                'activity_description' => $activity['description'],
                'activity_time' => $activity['time'],
                'relative_time' => getRelativeTime($activity['time']),
                'notes' => $activity['notes'],
                'color' => $activity['color'],
                'icon' => $activity['icon']
            ];
            
            // Dodaj czas pracy dla zejść ze służby
            if ($activity['duration_minutes'] > 0) {
                $formatted_activity['details'] = 'Czas pracy: ' . formatHours($activity['duration_minutes'] / 60);
            }
            
            $all_activities[] = $formatted_activity;
        }
        
        // 2. Sprawdź czy istnieje tabela officer_activities
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'officer_activities'");
        $stmt->execute();
        if ($stmt->fetch()) {
            // Pobierz aktywności RP
            $stmt = $pdo->prepare("
                SELECT 
                    activity_type as type,
                    title as description,
                    created_at as time,
                    CONCAT(COALESCE(description, ''), 
                           CASE WHEN location IS NOT NULL THEN CONCAT(' - ', location) ELSE '' END,
                           CASE WHEN citizen_involved IS NOT NULL THEN CONCAT(' (', citizen_involved, ')') ELSE '' END) as notes,
                    CASE activity_type
                        WHEN 'report' THEN '#f59e0b'
                        WHEN 'ticket' THEN '#f97316' 
                        WHEN 'arrest' THEN '#dc2626'
                        WHEN 'note' THEN '#059669'
                        WHEN 'patrol' THEN '#8b5cf6'
                        ELSE '#64748b'
                    END as color,
                    CASE activity_type
                        WHEN 'report' THEN 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z'
                        WHEN 'ticket' THEN 'M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z'
                        WHEN 'arrest' THEN 'M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1Z'
                        WHEN 'note' THEN 'M3,3V21H21V3H3M18,18H6V16H18V18M18,14H6V12H18V14M18,10H6V8H18V10M18,6H6V4H18V6Z'
                        WHEN 'patrol' THEN 'M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z'
                        ELSE 'M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z'
                    END as icon
                FROM officer_activities 
                WHERE officer_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            
            $stmt->execute([$officer_id]);
            $rp_activities = $stmt->fetchAll();
            
            foreach ($rp_activities as $activity) {
                $all_activities[] = [
                    'activity_type' => $activity['type'],
                    'activity_description' => $activity['description'],
                    'activity_time' => $activity['time'],
                    'relative_time' => getRelativeTime($activity['time']),
                    'notes' => $activity['notes'],
                    'color' => $activity['color'],
                    'icon' => $activity['icon']
                ];
            }
        }
        
        // 3. Posortuj wszystko według czasu
        usort($all_activities, function($a, $b) {
            return strtotime($b['activity_time']) - strtotime($a['activity_time']);
        });
        
        // 4. Ogranicz do 15 najnowszych
        return array_slice($all_activities, 0, 15);
        
    } catch (Exception $e) {
        error_log("API Error getting activities for officer $officer_id: " . $e->getMessage());
        return [];
    }
}

function formatHours($hours) {
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

function getRelativeTime($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Przed chwilą';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' min temu';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' godz temu';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' dni temu';
    } else {
        return date('d.m.Y', $time);
    }
}
?>