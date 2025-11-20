<?php
// ---- NEXORA ERLC API (JSON) ----
// Wymaga: PHP 7.4+ z cURL

declare(strict_types=1);
session_start();

/*
 // (opcjonalnie) odkomentuj, jeśli chcesz wymagać zalogowanej sesji do API:
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>'Unauthorized (no session)']);
    exit;
}
*/

// Szybki „ping” dla autodetekcji ścieżki przez UI
if (isset($_GET['ping'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['success'=>true,'pong'=>true,'ts'=>time()]);
    exit;
}

/* =========================
   KONFIGURACJA
   ========================= */
define('ERLC_SERVER_KEY', 'OBszqACLXDqpcxtknUnQ-fFzFRzbqYIlLMALHjuGAuKTzXEoSwGvYFAPwfOef'); // <-- PODMIEŃ NA SWÓJ KLUCZ
define('ERLC_SERVER_ID',  '');     // opcjonalnie: jeśli znasz ID konkretnego serwera
define('REQUEST_TIMEOUT', 12);
define('DEBUG_LOG', true);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!ERLC_SERVER_KEY || ERLC_SERVER_KEY === 'PASTE_YOUR_REAL_ERLC_SERVER_KEY_HERE') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'ERLC_SERVER_KEY not configured','timestamp'=>time()]);
    exit;
}

/* =========================
   HELPERY HTTP/ERLC
   ========================= */
function http_get_json(string $url, array $headers = []): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => REQUEST_TIMEOUT,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (DEBUG_LOG) {
        error_log("HTTP GET $url -> $code");
        if ($err) error_log("cURL error: $err");
        if ($raw && $code >= 400) error_log("Body: $raw");
    }
    if ($err) return ['ok'=>false,'code'=>$code,'error'=>$err];

    $json = json_decode($raw ?? '', true);
    if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
        return ['ok'=>false,'code'=>$code,'error'=>'Invalid JSON from API'];
    }
    return ['ok'=>($code>=200 && $code<300), 'code'=>$code, 'data'=>$json];
}

function erlc_headers(): array {
    return [
        'Server-Key: ' . ERLC_SERVER_KEY,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
}

function erlc_get_server_id(): array {
    if (ERLC_SERVER_ID !== '') return ['ok'=>true,'server_id'=>ERLC_SERVER_ID];
    $r = http_get_json('https://api.policeroleplay.community/v1/servers', erlc_headers());
    if (!$r['ok']) return ['ok'=>false, 'error'=>$r['error'] ?? ('HTTP '.$r['code'])];
    $list = $r['data']['data'] ?? [];
    if (!is_array($list) || !count($list)) return ['ok'=>false,'error'=>'No servers visible for this key'];
    $first = $list[0];
    $id = $first['id'] ?? $first['serverId'] ?? null;
    if (!$id) return ['ok'=>false,'error'=>'Server ID missing in /servers response'];
    return ['ok'=>true,'server_id'=>(string)$id];
}

function erlc_get_players(string $serverId): array {
    $url = 'https://api.policeroleplay.community/v1/servers/'.rawurlencode($serverId).'/players';
    $r   = http_get_json($url, erlc_headers());
    if (!$r['ok']) return ['ok'=>false,'error'=>$r['error'] ?? ('HTTP '.$r['code'])];

    $players = $r['data']['data'] ?? [];
    if (!is_array($players)) $players = [];

    $normalized = [];
    foreach ($players as $p) {
        $name = $p['username'] ?? $p['robloxUsername'] ?? $p['name'] ?? '';
        $rid  = $p['robloxUserId'] ?? $p['robloxId'] ?? $p['id'] ?? 0;
        $ping = (int)($p['ping'] ?? 0);
        $pos  = $p['position'] ?? $p['location'] ?? [];
        $x = (float)($pos['x'] ?? $pos['X'] ?? 0);
        $z = (float)($pos['z'] ?? $pos['Z'] ?? ($pos['y'] ?? 0));
        $normalized[] = [
            'id'       => (int)$rid,
            'username' => (string)$name,
            'position' => ['x'=>$x,'z'=>$z],
            'ping'     => $ping,
            'team'     => $p['team'] ?? ($p['department'] ?? 'Unknown'),
        ];
    }
    return ['ok'=>true,'players'=>$normalized];
}

/* =========================
   „BAZA” OFICERÓW (demo)
   ========================= */
$OFFICERS = [
    1 => ['name'=>'John Doe',   'badge'=>'LAPD001','faction'=>'LAPD','erlc_name'=>'JohnDoe_ERLC'],
    2 => ['name'=>'Jane Smith', 'badge'=>'LAPD002','faction'=>'LAPD','erlc_name'=>'JaneSmith_ERLC'],
    3 => ['name'=>'Mike Wilson','badge'=>'LASD001','faction'=>'LASD','erlc_name'=>'MikeWilson_ERLC'],
    4 => ['name'=>'Sarah Brown','badge'=>'LASD002','faction'=>'LASD','erlc_name'=>'SarahBrown_ERLC'],
    5 => ['name'=>'David Lee',  'badge'=>'GPS001', 'faction'=>'GPS', 'erlc_name'=>'DavidLee_ERLC'],
];

/* =========================
   LOGIKA API
   ========================= */
$serverIdRes = erlc_get_server_id();
if (!$serverIdRes['ok']) {
    http_response_code(502);
    echo json_encode(['success'=>false,'error'=>'Servers list failed: '.$serverIdRes['error'],'timestamp'=>time()]);
    exit;
}
$playersRes = erlc_get_players($serverIdRes['server_id']);
if (!$playersRes['ok']) {
    http_response_code(502);
    echo json_encode(['success'=>false,'error'=>'Players fetch failed: '.$playersRes['error'],'timestamp'=>time()]);
    exit;
}

$byName = [];
foreach ($playersRes['players'] as $pl) $byName[strtolower($pl['username'])] = $pl;

$active = [];
foreach ($OFFICERS as $id=>$off) {
    $needle = strtolower($off['erlc_name']);
    if (isset($byName[$needle])) {
        $p = $byName[$needle];
        $active[] = [
            'id'           => $id,
            'name'         => $off['name'],
            'badge_number' => $off['badge'],
            'faction'      => $off['faction'],
            'position'     => ['x'=>$p['position']['x'] ?? 0, 'z'=>$p['position']['z'] ?? 0],
            'ping'         => $p['ping'] ?? 0,
        ];
    }
}

echo json_encode([
    'success'   => true,
    'officers'  => $active,
    'total'     => count($active),
    'server_id' => $serverIdRes['server_id'],
    'timestamp' => time(),
]);
