<?php
/**
 * Fix admin user password
 */

require_once __DIR__ . '/../config.php';

$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Update admin user password
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->execute([$hash]);
    
    echo "✓ Admin password updated successfully!\n";
    echo "\nCredentials:\n";
    echo "  Username: admin\n";
    echo "  Password: Admin@123\n";
    echo "\nYou can now login with these credentials.\n";
    
} catch (PDOException $e) {
    die("✗ Error updating password: " . $e->getMessage() . "\n");
}
