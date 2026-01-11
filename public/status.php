<?php
/**
 * EduCRM Status Check
 * Quick health check for the application
 */

header('Content-Type: application/json');

$status = [
    'app' => 'EduCRM',
    'version' => '1.0.0',
    'status' => 'operational',
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

// Check if .env exists
$status['checks']['env_file'] = [
    'name' => 'Environment Configuration',
    'status' => file_exists(__DIR__ . '/../.env') ? 'ok' : 'error',
    'message' => file_exists(__DIR__ . '/../.env') ? 'Environment file found' : 'Environment file missing'
];

// Check if vendor directory exists
$status['checks']['dependencies'] = [
    'name' => 'Composer Dependencies',
    'status' => file_exists(__DIR__ . '/../vendor/autoload.php') ? 'ok' : 'error',
    'message' => file_exists(__DIR__ . '/../vendor/autoload.php') ? 'Dependencies installed' : 'Run composer install'
];

// Check if installed
$lockFile = __DIR__ . '/../storage/installed';
$status['checks']['installation'] = [
    'name' => 'Application Installation',
    'status' => file_exists($lockFile) ? 'ok' : 'pending',
    'message' => file_exists($lockFile) ? 'Application is installed' : 'Visit /install.php to complete installation'
];

// Check storage permissions
$storageWritable = is_writable(__DIR__ . '/../storage');
$status['checks']['storage_permissions'] = [
    'name' => 'Storage Permissions',
    'status' => $storageWritable ? 'ok' : 'warning',
    'message' => $storageWritable ? 'Storage directory is writable' : 'Storage directory may not be writable'
];

// Check database connection if installed
if (file_exists(__DIR__ . '/../.env') && file_exists($lockFile)) {
    try {
        // Load environment
        $envContent = file_get_contents(__DIR__ . '/../.env');
        preg_match('/DB_HOST=(.*)/', $envContent, $hostMatch);
        preg_match('/DB_PORT=(.*)/', $envContent, $portMatch);
        preg_match('/DB_DATABASE=(.*)/', $envContent, $dbMatch);
        preg_match('/DB_USERNAME=(.*)/', $envContent, $userMatch);
        preg_match('/DB_PASSWORD=(.*)/', $envContent, $passMatch);

        if ($hostMatch && $portMatch && $dbMatch && $userMatch) {
            $host = trim($hostMatch[1]);
            $port = trim($portMatch[1]);
            $dbname = trim($dbMatch[1]);
            $user = trim($userMatch[1]);
            $password = isset($passMatch[1]) ? trim($passMatch[1]) : '';

            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);

            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            $userCount = $stmt->fetchColumn();

            $status['checks']['database'] = [
                'name' => 'Database Connection',
                'status' => 'ok',
                'message' => "Connected to database ($userCount users found)"
            ];
        }
    } catch (Exception $e) {
        $status['checks']['database'] = [
            'name' => 'Database Connection',
            'status' => 'error',
            'message' => 'Could not connect to database: ' . $e->getMessage()
        ];
    }
}

// Determine overall status
$overallStatus = 'operational';
foreach ($status['checks'] as $check) {
    if ($check['status'] === 'error') {
        $overallStatus = 'error';
        break;
    } elseif ($check['status'] === 'warning' && $overallStatus !== 'error') {
        $overallStatus = 'warning';
    } elseif ($check['status'] === 'pending' && $overallStatus === 'operational') {
        $overallStatus = 'pending';
    }
}

$status['status'] = $overallStatus;

// Add quick links
$status['links'] = [
    'admin' => '/admin',
    'installer' => '/install.php',
    'documentation' => '/BOLT_DEPLOYMENT.md'
];

echo json_encode($status, JSON_PRETTY_PRINT);
