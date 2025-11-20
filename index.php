<?php
// index.php - Strona logowania z obsługą pierwszego logowania
require_once 'config.php';

// Jeśli już zalogowany, przekieruj do dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Sprawdź czy nie trzeba wymusić zmiany hasła
    if (isset($_SESSION['first_login']) && $_SESSION['first_login']) {
        header('Location: force_password_reset.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$error = '';
$success = '';
$show_password_reset = false;

// Obsługa logowania
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        // Zmiana hasła przy pierwszym logowaniu
        $username = $_POST['username'] ?? '';
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password) || strlen($new_password) < 6) {
            $error = 'Nowe hasło musi mieć minimum 6 znaków';
            $show_password_reset = true;
        } elseif ($new_password !== $confirm_password) {
            $error = 'Nowe hasła nie są identyczne';
            $show_password_reset = true;
        } else {
            // Sprawdź stare hasło i zmień na nowe
            if (changeFirstLoginPassword($username, $old_password, $new_password)) {
                $success = 'Hasło zostało zmienione! Możesz się teraz zalogować.';
                $show_password_reset = false;
                // Wyczyść tymczasowe dane sesji
                unset($_SESSION['temp_username']);
                unset($_SESSION['temp_password']);
            } else {
                $error = 'Błąd podczas zmiany hasła lub nieprawidłowe stare hasło';
                $show_password_reset = true;
            }
        }
    } else {
        // Normalne logowanie
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Użyj funkcji loginUser z config.php
        $login_result = loginUser($username, $password);
        
        if ($login_result === true) {
            // Sprawdź czy to pierwsze logowanie
            if (isset($_SESSION['first_login']) && $_SESSION['first_login']) {
                // Wyloguj i pokaż formularz zmiany hasła
                $temp_username = $_SESSION['username'];
                session_destroy();
                session_start();
                $_SESSION['temp_username'] = $temp_username;
                $_SESSION['temp_password'] = $password;
                $show_password_reset = true;
                $success = 'To jest Twoje pierwsze logowanie. Musisz zmienić hasło.';
            } else {
                header('Location: dashboard.php');
                exit();
            }
        } else {
            $error = 'Nieprawidłowa nazwa użytkownika lub hasło';
        }
    }
}

// Sprawdź czy mamy tymczasowe dane do zmiany hasła
if (isset($_SESSION['temp_username']) && !$show_password_reset && empty($success)) {
    $show_password_reset = true;
}

