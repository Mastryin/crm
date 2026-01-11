<?php
/**
 * EduCRM Web Installer
 * Visit this page once to set up your database and create the superadmin user
 */

// Check if already installed
$lockFile = __DIR__ . '/../storage/installed';
if (file_exists($lockFile)) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Already Installed</title>
        <style>
            body { font-family: system-ui; max-width: 600px; margin: 50px auto; padding: 20px; }
            .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
            a { color: #007bff; }
        </style>
    </head>
    <body>
        <div class="success">
            <h2>✓ Already Installed</h2>
            <p>EduCRM has already been installed on this system.</p>
            <p><a href="/admin">Go to Admin Panel →</a></p>
        </div>
    </body>
    </html>
    ');
}

// Load environment
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$output = [];
$success = true;

ob_start();

try {
    // Run migrations
    echo "Running database migrations...\n";
    Artisan::call('migrate', ['--force' => true]);
    $output[] = ['type' => 'success', 'message' => 'Database migrations completed'];

    // Run seeders
    echo "Seeding database...\n";
    Artisan::call('db:seed', ['--force' => true]);
    $output[] = ['type' => 'success', 'message' => 'Database seeded successfully'];

    // Create superadmin
    $output[] = ['type' => 'success', 'message' => 'SuperAdmin user created'];
    $output[] = ['type' => 'info', 'message' => 'Email: rohanmishra.design@gmail.com'];
    $output[] = ['type' => 'info', 'message' => 'Password: admin@123'];

    // Create lock file
    touch($lockFile);

} catch (Exception $e) {
    $success = false;
    $output[] = ['type' => 'error', 'message' => 'Installation failed: ' . $e->getMessage()];
}

$console = ob_get_clean();

?>
<!DOCTYPE html>
<html>
<head>
    <title>EduCRM Installation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .message {
            padding: 12px 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .console {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .button:hover {
            background: #0056b3;
        }
        .credentials {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .credentials code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 EduCRM Installation</h1>

        <?php if ($success): ?>
            <div class="message success">
                <strong>✓ Installation Successful!</strong>
            </div>

            <?php foreach ($output as $msg): ?>
                <div class="message <?php echo $msg['type']; ?>">
                    <?php echo htmlspecialchars($msg['message']); ?>
                </div>
            <?php endforeach; ?>

            <div class="credentials">
                <h3>🔐 Login Credentials</h3>
                <p>
                    <strong>Email:</strong> <code>rohanmishra.design@gmail.com</code><br>
                    <strong>Password:</strong> <code>admin@123</code>
                </p>
                <p style="color: #856404; margin-top: 15px;">
                    ⚠️ Please change your password after first login for security.
                </p>
            </div>

            <a href="/admin" class="button">Go to Admin Panel →</a>

        <?php else: ?>
            <div class="message error">
                <strong>✗ Installation Failed</strong>
            </div>

            <?php foreach ($output as $msg): ?>
                <div class="message <?php echo $msg['type']; ?>">
                    <?php echo htmlspecialchars($msg['message']); ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <?php if ($console): ?>
            <details style="margin-top: 20px;">
                <summary style="cursor: pointer; color: #666;">View Console Output</summary>
                <div class="console"><?php echo htmlspecialchars($console); ?></div>
            </details>
        <?php endif; ?>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

        <p style="color: #666; font-size: 14px;">
            This installer has been run. To reinstall, delete the file:<br>
            <code>storage/installed</code>
        </p>
    </div>
</body>
</html>
