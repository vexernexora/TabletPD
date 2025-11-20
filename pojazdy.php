<?php
require_once 'config.php';

requireAuth();

$message = '';
$error = '';
$pdo = getDB();

if (!$pdo) {
    die("Błąd połączenia z bazą danych");
}

$use_file_storage = false;
$uploads_dir = 'uploads';
$vehicles_dir = $uploads_dir . '/vehicles';

if (is_writable('.')) {
    if (!is_dir($uploads_dir)) {
        if (@mkdir($uploads_dir, 0755, true)) {
            $use_file_storage = true;
        }
    } else {
        $use_file_storage = true;
    }
    
    if ($use_file_storage && !is_dir($vehicles_dir)) {
        if (!@mkdir($vehicles_dir, 0755, true)) {
            $use_file_storage = false;
        }
    }
} else {
    error_log("Brak uprawnień do zapisu - używam przechowywania zdjęć w bazie danych");
}

$current_user_id = $_SESSION['user_id'] ?? 1;
$mysql_connected = false;
$current_user = null;

try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    $users_table_exists = $stmt->fetch() ? true : false;
    
    if ($users_table_exists) {
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
            $current_user = [
                'username' => $user_data['username'],
                'rank' => $user_data['role'] ?? 'user',
                'full_name' => $user_data['full_name'],
                'badge_number' => $user_data['badge_number'] ?? 'N/A'
            ];
            $mysql_connected = true;
        }
    }
} catch (Exception $e) {
    error_log("MySQL error: " . $e->getMessage());
}

if (!$mysql_connected || !$current_user) {
    $session_user_data = $_SESSION['user_data'] ?? ['name' => 'Użytkownik', 'badge' => 'N/A', 'role' => 'guest'];
    
    $current_user = [
        'username' => $_SESSION['username'] ?? 'guest',
        'rank' => $_SESSION['user_role'] ?? $session_user_data['role'] ?? 'user',
        'full_name' => $session_user_data['name'] ?? 'Użytkownik',
        'badge_number' => $session_user_data['badge'] ?? 'N/A'
    ];
}

$is_admin = $current_user && isset($current_user['rank']) && $current_user['rank'] === 'admin';
$can_edit = $current_user && isset($current_user['rank']) && in_array($current_user['rank'], ['admin', 'officer']);

try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'pojazdy'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE pojazdy (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rejestracja VARCHAR(20) NOT NULL UNIQUE,
                marka VARCHAR(100) NOT NULL,
                model VARCHAR(100) NOT NULL,
                rocznik INT,
                kolor VARCHAR(50),
                typ_pojazdu ENUM('samochod', 'motocykl', 'ciezarowka', 'autobus', 'inne') DEFAULT 'samochod',
                vin VARCHAR(50),
                wlasciciel_imie VARCHAR(100),
                wlasciciel_nazwisko VARCHAR(100),
                wlasciciel_pesel VARCHAR(15),
                status_poszukiwania ENUM('NIE_POSZUKIWANY', 'POSZUKIWANY') DEFAULT 'NIE_POSZUKIWANY',
                zdjecie LONGBLOB,
                zdjecie_mime VARCHAR(50),
                zdjecie_filename VARCHAR(255),
                uwagi TEXT,
                data_dodania TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ostatnia_aktualizacja TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        $sample_vehicles = [
            ['WA12345', 'Toyota', 'Camry', 2020, 'Srebrny', 'samochod', 'JT2BF28K123456789', 'Jan', 'Kowalski', '80010112345'],
            ['KR67890', 'BMW', 'X5', 2019, 'Czarny', 'samochod', 'WBAFR9C51DD123456', 'Anna', 'Nowak', '85050587654'],
            ['GD11111', 'Volkswagen', 'Golf', 2018, 'Biały', 'samochod', 'WVWZZZ1JZ1W123456', 'Piotr', 'Wiśniewski', '75111298765'],
            ['PO22222', 'Ford', 'Transit', 2021, 'Niebieski', 'ciezarowka', '1FTBW2CM8JKB12345', 'Marek', 'Zieliński', '70020345678'],
            ['WR33333', 'Harley-Davidson', 'Sportster', 2017, 'Czerwony', 'motocykl', '1HD1KB4197Y123456', 'Tomasz', 'Lewandowski', '88090567890'],
            ['LU44444', 'Mercedes', 'Sprinter', 2022, 'Szary', 'ciezarowka', 'WD3PE7CD4NP123456', 'Katarzyna', 'Kamińska', '82040198765']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO pojazdy (rejestracja, marka, model, rocznik, kolor, typ_pojazdu, vin, wlasciciel_imie, wlasciciel_nazwisko, wlasciciel_pesel) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sample_vehicles as $vehicle) {
            $stmt->execute($vehicle);
        }
    } else {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM pojazdy LIKE 'zdjecie_filename'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE pojazdy ADD COLUMN zdjecie_filename VARCHAR(255) AFTER status_poszukiwania");
        }
        
        $stmt = $pdo->prepare("SHOW COLUMNS FROM pojazdy LIKE 'zdjecie'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE pojazdy ADD COLUMN zdjecie LONGBLOB AFTER status_poszukiwania");
        }
        
        $stmt = $pdo->prepare("SHOW COLUMNS FROM pojazdy LIKE 'zdjecie_mime'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE pojazdy ADD COLUMN zdjecie_mime VARCHAR(50) AFTER zdjecie");
        }
    }
    
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'pojazdy_historia'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE pojazdy_historia (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pojazd_id INT NOT NULL,
                typ ENUM('poszukiwanie', 'zatrzymanie', 'notatka', 'zmiana_wlasciciela') NOT NULL,
                opis TEXT NOT NULL,
                funkcjonariusz VARCHAR(255),
                data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pojazd_id) REFERENCES pojazdy(id) ON DELETE CASCADE
            )
        ");
    }
    
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'poszukiwania_pojazdy'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE poszukiwania_pojazdy (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pojazd_id INT NOT NULL,
                powod VARCHAR(255) NOT NULL,
                opis TEXT,
                priorytet ENUM('niski', 'normalny', 'wysoki') DEFAULT 'normalny',
                funkcjonariusz VARCHAR(255),
                data_rozpoczecia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_zakonczenia TIMESTAMP NULL,
                status ENUM('aktywne', 'zakonczone') DEFAULT 'aktywne',
                FOREIGN KEY (pojazd_id) REFERENCES pojazdy(id) ON DELETE CASCADE
            )
        ");
    }
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
}

