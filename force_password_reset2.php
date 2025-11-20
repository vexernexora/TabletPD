/* <?php
// force_password_reset.php - Wymuszona zmiana hasła przy pierwszym logowaniu
require_once 'config.php';

// Sprawdzenie autoryzacji
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    redirect('index.php');
}

// Sprawdź czy faktycznie to pierwsze logowanie
$pdo = getDB();
$stmt = $pdo->prepare("SELECT first_login, username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user['first_login']) {
    redirect('dashboard.php'); // Jeśli już zmienił hasło, przekieruj na dashboard
}

$error = '';
$success = '';

// Obsługa zmiany hasła
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Walidacja
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'Wszystkie pola są wymagane';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Nowe hasło musi mieć co najmniej 8 znaków';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Nowe hasła nie są identyczne';
    } elseif ($currentPassword === $newPassword) {
        $error = 'Nowe hasło musi być różne od obecnego';
    } else {
        // Sprawdź obecne hasło
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        
        if (!password_verify($currentPassword, $userData['password'])) {
            $error = 'Obecne hasło jest nieprawidłowe';
        } else {
            // Zapisz nowe hasło
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, first_login = FALSE, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
            
            // Log akcji
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_logs (admin_user_id, action, target_user_id, target_username, details, ip_address) 
                    VALUES (?, 'PASSWORD_RESET', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'], $_SESSION['user_id'], $user['username'],
                    'User changed password on first login',
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } catch (Exception $e) {
                // Ignoruj błąd logowania, ważne że hasło się zmieniło
            }
            
            $success = 'Hasło zostało zmienione pomyślnie! Za chwilę nastąpi przekierowanie...';
            
            // Przekierowanie po 3 sekundach
            header("refresh:3;url=dashboard.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zmiana hasła - <?php echo SYSTEM_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 500px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
        }
        
        .logo svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        
        .title {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .subtitle {
            font-size: 16px;
            color: #64748b;
            line-height: 1.5;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fef3c7, #fcd34d);
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .warning-icon {
            color: #d97706;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .warning-text {
            color: #92400e;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 16px;
        }
        
        .form-input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 2px solid #ef4444;
            color: #dc2626;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .success-message {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            border: 2px solid #10b981;
            color: #059669;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        
        .password-requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .password-requirements h4 {
            color: #374151;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .password-requirements ul {
            list-style: none;
            padding: 0;
        }
        
        .password-requirements li {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .password-requirements li::before {
            content: '•';
            color: #3b82f6;
            font-weight: bold;
        }
        
        .user-info {
            background: #eff6ff;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .user-info .username {
            font-weight: 600;
            color: #1e40af;
            font-size: 18px;
        }
        
        .user-info .role {
            color: #3730a3;
            font-size: 14px;
            margin-top: 5px;
        }
        
        @media (max-width: 600px) {
            .reset-container {
                padding: 30px 20px;
            }
            
            .title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="header">
            <div class="logo">
                <svg viewBox="0 0 24 24">
                    <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.6 14.8,10V11.5C15.4,12.1 16,12.8 16,14V18C16,19.1 15.1,20 14,20H10C8.9,20 8,19.1 8,18V14C8,12.8 8.6,12.1 9.2,11.5V10C9.2,8.6 10.6,7 12,7M12,8.2C11.2,8.2 10.5,8.7 10.5,10V11.5H13.5V10C13.5,8.7 12.8,8.2 12,8.2Z"/>
                </svg>
            </div>
            <h1 class="title">Zmiana hasła wymagana</h1>
            <p class="subtitle">To Twoje pierwsze logowanie. Ze względów bezpieczeństwa musisz zmienić tymczasowe hasło.</p>
        </div>
        
        <div class="user-info">
            <div class="username"><?php echo htmlspecialchars($user['username']); ?></div>
            <div class="role">Funkcjonariusz</div>
        </div>
        
        <div class="warning-box">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="warning-text">
                <strong>Ważne!</strong> Twoje tymczasowe hasło zostało wygenerowane przez administratora. 
                Aby zapewnić bezpieczeństwo konta, musisz je zmienić na własne, bezpieczne hasło.
            </div>
        </div>
        
        <?php if ($error): ?>
        <div class="error-message">
            <i class="fas fa-times-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="current_password">Obecne hasło (tymczasowe)</label>
                <input type="password" class="form-input" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="new_password">Nowe hasło</label>
                <input type="password" class="form-input" id="new_password" name="new_password" required autocomplete="new-password">
                <div class="password-requirements">
                    <h4>Wymagania dotyczące hasła:</h4>
                    <ul>
                        <li>Co najmniej 8 znaków</li>
                        <li>Różne od obecnego hasła</li>
                        <li>Zalecane: użyj kombinacji liter, cyfr i znaków specjalnych</li>
                        <li>Unikaj łatwych do odgadnięcia haseł</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="confirm_password">Potwierdź nowe hasło</label>
                <input type="password" class="form-input" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-key"></i>
                Zmień hasło i kontynuuj
            </button>
        </form>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        // Real-time password validation
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const requirements = document.querySelectorAll('.password-requirements li');
            
            // Check length
            if (requirements[0]) {
                requirements[0].style.color = password.length >= 8 ? '#059669' : '#64748b';
            }
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    this.style.borderColor = '#10b981';
                    this.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
                } else {
                    this.style.borderColor = '#ef4444';
                    this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                }
            } else {
                this.style.borderColor = '#e5e7eb';
                this.style.boxShadow = 'none';
            }
        });
        
        // Prevent going back
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };
    </script>
</body>
</html>