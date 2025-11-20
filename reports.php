<?php
// reports.php - Aplikacja raportów policyjnych w nowym stylu

// Sprawdź czy config.php istnieje
if (!file_exists('config.php')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Brak pliku config.php']);
        exit;
    }
} else {
    require_once 'config.php';
}

// Sprawdzenie autoryzacji - tylko jeśli funkcja istnieje
if (function_exists('requireAuth')) {
    requireAuth();
} else {
    // Jeśli brak funkcji requireAuth, utwórz dummy user
    if (!isset($_SESSION)) {
        session_start();
    }
    if (!isset($_SESSION['user_data'])) {
        $_SESSION['user_data'] = [
            'name' => 'Test User',
            'badge' => 'TEST001',
            'role' => 'admin'
        ];
    }
}

// Wyczyść wszelkie output buffery i błędy przed AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Wyczyść output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Wyłącz error reporting dla AJAX - TYMCZASOWO WŁĄCZONE
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    header('Content-Type: application/json');
}

// System uprawnień - dodane bez zmiany oryginalnego kodu
function canDeleteReport($user) {
    return isset($user['role']) && $user['role'] === 'admin';
}

function canChangeStatus($user) {
    return isset($user['role']) && $user['role'] === 'admin';
}

function canEditReport($user, $report = null) {
    if (isset($user['role']) && $user['role'] === 'admin') {
        return true;
    }
    
    if ($report && isset($report['officer_name']) && isset($user['name'])) {
        return $report['officer_name'] === $user['name'];
    }
    
    return true; // Domyślnie pozwól edytować
}

function canCreateReport($user) {
    $role = isset($user['role']) ? $user['role'] : 'officer';
    return in_array($role, ['admin', 'officer']);
}

function getAvailableDepartments() {
    return [
        'DTU' => 'Drogówka (DTU)',
        'SWAT' => 'SWAT', 
        'PATROL' => 'Patrol',
        'CRIMINAL' => 'Kryminalna'
    ];
}

// Szablony raportów
$report_templates = [
    'traffic' => [
        'title' => 'Wykroczenie drogowe',
        'incident_type' => 'Traffic',
        'priority' => 'Medium',
        'content' => 'Wykroczenie drogowe - szczegóły zdarzenia:

1. CZAS I MIEJSCE:
   - Data: [DATA]
   - Godzina: [GODZINA]
   - Lokalizacja: [ADRES/LOKALIZACJA]

2. POJAZD:
   - Marka i model: [MARKA MODEL]
   - Numer rejestracyjny: [NUMER]
   - Kolor: [KOLOR]

3. KIERUJĄCY:
   - Imię i nazwisko: [IMIĘ NAZWISKO]
   - PESEL: [PESEL]
   - Adres: [ADRES]
   - Prawo jazdy nr: [NUMER PJ]

4. RODZAJ WYKROCZENIA:
   - Opis: [OPIS WYKROCZENIA]
   - Podstawa prawna: [ARTYKUŁ]

5. OKOLICZNOŚCI:
   [SZCZEGÓŁOWY OPIS ZDARZENIA]

6. DZIAŁANIA PODJĘTE:
   - Mandat karny: [KWOTA/NIE]
   - Punkty karne: [LICZBA]
   - Inne sankcje: [OPIS]'
    ],
    'domestic' => [
        'title' => 'Interwencja domowa',
        'incident_type' => 'Domestic',
        'priority' => 'High',
        'content' => 'Interwencja domowa - raport zdarzenia:

1. PODSTAWOWE INFORMACJE:
   - Data i godzina zgłoszenia: [DATA GODZINA]
   - Data i godzina przyjazdu: [DATA GODZINA]
   - Adres interwencji: [ADRES]

2. ZGŁASZAJĄCY:
   - Imię i nazwisko: [IMIĘ NAZWISKO]
   - Relacja do stron: [RELACJA]
   - Kontakt: [TELEFON]

3. STRONY KONFLIKTU:
   Strona A:
   - Imię i nazwisko: [IMIĘ NAZWISKO]
   - PESEL: [PESEL]
   - Adres: [ADRES]
   
   Strona B:
   - Imię i nazwisko: [IMIĘ NAZWISKO]
   - PESEL: [PESEL]
   - Adres: [ADRES]

4. PRZEBIEG INTERWENCJI:
   [SZCZEGÓŁOWY OPIS ZDARZENIA I PODJĘTYCH DZIAŁAŃ]

5. OBRAŻENIA/ZNISZCZENIA:
   [OPIS EWENTUALNYCH OBRAŻEŃ LUB ZNISZCZEŃ]

6. DZIAŁANIA PODJĘTE:
   - Pouczenie: [TAK/NIE]
   - Nakaz opuszczenia mieszkania: [TAK/NIE]
   - Zatrzymanie: [TAK/NIE - KOGO]
   - Wezwanie służb medycznych: [TAK/NIE]

7. ZALECENIA:
   [ZALECENIA I DALSZE KROKI]'
    ],
    'criminal' => [
        'title' => 'Przestępstwo kryminalne',
        'incident_type' => 'Criminal',
        'priority' => 'High',
        'content' => 'Raport przestępstwa kryminalnego:

1. PODSTAWOWE DANE:
   - Rodzaj przestępstwa: [RODZAJ]
   - Kwalifikacja prawna: [ARTYKUŁ KK]
   - Data i godzina zdarzenia: [DATA GODZINA]
   - Miejsce zdarzenia: [DOKŁADNY ADRES]

2. POKRZYWDZONY:
   - Imię i nazwisko: [IMIĘ NAZWISKO]
   - PESEL: [PESEL]
   - Adres: [ADRES]
   - Kontakt: [TELEFON]

3. PODEJRZANY (jeśli ustalony):
   - Imię i nazwisko: [IMIĘ NAZWISKO / NIEUSTALONY]
   - PESEL: [PESEL]
   - Adres: [ADRES]
   - Rysopis: [OPIS WYGLĄDU]

4. PRZEBIEG ZDARZENIA:
   [SZCZEGÓŁOWY CHRONOLOGICZNY OPIS ZDARZENIA]

5. SZKODY/STRATY:
   - Rodzaj: [OPIS]
   - Wartość: [KWOTA W ZŁ]

6. DOWODY ZABEZPIECZONE:
   [LISTA ZABEZPIECZONYCH DOWODÓW]

7. CZYNNOŚCI WYKONANE:
   - Oględziny miejsca zdarzenia: [TAK/NIE]
   - Przesłuchanie świadków: [LICZBA]
   - Zabezpieczenie śladów: [TAK/NIE]
   - Powiadomienie prokuratora: [TAK/NIE]

8. WNIOSKI:
   [WNIOSKI I ZALECENIA CO DO DALSZEGO POSTĘPOWANIA]'
    ]
];

// Pobierz dane użytkownika
$user = $_SESSION['user_data'];

// FUNKCJA: Sprawdź i utwórz kolumnę images w tabeli police_reports
function ensureImagesColumn($pdo) {
    if (!$pdo) return false;
    
    try {
        // Sprawdź czy kolumna images istnieje
        $stmt = $pdo->query("SHOW COLUMNS FROM police_reports LIKE 'images'");
        if ($stmt->rowCount() == 0) {
            // Dodaj kolumnę images
            $pdo->exec("ALTER TABLE police_reports ADD COLUMN images TEXT DEFAULT '[]'");
            error_log("Dodano kolumnę images do tabeli police_reports");
        }
        return true;
    } catch (Exception $e) {
        error_log("Błąd przy sprawdzaniu/tworzeniu kolumny images: " . $e->getMessage());
        return false;
    }
}