function updateSearchStatus($pdo, $vehicle_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM poszukiwania_pojazdy WHERE pojazd_id = ? AND status = 'aktywne'");
        $stmt->execute([$vehicle_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_status = ($result['count'] > 0) ? 'POSZUKIWANY' : 'NIE_POSZUKIWANY';
        
        $stmt = $pdo->prepare("UPDATE pojazdy SET status_poszukiwania = ? WHERE id = ?");
        $stmt->execute([$new_status, $vehicle_id]);
        
        return $new_status;
    } catch (Exception $e) {
        error_log("Error updating search status: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    switch ($action) {
        case 'get_vehicle':
            try {
                $id = intval($_POST['id']);
                error_log("Getting vehicle with ID: " . $id);
                
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                           COUNT(ph.id) as historia_count,
                           COUNT(ps.id) as poszukiwania_count,
                           LENGTH(p.zdjecie) as zdjecie_size
                    FROM pojazdy p
                    LEFT JOIN pojazdy_historia ph ON p.id = ph.pojazd_id
                    LEFT JOIN poszukiwania_pojazdy ps ON p.id = ps.pojazd_id AND ps.status = 'aktywne'
                    WHERE p.id = ?
                    GROUP BY p.id
                ");
                $stmt->execute([$id]);
                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$vehicle) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Pojazd nie został znaleziony'
                    ]);
                    exit;
                }
                
                error_log("Vehicle found: " . print_r($vehicle, true));
                
                $stmt = $pdo->prepare("
                    SELECT *, DATE_FORMAT(data, '%Y-%m-%d %H:%i:%s') as formatted_date
                    FROM pojazdy_historia 
                    WHERE pojazd_id = ? 
                    ORDER BY data DESC
                ");
                $stmt->execute([$id]);
                $vehicle['historia'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    SELECT *, DATE_FORMAT(data_rozpoczecia, '%Y-%m-%d %H:%i:%s') as formatted_date
                    FROM poszukiwania_pojazdy 
                    WHERE pojazd_id = ? AND status = 'aktywne'
                    ORDER BY data_rozpoczecia DESC
                ");
                $stmt->execute([$id]);
                $vehicle['poszukiwania'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $vehicle['zdjecie_base64'] = null;
                if ($vehicle['zdjecie_filename'] && file_exists('uploads/vehicles/' . $vehicle['zdjecie_filename'])) {
                    $image_data = file_get_contents('uploads/vehicles/' . $vehicle['zdjecie_filename']);
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_buffer($finfo, $image_data);
                    finfo_close($finfo);
                    $vehicle['zdjecie_base64'] = 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
                    error_log("Using file photo: " . $vehicle['zdjecie_filename']);
                } elseif ($vehicle['zdjecie_size'] > 0) {
                    try {
                        $stmt = $pdo->prepare("SELECT zdjecie, zdjecie_mime FROM pojazdy WHERE id = ?");
                        $stmt->execute([$id]);
                        $photo_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($photo_data && $photo_data['zdjecie']) {
                            $vehicle['zdjecie_base64'] = 'data:' . $photo_data['zdjecie_mime'] . ';base64,' . base64_encode($photo_data['zdjecie']);
                            error_log("Using database photo, size: " . strlen($photo_data['zdjecie']));
                        }
                    } catch (Exception $photo_error) {
                        error_log("Photo error: " . $photo_error->getMessage());
                    }
                }
                
                $vehicle['user_permissions'] = [
                    'is_admin' => $is_admin,
                    'can_edit' => $can_edit,
                    'can_delete' => $is_admin
                ];
                
                unset($vehicle['zdjecie']);
                
                echo json_encode([
                    'success' => true,
                    'vehicle' => $vehicle,
                    'message' => 'Pojazd znaleziony'
                ]);
                
            } catch (Exception $e) {
                error_log("Error in get_vehicle: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Błąd serwera: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'add_search':
            if (!$can_edit) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Brak uprawnień do dodawania poszukiwań'
                ]);
                exit;
            }
            
            try {
                $vehicle_id = intval($_POST['vehicle_id']);
                $reason = trim($_POST['reason'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $priority = $_POST['priority'] ?? 'normalny';
                $officer = trim($_POST['officer'] ?? '');

                if (empty($reason) || empty($officer)) {
                    throw new Exception("Powód poszukiwania i funkcjonariusz są wymagane");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO poszukiwania_pojazdy (pojazd_id, powod, opis, priorytet, funkcjonariusz) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$vehicle_id, $reason, $description, $priority, $officer]);

                $stmt = $pdo->prepare("
                    INSERT INTO pojazdy_historia (pojazd_id, typ, opis, funkcjonariusz) 
                    VALUES (?, 'poszukiwanie', ?, ?)
                ");
                $description_text = "Rozpoczęto poszukiwanie: $reason";
                if (!empty($description)) {
                    $description_text .= " - $description";
                }
                $stmt->execute([$vehicle_id, $description_text, $officer]);

                updateSearchStatus($pdo, $vehicle_id);

                echo json_encode([
                    'success' => true,
                    'message' => 'Poszukiwanie zostało dodane'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Błąd: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'end_search':
            if (!$can_edit) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Brak uprawnień do kończenia poszukiwań'
                ]);
                exit;
            }
            
            try {
                $search_id = intval($_POST['search_id']);
                $reason = trim($_POST['reason'] ?? '');
                $officer = trim($_POST['officer'] ?? '');

                if (empty($reason) || empty($officer)) {
                    throw new Exception("Powód zakończenia i funkcjonariusz są wymagane");
                }

                $stmt = $pdo->prepare("SELECT pojazd_id, powod FROM poszukiwania_pojazdy WHERE id = ?");
                $stmt->execute([$search_id]);
                $search = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$search) {
                    throw new Exception("Poszukiwanie nie zostało znalezione");
                }

                $stmt = $pdo->prepare("
                    UPDATE poszukiwania_pojazdy 
                    SET status = 'zakonczone', data_zakonczenia = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$search_id]);

                $stmt = $pdo->prepare("
                    INSERT INTO pojazdy_historia (pojazd_id, typ, opis, funkcjonariusz) 
                    VALUES (?, 'poszukiwanie', ?, ?)
                ");
                $description_text = "Zakończono poszukiwanie '{$search['powod']}': $reason";
                $stmt->execute([$search['pojazd_id'], $description_text, $officer]);

                updateSearchStatus($pdo, $search['pojazd_id']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Poszukiwanie zostało zakończone'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Błąd: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'add_note':
            if (!$can_edit) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Brak uprawnień do dodawania notatek'
                ]);
                exit;
            }
            
            try {
                $vehicle_id = intval($_POST['vehicle_id']);
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $officer = trim($_POST['officer'] ?? '');

                if (empty($title) || empty($content) || empty($officer)) {
                    throw new Exception("Wszystkie pola są wymagane");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO pojazdy_historia (pojazd_id, typ, opis, funkcjonariusz) 
                    VALUES (?, 'notatka', ?, ?)
                ");
                $description = "$title: $content";
                $stmt->execute([$vehicle_id, $description, $officer]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Notatka została dodana'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Błąd: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'upload_photo':
            if (!$is_admin) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Brak uprawnień do zmiany zdjęć'
                ]);
                exit;
            }
            
            try {
                global $use_file_storage;
                $vehicle_id = intval($_POST['vehicle_id']);
                
                if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Błąd podczas przesyłania zdjęcia");
                }
                
                $file = $_FILES['photo'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception("Nieobsługiwany format pliku");
                }
                
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception("Plik jest za duży (maksymalnie 5MB)");
                }
                
                if ($use_file_storage && is_writable('uploads/vehicles')) {
                    $stmt = $pdo->prepare("SELECT zdjecie_filename FROM pojazdy WHERE id = ?");
                    $stmt->execute([$vehicle_id]);
                    $old_photo = $stmt->fetchColumn();
                    
                    if ($old_photo && file_exists('uploads/vehicles/' . $old_photo)) {
                        unlink('uploads/vehicles/' . $old_photo);
                    }
                    
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'vehicle_' . $vehicle_id . '_' . time() . '.' . $extension;
                    $filepath = 'uploads/vehicles/' . $filename;
                    
                    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                        throw new Exception("Błąd podczas zapisywania pliku");
                    }
                    
                    $stmt = $pdo->prepare("UPDATE pojazdy SET zdjecie_filename = ?, zdjecie = NULL, zdjecie_mime = NULL WHERE id = ?");
                    $stmt->execute([$filename, $vehicle_id]);
                } else {
                    $image_data = file_get_contents($file['tmp_name']);
                    
                    $stmt = $pdo->prepare("UPDATE pojazdy SET zdjecie = ?, zdjecie_mime = ?, zdjecie_filename = NULL WHERE id = ?");
                    $stmt->execute([$image_data, $file['type'], $vehicle_id]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Zdjęcie zostało przesłane'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Błąd: ' . $e->getMessage()
                ]);
            }
            exit;

        case 'delete_history':
            if (!$is_admin) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Brak uprawnień do usuwania wpisów'
                ]);
                exit;
            }
            
            try {
                $history_id = intval($_POST['history_id']);
                $reason = trim($_POST['reason'] ?? '');
                
                if (empty($reason)) {
                    throw new Exception("Powód usunięcia jest wymagany");
                }
                
                $stmt = $pdo->prepare("DELETE FROM pojazdy_historia WHERE id = ?");
                $stmt->execute([$history_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Wpis został usunięty'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit;
    }
}

$search_registration = $_GET['search_registration'] ?? '';
$search_owner = $_GET['search_owner'] ?? '';
$search_status = $_GET['search_status'] ?? '';
$search_query = $_GET['search'] ?? '';

function getVehiclesWithFilters($pdo, $registration = '', $owner = '', $status = '', $general = '') {
    $sql = "
        SELECT p.*, 
               COUNT(ph.id) as historia_count,
               COUNT(ps.id) as aktywne_poszukiwania,
               LENGTH(p.zdjecie) as zdjecie_size
        FROM pojazdy p
        LEFT JOIN pojazdy_historia ph ON p.id = ph.pojazd_id
        LEFT JOIN poszukiwania_pojazdy ps ON p.id = ps.pojazd_id AND ps.status = 'aktywne'
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($registration)) {
        $sql .= " AND p.rejestracja LIKE ?";
        $params[] = "%$registration%";
    }
    
    if (!empty($owner)) {
        $sql .= " AND (CONCAT(p.wlasciciel_imie, ' ', p.wlasciciel_nazwisko) LIKE ? OR p.wlasciciel_pesel LIKE ?)";
        $params[] = "%$owner%";
        $params[] = "%$owner%";
    }
    
    if (!empty($status)) {
        $sql .= " AND p.status_poszukiwania = ?";
        $params[] = $status;
    }
    
    if (!empty($general)) {
        $sql .= " AND (
            p.rejestracja LIKE ? OR 
            p.marka LIKE ? OR 
            p.model LIKE ? OR
            CONCAT(p.wlasciciel_imie, ' ', p.wlasciciel_nazwisko) LIKE ? OR
            p.wlasciciel_pesel LIKE ?
        )";
        $params[] = "%$general%";
        $params[] = "%$general%";
        $params[] = "%$general%";
        $params[] = "%$general%";
        $params[] = "%$general%";
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.rejestracja";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $vehicles = getVehiclesWithFilters($pdo, $search_registration, $search_owner, $search_status, $search_query);
} catch (Exception $e) {
    $error = "Błąd podczas pobierania danych: " . $e->getMessage();
    $vehicles = [];
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pojazdy - System Policyjny</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .vehicles-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            color: white;
            box-shadow: 0 10px 40px rgba(30, 64, 175, 0.15);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .header-left p {
            font-size: 16px;
            opacity: 0.8;
        }
        
        .user-info {
            text-align: right;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .admin-badge {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 8px;
            display: inline-block;
        }
        
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            border: 1px solid;
        }
        
        .error-message {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        
        .success-message {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
            display: none;
        }
        
        .success-message.show {
            display: block;
        }
        
        .filters-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .filters-header {
            background: #f8fafc;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .filters-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
        }
        
        .filters-content {
            padding: 24px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
            font-size: 14px;
        }
        
        .form-control {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            text-align: center;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.25);
        }
        
        .btn-outline {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
        }
        
        .btn-outline:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        
        .vehicles-table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8fafc;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #334155;
        }
        
        .table-stats {
            font-size: 14px;
            color: #64748b;
        }
        
        .vehicles-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .vehicles-table th {
            background: #f8fafc;
            padding: 16px 20px;
            text-align: left;
            font-weight: 700;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .vehicles-table td {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .vehicles-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .vehicle-photo {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .vehicle-photo:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .no-photo {
            width: 60px;
            height: 60px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 12px;
            text-align: center;
            font-weight: 500;
        }
        
        .vehicle-reg {
            font-size: 16px;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 4px;
        }
        
        .vehicle-type {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .vehicle-details {
            font-weight: 600;
            margin-bottom: 4px;
            color: #334155;
        }
        
        .vehicle-year-color {
            font-size: 13px;
            color: #64748b;
        }
        
        .owner-name {
            font-weight: 600;
            margin-bottom: 4px;
            color: #334155;
        }
        
        .owner-pesel {
            font-size: 13px;
            color: #64748b;
            font-family: 'Monaco', 'Menlo', monospace;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-wanted {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .status-clear {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        .btn-info {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
        }
        
        .btn-info:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(6, 182, 212, 0.25);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
        }
        
        .empty-state-description {
            font-size: 14px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: 20px;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 24px 30px;
            border-radius: 20px 20px 0 0;
            position: relative;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 24px;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: background 0.2s ease;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .modal-content {
            padding: 30px;
        }
        
        .vehicle-detail-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .vehicle-photo-large {
            width: 100%;
            max-width: 300px;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .vehicle-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .info-item {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            border-left: 4px solid #3b82f6;
        }
        
        .info-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
        }
        
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }
        
        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        
        .tab:hover {
            color: #3b82f6;
            background: #f8fafc;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .history-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border-left: 4px solid #3b82f6;
            position: relative;
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .history-type {
            font-weight: 700;
            color: #3b82f6;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        
        .history-date {
            color: #64748b;
            font-size: 13px;
            font-family: 'Monaco', 'Menlo', monospace;
        }
        
        .history-description {
            color: #1e293b;
            line-height: 1.6;
            margin-bottom: 12px;
        }
        
        .history-officer {
            color: #64748b;
            font-size: 13px;
            font-style: italic;
        }
        
        .delete-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 12px;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .history-item:hover .delete-btn {
            display: flex;
        }
        
        .search-item {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        
        .search-priority {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 12px;
        }
        
        .priority-wysoki { background: #fef2f2; color: #dc2626; }
        .priority-normalny { background: #fffbeb; color: #d97706; }
        .priority-niski { background: #f0fdf4; color: #059669; }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .vehicles-container {
                padding: 16px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .vehicle-detail-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .vehicle-info-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }
            
            .user-info {
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="vehicles-container">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>System Zarządzania Pojazdami</h1>
                    <p>Policyjny system rejestracji i kontroli pojazdów</p>
                </div>
                <div class="user-info">
                    <div><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                    <div><?php echo htmlspecialchars($current_user['rank']); ?></div>
                    <div><?php echo htmlspecialchars($current_user['badge_number']); ?></div>
                    <?php if ($is_admin): ?>
                        <div class="admin-badge">Administrator</div>
                    <?php elseif ($can_edit): ?>
                        <div class="admin-badge">Officer</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="message error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div id="successMessage" class="message success-message"></div>

        <div class="filters-section">
            <div class="filters-header">
                <h3>Wyszukiwanie pojazdów</h3>
                <p>Użyj filtrów poniżej, aby znaleźć konkretny pojazd</p>
            </div>
            <div class="filters-content">
                <form method="GET">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label class="form-label">Wyszukiwanie ogólne</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Indeks Stanowy, marka, model, właściciel..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Indeks Stanowy</label>
                            <input type="text" name="search_registration" class="form-control" 
                                   placeholder="np. WA12345" 
                                   value="<?php echo htmlspecialchars($search_registration); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Właściciel</label>
                            <input type="text" name="search_owner" class="form-control" 
                                   placeholder="Imię, nazwisko lub PESEL" 
                                   value="<?php echo htmlspecialchars($search_owner); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status poszukiwania</label>
                            <select name="search_status" class="form-control">
                                <option value="">Wszystkie</option>
                                <option value="POSZUKIWANY" <?php echo $search_status === 'POSZUKIWANY' ? 'selected' : ''; ?>>Poszukiwane</option>
                                <option value="NIE_POSZUKIWANY" <?php echo $search_status === 'NIE_POSZUKIWANY' ? 'selected' : ''; ?>>Nie poszukiwane</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary">Szukaj</button>
                        <a href="?" class="btn btn-outline">Wyczyść filtry</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="vehicles-table-container">
            <div class="table-header">
                <h3>Lista pojazdów</h3>
                <div class="table-stats">
                    Znaleziono: <strong><?php echo count($vehicles); ?></strong> pojazdów
                </div>
            </div>
            
            <?php if (empty($vehicles)): ?>
                <div class="empty-state">
                    <div class="empty-state-title">Nie znaleziono pojazdów</div>
                    <div class="empty-state-description">
                        Spróbuj zmienić kryteria wyszukiwania lub wyczyść filtry, aby zobaczyć wszystkie pojazdy.
                    </div>
                </div>
            <?php else: ?>
                <table class="vehicles-table">
                    <thead>
                        <tr>
                            <th>Zdjęcie</th>
                            <th>Indeks Stanowy</th>
                            <th>Pojazd</th>
                            <th>Właściciel</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $vehicle): ?>
                        <tr data-vehicle-id="<?php echo $vehicle['id']; ?>" class="vehicle-row" style="cursor: pointer;">
                            <td>
                                <?php 
                                $has_file_photo = !empty($vehicle['zdjecie_filename']) && file_exists('uploads/vehicles/' . $vehicle['zdjecie_filename']);
                                $has_db_photo = !empty($vehicle['zdjecie_size']) && $vehicle['zdjecie_size'] > 0;
                                ?>
                                <?php if ($has_file_photo): ?>
                                    <img src="uploads/vehicles/<?php echo htmlspecialchars($vehicle['zdjecie_filename']); ?>" 
                                         alt="Zdjęcie pojazdu" class="vehicle-photo">
                                <?php elseif ($has_db_photo): ?>
                                    <?php
                                    try {
                                        $stmt_photo = $pdo->prepare("SELECT zdjecie, zdjecie_mime FROM pojazdy WHERE id = ? LIMIT 1");
                                        $stmt_photo->execute([$vehicle['id']]);
                                        $photo_data = $stmt_photo->fetch(PDO::FETCH_ASSOC);
                                        if ($photo_data && $photo_data['zdjecie']):
                                    ?>
                                        <img src="data:<?php echo htmlspecialchars($photo_data['zdjecie_mime']); ?>;base64,<?php echo base64_encode($photo_data['zdjecie']); ?>" 
                                             alt="Zdjęcie pojazdu" class="vehicle-photo">
                                    <?php 
                                        else:
                                    ?>
                                        <div class="no-photo">Brak zdjęcia</div>
                                    <?php 
                                        endif;
                                    } catch (Exception $e) {
                                        error_log("Photo display error: " . $e->getMessage());
                                    ?>
                                        <div class="no-photo">Błąd zdjęcia</div>
                                    <?php } ?>
                                <?php else: ?>
                                    <div class="no-photo">Brak zdjęcia</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="vehicle-reg"><?php echo htmlspecialchars($vehicle['rejestracja']); ?></div>
                                <div class="vehicle-type"><?php echo htmlspecialchars($vehicle['typ_pojazdu']); ?></div>
                            </td>
                            <td>
                                <div class="vehicle-details">
                                    <?php echo htmlspecialchars($vehicle['marka'] . ' ' . $vehicle['model']); ?>
                                </div>
                                <div class="vehicle-year-color">
                                    <?php echo htmlspecialchars($vehicle['rocznik'] . ' • ' . $vehicle['kolor']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="owner-name">
                                    <?php echo htmlspecialchars($vehicle['wlasciciel_imie'] . ' ' . $vehicle['wlasciciel_nazwisko']); ?>
                                </div>
                                <div class="owner-pesel">
                                    PESEL: <?php echo htmlspecialchars($vehicle['wlasciciel_pesel']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $vehicle['status_poszukiwania'] === 'POSZUKIWANY' ? 'status-wanted' : 'status-clear'; ?>">
                                    <?php echo $vehicle['status_poszukiwania'] === 'POSZUKIWANY' ? 'Poszukiwany' : 'Nie poszukiwany'; ?>
                                </span>
                                <?php if ($vehicle['aktywne_poszukiwania'] > 0): ?>
                                    <div style="font-size: 12px; color: #ef4444; margin-top: 4px; font-weight: 600;">
                                        <?php echo $vehicle['aktywne_poszukiwania']; ?> aktywnych poszukiwań
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div id="vehicleModal" class="modal-overlay">
        <div class="modal" style="width: 1000px;">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Szczegóły pojazdu</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-content" id="vehicleDetailsContent">
            </div>
        </div>
    </div>

    <div id="searchModal" class="modal-overlay">
        <div class="modal" style="width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title" id="searchModalTitle">Dodaj poszukiwanie</h2>
                <button class="modal-close" onclick="closeSearchModal()">×</button>
            </div>
            <div class="modal-content">
                <form id="searchForm">
                    <div class="form-group">
                        <label class="form-label">Powód poszukiwania *</label>
                        <input type="text" id="searchReason" class="form-control" required 
                               placeholder="np. Kradzież pojazdu, Przestępstwo drogowe...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Szczegółowy opis</label>
                        <textarea id="searchDescription" class="form-control" rows="3" 
                                  placeholder="Dodatkowe informacje o poszukiwaniu..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Priorytet</label>
                            <select id="searchPriority" class="form-control">
                                <option value="normalny">Normalny</option>
                                <option value="wysoki">Wysoki</option>
                                <option value="niski">Niski</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Funkcjonariusz *</label>
                            <input type="text" id="searchOfficer" class="form-control" required
                                   value="<?php echo htmlspecialchars($current_user['full_name']); ?>">
                        </div>
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Dodaj poszukiwanie</button>
                        <button type="button" class="btn btn-outline" onclick="closeSearchModal()">Anuluj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="noteModal" class="modal-overlay">
        <div class="modal" style="width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title">Dodaj notatkę</h2>
                <button class="modal-close" onclick="closeNoteModal()">×</button>
            </div>
            <div class="modal-content">
                <form id="noteForm">
                    <div class="form-group">
                        <label class="form-label">Tytuł notatki *</label>
                        <input type="text" id="noteTitle" class="form-control" required 
                               placeholder="Krótki tytuł notatki...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Treść notatki *</label>
                        <textarea id="noteContent" class="form-control" rows="4" required 
                                  placeholder="Szczegółowe informacje..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Funkcjonariusz *</label>
                        <input type="text" id="noteOfficer" class="form-control" required
                               value="<?php echo htmlspecialchars($current_user['full_name']); ?>">
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Dodaj notatkę</button>
                        <button type="button" class="btn btn-outline" onclick="closeNoteModal()">Anuluj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="photoModal" class="modal-overlay">
        <div class="modal" style="width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">Dodaj/zmień zdjęcie pojazdu</h2>
                <button class="modal-close" onclick="closePhotoModal()">×</button>
            </div>
            <div class="modal-content">
                <form id="photoForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Wybierz zdjęcie *</label>
                        <input type="file" id="photoInput" class="form-control" accept="image/*" required>
                        <small style="color: #64748b; margin-top: 8px; display: block;">
                            Dozwolone formaty: JPG, PNG, GIF, WebP. Maksymalny rozmiar: 5MB
                        </small>
                    </div>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Prześlij zdjęcie</button>
                        <button type="button" class="btn btn-outline" onclick="closePhotoModal()">Anuluj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal" style="width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">Potwierdź usunięcie</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-content">
                <p style="margin-bottom: 20px;">Czy na pewno chcesz usunąć ten wpis z historii?</p>
                <div class="form-group">
                    <label class="form-label">Powód usunięcia *</label>
                    <textarea id="deleteReason" class="form-control" rows="3" required 
                              placeholder="Wyjaśnij dlaczego usuwasz ten wpis..."></textarea>
                </div>
                <div class="action-buttons">
                    <button type="button" class="btn" style="background: #ef4444; color: white;" onclick="confirmDelete()">Usuń wpis</button>
                    <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Anuluj</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentVehicleId = null;
        let currentVehicleData = null;
        let currentDeleteItemId = null;
        let currentDeleteType = null;
        const userIsAdmin = <?php echo json_encode($is_admin); ?>;
        const userCanEdit = <?php echo json_encode($can_edit); ?>;
        
        console.log('System loaded. Click F12 -> Console to see debug info.');
        
        const vehicleRows = document.querySelectorAll('.vehicle-row');
        console.log('Found vehicle rows:', vehicleRows.length);
        
        function showVehicleDetails(vehicleId) {
            console.log('Kliknięto pojazd ID:', vehicleId);
            currentVehicleId = vehicleId;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_vehicle&id=${vehicleId}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        currentVehicleData = data.vehicle;
                        displayVehicleDetails(data.vehicle);
                        document.getElementById('vehicleModal').classList.add('show');
                        document.body.style.overflow = 'hidden';
                    } else {
                        alert('Błąd: ' + (data.message || 'Nie można pobrać danych pojazdu'));
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response was:', text);
                    alert('Błąd: Nieprawidłowa odpowiedź serwera');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('Wystąpił błąd podczas pobierania danych');
            });
        }
        
        function displayVehicleDetails(vehicle) {
            const photoSrc = vehicle.zdjecie_base64 || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkJyYWsgemRqxJljYTwvdGV4dD48L3N2Zz4=';
            
            const statusText = vehicle.status_poszukiwania === 'POSZUKIWANY' ? 'Poszukiwany' : 'Nie poszukiwany';
            const statusClass = vehicle.status_poszukiwania === 'POSZUKIWANY' ? 'status-wanted' : 'status-clear';
            
            document.getElementById('modalTitle').textContent = `${vehicle.marka} ${vehicle.model} (${vehicle.rejestracja})`;
            
            let historyHtml = '';
            if (vehicle.historia && vehicle.historia.length > 0) {
                vehicle.historia.forEach(item => {
                    const deleteButton = userIsAdmin ? `<button class="delete-btn" onclick="openDeleteModal(${item.id}, 'history')" title="Usuń wpis">×</button>` : '';
                    historyHtml += `
                        <div class="history-item">
                            ${deleteButton}
                            <div class="history-header">
                                <span class="history-type">${item.typ}</span>
                                <span class="history-date">${item.formatted_date}</span>
                            </div>
                            <div class="history-description">${item.opis}</div>
                            <div class="history-officer">Funkcjonariusz: ${item.funkcjonariusz}</div>
                        </div>
                    `;
                });
            } else {
                historyHtml = '<div class="empty-state"><div class="empty-state-title">Brak wpisów w historii</div></div>';
            }
            
            let searchesHtml = '';
            if (vehicle.poszukiwania && vehicle.poszukiwania.length > 0) {
                vehicle.poszukiwania.forEach(search => {
                    const endButton = userCanEdit ? `<button class="btn btn-sm" style="background: #10b981; color: white; margin-top: 12px;" onclick="openEndSearchModal(${search.id})">Zakończ poszukiwanie</button>` : '';
                    searchesHtml += `
                        <div class="search-item">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <strong>${search.powod}</strong>
                                <span class="search-priority priority-${search.priorytet}">${search.priorytet}</span>
                            </div>
                            ${search.opis ? `<div style="margin-bottom: 12px; color: #64748b;">${search.opis}</div>` : ''}
                            <div style="font-size: 13px; color: #64748b;">
                                Rozpoczęte: ${search.formatted_date} | Funkcjonariusz: ${search.funkcjonariusz}
                            </div>
                            ${endButton}
                        </div>
                    `;
                });
            } else {
                searchesHtml = '<div class="empty-state"><div class="empty-state-title">Brak aktywnych poszukiwań</div></div>';
            }
            
            const editButtons = userCanEdit ? `
                <button class="btn btn-primary" onclick="openSearchModal()">Dodaj poszukiwanie</button>
                <button class="btn btn-outline" onclick="openNoteModal()">Dodaj notatkę</button>
            ` : '';
            
            const content = `
                <div class="vehicle-detail-grid">
                    <div>
                        <img src="${photoSrc}" alt="Zdjęcie pojazdu" class="vehicle-photo-large">
                        ${userIsAdmin ? `
                        <div style="margin-top: 16px;">
                            <button class="btn btn-outline" onclick="openPhotoModal()" style="width: 100%;">Zmień zdjęcie</button>
                        </div>` : ''}
                    </div>
                    <div class="vehicle-info-grid">
                        <div class="info-item">
                            <div class="info-label">Indeks Stanowy</div>
                            <div class="info-value">${vehicle.rejestracja}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status poszukiwania</div>
                            <div class="info-value">
                                <span class="status-badge ${statusClass}">${statusText}</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Marka i Model</div>
                            <div class="info-value">${vehicle.marka} ${vehicle.model}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Typ pojazdu</div>
                            <div class="info-value">${vehicle.typ_pojazdu}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Rocznik</div>
                            <div class="info-value">${vehicle.rocznik || 'Nie podano'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Kolor</div>
                            <div class="info-value">${vehicle.kolor || 'Nie podano'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Właściciel</div>
                            <div class="info-value">${vehicle.wlasciciel_imie} ${vehicle.wlasciciel_nazwisko}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">PESEL</div>
                            <div class="info-value">${vehicle.wlasciciel_pesel}</div>
                        </div>
                        ${vehicle.uwagi ? `
                        <div class="info-item" style="grid-column: 1 / -1;">
                            <div class="info-label">Uwagi</div>
                            <div class="info-value">${vehicle.uwagi}</div>
                        </div>` : ''}
                    </div>
                </div>
                
                <div class="action-buttons">
                    ${editButtons}
                </div>
                
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('history')">Historia (${vehicle.historia_count})</button>
                    <button class="tab" onclick="switchTab('searches')">Poszukiwania (${vehicle.poszukiwania_count})</button>
                </div>
                
                <div id="historyTab" class="tab-content active">
                    ${historyHtml}
                </div>
                
                <div id="searchesTab" class="tab-content">
                    ${searchesHtml}
                </div>
            `;
            
            document.getElementById('vehicleDetailsContent').innerHTML = content;
        }
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + 'Tab').classList.add('active');
        }
        
        function openSearchModal() {
            if (!userCanEdit) {
                alert('Brak uprawnień do dodawania poszukiwań');
                return;
            }
            
            document.getElementById('searchModalTitle').textContent = 'Dodaj poszukiwanie';
            document.getElementById('searchForm').reset();
            document.getElementById('searchOfficer').value = '<?php echo htmlspecialchars($current_user['full_name']); ?>';
            
            const existingInput = document.querySelector('input[name="search_id"]');
            if (existingInput) {
                existingInput.remove();
            }
            
            document.getElementById('searchModal').classList.add('show');
        }
        
        function openEndSearchModal(searchId) {
            if (!userCanEdit) {
                alert('Brak uprawnień do kończenia poszukiwań');
                return;
            }
            
            document.getElementById('searchModalTitle').textContent = 'Zakończ poszukiwanie';
            document.getElementById('searchForm').reset();
            document.getElementById('searchOfficer').value = '<?php echo htmlspecialchars($current_user['full_name']); ?>';
            
            const existingInput = document.querySelector('input[name="search_id"]');
            if (existingInput) {
                existingInput.remove();
            }
            
            const searchIdInput = document.createElement('input');
            searchIdInput.type = 'hidden';
            searchIdInput.name = 'search_id';
            searchIdInput.value = searchId;
            document.getElementById('searchForm').appendChild(searchIdInput);
            
            document.querySelector('#searchForm .form-label').textContent = 'Powód zakończenia *';
            document.querySelector('#searchReason').placeholder = 'np. Pojazd odnaleziony, Pomyłka...';
            document.querySelector('#searchForm button[type="submit"]').textContent = 'Zakończ poszukiwanie';
            
            document.getElementById('searchDescription').closest('.form-group').style.display = 'none';
            document.getElementById('searchPriority').closest('.form-group').style.display = 'none';
            
            document.getElementById('searchModal').classList.add('show');
        }
        
        function closeSearchModal() {
            document.getElementById('searchModal').classList.remove('show');
            
            document.querySelector('#searchForm .form-label').textContent = 'Powód poszukiwania *';
            document.querySelector('#searchReason').placeholder = 'np. Kradzież pojazdu, Przestępstwo drogowe...';
            document.querySelector('#searchForm button[type="submit"]').textContent = 'Dodaj poszukiwanie';
            
            document.getElementById('searchDescription').closest('.form-group').style.display = 'block';
            document.getElementById('searchPriority').closest('.form-group').style.display = 'block';
        }
        
        function openNoteModal() {
            if (!userCanEdit) {
                alert('Brak uprawnień do dodawania notatek');
                return;
            }
            document.getElementById('noteForm').reset();
            document.getElementById('noteOfficer').value = '<?php echo htmlspecialchars($current_user['full_name']); ?>';
            document.getElementById('noteModal').classList.add('show');
        }
        
        function closeNoteModal() {
            document.getElementById('noteModal').classList.remove('show');
        }
        
        function openPhotoModal() {
            if (!userIsAdmin) {
                alert('Brak uprawnień do zmiany zdjęć');
                return;
            }
            document.getElementById('photoForm').reset();
            document.getElementById('photoModal').classList.add('show');
        }
        
        function closePhotoModal() {
            document.getElementById('photoModal').classList.remove('show');
        }

        function openDeleteModal(itemId, type) {
            currentDeleteItemId = itemId;
            currentDeleteType = type;
            document.getElementById('deleteModal').classList.add('show');
            document.getElementById('deleteReason').value = '';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            currentDeleteItemId = null;
            currentDeleteType = null;
        }
        
        function confirmDelete() {
            const reason = document.getElementById('deleteReason').value.trim();
            
            if (!reason) {
                alert('Powód usunięcia jest wymagany');
                return;
            }
            
            if (!currentDeleteItemId || !currentDeleteType) {
                alert('Błąd: Brak danych do usunięcia');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_history&history_id=${currentDeleteItemId}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeDeleteModal();
                    showSuccess(data.message);
                    setTimeout(() => {
                        showVehicleDetails(currentVehicleId);
                    }, 500);
                } else {
                    alert('Błąd: ' + (data.message || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas usuwania wpisu');
            });
        }
        
        function closeModal() {
            document.getElementById('vehicleModal').classList.remove('show');
            document.body.style.overflow = '';
            currentVehicleId = null;
            currentVehicleData = null;
        }
        
        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.classList.add('show');
            setTimeout(() => {
                successDiv.classList.remove('show');
            }, 4000);
        }
        
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const searchId = this.querySelector('input[name="search_id"]');
            const isEndingSearch = searchId && searchId.value;
            
            const formData = new FormData();
            formData.append('action', isEndingSearch ? 'end_search' : 'add_search');
            
            if (isEndingSearch) {
                formData.append('search_id', searchId.value);
                formData.append('reason', document.getElementById('searchReason').value);
                formData.append('officer', document.getElementById('searchOfficer').value);
            } else {
                formData.append('vehicle_id', currentVehicleId);
                formData.append('reason', document.getElementById('searchReason').value);
                formData.append('description', document.getElementById('searchDescription').value);
                formData.append('priority', document.getElementById('searchPriority').value);
                formData.append('officer', document.getElementById('searchOfficer').value);
            }
            
            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeSearchModal();
                    showSuccess(data.message);
                    setTimeout(() => showVehicleDetails(currentVehicleId), 500);
                } else {
                    alert('Błąd: ' + (data.message || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas przetwarzania poszukiwania');
            });
        });
        
        document.getElementById('noteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'add_note');
            formData.append('vehicle_id', currentVehicleId);
            formData.append('title', document.getElementById('noteTitle').value);
            formData.append('content', document.getElementById('noteContent').value);
            formData.append('officer', document.getElementById('noteOfficer').value);
            
            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeNoteModal();
                    showSuccess(data.message);
                    setTimeout(() => showVehicleDetails(currentVehicleId), 500);
                } else {
                    alert('Błąd: ' + (data.message || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas dodawania notatki');
            });
        });

        document.getElementById('photoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'upload_photo');
            formData.append('vehicle_id', currentVehicleId);
            
            const photoInput = document.getElementById('photoInput');
            if (photoInput.files.length === 0) {
                alert('Wybierz zdjęcie do przesłania');
                return;
            }
            
            formData.append('photo', photoInput.files[0]);
            
            fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closePhotoModal();
                    showSuccess(data.message);
                    setTimeout(() => showVehicleDetails(currentVehicleId), 500);
                } else {
                    alert('Błąd: ' + (data.message || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas przesyłania zdjęcia');
            });
        });
        
        document.addEventListener('click', function(e) {
            const vehicleRow = e.target.closest('.vehicle-row');
            if (vehicleRow) {
                const vehicleId = vehicleRow.getAttribute('data-vehicle-id');
                if (vehicleId) {
                    showVehicleDetails(parseInt(vehicleId));
                }
            }
            
            if (e.target.classList.contains('modal-overlay')) {
                if (e.target.id === 'vehicleModal') closeModal();
                if (e.target.id === 'searchModal') closeSearchModal();
                if (e.target.id === 'noteModal') closeNoteModal();
                if (e.target.id === 'photoModal') closePhotoModal();
                if (e.target.id === 'deleteModal') closeDeleteModal();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('searchModal').classList.contains('show')) closeSearchModal();
                else if (document.getElementById('noteModal').classList.contains('show')) closeNoteModal();
                else if (document.getElementById('photoModal').classList.contains('show')) closePhotoModal();
                else if (document.getElementById('deleteModal').classList.contains('show')) closeDeleteModal();
                else if (document.getElementById('vehicleModal').classList.contains('show')) closeModal();
            }
        });
        
        console.log('Vehicle management system loaded successfully');
        console.log('User is admin:', userIsAdmin);
        console.log('User can edit:', userCanEdit);
    </script>
</body>
</html>