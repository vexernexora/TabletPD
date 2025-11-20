<?php
// google.php - Funkcjonalna przeglądarka internetowa z proxy
session_start();

// Sprawdzenie autoryzacji
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$current_url = isset($_GET['url']) ? $_GET['url'] : 'https://www.google.com';
$proxy_mode = isset($_GET['proxy']) ? true : false;

// Sanitize URL
if (!filter_var($current_url, FILTER_VALIDATE_URL)) {
    $current_url = 'https://www.google.com/search?q=' . urlencode($current_url);
}

// Lista domen, które często blokują iframe
$blocked_domains = [
    'pornhub.com', 'www.pornhub.com',
    'xvideos.com', 'www.xvideos.com',
    'xnxx.com', 'www.xnxx.com',
    'youtube.com', 'www.youtube.com', 'm.youtube.com',
    'facebook.com', 'www.facebook.com', 'm.facebook.com',
    'twitter.com', 'www.twitter.com', 'x.com', 'www.x.com',
    'instagram.com', 'www.instagram.com',
    'tiktok.com', 'www.tiktok.com',
    'netflix.com', 'www.netflix.com',
    'amazon.com', 'www.amazon.com',
    'ebay.com', 'www.ebay.com',
    'paypal.com', 'www.paypal.com',
    'github.com', 'www.github.com',
    'banking.', 'bank.', 'login.'
];

// Lista domen całkowicie zablokowanych
$completely_blocked_domains = [
    'tablet.vexer.site',
    'vexer.site'
];

function isDomainBlocked($url, $blocked_domains) {
    $parsed_url = parse_url($url);
    if (!$parsed_url || !isset($parsed_url['host'])) {
        return false;
    }
    
    $host = strtolower($parsed_url['host']);
    foreach ($blocked_domains as $blocked) {
        // Exact match or subdomain match
        if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
            return true;
        }
    }
    return false;
}

function isDomainCompletelyBlocked($url, $completely_blocked_domains) {
    $parsed_url = parse_url($url);
    if (!$parsed_url || !isset($parsed_url['host'])) {
        return false;
    }
    
    $host = strtolower($parsed_url['host']);
    foreach ($completely_blocked_domains as $blocked) {
        // Exact match or subdomain match
        if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
            return true;
        }
    }
    return false;
}

$is_blocked = isDomainBlocked($current_url, $blocked_domains);
$is_completely_blocked = isDomainCompletelyBlocked($current_url, $completely_blocked_domains);

// Sprawdź czy strona jest całkowicie zablokowana
if ($is_completely_blocked) {
    // Przekieruj na stronę błędu z komunikatem
    $blocked_domain = parse_url($current_url, PHP_URL_HOST);
    $error_message = "Dostęp do domeny " . htmlspecialchars($blocked_domain) . " został zablokowany przez administrator systemu.";
}