// Funkcja do zmiany hasła przy pierwszym logowaniu
function changeFirstLoginPassword($username, $old_password, $new_password) {
    global $USERS;
    
    $pdo = getDB();
    if ($pdo) {
        try {
            // Sprawdź stare hasło
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($old_password, $user['password'])) {
                // Zmień hasło i ustaw first_login na FALSE
                $new_hash = hashPassword($new_password);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, first_login = FALSE WHERE id = ?");
                $stmt->execute([$new_hash, $user['id']]);
                
                return true;
            }
            return false;
        } catch (PDOException $e) {
            // Fallback do systemu demo
            error_log("Database error in changeFirstLoginPassword: " . $e->getMessage());
        }
    }
    
    // Zwróć false jeśli baza danych nie działa
    return false;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_NAME; ?> - Logowanie</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            gap: 40px;
            align-items: center;
        }
        
        .login-box {
            flex: 1;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            animation: slideInLeft 0.6s ease-out;
        }
        
        .info-panel {
            flex: 1;
            max-width: 500px;
            color: white;
            animation: slideInRight 0.6s ease-out;
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .logo {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .logo svg {
            width: 60px;
            height: 60px;
            fill: white;
        }
        
        h1 {
            text-align: center;
            color: #1e293b;
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 40px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8fafc;
        }
        
        input:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 5px;
        }
        
        .toggle-password:hover {
            color: #3b82f6;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
        }
        
        .remember-me input {
            width: auto;
            margin: 0;
        }
        
        .remember-me label {
            margin: 0;
            font-size: 14px;
            color: #64748b;
            cursor: pointer;
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .error-msg {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            animation: shake 0.5s;
        }
        
        .success-msg {
            background: #dcfce7;
            color: #16a34a;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .demo-info {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }
        
        .demo-info h3 {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .demo-account {
            background: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        
        .demo-account strong {
            color: #1e293b;
        }
        
        .info-panel h2 {
            font-size: 36px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .info-panel p {
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .feature-icon svg {
            width: 24px;
            height: 24px;
            fill: white;
        }
        
        .feature-text h3 {
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .feature-text p {
            font-size: 14px;
            margin: 0;
            opacity: 0.8;
        }
        
        .version {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            opacity: 0.7;
        }
        
        .password-reset-form {
            display: none;
        }
        
        .password-reset-form.active {
            display: block;
        }
        
        .password-info {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .password-info h3 {
            color: #92400e;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .password-info p {
            color: #92400e;
            font-size: 14px;
            margin: 0;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 968px) {
            .login-container {
                flex-direction: column;
            }
            
            .info-panel {
                max-width: 450px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2L3.5 7v6c0 5.55 3.84 10.74 8.5 12 4.66-1.26 8.5-6.45 8.5-12V7L12 2zm0 10h7c-.53 4.12-3.28 7.79-7 8.94V12H5V8.3l7-3.11v6.81z"/>
                </svg>
            </div>
            
            <h1><?php echo SYSTEM_NAME; ?></h1>
            <p class="subtitle"><?php echo $show_password_reset ? 'Zmień swoje hasło' : 'Zaloguj się do panelu zarządzania'; ?></p>
            
            <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Normalny formularz logowania -->
            <form method="POST" action="" class="login-form <?php echo $show_password_reset ? '' : 'active'; ?>" <?php echo $show_password_reset ? 'style="display: none;"' : ''; ?>>
                <div class="form-group">
                    <label for="username">Nazwa użytkownika</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Hasło</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Zapamiętaj mnie</label>
                </div>
                
                <button type="submit" class="login-btn">Zaloguj się</button>
            </form>
            
            <!-- Formularz zmiany hasła przy pierwszym logowaniu -->
            <form method="POST" action="" class="password-reset-form <?php echo $show_password_reset ? 'active' : ''; ?>">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="username" value="<?php echo htmlspecialchars($_SESSION['temp_username'] ?? ''); ?>">
                <input type="hidden" name="old_password" value="<?php echo htmlspecialchars($_SESSION['temp_password'] ?? ''); ?>">
                
                <div class="password-info">
                    <h3>⚠️ Pierwsze logowanie</h3>
                    <p>Ze względów bezpieczeństwa musisz zmienić swoje tymczasowe hasło.</p>
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nowe hasło (minimum 6 znaków)</label>
                    <div class="password-wrapper">
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Potwierdź nowe hasło</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="login-btn">Zmień hasło</button>
                
                <div class="back-to-login">
                    <a href="index.php">← Powrót do logowania</a>
                </div>
            </form>
                     
            <p class="version">Wersja <?php echo SYSTEM_VERSION; ?></p>
        </div>
        
        <div class="info-panel">
            <h2>System Policyjny</h2>
            <p>Zaawansowany system zarządzania jednostką policyjną z pełną kontrolą nad patrolami, zgłoszeniami i dokumentacją.</p>
            
            <div class="feature">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h3>Zarządzanie jednostkami</h3>
                    <p>Pełna kontrola nad patrolami i funkcjonariuszami</p>
                </div>
            </div>
            
            <div class="feature">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M16,11V3H8v6H2v12h20V11H16z M10,5h4v14h-4V5z M4,11h4v8H4V11z M20,19h-4v-6h4V19z"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h3>Statystyki i raporty</h3>
                    <p>Szczegółowe analizy i raporty w czasie rzeczywistym</p>
                </div>
            </div>
            
            <div class="feature">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 8.5 12 4.66-1.26 8.5-6.45 8.5-12V5l-8.5-4z"/>
                    </svg>
                </div>
                <div class="feature-text">
                    <h3>Bezpieczeństwo</h3>
                    <p>Najwyższe standardy ochrony danych</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
        }
        
        // Walidacja hasła w czasie rzeczywistym
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password.length < 6) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#10b981';
            }
            
            if (confirmPassword && password !== confirmPassword) {
                document.getElementById('confirm_password').style.borderColor = '#ef4444';
            } else if (confirmPassword) {
                document.getElementById('confirm_password').style.borderColor = '#10b981';
            }
        });
        
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#10b981';
            }
        });
    </script>
</body>
</html>