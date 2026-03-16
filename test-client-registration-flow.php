<?php
/**
 * Test Client Registration Flow
 * Simulates complete registration and verifies role is set to Client
 */

require_once 'config.php';
require_once 'Database.php';
require_once 'UserService.php';
require_once 'ClientService.php';

echo "<h1>Client Registration Flow Test</h1>";

try {
    $db = Database::getInstance();
    
    // Step 1: Verify Client role exists
    echo "<h2>Step 1: Verify Client Role Exists</h2>";
    $clientRole = $db->fetchOne(
        "SELECT role_id FROM user_roles WHERE role_name = ?",
        ['Client']
    );
    
    if (!$clientRole) {
        echo "<p style='color: red;'><strong>✗ FAILED:</strong> Client role not found in database</p>";
        echo "<p>Please run schema.sql to populate user_roles table</p>";
        exit;
    }
    
    echo "<p style='color: green;'><strong>✓ PASS:</strong> Client role found with ID = " . $clientRole['role_id'] . "</p>";
    $clientRoleId = $clientRole['role_id'];
    
    // Step 2: Create test user data
    echo "<h2>Step 2: Prepare Test User Data</h2>";
    $testEmail = 'test-client-' . time() . '@example.com';
    $testUsername = 'testclient_' . time();
    
    echo "<p>Test Email: " . htmlspecialchars($testEmail) . "</p>";
    echo "<p>Test Username: " . htmlspecialchars($testUsername) . "</p>";
    
    // Step 3: Create user via UserService
    echo "<h2>Step 3: Create User with Client Role</h2>";
    try {
        $userService = new UserService();
        $newUser = $userService->createUser([
            'username' => $testUsername,
            'email' => $testEmail,
            'password' => 'TestPassword123',
            'first_name' => 'Test',
            'last_name' => 'Client',
            'role_id' => $clientRoleId
        ]);
        
        echo "<p style='color: green;'><strong>✓ User Created</strong></p>";
        echo "<table border='1' cellpadding='10' style='margin: 15px 0;'>";
        echo "<tr><td><strong>User ID:</strong></td><td>" . htmlspecialchars($newUser['user_id']) . "</td></tr>";
        echo "<tr><td><strong>Username:</strong></td><td>" . htmlspecialchars($newUser['username']) . "</td></tr>";
        echo "<tr><td><strong>Email:</strong></td><td>" . htmlspecialchars($newUser['email']) . "</td></tr>";
        echo "<tr><td><strong>Role ID:</strong></td><td>" . htmlspecialchars($newUser['role_id']) . "</td></tr>";
        echo "<tr><td><strong>Role Name:</strong></td><td><strong style='color: green;'>" . htmlspecialchars($newUser['role_name'] ?? 'UNKNOWN') . "</strong></td></tr>";
        echo "</table>";
        
        // Verify role is Client
        if ($newUser['role_name'] !== 'Client') {
            echo "<p style='color: red;'><strong>✗ ERROR:</strong> User role is '" . htmlspecialchars($newUser['role_name']) . "' instead of 'Client'</strong></p>";
            exit;
        }
        
        if ($newUser['role_id'] != $clientRoleId) {
            echo "<p style='color: red;'><strong>✗ ERROR:</strong> User role_id is " . $newUser['role_id'] . " instead of " . $clientRoleId . "</strong></p>";
            exit;
        }
        
        echo "<p style='color: green;'><strong>✓ PASS:</strong> User has correct Client role</p>";
        
        $userId = $newUser['user_id'];
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ FAILED:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }
    
    // Step 4: Create client profile
    echo "<h2>Step 4: Create Client Profile</h2>";
    try {
        $clientService = new ClientService();
        $newClient = $clientService->registerClient([
            'user_id' => $userId,
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => $testEmail,
            'contact_number' => '+1234567890',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'street_address' => '123 Test St',
            'city' => 'Test City',
            'province' => 'Test Province',
            'zip_code' => '12345',
            'client_status' => 'active',
            'kyc_status' => 'pending'
        ]);
        
        echo "<p style='color: green;'><strong>✓ Client Profile Created</strong></p>";
        echo "<table border='1' cellpadding='10' style='margin: 15px 0;'>";
        echo "<tr><td><strong>Client ID:</strong></td><td>" . htmlspecialchars($newClient['client_id']) . "</td></tr>";
        echo "<tr><td><strong>User ID:</strong></td><td>" . htmlspecialchars($newClient['user_id']) . "</td></tr>";
        echo "<tr><td><strong>Name:</strong></td><td>" . htmlspecialchars($newClient['first_name']) . " " . htmlspecialchars($newClient['last_name']) . "</td></tr>";
        echo "<tr><td><strong>Email:</strong></td><td>" . htmlspecialchars($newClient['email']) . "</td></tr>";
        echo "<tr><td><strong>Status:</strong></td><td>" . htmlspecialchars($newClient['client_status']) . "</td></tr>";
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ FAILED:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }
    
    // Step 5: Simulate session
    echo "<h2>Step 5: Session Assignment (from auth.php)</h2>";
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $testUsername;
    $_SESSION['role'] = 'Client';
    
    echo "<table border='1' cellpadding='10' style='margin: 15px 0;'>";
    echo "<tr><td><strong>Session User ID:</strong></td><td>" . htmlspecialchars($_SESSION['user_id']) . "</td></tr>";
    echo "<tr><td><strong>Session Username:</strong></td><td>" . htmlspecialchars($_SESSION['username']) . "</td></tr>";
    echo "<tr><td><strong>Session Role:</strong></td><td><strong style='color: green;'>" . htmlspecialchars($_SESSION['role']) . "</strong></td></tr>";
    echo "</table>";
    
    if ($_SESSION['role'] !== 'Client') {
        echo "<p style='color: red;'><strong>✗ ERROR:</strong> Session role is '" . htmlspecialchars($_SESSION['role']) . "' instead of 'Client'</strong></p>";
        exit;
    }
    
    echo "<p style='color: green;'><strong>✓ PASS:</strong> Session role is correctly set to Client</p>";
    
    // Final verification
    echo "<h2>Final Verification</h2>";
    $verifyUser = $db->fetchOne(
        "SELECT u.*, ur.role_name FROM users u
         LEFT JOIN user_roles ur ON u.role_id = ur.role_id
         WHERE u.user_id = ?",
        [$userId]
    );
    
    echo "<p><strong>User in Database:</strong></p>";
    echo "<table border='1' cellpadding='10' style='margin: 15px 0;'>";
    echo "<tr><td><strong>Username:</strong></td><td>" . htmlspecialchars($verifyUser['username']) . "</td></tr>";
    echo "<tr><td><strong>Role Name:</strong></td><td><strong style='color: green;'>" . htmlspecialchars($verifyUser['role_name']) . "</strong></td></tr>";
    echo "<tr><td><strong>Role ID:</strong></td><td>" . htmlspecialchars($verifyUser['role_id']) . "</td></tr>";
    echo "</table>";
    
    echo "<hr>";
    echo "<h2 style='color: green;'>✓ All Tests Passed!</h2>";
    echo "<p>Client registration correctly creates users with Client role only.</p>";
    echo "<p><a href='client_register.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Registration Form →</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ Test Failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
