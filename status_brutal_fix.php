<?php
// status_brutal_fix.php - BRUTALNA ŁATKA NA PROBLEM Z CZASEM
// Zastąp swoją funkcję changeUserStatus() tą wersją

function changeUserStatus($pdo, $user_id, $new_status, $admin_action = false) {
    try {
        $pdo->beginTransaction();
        
        // Pobierz aktualny status
        $stmt = $pdo->prepare("SELECT * FROM officer_status WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current = $stmt->fetch();
        
        if ($new_status == 1) { // Na służbie
            
            // BRUTALNA ŁATKA: Zawsze odejmij 60 minut przy wejściu na służbę
            if ($current) {
                // Odejmij 60 minut od duration_minutes
                $new_duration = max(0, ($current['duration_minutes'] ?? 0) - 60);
                
                $stmt = $pdo->prepare("
                    UPDATE officer_status 
                    SET status = 1, 
                        start_time = DATE_SUB(NOW(), INTERVAL 1 HOUR), -- Cofnij start_time o godzinę
                        end_time = NULL,
                        duration_minutes = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$new_duration, $user_id]);
            } else {
                // Nowy rekord - zacznij od -60
                $stmt = $pdo->prepare("
                    INSERT INTO officer_status (user_id, status, start_time, duration_minutes) 
                    VALUES (?, 1, DATE_SUB(NOW(), INTERVAL 1 HOUR), -60)
                ");
                $stmt->execute([$user_id]);
            }
            
            // Historia
            $stmt = $pdo->prepare("
                INSERT INTO status_history (user_id, status_from, status_to, change_time, notes) 
                VALUES (?, ?, 1, NOW(), 'Start z -60 minut (fix)')
            ");
            $stmt->execute([$user_id, $current ? $current['status'] : 3]);
            
        } else { // Poza służbą
            
            if ($current && $current['status'] == 1 && $current['start_time']) {
                // Użyj DATE_ADD aby dodać godzinę do start_time przed obliczeniem
                $stmt = $pdo->prepare("
                    SELECT TIMESTAMPDIFF(MINUTE, 
                        DATE_ADD(start_time, INTERVAL 1 HOUR), 
                        NOW()
                    ) as duration 
                    FROM officer_status 
                    WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch();
                $duration = max(0, min(480, $result['duration'] ?? 0)); // 0-480 minut max
                
                // Zaktualizuj
                $new_total = ($current['duration_minutes'] ?? 0) + $duration;
                
                $stmt = $pdo->prepare("
                    UPDATE officer_status 
                    SET status = 3, 
                        end_time = NOW(), 
                        duration_minutes = ?,
                        start_time = NULL
                    WHERE user_id = ?
                ");
                $stmt->execute([$new_total, $user_id]);
                
                // Statystyki tygodniowe
                if ($duration > 0) {
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
                }
                
                // Historia
                $stmt = $pdo->prepare("
                    INSERT INTO status_history (user_id, status_from, status_to, change_time, duration_minutes, notes) 
                    VALUES (?, 1, 3, NOW(), ?, 'Koniec służby')
                ");
                $stmt->execute([$user_id, $duration]);
                
            } else {
                // Brak aktywnej sesji
                if ($current) {
                    $stmt = $pdo->prepare("
                        UPDATE officer_status 
                        SET status = 3, start_time = NULL, end_time = NOW()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO officer_status (user_id, status, end_time, duration_minutes) 
                        VALUES (?, 3, NOW(), 0)
                    ");
                    $stmt->execute([$user_id]);
                }
            }
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Błąd changeUserStatus: " . $e->getMessage());
        return false;
    }
}

// DODATKOWO: Funkcja do jednorazowego naprawienia wszystkich użytkowników
function fixAllUsers($pdo) {
    $stmt = $pdo->prepare("UPDATE officer_status SET duration_minutes = GREATEST(0, duration_minutes - 60)");
    $stmt->execute();
    echo "Odjęto 60 minut wszystkim użytkownikom!";
}
?>