<?php
/**
 * Verify Client Role
 * Checks that Client role exists and is properly configured
 */

require_once 'config.php';
require_once 'Database.php';

echo "<h1>Client Registration Role Verification</h1>";

try {
    $db = Database::getInstance();
    
    // Check all roles
    echo "<h2>All User Roles in System</h2>";
    $roles = $db->fetchAll("SELECT role_id, role_name, description FROM user_roles ORDER BY role_id", []);
    
    if (empty($roles)) {
        echo "<p style='color: red;'><strong>✗ ERROR: No roles found in database!</strong></p>";
        echo "<p>The user_roles table is empty. Run schema.sql to populate.</p>";
    } else {
        echo "<table border='1' cellpadding='10' style='width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Role ID</th>";
        echo "<th>Role Name</th>";
        echo "<th>Description</th>";
        echo "</tr>";
        
        $clientFound = false;
        foreach ($roles as $role) {
            $isClient = ($role['role_name'] === 'Client');
            $bgColor = $isClient ? '#e8f5e9' : '';
            echo "<tr style='background: $bgColor;'>";
            echo "<td>" . htmlspecialchars($role['role_id']) . "</td>";
            echo "<td>";
            if ($isClient) {
                echo "<strong style='color: green;'>✓ " . htmlspecialchars($role['role_name']) . "</strong>";
                $clientFound = true;
            } else {
                echo htmlspecialchars($role['role_name']);
            }
            echo "</td>";
            echo "<td>";
            if (!empty($role['description'])) {
                echo htmlspecialchars($role['description']);
            }
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        if ($clientFound) {
            echo "<p style='color: green;'><strong>✓ Client role exists in database</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>✗ ERROR: Client role NOT FOUND in user_roles table!</strong></p>";
            echo "<p>This is why new registrations may have wrong roles. Need to add Client role.</p>";
        }
    }
    
    // Check a registered client user
    echo "<h2>Test: Fetch Client Role by Name</h2>";
    $testQuery = $db->fetchOne(
        "SELECT role_id FROM user_roles WHERE role_name = ?",
        ['Client']
    );
    
    if ($testQuery) {
        echo "<p style='color: green;'><strong>✓ Query successful</strong></p>";
        echo "<p>Client role_id = " . htmlspecialchars($testQuery['role_id']) . "</p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Query returned no results</strong></p>";
        echo "<p>The Client role cannot be found by this query.</p>";
    }
    
    // Check how many users have Client role
    echo "<h2>Users with Client Role</h2>";
    $clientUsers = $db->fetchAll(
        "SELECT u.user_id, u.username, u.email, ur.role_name 
         FROM users u 
         JOIN user_roles ur ON u.role_id = ur.role_id 
         WHERE ur.role_name = ?",
        ['Client']
    );
    
    echo "<p>Total client users: " . count($clientUsers) . "</p>";
    
    if (!empty($clientUsers)) {
        echo "<table border='1' cellpadding='10' style='margin: 20px 0;'>";
        echo "<tr style='background: #f0f0f0;'><th>User ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
        foreach ($clientUsers as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['user_id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['role_name']) . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No client users found yet. This is normal if you haven't registered any clients.</p>";
    }
    
    echo "<hr>";
    echo "<h2>Recommendation</h2>";
    echo "<p><a href='client_register.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Client Registration →</a></p>";
    echo "<p>The registration form now has enhanced role validation to ensure all new clients get the Client role.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
