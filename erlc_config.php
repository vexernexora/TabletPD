<?php
// erlc_config.php - ERLC Configuration

return [
    'erlc_api' => [
        'base_url' => 'https://api.policeroleplay.community/v1',
        'server_key' => $_ENV['ERLC_SERVER_KEY'] ?? 'your_erlc_server_key_here',
        'server_id' => $_ENV['ERLC_SERVER_ID'] ?? 'your_server_id_here',
        'timeout' => 10,
        'retry_attempts' => 3,
        'rate_limit' => 60 // requests per minute
    ],
    
    'map_settings' => [
        'default_zoom' => 1.0,
        'min_zoom' => 0.3,
        'max_zoom' => 5.0,
        'update_interval' => 2000, // milliseconds
        'marker_size' => 40,
        'map_bounds' => [
            'min_x' => -2000,
            'max_x' => 2000,
            'min_z' => -2000,
            'max_z' => 2000
        ]
    ],
    
    'factions' => [
        'LAPD' => [
            'name' => 'Los Angeles Police Department',
            'color' => '#3b82f6',
            'gradient' => 'linear-gradient(135deg, #3b82f6, #1e40af)',
            'icon' => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'
        ],
        'LASD' => [
            'name' => 'Los Angeles Sheriff Department',
            'color' => '#f59e0b',
            'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)',
            'icon' => 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z'
        ],
        'GPS' => [
            'name' => 'GPS Tracker',
            'color' => '#6b7280',
            'gradient' => 'linear-gradient(135deg, #6b7280, #4b5563)',
            'icon' => 'M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0013 3.06V1h-2v2.06A8.994 8.994 0 003.06 11H1v2h2.06A8.994 8.994 0 0011 20.94V23h2v-2.06A8.994 8.994 0 0020.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z'
        ]
    ],
    
    'vehicle_types' => [
        'Police Cruiser' => '#3b82f6',
        'Sheriff Cruiser' => '#f59e0b',
        'Motorcycle' => '#10b981',
        'SWAT Van' => '#1f2937',
        'Helicopter' => '#8b5cf6',
        'Ambulance' => '#ef4444',
        'Fire Truck' => '#dc2626'
    ],
    
    'status_mapping' => [
        0 => ['name' => 'Offline', 'color' => '#6b7280'],
        1 => ['name' => 'On Duty', 'color' => '#10b981'],
        2 => ['name' => 'Busy', 'color' => '#f59e0b'],
        3 => ['name' => 'Off Duty', 'color' => '#ef4444']
    ],
    
    'security' => [
        'require_auth' => true,
        'allowed_roles' => ['admin', 'supervisor', 'dispatcher'],
        'ip_whitelist' => [],
        'rate_limit_enabled' => true
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'file' => 'logs/erlc_map.log',
        'max_size' => '10MB',
        'retain_days' => 30
    ],
    
    'features' => [
        'real_time_tracking' => true,
        'vehicle_tracking' => true,
        'route_history' => true,
        'alert_system' => true,
        'officer_status' => true,
        'dispatch_integration' => false
    ]
];
?>