// Jeśli to jest proxy request, obsłuż go
if (isset($_GET['proxy_fetch']) && $_GET['proxy_fetch'] === '1') {
    $target_url = $_GET['url'];
    
    if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        exit('Invalid URL');
    }
    
    // Sprawdź czy domena nie jest całkowicie zablokowana
    if (isDomainCompletelyBlocked($target_url, $completely_blocked_domains)) {
        http_response_code(403);
        exit('Access to this domain is blocked by system administrator.');
    }
    
    // Inicjalizacja cURL
    $ch = curl_init();
    
    // Ustawienia cURL dla lepszej kompatybilności
    curl_setopt_array($ch, [
        CURLOPT_URL => $target_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: pl-PL,pl;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1'
        ],
        CURLOPT_ENCODING => '',
        CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/browser_cookies.txt',
        CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/browser_cookies.txt'
    ]);
    
    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($content === false || !empty($error)) {
        http_response_code(500);
        exit('Proxy Error: ' . $error);
    }
    
    if ($http_code >= 400) {
        http_response_code($http_code);
        exit('HTTP Error: ' . $http_code);
    }
    
    // Przetwarzanie zawartości HTML
    if (strpos($content_type, 'text/html') !== false) {
        $base_url = parse_url($target_url);
        $base_domain = $base_url['scheme'] . '://' . $base_url['host'];
        $base_path = dirname($target_url);
        
        // Naprawa względnych linków
        $content = preg_replace_callback('/(?:src|href|action)=["\']([^"\']+)["\']/i', function($matches) use ($base_domain, $base_path, $target_url) {
            $url = $matches[1];
            
            // Skip data: URLs, javascript:, mailto:, tel:
            if (preg_match('/^(data:|javascript:|mailto:|tel:|#)/', $url)) {
                return $matches[0];
            }
            
            // Przekształć względne URL na bezwzględne
            if (substr($url, 0, 2) === '//') {
                $url = parse_url($target_url, PHP_URL_SCHEME) . ':' . $url;
            } elseif (substr($url, 0, 1) === '/') {
                $url = $base_domain . $url;
            } elseif (!preg_match('/^https?:\/\//', $url)) {
                $url = $base_path . '/' . $url;
            }
            
            // Proxy internal links
            if (strpos($url, $base_domain) === 0) {
                $url = 'google.php?proxy_fetch=1&url=' . urlencode($url);
            }
            
            return str_replace($matches[1], $url, $matches[0]);
        }, $content);
        
        // Usuń/zmodyfikuj problematyczne nagłówki bezpieczeństwa
        $content = preg_replace('/<meta[^>]*http-equiv=["\']?X-Frame-Options[^>]*>/i', '', $content);
        $content = preg_replace('/<meta[^>]*http-equiv=["\']?Content-Security-Policy[^>]*>/i', '', $content);
        
        // Dodaj base tag
        if (strpos($content, '<head>') !== false) {
            $content = str_replace('<head>', '<head><base href="' . htmlspecialchars($base_domain) . '">', $content);
        }
    }
    
    // Ustaw odpowiednie nagłówki
    if ($content_type) {
        header('Content-Type: ' . $content_type);
    }
    
    // Usuń problematyczne nagłówki bezpieczeństwa
    header_remove('X-Frame-Options');
    header_remove('Content-Security-Policy');
    
    echo $content;
    exit();
}