// Funkcja pomocnicza do czystego JSON
function jsonOutput($data) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Obsługa AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Start output buffering
    ob_start();
    
    try {
        // Bezpieczne sprawdzenie getDB
        $pdo = null;
        if (function_exists('getDB')) {
            try {
                $pdo = getDB();
            } catch (Exception $e) {
                $pdo = null; // Jeśli błąd bazy, pracuj w trybie demo
            }
        }
        
        switch ($_POST['action']) {
            case 'upload_image':
                try {
                    // Debug info
                    error_log("Upload attempt - FILES: " . print_r($_FILES, true));
                    
                    if (!isset($_FILES['image'])) {
                        jsonOutput(['success' => false, 'error' => 'Brak pliku w żądaniu']);
                    }
                    
                    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                        $errors = [
                            UPLOAD_ERR_INI_SIZE => 'Plik za duży (limit PHP)',
                            UPLOAD_ERR_FORM_SIZE => 'Plik za duży (limit formularza)', 
                            UPLOAD_ERR_PARTIAL => 'Plik przesłany częściowo',
                            UPLOAD_ERR_NO_FILE => 'Nie wybrano pliku',
                            UPLOAD_ERR_NO_TMP_DIR => 'Brak folderu tymczasowego',
                            UPLOAD_ERR_CANT_WRITE => 'Błąd zapisu na dysk',
                            UPLOAD_ERR_EXTENSION => 'Blokada przez rozszerzenie PHP'
                        ];
                        $error = $errors[$_FILES['image']['error']] ?? 'Nieznany błąd: ' . $_FILES['image']['error'];
                        error_log("Upload error: " . $error);
                        jsonOutput(['success' => false, 'error' => $error]);
                    }
                    
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $filename = $_FILES['image']['name'];
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    
                    if (!in_array($extension, $allowed)) {
                        jsonOutput(['success' => false, 'error' => 'Niedozwolony format: ' . $extension . '. Dozwolone: ' . implode(', ', $allowed)]);
                    }
                    
                    if ($_FILES['image']['size'] > 10 * 1024 * 1024) {
                        jsonOutput(['success' => false, 'error' => 'Plik za duży: ' . round($_FILES['image']['size'] / 1024 / 1024, 2) . 'MB (max 10MB)']);
                    }
                    
                    // Użyj bezwzględnej ścieżki - FOLDER REPO zgodnie z życzeniem
                    $uploadDir = __DIR__ . '/uploads/repo/';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            error_log("Cannot create directory: " . $uploadDir);
                            jsonOutput(['success' => false, 'error' => 'Nie można utworzyć folderu uploads']);
                        }
                        chmod($uploadDir, 0755);
                    }
                    
                    if (!is_writable($uploadDir)) {
                        error_log("Directory not writable: " . $uploadDir);
                        jsonOutput(['success' => false, 'error' => 'Folder uploads nie ma uprawnień do zapisu']);
                    }
                    
                    $newFilename = 'img_' . uniqid() . '_' . time() . '.' . $extension;
                    $uploadPath = $uploadDir . $newFilename;
                    
                    error_log("Trying to move file to: " . $uploadPath);
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                        if (file_exists($uploadPath)) {
                            $imageData = [
                                'filename' => $newFilename,
                                'original_name' => $filename,
                                'path' => $uploadPath,
                                'size' => $_FILES['image']['size'],
                                'url' => 'uploads/repo/' . $newFilename
                            ];
                            error_log("Upload successful: " . $uploadPath);
                            jsonOutput(['success' => true, 'image' => $imageData]);
                        } else {
                            error_log("File moved but doesn't exist: " . $uploadPath);
                            jsonOutput(['success' => false, 'error' => 'Plik przeniesiony ale nie istnieje']);
                        }
                    } else {
                        $error = 'Błąd move_uploaded_file';
                        if (!is_uploaded_file($_FILES['image']['tmp_name'])) {
                            $error .= ' - plik nie jest uploaded file';
                        }
                        if (!is_writable(dirname($uploadPath))) {
                            $error .= ' - katalog docelowy nie jest zapisywalny';
                        }
                        error_log("Move failed: " . $error);
                        jsonOutput(['success' => false, 'error' => $error]);
                    }
                } catch (Exception $e) {
                    error_log("Upload exception: " . $e->getMessage());
                    jsonOutput(['success' => false, 'error' => 'Błąd serwera: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_reports':
                if ($pdo) {
                    // Sprawdź i utwórz kolumnę images
                    ensureImagesColumn($pdo);
                    
                    // Sprawdź jakie tabele istnieją
                    try {
                        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        
                        $table_name = 'reports'; // Domyślna nazwa
                        if (in_array('police_reports', $tables)) {
                            $table_name = 'police_reports';
                        } elseif (in_array('reports', $tables)) {
                            $table_name = 'reports';
                        }
                        
                        // Sprawdź dostępne kolumny - DODANO IMAGES
                        $columns = "id, title, officer_name, officer_badge, incident_date, 
                                   incident_type, priority, status, created_at";
                        
                        $available_columns = $pdo->query("SHOW COLUMNS FROM $table_name")->fetchAll(PDO::FETCH_COLUMN);
                        
                        if (in_array('incident_time', $available_columns)) {
                            $columns .= ", incident_time";
                        }
                        if (in_array('target_departments', $available_columns)) {
                            $columns .= ", target_departments";
                        }
                        if (in_array('images', $available_columns)) {
                            $columns .= ", images";
                        }
                        
                        $stmt = $pdo->query("SELECT $columns FROM $table_name ORDER BY created_at DESC");
                        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Upewnij się że każdy raport ma pole images
                        foreach ($reports as &$report) {
                            if (!isset($report['images']) || $report['images'] === null) {
                                $report['images'] = '[]';
                            }
                        }
                        
                    } catch (Exception $e) {
                        // Jeśli błąd bazy, użyj demo data
                        $reports = [
                            [
                                'id' => 1,
                                'title' => 'Wykroczenie drogowe - przekroczenie prędkości',
                                'officer_name' => $user['name'],
                                'officer_badge' => $user['badge'] ?? 'PL001',
                                'incident_date' => date('Y-m-d'),
                                'incident_time' => '14:30',
                                'incident_type' => 'Traffic',
                                'priority' => 'Medium',
                                'status' => 'Draft',
                                'images' => '[]',
                                'created_at' => date('Y-m-d H:i:s')
                            ],
                            [
                                'id' => 2,
                                'title' => 'Zakłócenie porządku publicznego',
                                'officer_name' => $user['name'],
                                'officer_badge' => $user['badge'] ?? 'PL001',
                                'incident_date' => date('Y-m-d', strtotime('-1 day')),
                                'incident_time' => '22:15',
                                'incident_type' => 'Public Order',
                                'priority' => 'High',
                                'status' => 'Under Review',
                                'images' => '[]',
                                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                            ]
                        ];
                    }
                } else {
                    // Demo data fallback
                    $reports = [
                        [
                            'id' => 1,
                            'title' => 'Wykroczenie drogowe - przekroczenie prędkości',
                            'officer_name' => $user['name'],
                            'officer_badge' => $user['badge'] ?? 'PL001',
                            'incident_date' => date('Y-m-d'),
                            'incident_time' => '14:30',
                            'incident_type' => 'Traffic',
                            'priority' => 'Medium',
                            'status' => 'Draft',
                            'created_at' => date('Y-m-d H:i:s')
                        ],
                        [
                            'id' => 2,
                            'title' => 'Zakłócenie porządku publicznego',
                            'officer_name' => $user['name'],
                            'officer_badge' => $user['badge'] ?? 'PL001',
                            'incident_date' => date('Y-m-d', strtotime('-1 day')),
                            'incident_time' => '22:15',
                            'incident_type' => 'Public Order',
                            'priority' => 'High',
                            'status' => 'Under Review',
                            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
                        ]
                    ];
                }
                jsonOutput(['success' => true, 'reports' => $reports]);
                break;
                
            case 'get_report':
                $id = intval($_POST['id']);
                if ($pdo) {
                    // Sprawdź i utwórz kolumnę images
                    ensureImagesColumn($pdo);
                    
                    try {
                        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        $table_name = in_array('police_reports', $tables) ? 'police_reports' : 'reports';
                        
                        $stmt = $pdo->prepare("SELECT * FROM $table_name WHERE id = ?");
                        $stmt->execute([$id]);
                        $report = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$report) {
                            jsonOutput(['success' => false, 'error' => 'Raport nie został znaleziony']);
                            exit;
                        }
                        
                        // Upewnij się że images istnieje
                        if (!isset($report['images']) || $report['images'] === null) {
                            $report['images'] = '[]';
                        }
                        
                    } catch (Exception $e) {
                        $report = [
                            'id' => $id,
                            'title' => 'Przykładowy raport #' . $id,
                            'officer_name' => $user['name'],
                            'officer_badge' => $user['badge'] ?? 'PL001',
                            'incident_date' => date('Y-m-d'),
                            'incident_time' => '14:30',
                            'location' => 'ul. Przykładowa 123, Warszawa',
                            'incident_type' => 'Traffic',
                            'priority' => 'Medium',
                            'status' => 'Draft',
                            'report_content' => 'Szczegółowy opis zdarzenia...',
                            'evidence_description' => '',
                            'witnesses' => '',
                            'suspect_info' => '',
                            'victim_info' => '',
                            'follow_up_required' => 0,
                            'follow_up_notes' => '',
                            'images' => '[]',
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                    }
                } else {
                    $report = [
                        'id' => $id,
                        'title' => 'Przykładowy raport #' . $id,
                        'officer_name' => $user['name'],
                        'officer_badge' => $user['badge'] ?? 'PL001',
                        'incident_date' => date('Y-m-d'),
                        'incident_time' => '14:30',
                        'location' => 'ul. Przykładowa 123, Warszawa',
                        'incident_type' => 'Traffic',
                        'priority' => 'Medium',
                        'status' => 'Draft',
                        'report_content' => 'Szczegółowy opis zdarzenia...',
                        'evidence_description' => '',
                        'witnesses' => '',
                        'suspect_info' => '',
                        'victim_info' => '',
                        'follow_up_required' => 0,
                        'follow_up_notes' => '',
                        'images' => '[]',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
                
                jsonOutput(['success' => true, 'report' => $report]);
                break;
                
            case 'save_report':
                // Sprawdź uprawnienia do tworzenia/edycji
                if (!canCreateReport($user) && !isset($_POST['id'])) {
                    jsonOutput(['success' => false, 'error' => 'Brak uprawnień do tworzenia raportów']);
                    break;
                }
                
                if (isset($_POST['id'])) {
                    $report_id = intval($_POST['id']);
                    if ($pdo) {
                        try {
                            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                            $table_name = in_array('police_reports', $tables) ? 'police_reports' : 'reports';
                            
                            $stmt = $pdo->prepare("SELECT * FROM $table_name WHERE id = ?");
                            $stmt->execute([$report_id]);
                            $existing_report = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!canEditReport($user, $existing_report)) {
                                jsonOutput(['success' => false, 'error' => 'Brak uprawnień do edycji tego raportu']);
                                break;
                            }
                        } catch (Exception $e) {
                            // Ignore database errors for permissions check
                        }
                    }
                }
                
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'officer_name' => $user['name'],
                    'officer_badge' => $user['badge'] ?? '',
                    'incident_date' => $_POST['incident_date'] ?? date('Y-m-d'),
                    'location' => $_POST['location'] ?? '',
                    'incident_type' => $_POST['incident_type'] ?? '',
                    'priority' => $_POST['priority'] ?? 'Medium',
                    'status' => $_POST['status'] ?? 'Draft',
                    'report_content' => $_POST['report_content'] ?? '',
                    'evidence_description' => $_POST['evidence_description'] ?? '',
                    'witnesses' => $_POST['witnesses'] ?? '',
                    'suspect_info' => $_POST['suspect_info'] ?? '',
                    'victim_info' => $_POST['victim_info'] ?? '',
                    'follow_up_required' => isset($_POST['follow_up_required']) ? 1 : 0,
                    'follow_up_notes' => $_POST['follow_up_notes'] ?? '',
                    'images' => $_POST['images'] ?? '[]'  // DODANE: obsługa images z MySQL
                ];
                
                // Dodaj incident_time tylko jeśli nie jest puste
                if (!empty($_POST['incident_time'])) {
                    $data['incident_time'] = $_POST['incident_time'];
                }
                
                if (isset($_POST['id'])) {
                    // Update
                    $id = intval($_POST['id']);
                    if ($pdo) {
                        // Sprawdź i utwórz kolumnę images
                        ensureImagesColumn($pdo);
                        
                        try {
                            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                            $table_name = in_array('police_reports', $tables) ? 'police_reports' : 'reports';
                            
                            $sql = "UPDATE $table_name SET ";
                            $updates = [];
                            $values = [];
                            foreach ($data as $key => $value) {
                                $updates[] = "$key = ?";
                                $values[] = $value;
                            }
                            $sql .= implode(', ', $updates) . " WHERE id = ?";
                            $values[] = $id;
                            
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($values);
                            error_log("✅ Updated report ID $id with images: " . substr($_POST['images'] ?? '[]', 0, 100));
                        } catch (Exception $e) {
                            error_log("❌ Database update error: " . $e->getMessage());
                        }
                    }
                    
                    jsonOutput(['success' => true, 'id' => $id]);
                } else {
                    // Insert
                    if ($pdo) {
                        // Sprawdź i utwórz kolumnę images
                        ensureImagesColumn($pdo);
                        
                        try {
                            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                            $table_name = in_array('police_reports', $tables) ? 'police_reports' : 'reports';
                            
                            $columns = implode(', ', array_keys($data));
                            $placeholders = str_repeat('?,', count($data) - 1) . '?';
                            $sql = "INSERT INTO $table_name ($columns) VALUES ($placeholders)";
                            
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute(array_values($data));
                            $id = $pdo->lastInsertId();
                            error_log("✅ Created report ID $id with images: " . substr($_POST['images'] ?? '[]', 0, 100));
                        } catch (Exception $e) {
                            error_log("❌ Database insert error: " . $e->getMessage());
                            $id = rand(1000, 9999); // Random ID for demo
                        }
                    } else {
                        $id = rand(1000, 9999);
                    }
                    
                    jsonOutput(['success' => true, 'id' => $id]);
                }
                break;
                
            case 'delete_report':
                if (!canDeleteReport($user)) {
                    jsonOutput(['success' => false, 'error' => 'Brak uprawnień do usuwania raportów']);
                    break;
                }
                
                $id = intval($_POST['id']);
                if ($pdo) {
                    try {
                        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        $table_name = in_array('police_reports', $tables) ? 'police_reports' : 'reports';
                        
                        $stmt = $pdo->prepare("DELETE FROM $table_name WHERE id = ?");
                        $stmt->execute([$id]);
                    } catch (Exception $e) {
                        // Ignore database errors
                    }
                }
                jsonOutput(['success' => true]);
                break;
                
            case 'change_status':
                if (!canChangeStatus($user)) {
                    jsonOutput(['success' => false, 'error' => 'Brak uprawnień do zmiany statusu']);
                    break;
                }
                
                $id = intval($_POST['id']);
                $new_status = $_POST['status'];
                if ($pdo) {
                    try {
                        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        $table_name = in_array('police_reports', $tables) ? 'police_reports' : 'reports';
                        
                        $stmt = $pdo->prepare("UPDATE $table_name SET status = ? WHERE id = ?");
                        $stmt->execute([$new_status, $id]);
                    } catch (Exception $e) {
                        // Ignore database errors
                    }
                }
                jsonOutput(['success' => true]);
                break;
        }
        
    } catch (Exception $e) {
        // Wyczyść wszelkie output
        while (ob_get_level()) {
            ob_end_clean();
        }
        jsonOutput(['success' => false, 'error' => 'PHP Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    } catch (Error $e) {
        // Wyczyść wszelkie output  
        while (ob_get_level()) {
            ob_end_clean();
        }
        jsonOutput(['success' => false, 'error' => 'PHP Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Raportów Policyjnych</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            color: #0f172a;
        }

        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .user-info .badge {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .filters {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            min-width: 140px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .report-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .report-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
            border-color: #3b82f6;
        }

        .report-header {
            padding: 24px 24px 16px 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-bottom: 1px solid #e2e8f0;
        }

        .report-title-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .report-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            line-height: 1.4;
            flex: 1;
            margin-right: 16px;
        }

        .report-status {
            flex-shrink: 0;
        }

        .report-meta {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .meta-badge,
        .meta-date {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #64748b;
            background: white;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .report-body {
            padding: 24px;
        }

        .report-details {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .detail-row .full-width {
            grid-column: 1 / -1;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 32px;
        }

        .detail-item.full-width {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            min-width: 80px;
            flex-shrink: 0;
        }

        .detail-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-draft { 
            background: linear-gradient(135deg, #fef3c7, #fde68a); 
            color: #92400e; 
            border: 1px solid #f59e0b;
        }
        .status-under-review { 
            background: linear-gradient(135deg, #dbeafe, #bfdbfe); 
            color: #1e40af; 
            border: 1px solid #3b82f6;
        }
        .status-approved { 
            background: linear-gradient(135deg, #dcfce7, #bbf7d0); 
            color: #166534; 
            border: 1px solid #22c55e;
        }
        .status-closed { 
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb); 
            color: #374151; 
            border: 1px solid #6b7280;
        }

        .priority-badge,
        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .priority-low { 
            background: #e0f2fe; 
            color: #0369a1; 
            border: 1px solid #0ea5e9;
        }
        .priority-medium { 
            background: #fef3c7; 
            color: #d97706; 
            border: 1px solid #f59e0b;
        }
        .priority-high { 
            background: #fee2e2; 
            color: #dc2626; 
            border: 1px solid #ef4444;
        }
        .priority-critical { 
            background: #fce7f3; 
            color: #be185d; 
            border: 1px solid #ec4899;
        }

        .type-traffic { background: #e0f2fe; color: #0369a1; border: 1px solid #0ea5e9; }
        .type-criminal { background: #fee2e2; color: #dc2626; border: 1px solid #ef4444; }
        .type-domestic { background: #fef3c7; color: #d97706; border: 1px solid #f59e0b; }
        .type-public-order { background: #e0e7ff; color: #4338ca; border: 1px solid #6366f1; }
        .type-investigation { background: #f3e8ff; color: #7c3aed; border: 1px solid #8b5cf6; }
        .type-administrative { background: #f0f9ff; color: #0284c7; border: 1px solid #0ea5e9; }
        .type-emergency { background: #fecaca; color: #b91c1c; border: 1px solid #dc2626; }
        .type-other { background: #f3f4f6; color: #374151; border: 1px solid #6b7280; }

        .report-actions {
            padding: 20px 24px;
            border-top: 1px solid #f1f5f9;
            background: #fafbfc;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-view {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .report-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .report-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #64748b;
        }

        .meta-item svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-draft { background: #fef3c7; color: #92400e; }
        .status-under-review { background: #dbeafe; color: #1e40af; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-closed { background: #f3f4f6; color: #374151; }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-low { background: #f0f9ff; color: #0369a1; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-critical { background: #fecaca; color: #991b1b; }

        .type-badge {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .type-traffic { background: #dbeafe; color: #1e40af; }
        .type-criminal { background: #fee2e2; color: #dc2626; }
        .type-domestic { background: #fef3c7; color: #92400e; }
        .type-public-order { background: #e0e7ff; color: #3730a3; }
        .type-investigation { background: #f3e8ff; color: #7c3aed; }
        .type-administrative { background: #f0f9ff; color: #0369a1; }
        .type-emergency { background: #fecaca; color: #991b1b; }
        .type-other { background: #f3f4f6; color: #374151; }

        .report-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .btn-action {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #475569;
        }

        .btn-action.primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .btn-action.primary:hover {
            background: #2563eb;
            border-color: #2563eb;
        }

        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            color: #64748b;
            font-size: 16px;
        }

        .loading::before {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            margin-right: 12px;
            animation: spin 1s linear infinite;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #fca5a5;
        }

        .view-section {
            margin-bottom: 24px;
        }

        .view-section h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f3f4f6;
        }

        .view-section-content {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #3b82f6;
        }

        .view-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .view-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .view-info-label {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .view-info-value {
            font-size: 14px;
            color: #1f2937;
            font-weight: 500;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .message {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .message svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            padding: 4px;
            border-radius: 4px;
        }

        .close-btn:hover {
            background: #f1f5f9;
            color: #475569;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label,
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea,
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus,
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group textarea,
        .form-control[rows] {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .template-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .template-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        .template-icon {
            width: 48px;
            height: 48px;
            background: #3b82f6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px auto;
        }

        .template-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .template-description {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .template-selection {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .template-selection.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Rich text editor */
        .editor-toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
        }

        .editor-btn {
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            color: #374151;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .editor-btn:hover {
            background: #f3f4f6;
        }

        .editor-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .editor-content {
            min-height: 200px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 8px 8px;
            background: white;
            line-height: 1.6;
        }

        .editor-content:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .message {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .message svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .message.success {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border: 1px solid #22c55e;
        }

        .message.error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #b91c1c;
            border: 1px solid #ef4444;
        }

        .message.info {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .message.warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        @media (max-width: 768px) {
            .form-row,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                flex: 1;
            }
        }

        /* Rich text editor */
        .editor-toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
        }

        .editor-btn {
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            color: #374151;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .editor-btn:hover {
            background: #f3f4f6;
        }

        .editor-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .editor-content {
            min-height: 200px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 8px 8px;
            background: white;
            line-height: 1.6;
        }

        .editor-content:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Template selection */
        .template-selection {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .template-selection.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .template-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .template-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        .template-card h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .template-card p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Image Upload Styles - KOMPLETNE */
        .image-upload-zone {
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            background: #f7fafc;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .image-upload-zone:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            transform: translateY(-2px);
        }

        .image-upload-zone.dragover {
            border-color: #2563eb;
            background: #dbeafe;
            transform: scale(1.02);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
        }

        .upload-icon {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        .upload-button {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .upload-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
        }

        .images-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .image-preview {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            aspect-ratio: 1;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .image-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .image-preview:hover img {
            transform: scale(1.1);
        }

        .remove-image {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            opacity: 0;
        }

        .image-preview:hover .remove-image {
            opacity: 1;
        }

        .remove-image:hover {
            background: #dc2626;
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.6);
        }

        .image-filename {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 8px 4px 4px;
            font-size: 10px;
            text-align: center;
            border-radius: 0 0 8px 8px;
            word-break: break-all;
            line-height: 1.2;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .image-preview:hover .image-filename {
            opacity: 1;
        }

        /* === LIGHTBOX DO POWIĘKSZANIA ZDJĘĆ === */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            backdrop-filter: blur(5px);
            cursor: pointer;
        }

        .lightbox.active {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: lightboxFadeIn 0.3s ease;
        }

        @keyframes lightboxFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .lightbox-content {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .lightbox-image {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: lightboxImageIn 0.4s ease;
        }

        @keyframes lightboxImageIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }

        .lightbox-info {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 25px;
            color: white;
            text-align: center;
            max-width: 80%;
        }

        .lightbox-filename {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .lightbox-size {
            font-size: 14px;
            opacity: 0.8;
        }

        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-nav:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-50%) scale(1.1);
        }

        .lightbox-prev {
            left: 30px;
        }

        .lightbox-next {
            right: 30px;
        }

        /* === LEPSZY WYGLĄD KART RAPORTÓW === */
        .report-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #06b6d4);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .report-card:hover {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transform: translateY(-4px);
            border-color: #3b82f6;
        }

        .report-card:hover::before {
            opacity: 1;
        }

        .report-card-images {
            padding: 0 20px 15px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .report-images-preview {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .report-images-preview::-webkit-scrollbar {
            height: 4px;
        }

        .report-images-preview::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 2px;
        }

        .report-images-preview::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 2px;
        }

        .report-image-thumb {
            flex-shrink: 0;
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .report-image-thumb:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .report-image-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .report-images-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }

        /* Responsive design improvements */
        @media (max-width: 640px) {
            .header {
                padding: 16px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                justify-content: center;
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 10px;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="reports-container">
        <div class="header">
            <h1>System Raportów Policyjnych</h1>
            <div class="user-info">
                <span>Oficer: <?= htmlspecialchars($user['name']) ?></span>
                <span class="badge"><?= htmlspecialchars($user['badge']) ?></span>
                <span>Rola: <?= htmlspecialchars($user['role']) ?></span>
            </div>
            <div class="controls">
                <button class="btn btn-secondary" onclick="loadReports()">
                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                        <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 8 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                    </svg>
                    Odśwież
                </button>
                <?php if (canCreateReport($user)): ?>
                <button class="btn btn-primary" onclick="openNewReportModal()">
                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    Nowy raport
                </button>
                <button class="btn btn-secondary" onclick="toggleTemplateSelection()">
                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                        <polyline points="14,2 14,8 20,8"/>
                    </svg>
                    Szablony
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Typ zdarzenia</label>
                    <select id="filterType" onchange="applyFilters()">
                        <option value="">Wszystkie</option>
                        <option value="Traffic">Drogowy</option>
                        <option value="Criminal">Kryminalny</option>
                        <option value="Domestic">Domowy</option>
                        <option value="Public Order">Porządek publiczny</option>
                        <option value="Investigation">Dochodzenie</option>
                        <option value="Administrative">Administracyjny</option>
                        <option value="Emergency">Nagły wypadek</option>
                        <option value="Other">Inny</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select id="filterStatus" onchange="applyFilters()">
                        <option value="">Wszystkie</option>
                        <option value="Draft">Szkic</option>
                        <option value="Under Review">W przeglądzie</option>
                        <option value="Approved">Zatwierdzony</option>
                        <option value="Closed">Zamknięty</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Priorytet</label>
                    <select id="filterPriority" onchange="applyFilters()">
                        <option value="">Wszystkie</option>
                        <option value="Low">Niski</option>
                        <option value="Medium">Średni</option>
                        <option value="High">Wysoki</option>
                        <option value="Critical">Krytyczny</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Wyszukaj</label>
                    <input type="text" id="searchInput" placeholder="Wyszukaj w tytule..." oninput="filterReports(this.value)">
                </div>
            </div>
        </div>

        <div id="reportsGrid" class="reports-grid">
            <div class="loading">Ładowanie raportów...</div>
        </div>
    </div>

    <!-- Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Nowy raport</h2>
                <button type="button" class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="reportForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tytuł raportu</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Oficer</label>
                            <input type="text" name="officer_name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Numer odznaki</label>
                            <input type="text" name="officer_badge" class="form-control" value="<?php echo htmlspecialchars($user['badge'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Data zdarzenia</label>
                            <input type="date" name="incident_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Godzina zdarzenia</label>
                            <input type="time" name="incident_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Lokalizacja</label>
                        <input type="text" name="location" class="form-control" placeholder="np. ul. Przykładowa 123, Warszawa" required>
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Typ zdarzenia</label>
                            <select name="incident_type" class="form-control" required>
                                <option value="Traffic">Drogowy</option>
                                <option value="Criminal">Kryminalny</option>
                                <option value="Domestic">Domowy</option>
                                <option value="Public Order">Porządek publiczny</option>
                                <option value="Investigation">Dochodzenie</option>
                                <option value="Administrative">Administracyjny</option>
                                <option value="Emergency">Nagły wypadek</option>
                                <option value="Other">Inny</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priorytet</label>
                            <select name="priority" class="form-control" required>
                                <option value="Low">Niski</option>
                                <option value="Medium" selected>Średni</option>
                                <option value="High">Wysoki</option>
                                <option value="Critical">Krytyczny</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control" required>
                                <?php if ($user['role'] === 'admin'): ?>
                                <option value="Draft">Szkic</option>
                                <option value="Under Review" selected>W przeglądu</option>
                                <option value="Approved">Zatwierdzony</option>
                                <option value="Closed">Zamknięty</option>
                                <?php else: ?>
                                <option value="Under Review" selected>W przeglądu</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Opis zdarzenia</label>
                        <div class="editor-toolbar">
                            <button type="button" class="editor-btn" onclick="formatText('bold')">
                                <strong>B</strong>
                            </button>
                            <button type="button" class="editor-btn" onclick="formatText('italic')">
                                <em>I</em>
                            </button>
                            <button type="button" class="editor-btn" onclick="formatText('underline')">
                                <u>U</u>
                            </button>
                            <button type="button" class="editor-btn" onclick="formatText('insertUnorderedList')">
                                • Lista
                            </button>
                            <button type="button" class="editor-btn" onclick="formatText('insertOrderedList')">
                                1. Lista
                            </button>
                        </div>
                        <div class="editor-content" contenteditable="true" id="report_content"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dowody / materiały</label>
                        <textarea name="evidence_description" class="form-control" rows="3" placeholder="Opis zabezpieczonych dowodów, materiałów..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Świadkowie</label>
                            <textarea name="witnesses" class="form-control" rows="3" placeholder="Lista świadków zdarzenia..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Informacje o podejrzanym</label>
                            <textarea name="suspect_info" class="form-control" rows="3" placeholder="Dane podejrzanego (jeśli dotyczy)..."></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Informacje o pokrzywdzonym</label>
                        <textarea name="victim_info" class="form-control" rows="3" placeholder="Dane pokrzywdzonego (jeśli dotyczy)..."></textarea>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="follow_up_required">
                            <span class="form-label" style="margin: 0;">Wymagane działania następcze</span>
                        </label>
                        <textarea name="follow_up_notes" class="form-control" rows="2" placeholder="Opis wymaganych działań następczych..." style="margin-top: 10px;"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">📸 Załącz zdjęcia z miejsca zdarzenia</label>
                        <div class="image-upload-zone" onclick="document.getElementById('imageInput').click()" 
                             ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                            <input type="file" id="imageInput" accept="image/*" multiple style="display: none;" onchange="handleImageUpload(event)">
                            
                            <div class="upload-icon">
                                <svg viewBox="0 0 24 24" style="width: 48px; height: 48px; fill: #3b82f6;">
                                    <path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/>
                                </svg>
                            </div>
                            
                            <h4 style="color: #374151; margin: 16px 0 8px 0; font-size: 16px; font-weight: 600;">📷 Dodaj zdjęcia z miejsca zdarzenia</h4>
                            <p style="color: #6b7280; margin-bottom: 20px; font-size: 14px;">
                                Przeciągnij pliki tutaj lub kliknij aby wybrać
                            </p>
                            
                            <button type="button" class="upload-button" onclick="document.getElementById('imageInput').click()">
                                <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                                    <path d="M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
                                </svg>
                                Wybierz zdjęcia
                            </button>
                            
                            <div style="font-size: 12px; color: #9ca3af; margin-top: 15px; text-align: center;">
                                <strong>Obsługiwane formaty:</strong> JPG, PNG, GIF, WebP<br>
                                <strong>Maksymalny rozmiar:</strong> 10MB na plik
                            </div>
                        </div>
                        
                        <div id="imagesPreview" class="images-preview">
                            <!-- Podgląd zdjęć zostanie dodany tutaj przez JavaScript -->
                        </div>
                        <input type="hidden" id="images" name="images" value="[]">
                        
                        <!-- DEBUG INFO -->
                        <div style="margin-top: 10px; padding: 10px; background: #f3f4f6; border-radius: 6px; font-size: 12px; color: #6b7280;">
                            <strong>🔧 Debug:</strong> <span id="debugInfo">Brak zdjęć</span>
                            <button type="button" onclick="debugImageState()" style="margin-left: 10px; padding: 4px 8px; font-size: 11px; background: #e5e7eb; border: none; border-radius: 4px; cursor: pointer;">
                                Sprawdź stan
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Anuluj</button>
                    <button type="button" class="btn btn-primary btn-save" onclick="saveReport()">
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M15,9H5V5H15M12,19A3,3 0 0,1 9,16A3,3 0 0,1 12,13A3,3 0 0,1 15,16A3,3 0 0,1 12,19M17,3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V7L17,3Z"/>
                        </svg>
                        Zapisz raport
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Report Modal -->
    <div id="viewReportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Podgląd raportu</h2>
                <button type="button" class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="reportViewContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Zamknij</button>
            </div>
        </div>
    </div>

    <!-- Template Selection Modal -->
    <div class="template-selection" id="templateSelection">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Wybierz szablon raportu</h2>
                <button type="button" class="close-btn" onclick="toggleTemplateSelection()">&times;</button>
            </div>
            <div class="template-grid">
                <div class="template-card" onclick="selectTemplate('traffic')">
                    <div class="template-icon">
                        <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: white;">
                            <path d="M18.92,6.01C18.72,5.42 18.16,5 17.5,5H6.5C5.84,5 5.28,5.42 5.08,6.01L3,12V20A1,1 0 0,0 4,21H5A1,1 0 0,0 6,20V19H18V20A1,1 0 0,0 19,21H20A1,1 0 0,0 21,20V12L18.92,6.01M6.5,6.5H17.5L19,11H5L6.5,6.5M7.5,16A1.5,1.5 0 0,1 6,14.5A1.5,1.5 0 0,1 7.5,13A1.5,1.5 0 0,1 9,14.5A1.5,1.5 0 0,1 7.5,16M16.5,16A1.5,1.5 0 0,1 15,14.5A1.5,1.5 0 0,1 16.5,13A1.5,1.5 0 0,1 18,14.5A1.5,1.5 0 0,1 16.5,16Z"/>
                        </svg>
                    </div>
                    <div class="template-title">Wykroczenie drogowe</div>
                    <div class="template-description">Szablon do raportowania wykroczeń drogowych, kontroli prędkości, mandatów</div>
                </div>
                
                <div class="template-card" onclick="selectTemplate('domestic')">
                    <div class="template-icon">
                        <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: white;">
                            <path d="M10,20V14H14V20H19V12H22L12,3L2,12H5V20H10Z"/>
                        </svg>
                    </div>
                    <div class="template-title">Interwencja domowa</div>
                    <div class="template-description">Szablon do raportowania interwencji domowych, konfliktów rodzinnych</div>
                </div>
                
                <div class="template-card" onclick="selectTemplate('criminal')">
                    <div class="template-icon">
                        <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: white;">
                            <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.6 14.8,10V11.5C15.4,12.1 16,13.2 16,14.5C16,16.4 14.4,18 12.5,18C10.6,18 9,16.4 9,14.5C9,13.2 9.6,12.1 10.2,11.5V10C10.2,8.6 11.6,7 12,7Z"/>
                        </svg>
                    </div>
                    <div class="template-title">Przestępstwo kryminalne</div>
                    <div class="template-description">Szablon do raportowania przestępstw, kradzieży, napadów</div>
                </div>
            </div>
        </div>
    </div>

    <!-- === LIGHTBOX DO POWIĘKSZANIA ZDJĘĆ === -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()" title="Zamknij (Esc)">
            ×
        </button>
        <button class="lightbox-nav lightbox-prev" onclick="lightboxPrev(event)" title="Poprzednie (←)">
            ‹
        </button>
        <button class="lightbox-nav lightbox-next" onclick="lightboxNext(event)" title="Następne (→)">
            ›
        </button>
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <img class="lightbox-image" id="lightboxImage" src="" alt="">
            <div class="lightbox-info">
                <div class="lightbox-filename" id="lightboxFilename"></div>
                <div class="lightbox-size" id="lightboxSize"></div>
            </div>
        </div>
    </div>

    <script>
        let editingReportId = null;
        let reportImages = [];
        const reportTemplates = <?php echo json_encode($report_templates); ?>;

        // Kluczowe funkcje dostępne od razu
        function openNewReportModal() {
            editingReportId = null;
            reportImages = [];
            document.getElementById('modalTitle').textContent = 'Nowy raport';
            document.getElementById('reportForm').reset();
            document.getElementById('report_content').innerHTML = '';
            document.getElementById('imagesPreview').innerHTML = '';
            updateImagesInput();
            
            <?php if ($user['role'] !== 'admin'): ?>
            document.querySelector('select[name="status"]').value = 'Under Review';
            <?php endif; ?>
            
            document.getElementById('reportModal').classList.add('active');
        }

        function loadReports() {
            const grid = document.getElementById('reportsGrid');
            grid.innerHTML = '<div class="loading"><div class="spinner"></div><br>Ładowanie raportów...</div>';

            fetch('reports.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_reports'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReports(data.reports);
                } else {
                    grid.innerHTML = '<div class="error">Błąd: ' + (data.error || 'Nieznany błąd') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                grid.innerHTML = '<div class="error">Błąd połączenia z serwerem</div>';
            });
        }

        function updateImagesInput() {
            document.getElementById('images').value = JSON.stringify(reportImages);
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadReports();
            
            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                filterReports(this.value);
            });
        });

        function displayReports(reports) {
            const grid = document.getElementById('reportsGrid');
            
            if (reports.length === 0) {
                grid.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: #64748b;">
                        <svg viewBox="0 0 24 24" style="width: 64px; height: 64px; fill: currentColor; opacity: 0.3; margin-bottom: 20px;">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                        <h3 style="margin-bottom: 10px;">Brak raportów</h3>
                        <p>Nie znaleziono żadnych raportów do wyświetlenia.</p>
                    </div>
                `;
                return;
            }

            grid.innerHTML = reports.map(report => {
                const canEdit = <?php echo json_encode($user['role'] === 'admin'); ?> || report.officer_name === '<?php echo $user['name']; ?>';
                const canDelete = <?php echo json_encode(canDeleteReport($user)); ?>;
                
                return `
                    <div class="report-card" data-id="${report.id}">
                        <div class="report-header">
                            <div class="report-title-section">
                                <h3 class="report-title">${report.title}</h3>
                                <div class="report-status">
                                    <span class="status-badge status-${report.status.toLowerCase().replace(' ', '-')}">
                                        ${getStatusText(report.status)}
                                    </span>
                                </div>
                            </div>
                            <div class="report-meta">
                                <div class="meta-badge">
                                    <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: currentColor;">
                                        <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                                    </svg>
                                    ${report.officer_badge}
                                </div>
                                <div class="meta-date">
                                    <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: currentColor;">
                                        <path d="M19,3H18V1H16V3H8V1H6V3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M19,19H5V8H19V19Z"/>
                                    </svg>
                                    ${formatDate(report.created_at)}
                                </div>
                            </div>
                        </div>
                        
                        <div class="report-body">
                            <div class="report-details">
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <span class="detail-label">Oficer:</span>
                                        <span class="detail-value">${report.officer_name}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Data zdarzenia:</span>
                                        <span class="detail-value">${formatDate(report.incident_date)} ${report.incident_time || ''}</span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <span class="detail-label">Typ:</span>
                                        <span class="type-badge type-${report.incident_type.toLowerCase().replace(' ', '-')}">
                                            ${getTypeText(report.incident_type)}
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Priorytet:</span>
                                        <span class="priority-badge priority-${report.priority.toLowerCase()}">
                                            ${getPriorityText(report.priority)}
                                        </span>
                                    </div>
                                </div>
                                
                                ${report.location ? `
                                <div class="detail-row">
                                    <div class="detail-item full-width">
                                        <span class="detail-label">Lokalizacja:</span>
                                        <span class="detail-value">${report.location}</span>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="report-actions">
                            <button class="btn btn-view" onclick="viewReport(${report.id})">
                                <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                                    <path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"/>
                                </svg>
                                Podgląd
                            </button>
                            ${canEdit ? `
                                <button class="btn btn-edit" onclick="editReport(${report.id})">
                                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                                        <path d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z"/>
                                    </svg>
                                    Edytuj
                                </button>
                            ` : ''}
                            ${canDelete ? `
                                <button class="btn btn-delete" onclick="deleteReport(${report.id})">
                                    <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                                        <path d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"/>
                                    </svg>
                                    Usuń
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function filterReports(searchTerm) {
            const cards = document.querySelectorAll('.report-card');
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm.toLowerCase()) ? 'block' : 'none';
            });
        }

        function toggleTemplateSelection() {
            document.getElementById('templateSelection').classList.toggle('active');
        }

        function toggleTemplateSelection() {
            document.getElementById('templateSelection').classList.toggle('active');
        }

        function selectTemplate(template) {
            document.getElementById('templateSelection').classList.remove('active');
            openNewReportModal();
            
            const templateData = reportTemplates[template];
            if (templateData) {
                document.querySelector('input[name="title"]').value = templateData.title;
                document.querySelector('select[name="incident_type"]').value = templateData.incident_type;
                document.querySelector('select[name="priority"]').value = templateData.priority;
                document.getElementById('report_content').innerHTML = templateData.content.replace(/\n/g, '<br>');
            }
        }

        function editReport(id) {
            editingReportId = id;
            document.getElementById('modalTitle').textContent = 'Edycja raportu';
            
            fetch('reports.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_report&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const report = data.report;
                    const form = document.getElementById('reportForm');
                    
                    // Fill form fields
                    form.title.value = report.title || '';
                    form.officer_name.value = report.officer_name || '';
                    form.officer_badge.value = report.officer_badge || '';
                    form.incident_date.value = report.incident_date || '';
                    form.incident_time.value = report.incident_time || '';
                    form.location.value = report.location || '';
                    form.incident_type.value = report.incident_type || '';
                    form.priority.value = report.priority || '';
                    form.status.value = report.status || '';
                    form.evidence_description.value = report.evidence_description || '';
                    form.witnesses.value = report.witnesses || '';
                    form.suspect_info.value = report.suspect_info || '';
                    form.victim_info.value = report.victim_info || '';
                    form.follow_up_required.checked = report.follow_up_required == 1;
                    form.follow_up_notes.value = report.follow_up_notes || '';
                    
                    document.getElementById('report_content').innerHTML = report.report_content || '';
                    
                    // Load images
                    reportImages = [];
                    if (report.images && report.images !== '[]') {
                        try {
                            reportImages = JSON.parse(report.images);
                            displayImagesPreview();
                            updateImagesInput();
                        } catch (e) {
                            console.error('Error parsing images:', e);
                        }
                    }
                    
                    document.getElementById('reportModal').classList.add('active');
                } else {
                    showMessage('error', 'Błąd podczas ładowania raportu: ' + (data.error || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Błąd połączenia z serwerem');
            });
        }

        function viewReport(id) {
            fetch('reports.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=get_report&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const report = data.report;
                    
                    document.getElementById('reportViewContent').innerHTML = `
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                        Podstawowe informacje
                    </h3>
                    <div class="view-section-content">
                        <div class="view-info-grid">
                            <div class="view-info-item">
                                <div class="view-info-label">Tytuł</div>
                                <div class="view-info-value">${report.title}</div>
                            </div>
                            <div class="view-info-item">
                                <div class="view-info-label">Oficer</div>
                                <div class="view-info-value">${report.officer_name} (${report.officer_badge})</div>
                            </div>
                            <div class="view-info-item">
                                <div class="view-info-label">Data zdarzenia</div>
                                <div class="view-info-value">${formatDate(report.incident_date)} ${report.incident_time || ''}</div>
                            </div>
                            <div class="view-info-item">
                                <div class="view-info-label">Lokalizacja</div>
                                <div class="view-info-value">${report.location || 'Nie podano'}</div>
                            </div>
                            <div class="view-info-item">
                                <div class="view-info-label">Typ zdarzenia</div>
                                <div class="view-info-value">
                                    <span class="type-badge type-${report.incident_type.toLowerCase().replace(' ', '-')}">
                                        ${getTypeText(report.incident_type)}
                                    </span>
                                </div>
                            </div>
                            <div class="view-info-item">
                                <div class="view-info-label">Priorytet</div>
                                <div class="view-info-value">
                                    <span class="priority-badge priority-${report.priority.toLowerCase()}">
                                        ${getPriorityText(report.priority)}
                                    </span>
                                </div>
                            </div>
                            <div class="view-info-item">
                                <div class="view-info-label">Status</div>
                                <div class="view-info-value">
                                    <span class="status-badge status-${report.status.toLowerCase().replace(' ', '-')}">
                                        ${getStatusText(report.status)}
                                    </span>
                                </div>
                            </div>
                            <div class="view-info-item">
                                <div class="view-info-label">Data utworzenia</div>
                                <div class="view-info-value">${formatDateTime(report.created_at)}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                        </svg>
                        Treść raportu
                    </h3>
                    <div class="view-section-content">
                        <div style="white-space: pre-line; line-height: 1.6;">${report.report_content || 'Brak treści'}</div>
                    </div>
                </div>
                
                ${report.evidence_description ? `
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M9,11H15L13,9L11.5,10.5L10.5,9.5L9,11M20,6H12L10,4H4A2,2 0 0,0 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V8A2,2 0 0,0 20,6Z"/>
                        </svg>
                        Dowody / materiały
                    </h3>
                    <div class="view-section-content">
                        <div style="white-space: pre-line;">${report.evidence_description}</div>
                    </div>
                </div>
                ` : ''}
                
                ${report.witnesses ? `
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M16,4C18.11,4 19.99,5.89 19.99,8C19.99,10.11 18.11,12 16,12C13.89,12 12,10.11 12,8C12,5.89 13.89,4 16,4M16,14C20.42,14 24,15.79 24,18V20H8V18C8,15.79 11.58,14 16,14M6,6V9H4V6H1V4H4V1H6V4H9V6H6M6,13V16H4V13H1V11H4V8H6V11H9V13H6Z"/>
                        </svg>
                        Świadkowie
                    </h3>
                    <div class="view-section-content">
                        <div style="white-space: pre-line;">${report.witnesses}</div>
                    </div>
                </div>
                ` : ''}
                
                ${report.suspect_info ? `
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                        </svg>
                        Informacje o podejrzanym
                    </h3>
                    <div class="view-section-content">
                        <div style="white-space: pre-line;">${report.suspect_info}</div>
                    </div>
                </div>
                ` : ''}
                
                ${report.victim_info ? `
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/>
                        </svg>
                        Informacje o pokrzywdzonym
                    </h3>
                    <div class="view-section-content">
                        <div style="white-space: pre-line;">${report.victim_info}</div>
                    </div>
                </div>
                ` : ''}
                
                ${report.follow_up_required ? `
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M13,17H11V15H13V17M13,13H11V7H13V13Z"/>
                        </svg>
                        Działania następcze
                    </h3>
                    <div class="view-section-content" style="background: #fef3c7; border-left-color: #f59e0b;">
                        <div style="margin-bottom: 10px; font-weight: 600; color: #d97706;">
                            ⚠️ Wymagane są dalsze działania następcze
                        </div>
                        ${report.follow_up_notes ? `<div style="white-space: pre-line;">${report.follow_up_notes}</div>` : ''}
                    </div>
                </div>
                ` : ''}
                
                ${(() => {
                    let images = [];
                    try {
                        if (report.images && report.images !== '[]') {
                            images = JSON.parse(report.images);
                        }
                    } catch (e) {
                        images = [];
                    }
                    
                    if (images.length > 0) {
                        const imagesJsonEscaped = JSON.stringify(images).replace(/"/g, '&quot;');
                        return `
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/>
                        </svg>
                        📸 Załączone zdjęcia (${images.length})
                    </h3>
                    <div class="view-section-content">
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            ${images.map((img, index) => `
                                <div class="lightbox-gallery-item" onclick="openLightbox(${imagesJsonEscaped}, ${index}, '${report.title.replace(/'/g, "\\'")} - Załączniki')" 
                                     style="position: relative; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
                                            cursor: pointer; transition: all 0.3s ease; aspect-ratio: 4/3; background: #f1f5f9;"
                                     onmouseover="this.style.transform='scale(1.03) translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)'"
                                     onmouseout="this.style.transform='scale(1) translateY(0)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'">
                                    
                                    <img src="${img.url}" alt="${img.original_name}" 
                                         style="width: 100%; height: 100%; object-fit: cover; transition: all 0.3s ease;"
                                         onmouseover="this.style.transform='scale(1.1)'"
                                         onmouseout="this.style.transform='scale(1)'">
                                    
                                    <!-- Overlay z ikoną powiększenia -->
                                    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                                                background: linear-gradient(135deg, rgba(59,130,246,0.0) 0%, rgba(59,130,246,0.1) 100%); 
                                                display: flex; align-items: center; justify-content: center;
                                                opacity: 0; transition: all 0.3s ease;"
                                         onmouseover="this.style.opacity='1'"
                                         onmouseout="this.style.opacity='0'">
                                        <div style="background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); 
                                                    border-radius: 50%; width: 60px; height: 60px; 
                                                    display: flex; align-items: center; justify-content: center;
                                                    transform: scale(0.8); transition: all 0.3s ease;"
                                             onmouseover="this.style.transform='scale(1)'"
                                             onmouseout="this.style.transform='scale(0.8)'">
                                            <svg viewBox="0 0 24 24" style="width: 28px; height: 28px; fill: #3b82f6;">
                                                <path d="M15.5,14L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5M9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5C7,5 5,7 5,9.5C7,12 9.5,14"/>
                                                <path d="M12,10H10V8H12V10M12,14H10V12H12V14"/>
                                            </svg>
                                        </div>
                                    </div>
                                    
                                    <!-- Nazwa pliku na dole -->
                                    <div style="position: absolute; bottom: 0; left: 0; right: 0; 
                                                background: linear-gradient(transparent, rgba(0,0,0,0.8)); 
                                                color: white; padding: 15px 10px 8px; font-size: 12px; 
                                                text-align: center; word-break: break-all; line-height: 1.3;
                                                opacity: 0; transition: all 0.3s ease;"
                                         onmouseover="this.style.opacity='1'"
                                         onmouseout="this.style.opacity='0'">
                                        <div style="font-weight: 600; margin-bottom: 2px;">${img.original_name}</div>
                                        <div style="opacity: 0.8; font-size: 10px;">${index + 1} z ${images.length} • Kliknij aby powiększyć</div>
                                    </div>
                                    
                                    <!-- Numer zdjęcia -->
                                    <div style="position: absolute; top: 10px; right: 10px; 
                                                background: rgba(0,0,0,0.7); color: white; 
                                                padding: 6px 10px; border-radius: 15px; 
                                                font-size: 12px; font-weight: 600;">
                                        ${index + 1}/${images.length}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>`;
                    } else {
                        return '';
                    }
                })()}
            `;
                    
                    document.getElementById('viewReportModal').classList.add('active');
                } else {
                    showMessage('error', 'Błąd podczas ładowania raportu: ' + (data.error || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Błąd połączenia z serwerem');
            });
        }

        function saveReport() {
            const form = document.getElementById('reportForm');
            const formData = new FormData(form);
            
            // Get rich text content
            const reportContent = document.getElementById('report_content').innerHTML;
            formData.set('report_content', reportContent);
            
            formData.append('action', 'save_report');
            if (editingReportId) {
                formData.append('id', editingReportId);
            }

            // Show saving indicator
            const saveBtn = document.querySelector('.btn-save');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<div class="spinner" style="width: 16px; height: 16px; margin-right: 8px;"></div>Zapisywanie...';
            saveBtn.disabled = true;

            fetch('reports.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    loadReports();
                    showMessage('success', 'Raport został zapisany pomyślnie!');
                } else {
                    showMessage('error', 'Błąd podczas zapisywania: ' + (data.error || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Błąd połączenia z serwerem');
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }

        function deleteReport(id) {
            if (!confirm('Czy na pewno chcesz usunąć ten raport? Ta operacja jest nieodwracalna.')) {
                return;
            }

            fetch('reports.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=delete_report&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadReports();
                    showMessage('success', 'Raport został usunięty');
                } else {
                    showMessage('error', 'Błąd podczas usuwania: ' + (data.error || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Błąd połączenia z serwerem');
            });
        }

        // Rich text editor functions
        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            document.getElementById('report_content').focus();
        }

        // Modal functions
        function closeModal() {
            document.getElementById('reportModal').classList.remove('active');
        }

        function closeViewModal() {
            document.getElementById('viewReportModal').classList.remove('active');
        }

        // Helper functions
        function getStatusText(status) {
            const statusMap = {
                'Draft': 'Szkic',
                'Under Review': 'W przeglądu',
                'Approved': 'Zatwierdzony',
                'Closed': 'Zamknięty'
            };
            return statusMap[status] || status;
        }

        function getPriorityText(priority) {
            const priorityMap = {
                'Low': 'Niski',
                'Medium': 'Średni',
                'High': 'Wysoki',
                'Critical': 'Krytyczny'
            };
            return priorityMap[priority] || priority;
        }

        function getTypeText(type) {
            const typeMap = {
                'Traffic': 'Drogowy',
                'Criminal': 'Kryminalny',
                'Domestic': 'Domowy',
                'Public Order': 'Porządek publiczny',
                'Investigation': 'Dochodzenie',
                'Administrative': 'Administracyjny',
                'Emergency': 'Nagły wypadek',
                'Other': 'Inny'
            };
            return typeMap[type] || type;
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('pl-PL');
        }

        function formatDateTime(dateTimeString) {
            if (!dateTimeString) return 'N/A';
            const date = new Date(dateTimeString);
            return date.toLocaleString('pl-PL');
        }

        function showMessage(type, text) {
            // Remove existing messages
            document.querySelectorAll('.message').forEach(msg => msg.remove());
            
            const message = document.createElement('div');
            message.className = `message ${type}`;
            message.innerHTML = `
                <svg viewBox="0 0 24 24">
                    ${type === 'success' 
                        ? '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>'
                        : '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>'
                    }
                </svg>
                <span>${text}</span>
            `;
            
            document.querySelector('.reports-container').insertBefore(message, document.querySelector('.reports-container').firstChild);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (message.parentNode) {
                    message.style.opacity = '0';
                    message.style.transform = 'translateY(-20px)';
                    setTimeout(() => message.remove(), 300);
                }
            }, 5000);
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal') || e.target.classList.contains('template-selection')) {
                e.target.classList.remove('active');
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape to close modals
            if (e.key === 'Escape') {
                document.getElementById('reportModal').classList.remove('active');
                document.getElementById('viewReportModal').classList.remove('active');
                document.getElementById('templateSelection').classList.remove('active');
            }
            
            // Ctrl+N for new report
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                <?php if (canCreateReport($user)): ?>
                openNewReportModal();
                <?php endif; ?>
            }
            
            // Ctrl+S to save report (when modal is open)
            if (e.ctrlKey && e.key === 's' && document.getElementById('reportModal').classList.contains('active')) {
                e.preventDefault();
                saveReport();
            }
            
            // Ctrl+T for template selection
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                <?php if (canCreateReport($user)): ?>
                toggleTemplateSelection();
                <?php endif; ?>
            }
        });

        
        // ===== JEDYNE POPRAWNE FUNKCJE OBSŁUGI ZDJĘĆ =====
        
        // Image handling functions - NAPRAWIONE Z DEBUGGIEM
        function handleImageUpload(event) {
            console.log('=== handleImageUpload START ===');
            const files = Array.from(event.target.files);
            console.log('Selected files:', files);
            processFiles(files);
            event.target.value = ''; // Clear input
            console.log('=== handleImageUpload END ===');
        }

        function handleDrop(event) {
            console.log('=== handleDrop START ===');
            event.preventDefault();
            event.stopPropagation();
            
            const dropZone = event.currentTarget;
            dropZone.classList.remove('dragover');
            
            const files = Array.from(event.dataTransfer.files);
            console.log('Dropped files:', files);
            processFiles(files);
            console.log('=== handleDrop END ===');
        }

        function handleDragOver(event) {
            event.preventDefault();
            event.stopPropagation();
            event.currentTarget.classList.add('dragover');
        }

        function handleDragLeave(event) {
            event.preventDefault();
            event.stopPropagation();
            event.currentTarget.classList.remove('dragover');
        }

        function processFiles(files) {
            console.log('=== processFiles START ===');
            console.log('Processing files:', files);
            console.log('Current reportImages before:', reportImages);
            
            files.forEach((file, index) => {
                console.log(`Processing file ${index + 1}/${files.length}:`, file.name);
                
                if (!file.type.startsWith('image/')) {
                    console.error(`File ${file.name} is not an image:`, file.type);
                    showMessage('error', `"${file.name}" nie jest plikiem graficznym`);
                    return;
                }
                
                if (file.size > 10 * 1024 * 1024) {
                    console.error(`File ${file.name} too large:`, file.size);
                    showMessage('error', `"${file.name}" jest za duży (${Math.round(file.size / 1024 / 1024)}MB). Maksymalny rozmiar: 10MB`);
                    return;
                }
                
                console.log(`File ${file.name} validation passed, uploading...`);
                uploadSingleFile(file);
            });
            console.log('=== processFiles END ===');
        }

        function uploadSingleFile(file) {
            console.log('=== uploadSingleFile START ===');
            console.log('Uploading file:', file.name, 'Size:', file.size, 'Type:', file.type);
            
            const formData = new FormData();
            formData.append('image', file);
            formData.append('action', 'upload_image');
            
            console.log('FormData created, making request...');
            
            // Show upload progress
            const progressDiv = document.createElement('div');
            progressDiv.innerHTML = `
                <div style="padding: 15px; border: 2px dashed #3b82f6; border-radius: 8px; margin-bottom: 10px; background: #eff6ff; text-align: center;">
                    <div class="spinner" style="margin-bottom: 10px;"></div>
                    <div style="color: #1e40af; font-weight: 600;">Przesyłanie "${file.name}"...</div>
                    <div style="font-size: 12px; color: #64748b;">${Math.round(file.size / 1024)} KB</div>
                </div>
            `;
            document.getElementById('imagesPreview').appendChild(progressDiv);
            
            fetch('reports.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Upload response status:', response.status);
                console.log('Upload response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text(); // Najpierw jako text dla debugowania
            })
            .then(text => {
                console.log('Raw response text:', text);
                
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON response:', data);
                    return data;
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text that failed to parse:', text);
                    throw new Error('Odpowiedź serwera nie jest poprawnym JSON: ' + text.substring(0, 200));
                }
            })
            .then(data => {
                console.log('Upload response data:', data);
                progressDiv.remove();
                
                if (data.success) {
                    console.log('Upload successful, adding to reportImages');
                    reportImages.push(data.image);
                    console.log('reportImages after push:', reportImages);
                    displayImagesPreview();
                    updateImagesInput();
                    showMessage('success', `✅ Zdjęcie "${file.name}" zostało dodane`);
                } else {
                    console.error('Upload failed:', data.error);
                    showMessage('error', `❌ Błąd przesyłania "${file.name}": ${data.error}`);
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                progressDiv.remove();
                showMessage('error', `❌ Błąd przesyłania "${file.name}": ${error.message}`);
            });
            
            console.log('=== uploadSingleFile END ===');
        }

        function displayImagesPreview() {
            console.log('=== displayImagesPreview START ===');
            console.log('Current reportImages:', reportImages);
            
            const preview = document.getElementById('imagesPreview');
            const debugInfo = document.getElementById('debugInfo');
            
            if (!preview) {
                console.error('imagesPreview element not found!');
                return;
            }
            
            // Aktualizuj debug info
            if (debugInfo) {
                debugInfo.innerHTML = `${reportImages.length} zdjęć w pamięci`;
            }
            
            if (reportImages.length === 0) {
                console.log('No images to display');
                preview.innerHTML = '<div style="text-align: center; padding: 20px; color: #9ca3af; font-style: italic;">Brak dodanych zdjęć</div>';
                return;
            }
            
            const imagesHtml = reportImages.map((img, index) => {
                console.log(`Generating HTML for image ${index}:`, img);
                return `
                    <div class="image-preview" data-index="${index}">
                        <img src="${img.url}" alt="${img.original_name}" title="${img.original_name}" onclick="window.open('${img.url}', '_blank')">
                        <button type="button" class="remove-image" onclick="removeImage(${index})" title="Usuń zdjęcie">
                            ×
                        </button>
                        <div class="image-filename">${img.original_name}</div>
                    </div>
                `;
            }).join('');
            
            preview.innerHTML = imagesHtml;
            console.log('Images preview updated, HTML length:', imagesHtml.length);
            console.log('=== displayImagesPreview END ===');
        }

        // Rozszerzona funkcja debug
        function debugImageState() {
            console.log('=== DEBUG IMAGE STATE ===');
            console.log('reportImages:', reportImages);
            console.log('reportImages length:', reportImages.length);
            console.log('imagesPreview element:', document.getElementById('imagesPreview'));
            console.log('images input:', document.getElementById('images'));
            console.log('images input value:', document.getElementById('images')?.value);
            console.log('debugInfo element:', document.getElementById('debugInfo'));
            
            // Wyświetl alert z informacjami
            const info = [
                `🔧 STAN SYSTEMU ZDJĘĆ:`,
                `📸 Liczba zdjęć: ${reportImages.length}`,
                `💾 Dane JSON: ${document.getElementById('images')?.value || 'BRAK'}`,
                `🎯 Element preview: ${document.getElementById('imagesPreview') ? '✅' : '❌'}`,
                `🎯 Element input: ${document.getElementById('images') ? '✅' : '❌'}`,
                '',
                'Sprawdź konsolę deweloperską (F12) dla szczegółów'
            ].join('\n');
            
            alert(info);
            console.log('=========================');
            
            // Aktualizuj debug info na stronie
            const debugInfo = document.getElementById('debugInfo');
            if (debugInfo) {
                debugInfo.innerHTML = `${reportImages.length} zdjęć, JSON: ${document.getElementById('images')?.value?.length || 0} znaków`;
                debugInfo.style.color = reportImages.length > 0 ? '#16a34a' : '#6b7280';
            }
        }

        function removeImage(index) {
            console.log('=== removeImage START ===');
            console.log('Removing image at index:', index);
            console.log('Before removal:', reportImages);
            
            if (confirm('Czy na pewno chcesz usunąć to zdjęcie?')) {
                reportImages.splice(index, 1);
                console.log('After removal:', reportImages);
                displayImagesPreview();
                updateImagesInput();
                showMessage('success', '🗑️ Zdjęcie zostało usunięte');
            }
            console.log('=== removeImage END ===');
        }

        function updateImagesInput() {
            console.log('=== updateImagesInput START ===');
            const imagesInput = document.getElementById('images');
            const debugInfo = document.getElementById('debugInfo');
            const jsonValue = JSON.stringify(reportImages);
            
            if (!imagesInput) {
                console.error('images input element not found!');
                return;
            }
            
            imagesInput.value = jsonValue;
            console.log('Updated images input value:', jsonValue);
            
            // Aktualizuj debug info
            if (debugInfo) {
                debugInfo.innerHTML = `${reportImages.length} zdjęć, JSON: ${jsonValue.length} znaków`;
                debugInfo.style.color = reportImages.length > 0 ? '#16a34a' : '#6b7280';
                debugInfo.style.fontWeight = reportImages.length > 0 ? 'bold' : 'normal';
            }
            
            console.log('=== updateImagesInput END ===');
        }

        // Poprawiona funkcja openNewReportModal z resetem debug info
        function openNewReportModal() {
            console.log('=== openNewReportModal START ===');
            editingReportId = null;
            reportImages = [];
            document.getElementById('modalTitle').textContent = 'Nowy raport';
            document.getElementById('reportForm').reset();
            document.getElementById('report_content').innerHTML = '';
            
            // Reset preview i debug info
            const preview = document.getElementById('imagesPreview');
            const debugInfo = document.getElementById('debugInfo');
            
            if (preview) {
                preview.innerHTML = '<div style="text-align: center; padding: 20px; color: #9ca3af; font-style: italic;">Brak dodanych zdjęć</div>';
            }
            
            if (debugInfo) {
                debugInfo.innerHTML = 'Brak zdjęć';
                debugInfo.style.color = '#6b7280';
                debugInfo.style.fontWeight = 'normal';
            }
            
            updateImagesInput();
            
            <?php if ($user['role'] !== 'admin'): ?>
            document.querySelector('select[name="status"]').value = 'Under Review';
            <?php endif; ?>
            
            document.getElementById('reportModal').classList.add('active');
            console.log('=== openNewReportModal END ===');
        }

        // NAPRAWIONA funkcja saveReport z debuggiem
        function saveReport() {
            console.log('=== saveReport START ===');
            console.log('Current reportImages:', reportImages);
            
            const form = document.getElementById('reportForm');
            if (!form) {
                console.error('reportForm not found!');
                showMessage('error', 'Błąd: nie znaleziono formularza');
                return;
            }
            
            // WAŻNE: Użyj URLSearchParams zamiast FormData dla lepszej kompatybilności
            const formData = new URLSearchParams();
            
            // Dodaj wszystkie pola formularza
            const formElements = form.elements;
            for (let i = 0; i < formElements.length; i++) {
                const element = formElements[i];
                if (element.name && element.type !== 'file') {
                    if (element.type === 'checkbox') {
                        if (element.checked) {
                            formData.append(element.name, '1');
                        }
                    } else {
                        formData.append(element.name, element.value);
                    }
                }
            }
            
            // Dodaj treść z edytora
            const reportContent = document.getElementById('report_content').innerHTML;
            formData.set('report_content', reportContent);
            
            // KLUCZOWE: Dodaj zdjęcia
            const imagesJson = JSON.stringify(reportImages);
            formData.set('images', imagesJson);
            console.log('Images being sent:', imagesJson);
            
            formData.append('action', 'save_report');
            if (editingReportId) {
                formData.append('id', editingReportId);
                console.log('Editing report ID:', editingReportId);
            } else {
                console.log('Creating new report');
            }
            
            // Show saving indicator
            const saveBtn = document.querySelector('.btn-save');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<div class="spinner" style="width: 16px; height: 16px; margin-right: 8px;"></div>Zapisywanie...';
            saveBtn.disabled = true;
            
            console.log('Sending form data:', Object.fromEntries(formData));
            
            fetch('reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
            .then(response => {
                console.log('Save response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                console.log('Save raw response:', text);
                
                try {
                    const data = JSON.parse(text);
                    console.log('Save parsed response:', data);
                    return data;
                } catch (e) {
                    console.error('JSON parse error in save:', e);
                    throw new Error('Odpowiedź serwera nie jest poprawnym JSON: ' + text.substring(0, 200));
                }
            })
            .then(data => {
                if (data.success) {
                    console.log('✅ Report saved successfully');
                    closeModal();
                    loadReports();
                    showMessage('success', '✅ Raport został zapisany pomyślnie!');
                } else {
                    console.error('❌ Save failed:', data.error);
                    showMessage('error', '❌ Błąd podczas zapisywania: ' + (data.error || 'Nieznany błąd'));
                }
            })
            .catch(error => {
                console.error('❌ Save error:', error);
                showMessage('error', '❌ Błąd połączenia z serwerem: ' + error.message);
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
                console.log('=== saveReport END ===');
            });
        }

        // Debug funkcja do sprawdzania stanu
        function debugImageState() {
            console.log('=== DEBUG IMAGE STATE ===');
            console.log('reportImages:', reportImages);
            console.log('imagesPreview element:', document.getElementById('imagesPreview'));
            console.log('images input:', document.getElementById('images'));
            console.log('images input value:', document.getElementById('images')?.value);
            console.log('=========================');
        }

        // Dodaj debug do konsoli deweloperskiej
        window.debugImageState = debugImageState;
        window.reportImages = reportImages;

        // ===== INICJALIZACJA I SPRAWDZENIE DOM =====
        function initializeImageSystem() {
            console.log('🚀 Inicjalizacja systemu zdjęć...');
            
            // Sprawdź kluczowe elementy DOM
            const requiredElements = {
                'imageInput': document.getElementById('imageInput'),
                'imagesPreview': document.getElementById('imagesPreview'),
                'images': document.getElementById('images'),
                'debugInfo': document.getElementById('debugInfo')
            };
            
            let allElementsFound = true;
            
            Object.entries(requiredElements).forEach(([name, element]) => {
                if (element) {
                    console.log(`✅ Element ${name} znaleziony`);
                } else {
                    console.error(`❌ Element ${name} NIE ZNALEZIONY!`);
                    allElementsFound = false;
                }
            });
            
            if (allElementsFound) {
                console.log('✅ Wszystkie elementy DOM znalezione - system gotowy');
                
                // Ustaw początkowy stan debug info
                const debugInfo = document.getElementById('debugInfo');
                if (debugInfo) {
                    debugInfo.innerHTML = '✅ System zainicjalizowany - brak zdjęć';
                    debugInfo.style.color = '#16a34a';
                }
                
                return true;
            } else {
                console.error('❌ Niektóre elementy DOM nie zostały znalezione!');
                showMessage('error', '⚠️ Problem z inicjalizacją systemu zdjęć - sprawdź konsolę');
                return false;
            }
        }

        // Funkcja testowa - można wywołać z konsoli
        function testImageSystem() {
            console.log('🧪 TESTOWANIE SYSTEMU ZDJĘĆ');
            
            // Test 1: Sprawdzenie DOM
            console.log('Test 1: Sprawdzenie DOM...');
            const domTest = initializeImageSystem();
            
            // Test 2: Sprawdzenie stanu zmiennych
            console.log('Test 2: Sprawdzenie zmiennych...');
            console.log('reportImages:', reportImages);
            console.log('editingReportId:', editingReportId);
            
            // Test 3: Sprawdzenie funkcji
            console.log('Test 3: Sprawdzenie funkcji...');
            const functions = ['handleImageUpload', 'displayImagesPreview', 'updateImagesInput', 'saveReport'];
            functions.forEach(funcName => {
                if (typeof window[funcName] === 'function') {
                    console.log(`✅ Funkcja ${funcName} dostępna`);
                } else {
                    console.error(`❌ Funkcja ${funcName} NIEDOSTĘPNA!`);
                }
            });
            
            alert('🧪 Test zakończony - sprawdź konsolę deweloperską dla szczegółów');
            return domTest;
        }

        // Udostępnij funkcje testowe globalnie
        window.testImageSystem = testImageSystem;
        window.initializeImageSystem = initializeImageSystem;

        console.log('🎯 Image handling system loaded with debugging');
        console.log('📋 Available debug functions:');
        console.log('  - debugImageState() - sprawdź stan zdjęć');
        console.log('  - testImageSystem() - pełny test systemu');
        console.log('  - initializeImageSystem() - ponowna inicjalizacja');
        console.log('📋 Available global vars: reportImages, editingReportId');
        
        // Automatyczna inicjalizacja po załadowaniu DOM
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                console.log('🔄 Automatyczna inicjalizacja po DOMContentLoaded...');
                initializeImageSystem();
            }, 500);
        });

        // ===== LIGHTBOX SYSTEM DO POWIĘKSZANIA ZDJĘĆ =====
        let currentLightboxImages = [];
        let currentLightboxIndex = 0;

        function openLightbox(images, startIndex = 0, reportTitle = '') {
            console.log('🖼️ Opening lightbox with images:', images, 'Index:', startIndex);
            
            currentLightboxImages = images;
            currentLightboxIndex = startIndex;
            
            const lightbox = document.getElementById('lightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            const lightboxFilename = document.getElementById('lightboxFilename');
            const lightboxSize = document.getElementById('lightboxSize');
            
            if (!lightbox || !lightboxImage || !lightboxFilename || !lightboxSize) {
                console.error('❌ Lightbox elements not found!');
                return;
            }
            
            if (images.length === 0) {
                console.error('❌ No images to display in lightbox');
                return;
            }
            
            const currentImage = images[startIndex];
            
            lightboxImage.src = currentImage.url;
            lightboxImage.alt = currentImage.original_name;
            lightboxFilename.textContent = currentImage.original_name;
            lightboxSize.textContent = `Zdjęcie ${startIndex + 1} z ${images.length}${reportTitle ? ` - ${reportTitle}` : ''}`;
            
            // Show/hide navigation buttons
            const prevBtn = document.querySelector('.lightbox-prev');
            const nextBtn = document.querySelector('.lightbox-next');
            
            if (prevBtn) prevBtn.style.display = images.length > 1 ? 'flex' : 'none';
            if (nextBtn) nextBtn.style.display = images.length > 1 ? 'flex' : 'none';
            
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            
            console.log('✅ Lightbox opened successfully');
        }

        function closeLightbox() {
            console.log('❌ Closing lightbox');
            
            const lightbox = document.getElementById('lightbox');
            if (lightbox) {
                lightbox.classList.remove('active');
            }
            
            document.body.style.overflow = ''; // Restore scrolling
            currentLightboxImages = [];
            currentLightboxIndex = 0;
        }

        function lightboxPrev(event) {
            event.stopPropagation();
            
            if (currentLightboxImages.length <= 1) return;
            
            currentLightboxIndex = currentLightboxIndex > 0 ? 
                currentLightboxIndex - 1 : 
                currentLightboxImages.length - 1;
            
            updateLightboxImage();
        }

        function lightboxNext(event) {
            event.stopPropagation();
            
            if (currentLightboxImages.length <= 1) return;
            
            currentLightboxIndex = currentLightboxIndex < currentLightboxImages.length - 1 ? 
                currentLightboxIndex + 1 : 
                0;
            
            updateLightboxImage();
        }

        function updateLightboxImage() {
            const lightboxImage = document.getElementById('lightboxImage');
            const lightboxFilename = document.getElementById('lightboxFilename');
            const lightboxSize = document.getElementById('lightboxSize');
            
            if (!lightboxImage || !lightboxFilename || !lightboxSize) return;
            
            const currentImage = currentLightboxImages[currentLightboxIndex];
            
            lightboxImage.src = currentImage.url;
            lightboxImage.alt = currentImage.original_name;
            lightboxFilename.textContent = currentImage.original_name;
            lightboxSize.textContent = `Zdjęcie ${currentLightboxIndex + 1} z ${currentLightboxImages.length}`;
        }

        // Obsługa klawiatury dla lightbox
        document.addEventListener('keydown', function(e) {
            if (!document.getElementById('lightbox').classList.contains('active')) return;
            
            switch(e.key) {
                case 'Escape':
                    closeLightbox();
                    break;
                case 'ArrowLeft':
                    lightboxPrev(e);
                    break;
                case 'ArrowRight':
                    lightboxNext(e);
                    break;
            }
        });

        // Poprawione wyświetlanie zdjęć w modalach z lightbox
        function displayReportViewImages(images) {
            if (!images || images.length === 0) return '';
            
            return `
                <div class="view-section">
                    <h3>
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
                            <path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/>
                        </svg>
                        Załączone zdjęcia (${images.length})
                    </h3>
                    <div class="view-section-content">
                        <div class="images-preview" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                            ${images.map((img, index) => `
                                <div class="image-preview" onclick="openLightbox(${JSON.stringify(images).replace(/"/g, '&quot;')}, ${index}, 'Załączniki raportu')" style="cursor: pointer;">
                                    <img src="${img.url}" alt="${img.original_name}" loading="lazy" style="transition: transform 0.3s ease;">
                                    <div class="image-filename" style="opacity: 1;">${img.original_name}</div>
                                </div>
                            `).join('')}
                        </div>
                        <div style="margin-top: 15px; padding: 10px; background: #f0f9ff; border-radius: 8px; text-align: center; color: #1e40af; font-size: 14px;">
                            💡 <strong>Wskazówka:</strong> Kliknij na zdjęcie aby je powiększyć. Użyj strzałek ← → do nawigacji.
                        </div>
                    </div>
                </div>
            `;
        }

        // Udostępnij funkcje globalnie
        window.openLightbox = openLightbox;
        window.closeLightbox = closeLightbox;
        window.lightboxPrev = lightboxPrev;
        window.lightboxNext = lightboxNext;
        
    </script>
</body>
</html>