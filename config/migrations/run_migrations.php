<?php
/**
 * Run database migrations
 */

require_once __DIR__ . '/../config.php';

echo "Running database migrations...\n\n";

// Read the migration SQL file
$migrationFile = __DIR__ . '/001_create_users_table.sql';

if (!file_exists($migrationFile)) {
    die("Error: Migration file not found: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);

if ($sql === false) {
    die("Error: Could not read migration file\n");
}

// Split SQL into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

try {
    $pdo->beginTransaction();
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $pdo->exec($statement);
        }
    }
    
    $pdo->commit();
    echo "\n✓ Migration completed successfully!\n";
    echo "\nDefault admin user created:\n";
    echo "  Username: admin\n";
    echo "  Password: Admin@123\n";
    echo "\n⚠️  IMPORTANT: Change this password after first login!\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    die("\n✗ Migration failed: " . $e->getMessage() . "\n");
}