// Jeśli strona jest zablokowana i nie używamy proxy, przekieruj z proxy
if ($is_blocked && !$proxy_mode && isset($_GET['url']) && !$is_completely_blocked) {
    $proxy_url = 'google.php?url=' . urlencode($current_url) . '&proxy=1';
    header('Location: ' . $proxy_url);
    exit();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Przeglądarka - System Policyjny</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .browser-toolbar {
            background: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 50px;
        }
        
        .nav-buttons {
            display: flex;
            gap: 4px;
        }
        
        .nav-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 4px;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .nav-btn:hover {
            background: #e8eaed;
        }
        
        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .nav-btn svg {
            width: 16px;
            height: 16px;
            fill: #5f6368;
        }
        
        .address-bar {
            flex: 1;
            max-width: 600px;
            margin: 0 16px;
            position: relative;
        }
        
        .address-input {
            width: 100%;
            height: 36px;
            padding: 8px 40px 8px 12px;
            border: 1px solid #dadce0;
            border-radius: 18px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .address-input:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 1px #1a73e8;
        }
        
        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: none;
            background: none;
            cursor: pointer;
        }
        
        .search-btn svg {
            width: 16px;
            height: 16px;
            fill: #5f6368;
        }
        
        .browser-content {
            flex: 1;
            position: relative;
            background: white;
        }
        
        .browser-frame {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #1a73e8;
            transform-origin: left;
            animation: loading 2s ease-in-out;
            z-index: 1000;
        }
        
        @keyframes loading {
            0% { transform: scaleX(0); }
            50% { transform: scaleX(0.7); }
            100% { transform: scaleX(1); opacity: 0; }
        }
        
        .status-bar {
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            padding: 4px 16px;
            font-size: 12px;
            color: #5f6368;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .quick-links {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .quick-link {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            color: #1a73e8;
            font-size: 12px;
            transition: all 0.2s ease;
        }
        
        .quick-link:hover {
            background: #e8f0fe;
        }
        
        .proxy-indicator {
            background: #fef7e0;
            color: #b45309;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .proxy-notice {
            background: #e8f5e8;
            border: 1px solid #a5d6a7;
            border-radius: 8px;
            padding: 16px;
            margin: 20px;
            color: #2e7d32;
        }
        
        .proxy-notice h3 {
            margin-bottom: 8px;
            color: #2e7d32;
        }
        
        .proxy-notice button {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 12px;
            margin-right: 8px;
        }
        
        .proxy-notice button:hover {
            background: #1557b0;
        }
        
        .blocked-page {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 40px;
            background: #fff5f5;
        }
        
        .blocked-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 24px;
            fill: #dc2626;
        }
        
        .blocked-page h2 {
            color: #dc2626;
            margin-bottom: 16px;
            font-size: 24px;
        }
        
        .blocked-page p {
            color: #7f1d1d;
            margin-bottom: 24px;
            max-width: 500px;
            line-height: 1.6;
        }
        
        .blocked-page .warning-box {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            max-width: 600px;
        }
        
        .blocked-page .warning-box h3 {
            color: #dc2626;
            margin-bottom: 12px;
        }
        
        .blocked-page .warning-box ul {
            text-align: left;
            color: #7f1d1d;
            margin: 12px 0;
        }
        
        .blocked-page .warning-box li {
            margin: 8px 0;
        }
        
        .error-page {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 40px;
        }
        
        .error-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .home-page {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #f8f9fa;
        }
        
        .google-logo {
            margin-bottom: 32px;
        }
        
        .search-container {
            width: 100%;
            max-width: 600px;
            margin-bottom: 32px;
        }
        
        .search-input {
            width: 100%;
            height: 44px;
            padding: 12px 16px;
            border: 1px solid #dadce0;
            border-radius: 24px;
            font-size: 16px;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .search-input:focus {
            border-color: #1a73e8;
            box-shadow: 0 2px 5px 1px rgba(64,60,67,.16);
        }
        
        .search-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 32px;
        }
        
        .search-button {
            padding: 8px 20px;
            border: 1px solid #f8f9fa;
            border-radius: 4px;
            background: #f8f9fa;
            color: #3c4043;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .search-button:hover {
            box-shadow: 0 1px 1px rgba(0,0,0,.1);
            background: #f8f9fa;
            border: 1px solid #dadce0;
        }
        
        .open-externally {
            background: #1a73e8;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: all 0.2s ease;
        }
        
        .open-externally:hover {
            background: #1557b0;
            color: white;
        }
        
        .proxy-content {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }
    </style>
