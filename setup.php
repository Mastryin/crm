<?php

/**
 * EduCRM Setup Script
 *
 * This script sets up the database and creates the superadmin user
 * Run this once after deploying the application
 */

echo "=== EduCRM Setup ===\n\n";

// Load environment variables
if (!file_exists(__DIR__ . '/.env')) {
    die("ERROR: .env file not found. Please create it first.\n");
}

// Simple .env parser
function loadEnv($file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B'\"");

        if (!empty($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Connect to database
try {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $dbname = getenv('DB_DATABASE');
    $user = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✓ Connected to database successfully\n";
} catch (PDOException $e) {
    die("ERROR: Could not connect to database: " . $e->getMessage() . "\n");
}

// Check if migrations are needed
try {
    $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'users')");
    $tablesExist = $stmt->fetchColumn();

    if (!$tablesExist) {
        echo "\n⚠ Database tables not found. You need to run migrations.\n";
        echo "Please run: php artisan migrate --seed\n\n";
        exit(1);
    }

    echo "✓ Database tables exist\n";
} catch (PDOException $e) {
    echo "⚠ Could not check database tables: " . $e->getMessage() . "\n";
}

// Check if superadmin exists
try {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = :email");
    $stmt->execute(['email' => 'rohanmishra.design@gmail.com']);
    $user = $stmt->fetch();

    if ($user) {
        echo "✓ SuperAdmin user already exists\n";
        echo "  Email: rohanmishra.design@gmail.com\n";
        echo "  Name: {$user['name']}\n";
    } else {
        echo "\n⚠ SuperAdmin user not found\n";
        echo "Creating superadmin user...\n";

        // Get admin role
        $stmt = $pdo->query("SELECT id FROM roles WHERE name = 'Administrator' LIMIT 1");
        $role = $stmt->fetch();
        $roleId = $role ? $role['id'] : null;

        if (!$roleId) {
            // Create admin role
            $roleId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $stmt = $pdo->prepare("INSERT INTO roles (id, name, description, permission_type, created_at, updated_at) VALUES (:id, :name, :desc, :perm, NOW(), NOW())");
            $stmt->execute([
                'id' => $roleId,
                'name' => 'Administrator',
                'desc' => 'Super Administrator with full system access',
                'perm' => 'all'
            ]);

            echo "  ✓ Created Administrator role\n";
        }

        // Get default group
        $stmt = $pdo->query("SELECT id FROM groups WHERE name = 'Default' LIMIT 1");
        $group = $stmt->fetch();
        $groupId = $group ? $group['id'] : null;

        if (!$groupId) {
            // Create default group
            $groupId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $stmt = $pdo->prepare("INSERT INTO groups (id, name, description, created_at, updated_at) VALUES (:id, :name, :desc, NOW(), NOW())");
            $stmt->execute([
                'id' => $groupId,
                'name' => 'Default',
                'desc' => 'Default user group'
            ]);

            echo "  ✓ Created Default group\n";
        }

        // Create user
        $userId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $hashedPassword = password_hash('admin@123', PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password, role_id, status, view_permission, created_at, updated_at) VALUES (:id, :name, :email, :password, :role_id, :status, :view, NOW(), NOW())");
        $stmt->execute([
            'id' => $userId,
            'name' => 'Rohan Mishra',
            'email' => 'rohanmishra.design@gmail.com',
            'password' => $hashedPassword,
            'role_id' => $roleId,
            'status' => 1,
            'view' => 'global'
        ]);

        // Assign to group
        $stmt = $pdo->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (:user_id, :group_id)");
        $stmt->execute([
            'user_id' => $userId,
            'group_id' => $groupId
        ]);

        echo "  ✓ SuperAdmin user created successfully!\n";
        echo "\n";
        echo "  Login Credentials:\n";
        echo "  Email: rohanmishra.design@gmail.com\n";
        echo "  Password: admin@123\n";
    }
} catch (PDOException $e) {
    echo "ERROR: Could not create superadmin user: " . $e->getMessage() . "\n";
}

echo "\n=== Setup Complete ===\n";
echo "\nYou can now access the application at: " . getenv('APP_URL') . "/admin\n";
