<?php
/**
 * Direct Database Update Script
 * Updates all user account passwords with valid bcrypt hashes
 * Execute via Supabase REST API
 */

require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

// Test user credentials and their proper bcrypt-hashed passwords
$testUsers = [
    ['username' => 'admin', 'password' => 'Admin@123456'],
    ['username' => 'branch_manager', 'password' => 'Manager@123456'],
    ['username' => 'kyc_officer', 'password' => 'KYC@123456'],
    ['username' => 'loan_officer', 'password' => 'Loan@123456'],
    ['username' => 'teller', 'password' => 'Teller@123456'],
    ['username' => 'collector', 'password' => 'Collector@123456'],
    ['username' => 'savings_officer', 'password' => 'Savings@123456'],
    ['username' => 'compliance_officer', 'password' => 'Compliance@123456'],
    ['username' => 'client_demo', 'password' => 'Client@123456'],
];

$success = [];
$failed = [];

try {
    $db = service('database');
    
    foreach ($testUsers as $user) {
        try {
            // Generate valid bcrypt hash
            $passwordHash = password_hash($user['password'], PASSWORD_BCRYPT);
            
            // Update password hash via Supabase REST API
            $query = "UPDATE users SET password_hash = ? WHERE username = ?";
            $result = $db->query($query, [$passwordHash, $user['username']]);
            
            $success[] = [
                'username' => $user['username'],
                'password' => $user['password'],
                'status' => 'updated',
                'hash_preview' => substr($passwordHash, 0, 25) . '...'
            ];
            
            log_message('INFO', "Updated password for user: " . $user['username']);
            
        } catch (Exception $e) {
            $failed[] = [
                'username' => $user['username'],
                'error' => $e->getMessage()
            ];
            log_message('ERROR', "Failed to update user " . $user['username'] . ": " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Password update completed',
        'updated' => count($success),
        'failed' => count($failed),
        'updates' => $success,
        'errors' => $failed,
        'next_step' => 'Test login with credentials provided above',
        'instructions' => 'Open http://localhost/Micro_Financial/Micro_Financial-main/client_login.php and log in with any username/password combination shown in the updates array'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'Failed to update passwords in database',
        'debug' => DEBUG_MODE ? $e->getTraceAsString() : null
    ], JSON_PRETTY_PRINT);
    log_message('ERROR', "Database update failed: " . $e->getMessage());
}
?>