</head>
<body>
    <div class="browser-toolbar">
        <div class="nav-buttons">
            <button class="nav-btn" onclick="goBack()" id="backBtn">
                <svg viewBox="0 0 24 24">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2z"/>
                </svg>
            </button>
            <button class="nav-btn" onclick="goForward()" id="forwardBtn">
                <svg viewBox="0 0 24 24">
                    <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                </svg>
            </button>
            <button class="nav-btn" onclick="refresh()">
                <svg viewBox="0 0 24 24">
                    <path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                </svg>
            </button>
            <button class="nav-btn" onclick="goHome()">
                <svg viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
            </button>
        </div>
        
        <div class="address-bar">
            <input type="text" class="address-input" id="addressBar" placeholder="Wyszukaj w Google lub wpisz adres URL" value="<?php echo htmlspecialchars($current_url); ?>" onkeypress="handleEnter(event)">
            <button class="search-btn" onclick="navigateToUrl()">
                <svg viewBox="0 0 24 24">
                    <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
            </button>
        </div>
        
        <div class="quick-links">
            <a href="#" class="quick-link" onclick="loadUrl('https://www.google.com')">Google</a>
            <a href="#" class="quick-link" onclick="loadUrl('https://images.google.com')">Obrazy</a>
            <a href="#" class="quick-link" onclick="loadUrl('https://maps.google.com')">Mapy</a>
            <a href="#" class="quick-link" onclick="loadUrl('https://translate.google.com')">Tłumacz</a>
        </div>
    </div>
    
    <div class="browser-content">
        <div class="loading-overlay" id="loadingBar" style="display: none;"></div>
        
        <!-- Home Page -->
        <div class="home-page" id="homePage" style="<?php echo ($current_url === 'https://www.google.com' && !$proxy_mode) ? 'display: flex;' : 'display: none;'; ?>">
            <div class="google-logo">
                <svg width="200" height="68" viewBox="0 0 272 92">
                    <g fill="none" fill-rule="evenodd">
                        <path d="M115.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18C71.25 34.32 81.24 25 93.5 25s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44S80.99 39.2 80.99 47.18c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z" fill="#EA4335"/>
                        <path d="M163.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18c0-12.85 9.99-22.18 22.25-22.18s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44s-12.51 5.46-12.51 13.44c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z" fill="#FBBC05"/>
                        <path d="M209.75 26.34v39.82c0 16.38-9.66 23.07-21.08 23.07-10.75 0-17.22-7.19-19.66-13.07l8.48-3.53c1.51 3.61 5.21 7.87 11.17 7.87 7.31 0 11.84-4.51 11.84-13v-3.19h-.34c-2.18 2.69-6.38 5.04-11.68 5.04-11.09 0-21.25-9.66-21.25-22.09 0-12.52 10.16-22.26 21.25-22.26 5.29 0 9.49 2.35 11.68 4.96h.34v-3.61h9.25zm-8.56 20.92c0-7.81-5.21-13.52-11.84-13.52-6.72 0-12.35 5.71-12.35 13.52 0 7.73 5.63 13.36 12.35 13.36 6.63 0 11.84-5.63 11.84-13.36z" fill="#34A853"/>
                        <path d="M225 3v65h-9.5V3h9.5z" fill="#EA4335"/>
                        <path d="M262.02 54.48l7.56 5.04c-2.44 3.61-8.32 9.83-18.48 9.83-12.6 0-22.01-9.74-22.01-22.18 0-13.19 9.49-22.18 20.92-22.18 11.51 0 17.14 9.16 18.98 14.11l1.01 2.52-29.65 12.28c2.27 4.45 5.8 6.72 10.75 6.72 4.96 0 8.4-2.44 10.92-6.14zm-23.27-7.98l19.82-8.23c-1.09-2.77-4.37-4.7-8.23-4.7-4.95 0-11.84 4.37-11.59 12.93z" fill="#EA4335"/>
                        <path d="M35.29 41.41V32H67c.31 1.64.47 3.58.47 5.68 0 7.06-1.93 15.79-8.15 22.01-6.05 6.3-13.78 9.66-24.02 9.66C16.32 69.35.36 53.89.36 34.91.36 15.93 16.32.47 35.3.47c10.5 0 17.98 4.12 23.6 9.49l-6.64 6.64c-4.03-3.78-9.49-6.72-16.97-6.72-13.86 0-24.7 11.17-24.7 25.03 0 13.86 10.84 25.03 24.7 25.03 8.99 0 14.11-3.61 17.39-6.89 2.66-2.66 4.41-6.46 5.1-11.65l-22.49.01z" fill="#4285F4"/>
                    </g>
                </svg>
            </div>
            
            <div class="search-container">
                <input type="text" class="search-input" id="homeSearchInput" placeholder="Wyszukaj w Google lub wpisz adres URL" onkeypress="handleHomeSearch(event)">
            </div>
            
            <div class="search-buttons">
                <button class="search-button" onclick="performHomeSearch()">Szukaj w Google</button>
                <button class="search-button" onclick="loadUrl('https://www.google.com/doodles')">Szczęśliwy traf</button>
            </div>
        </div>
        
        <?php if ($is_blocked && $proxy_mode): ?>
        <!-- Proxy Notice -->
        <div class="proxy-notice">
            <h3>🔒 Tryb bezpiecznego proxy aktywny</h3>
            <p><strong><?php echo htmlspecialchars(parse_url($current_url, PHP_URL_HOST)); ?></strong> ładowana przez nasz bezpieczny serwer proxy.</p>
            <p>Wszystkie treści są przetwarzane przez nasz serwer dla pełnej kompatybilności.</p>
            <button onclick="window.open('<?php echo htmlspecialchars($current_url); ?>', '_blank')">Otwórz bezpośrednio</button>
            <button onclick="goHome()">Strona główna</button>
        </div>
        <?php endif; ?>
        
        <!-- Blocked Domain Page -->
        <?php if (isset($is_completely_blocked) && $is_completely_blocked): ?>
        <div class="blocked-page" id="blockedPage" style="display: flex;">
            <svg class="blocked-icon" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/>
            </svg>
            
            <h2>🚫 Dostęp zablokowany</h2>
            <p><?php echo isset($error_message) ? $error_message : 'Dostęp do tej strony został zablokowany.'; ?></p>
            
            <div class="warning-box">
                <h3>⚠️ Informacje systemowe</h3>
                <p><strong>Domena:</strong> <?php echo htmlspecialchars(parse_url($current_url, PHP_URL_HOST)); ?></p>
                <p><strong>Powód blokady:</strong> Strona znajduje się na liście domen zablokowanych przez administratora systemu.</p>
                
                <h4>Działania podjęte:</h4>
                <ul>
                    <li>Zablokowano dostęp przez przeglądarkę systemową</li>
                    <li>Zablokowano dostęp przez serwer proxy</li>
                    <li>Próba dostępu została zarejestrowana w logach bezpieczeństwa</li>
                </ul>
                
                <p style="margin-top: 16px;"><strong>Aby uzyskać dostęp do tej strony, skontaktuj się z administratorem systemu.</strong></p>
            </div>
            
            <button class="search-button" onclick="goHome()" style="margin: 8px; background: #dc2626; color: white;">Wróć do strony głównej</button>
        </div>
        <?php endif; ?>
        
        <!-- Browser Frame -->
        <?php if (isset($is_completely_blocked) && $is_completely_blocked): ?>
        <!-- Domain completely blocked - no frame shown -->
        <?php elseif ($proxy_mode && $is_blocked): ?>
        <!-- Proxy Mode Frame -->
        <iframe class="browser-frame proxy-content" id="browserFrame" 
                src="google.php?proxy_fetch=1&url=<?php echo urlencode($current_url); ?>" 
                style="<?php echo ($current_url === 'https://www.google.com' && !$proxy_mode) ? 'display: none;' : 'display: block;'; ?>"
                sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-popups-to-escape-sandbox">
        </iframe>
        <?php else: ?>
        <!-- Normal Frame -->
        <iframe class="browser-frame" id="browserFrame" 
                src="<?php echo ($current_url !== 'https://www.google.com') ? htmlspecialchars($current_url) : ''; ?>" 
                style="<?php echo ($current_url === 'https://www.google.com' && !$proxy_mode) ? 'display: none;' : 'display: block;'; ?>"
                sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-top-navigation allow-downloads">
        </iframe>
        <?php endif; ?>
        
        <!-- Error Page -->
        <div class="error-page" id="errorPage">
            <svg class="error-icon" viewBox="0 0 24 24" fill="#ea4335">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <h2>Nie można załadować strony</h2>
            <p>Ta strona może blokować osadzanie lub wystąpił błąd połączenia.</p>
            <button class="search-button" onclick="retryWithProxy()" style="margin: 8px;">Spróbuj z proxy</button>
            <button class="search-button" onclick="window.open(document.getElementById('addressBar').value, '_blank')" style="margin: 8px;">Otwórz w nowej karcie</button>
            <button class="search-button" onclick="goHome()">Wróć do strony głównej</button>
        </div>
    </div>
    
    <div class="status-bar">
        <span id="statusText">Gotowy</span>
        <?php if ($proxy_mode): ?>
        <div class="proxy-indicator">🔒 Bezpieczny Proxy</div>
        <?php endif; ?>
    </div>
    
    <script>
        let history = ['https://www.google.com'];
        let historyIndex = 0;
        
        function showLoading() {
            document.getElementById('loadingBar').style.display = 'block';
            document.getElementById('statusText').textContent = 'Ładowanie...';
            
            setTimeout(() => {
                document.getElementById('loadingBar').style.display = 'none';
                document.getElementById('statusText').textContent = 'Gotowy';
            }, 2000);
        }
        
        function updateButtons() {
            document.getElementById('backBtn').disabled = historyIndex <= 0;
            document.getElementById('forwardBtn').disabled = historyIndex >= history.length - 1;
        }
        
        function addToHistory(url) {
            if (historyIndex < history.length - 1) {
                history = history.slice(0, historyIndex + 1);
            }
            
            history.push(url);
            historyIndex = history.length - 1;
            updateButtons();
        }
        
        function loadUrl(url) {
            showLoading();
            
            // Lista domen wymagających proxy
            const blockedDomains = [
                'pornhub.com', 'www.pornhub.com',
                'xvideos.com', 'www.xvideos.com', 
                'xnxx.com', 'www.xnxx.com',
                'youtube.com', 'www.youtube.com', 'm.youtube.com',
                'facebook.com', 'www.facebook.com', 'm.facebook.com', 
                'twitter.com', 'www.twitter.com', 'x.com', 'www.x.com',
                'instagram.com', 'www.instagram.com',
                'tiktok.com', 'www.tiktok.com',
                'netflix.com', 'www.netflix.com',
                'amazon.com', 'www.amazon.com',
                'github.com', 'www.github.com'
            ];
            
            // Lista domen całkowicie zablokowanych
            const completelyBlockedDomains = [
                'tablet.vexer.site',
                'vexer.site'
            ];
            
            let needsProxy = false;
            let isCompletelyBlocked = false;
            
            try {
                const parsedUrl = new URL(url);
                const hostname = parsedUrl.hostname.toLowerCase();
                
                // Sprawdź czy domena jest całkowicie zablokowana
                isCompletelyBlocked = completelyBlockedDomains.some(domain => 
                    hostname === domain || hostname.endsWith('.' + domain)
                );
                
                if (isCompletelyBlocked) {
                    // Przekieruj do strony z komunikatem blokady
                    window.location.href = 'google.php?url=' + encodeURIComponent(url);
                    return;
                }
                
                // Sprawdź czy potrzebuje proxy
                needsProxy = blockedDomains.some(domain => 
                    hostname === domain || hostname.endsWith('.' + domain)
                );
            } catch (e) {
                // Invalid URL, continue
            }
            
            if (needsProxy) {
                // Przekieruj do trybu proxy
                window.location.href = 'google.php?url=' + encodeURIComponent(url) + '&proxy=1';
                return;
            }
            
            const frame = document.getElementById('browserFrame');
            const homePage = document.getElementById('homePage');
            const errorPage = document.getElementById('errorPage');
            const addressBar = document.getElementById('addressBar');
            
            // Ukryj wszystkie elementy
            frame.style.display = 'none';
            homePage.style.display = 'none';
            errorPage.style.display = 'none';
            
            if (url === 'https://www.google.com' || url === '') {
                // Pokaż stronę główną
                homePage.style.display = 'flex';
                addressBar.value = 'https://www.google.com';
            } else {
                // Załaduj w iframe
                frame.src = url;
                frame.style.display = 'block';
                addressBar.value = url;
                
                // Obsługa błędów iframe
                frame.onload = function() {
                    document.getElementById('statusText').textContent = 'Załadowano';
                };
                
                frame.onerror = function() {
                    frame.style.display = 'none';
                    errorPage.style.display = 'flex';
                    document.getElementById('statusText').textContent = 'Błąd ładowania';
                };
                
                // Wykryj blokowanie iframe
                setTimeout(() => {
                    try {
                        const frameDoc = frame.contentDocument || frame.contentWindow.document;
                        if (!frameDoc && frame.src) {
                            console.log('Iframe może być zablokowana');
                        }
                    } catch (e) {
                        console.log('Cross-origin iframe (normalne zachowanie)');
                    }
                }, 3000);
            }
            
            addToHistory(url);
        }
        
        function retryWithProxy() {
            const currentUrl = document.getElementById('addressBar').value;
            window.location.href = 'google.php?url=' + encodeURIComponent(currentUrl) + '&proxy=1';
        }
        
        function navigateToUrl() {
            const input = document.getElementById('addressBar').value.trim();
            if (!input) return;
            
            let url = input;
            
            // Sprawdź czy to URL czy zapytanie wyszukiwania
            if (url.includes('.') && !url.includes(' ')) {
                // Prawdopodobnie URL
                if (!url.startsWith('http://') && !url.startsWith('https://')) {
                    url = 'https://' + url;
                }
                
                // Sprawdź czy domena nie jest całkowicie zablokowana
                try {
                    const parsedUrl = new URL(url);
                    const hostname = parsedUrl.hostname.toLowerCase();
                    const isCompletelyBlocked = completelyBlockedDomains.some(domain => 
                        hostname === domain || hostname.endsWith('.' + domain)
                    );
                    
                    if (isCompletelyBlocked) {
                        // Przekieruj do strony z komunikatem blokady
                        window.location.href = 'google.php?url=' + encodeURIComponent(url);
                        return;
                    }
                } catch (e) {
                    // Invalid URL, continue
                }
            } else {
                // Zapytanie wyszukiwania
                url = 'https://www.google.com/search?q=' + encodeURIComponent(url);
            }
            
            // Użyj przekierowania strony dla prawidłowej obsługi proxy
            window.location.href = 'google.php?url=' + encodeURIComponent(url);
        }
        
        function handleEnter(event) {
            if (event.key === 'Enter') {
                navigateToUrl();
            }
        }
        
        function handleHomeSearch(event) {
            if (event.key === 'Enter') {
                performHomeSearch();
            }
        }
        
        function performHomeSearch() {
            const input = document.getElementById('homeSearchInput').value.trim();
            if (!input) {
                window.location.href = 'google.php';
                return;
            }
            
            let url;
            if (input.includes('.') && !input.includes(' ')) {
                // URL
                url = input.startsWith('http') ? input : 'https://' + input;
                
                // Sprawdź czy domena nie jest całkowicie zablokowana
                try {
                    const parsedUrl = new URL(url);
                    const hostname = parsedUrl.hostname.toLowerCase();
                    const isCompletelyBlocked = completelyBlockedDomains.some(domain => 
                        hostname === domain || hostname.endsWith('.' + domain)
                    );
                    
                    if (isCompletelyBlocked) {
                        // Przekieruj do strony z komunikatem blokady
                        window.location.href = 'google.php?url=' + encodeURIComponent(url);
                        return;
                    }
                } catch (e) {
                    // Invalid URL, continue
                }
            } else {
                // Wyszukiwanie
                url = 'https://www.google.com/search?q=' + encodeURIComponent(input);
            }
            
            window.location.href = 'google.php?url=' + encodeURIComponent(url);
        }
        
        function goBack() {
            if (historyIndex > 0) {
                historyIndex--;
                const url = history[historyIndex];
                window.location.href = 'google.php?url=' + encodeURIComponent(url);
            }
        }
        
        function goForward() {
            if (historyIndex < history.length - 1) {
                historyIndex++;
                const url = history[historyIndex];
                window.location.href = 'google.php?url=' + encodeURIComponent(url);
            }
        }
        
        function refresh() {
            window.location.reload();
        }
        
        function goHome() {
            window.location.href = 'google.php';
        }
        
        // Inicjalizacja
        updateButtons();
        
        // Fokus na pole wyszukiwania gdy strona główna jest widoczna
        const homeSearchInput = document.getElementById('homeSearchInput');
        if (homeSearchInput && document.getElementById('homePage').style.display === 'flex') {
            homeSearchInput.focus();
        }
        
        // Wykryj czy iframe został zablokowany
        const frame = document.getElementById('browserFrame');
        if (frame && frame.src && frame.style.display === 'block') {
            frame.addEventListener('load', function() {
                // Sprawdź czy iframe rzeczywiście się załadował
                setTimeout(() => {
                    try {
                        const rect = frame.getBoundingClientRect();
                        if (rect.height < 100) {
                            // Iframe może być zablokowany
                            console.log('Iframe prawdopodobnie zablokowany');
                            document.getElementById('statusText').textContent = 'Może wymagać proxy';
                        }
                    } catch (e) {
                        console.log('Nie można sprawdzić statusu iframe');
                    }
                }, 2000);
            });
        }
    </script>
</body>
</html>