<?php
/**
 * Create Test User Accounts
 * Creates default user accounts for all roles
 */

require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    log_message('INFO', 'Starting user account creation');
    
    $db = service('database');
    
    // Define test users for each role
    $testUsers = [
        [
            'username' => 'admin',
            'email' => 'admin@microfinance.local',
            'password' => 'Admin@123456',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'role_name' => 'Admin'
        ],
        [
            'username' => 'branch_manager',
            'email' => 'manager@microfinance.local',
            'password' => 'Manager@123456',
            'first_name' => 'Branch',
            'last_name' => 'Manager',
            'role_name' => 'Portfolio Manager'
        ],
        [
            'username' => 'kyc_officer',
            'email' => 'kyc@microfinance.local',
            'password' => 'KYC@123456',
            'first_name' => 'KYC',
            'last_name' => 'Officer',
            'role_name' => 'KYC Officer'
        ],
        [
            'username' => 'loan_officer',
            'email' => 'loan@microfinance.local',
            'password' => 'Loan@123456',
            'first_name' => 'Loan',
            'last_name' => 'Officer',
            'role_name' => 'Loan Officer'
        ],
        [
            'username' => 'teller',
            'email' => 'teller@microfinance.local',
            'password' => 'Teller@123456',
            'first_name' => 'Teller',
            'last_name' => 'Staff',
            'role_name' => 'Staff'
        ],
        [
            'username' => 'collector',
            'email' => 'collector@microfinance.local',
            'password' => 'Collector@123456',
            'first_name' => 'Loan',
            'last_name' => 'Collector',
            'role_name' => 'Loan Collector'
        ],
        [
            'username' => 'savings_officer',
            'email' => 'savings@microfinance.local',
            'password' => 'Savings@123456',
            'first_name' => 'Savings',
            'last_name' => 'Officer',
            'role_name' => 'Savings Officer'
        ],
        [
            'username' => 'compliance_officer',
            'email' => 'compliance@microfinance.local',
            'password' => 'Compliance@123456',
            'first_name' => 'Compliance',
            'last_name' => 'Officer',
            'role_name' => 'Compliance Officer'
        ],
        [
            'username' => 'client_demo',
            'email' => 'client@microfinance.local',
            'password' => 'Client@123456',
            'first_name' => 'Demo',
            'last_name' => 'Client',
            'role_name' => 'Client'
        ],
    ];
    
    $createdUsers = [];
    $failedUsers = [];
    
    foreach ($testUsers as $userData) {
        try {
            // Check if user already exists
            $existing = $db->fetchOne(
                "SELECT * FROM users WHERE username = ?",
                [$userData['username']]
            );
            
            if ($existing) {
                log_message('WARNING', "User already exists: " . $userData['username']);
                $failedUsers[] = [
                    'username' => $userData['username'],
                    'reason' => 'User already exists',
                    'user_id' => $existing['user_id']
                ];
                continue;
            }
            
            // Get role ID from role name
            $role = $db->fetchOne(
                "SELECT role_id FROM user_roles WHERE role_name = ?",
                [$userData['role_name']]
            );
            
            if (!$role) {
                log_message('ERROR', "Role not found: " . $userData['role_name']);
                $failedUsers[] = [
                    'username' => $userData['username'],
                    'reason' => 'Role not found: ' . $userData['role_name']
                ];
                continue;
            }
            
            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_BCRYPT);
            
            // Create user
            $userService = service('user');
            $newUser = $userService->createUser([
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => $userData['password'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'role_id' => $role['role_id']
            ]);
            
            $createdUsers[] = [
                'username' => $userData['username'],
                'email' => $userData['email'],
                'role' => $userData['role_name'],
                'user_id' => $newUser['user_id'] ?? 'N/A',
                'password' => $userData['password'] // For reference only - REMOVE IN PRODUCTION
            ];
            
            log_message('INFO', "User created successfully: " . $userData['username']);
            
        } catch (Exception $e) {
            log_message('ERROR', "Failed to create user " . $userData['username'] . ": " . $e->getMessage());
            $failedUsers[] = [
                'username' => $userData['username'],
                'reason' => $e->getMessage()
            ];
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'status' => 200,
        'success' => true,
        'message' => 'User creation process completed',
        'data' => [
            'total_attempted' => count($testUsers),
            'created' => count($createdUsers),
            'failed' => count($failedUsers),
            'created_users' => $createdUsers,
            'failed_users' => $failedUsers
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    log_message('ERROR', 'User creation failed: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ], JSON_PRETTY_PRINT);
}
?>
