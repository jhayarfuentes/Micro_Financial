<?php
/**
 * Admin Account Setup
 * Creates a superadmin user account in Supabase
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/init.php';

echo "════════════════════════════════════════════\n";
echo "SUPERADMIN ACCOUNT SETUP\n";
echo "════════════════════════════════════════════\n\n";

// Admin credentials
$adminUsername = 'admin';
$adminEmail = 'admin@microfinancial.local';
$adminPassword = 'Admin@123456'; // Default password - user should change after first login
$adminFirstName = 'Super';
$adminLastName = 'Admin';

$hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);

echo "Creating superadmin account...\n";
echo "• Username: $adminUsername\n";
echo "• Email: $adminEmail\n";
echo "• Name: $adminFirstName $adminLastName\n\n";

try {
    $db = service('database');
    
    // Get Admin role ID
    echo "Fetching Admin role...\n";
    $adminRole = $db->fetchOne(
        "SELECT role_id FROM user_roles WHERE role_name = 'Admin'"
    );
    
    if (!$adminRole) {
        throw new Exception("Admin role not found. Ensure schema.sql was executed.");
    }
    
    $adminRoleId = $adminRole['role_id'];
    echo "✓ Admin role found (ID: $adminRoleId)\n\n";
    
    // Check if admin already exists
    echo "Checking for existing admin account...\n";
    $existingAdmin = $db->fetchOne(
        "SELECT user_id FROM users WHERE username = ? OR email = ?",
        [$adminUsername, $adminEmail]
    );
    
    if ($existingAdmin) {
        echo "⚠️  Admin account already exists (ID: {$existingAdmin['user_id']})\n";
        echo "Skipping creation.\n";
        exit(0);
    }
    
    echo "✓ No existing admin found\n\n";
    
    // Create admin user
    echo "Creating admin user account...\n";
    $result = $db->insert('users', [
        'username' => $adminUsername,
        'email' => $adminEmail,
        'password_hash' => $hashedPassword,
        'first_name' => $adminFirstName,
        'last_name' => $adminLastName,
        'role_id' => $adminRoleId,
        'is_active' => true
    ]);
    
    if ($result) {
        echo "✅ Admin user created successfully!\n\n";
        
        echo "════════════════════════════════════════════\n";
        echo "LOGIN CREDENTIALS\n";
        echo "════════════════════════════════════════════\n";
        echo "URL:      http://localhost/Micro_Financial/Micro_Financial-main/dashboard.php\n";
        echo "Username: $adminUsername\n";
        echo "Email:    $adminEmail\n";
        echo "Password: $adminPassword\n\n";
        
        echo "⚠️  IMPORTANT: Change the password after first login!\n\n";
        
        echo "════════════════════════════════════════════\n";
        echo "ACCOUNT DETAILS\n";
        echo "════════════════════════════════════════════\n";
        echo "Role:           Admin\n";
        echo "First Name:     $adminFirstName\n";
        echo "Last Name:      $adminLastName\n";
        echo "Status:         Active\n";
        echo "Database:       livsafely @ Supabase\n\n";
        
        echo "✨ Ready to login! Use the credentials above.\n";
    } else {
        throw new Exception("Failed to create admin user");
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR\n";
    echo "════════════════════════════════════════════\n";
    echo "Could not connect via PHP: " . $e->getMessage() . "\n\n";
    
    echo "MANUAL SETUP INSTRUCTIONS:\n";
    echo "────────────────────────────────────────\n";
    echo "1. Go to: https://app.supabase.com\n";
    echo "2. Select your project: lvvfsgkxpulbpwrpyhuf\n";
    echo "3. Navigate to: SQL Editor\n";
    echo "4. Click: New Query\n";
    echo "5. Copy & paste the SQL below:\n\n";
    
    $hashedPw = password_hash($adminPassword, PASSWORD_BCRYPT);
    
    echo "--- BEGIN SQL ---\n";
    echo "INSERT INTO users (username, email, password_hash, first_name, last_name, role_id, is_active)\n";
    echo "VALUES (\n";
    echo "    'admin',\n";
    echo "    'admin@microfinancial.local',\n";
    echo "    '" . addslashes($hashedPw) . "',\n";
    echo "    'Super',\n";
    echo "    'Admin',\n";
    echo "    (SELECT role_id FROM user_roles WHERE role_name = 'Admin'),\n";
    echo "    true\n";
    echo ");\n";
    echo "--- END SQL ---\n\n";
    
    echo "6. Click: Run\n";
    echo "7. Your admin account will be created\n\n";
    
    echo "Login Credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: $adminPassword\n";
}
?>
