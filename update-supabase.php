<?php
/**
 * Update Database - User Interface
 * Simple button to update all user passwords
 */

require_once 'init.php';

$action = $_GET['action'] ?? '';
$result = null;

// Test user credentials
$testUsers = [
    ['username' => 'admin', 'password' => 'Admin@123456', 'role' => 'Admin'],
    ['username' => 'branch_manager', 'password' => 'Manager@123456', 'role' => 'Portfolio Manager'],
    ['username' => 'kyc_officer', 'password' => 'KYC@123456', 'role' => 'KYC Officer'],
    ['username' => 'loan_officer', 'password' => 'Loan@123456', 'role' => 'Loan Officer'],
    ['username' => 'teller', 'password' => 'Teller@123456', 'role' => 'Staff/Teller'],
    ['username' => 'collector', 'password' => 'Collector@123456', 'role' => 'Loan Collector'],
    ['username' => 'savings_officer', 'password' => 'Savings@123456', 'role' => 'Savings Officer'],
    ['username' => 'compliance_officer', 'password' => 'Compliance@123456', 'role' => 'Compliance Officer'],
    ['username' => 'client_demo', 'password' => 'Client@123456', 'role' => 'Client'],
];

if ($action === 'execute') {
    // Execute the update
    $success = [];
    $failed = [];
    
    try {
        $db = service('database');
        
        foreach ($testUsers as $user) {
            try {
                // Generate valid bcrypt hash
                $passwordHash = password_hash($user['password'], PASSWORD_BCRYPT);
                
                // Update password hash
                $query = "UPDATE users SET password_hash = ? WHERE username = ?";
                $db->query($query, [$passwordHash, $user['username']]);
                
                $success[] = [
                    'username' => $user['username'],
                    'password' => $user['password'],
                    'role' => $user['role']
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
        
        $result = [
            'success' => true,
            'updated' => count($success),
            'failed' => count($failed),
            'updated_users' => $success,
            'errors' => $failed
        ];
        
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'error' => $e->getMessage()
        ];
        log_message('ERROR', "Database update failed: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Supabase Passwords - Micro Financial</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #0F1E4A; margin-bottom: 10px; }
        .section { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #0F1E4A; margin-bottom: 15px; border-bottom: 2px solid #3B82F6; padding-bottom: 10px; }
        .status-banner { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .status-success { background: #ECFDF5; border-left: 4px solid #10B981; color: #065F46; }
        .status-error { background: #FEF2F2; border-left: 4px solid #EF4444; color: #7F1D1D; }
        .btn { display: inline-block; padding: 12px 24px; border-radius: 5px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; font-size: 16px; }
        .btn-primary { background: #3B82F6; color: white; }
        .btn-primary:hover { background: #2563EB; }
        .btn-success { background: #10B981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-disabled { background: #9CA3AF; color: white; cursor: not-allowed; }
        .action-area { text-align: center; margin: 30px 0; }
        .user-list { list-style: none; }
        .user-item { padding: 10px; margin: 5px 0; background: #f9f9f9; border-radius: 4px; display: flex; align-items: center; }
        .user-item.success::before { content: "✅"; margin-right: 10px; color: #10B981; }
        .user-item.error::before { content: "❌"; margin-right: 10px; color: #EF4444; }
        .user-name { font-weight: 500; color: #0F1E4A; min-width: 150px; }
        .credentials { font-size: 12px; color: #666; margin-left: auto; }
        .code { background: #1e293b; color: #e2e8f0; padding: 3px 8px; border-radius: 3px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0F1E4A; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Update Supabase Database</h1>
            <p>Fix invalid password hashes for all test user accounts</p>
        </div>

        <?php if ($result && $result['success']): ?>
        <div class="status-banner status-success">
            <h2>✅ Database Updated Successfully!</h2>
            <p><?php echo $result['updated']; ?> user account(s) updated with valid passwords.</p>
            <?php if ($result['failed'] > 0): ?>
                <p style="margin-top: 10px;">⚠️ <?php echo $result['failed']; ?> account(s) failed to update.</p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>📋 Update Results</h2>
            
            <?php if (!empty($result['updated_users'])): ?>
            <h3 style="color: #10B981; margin-bottom: 10px;">✅ Successfully Updated:</h3>
            <ul class="user-list">
                <?php foreach ($result['updated_users'] as $user): ?>
                <li class="user-item success">
                    <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                    <span class="credentials"><code><?php echo htmlspecialchars($user['password']); ?></code></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            
            <?php if (!empty($result['errors'])): ?>
            <h3 style="color: #EF4444; margin: 20px 0 10px 0;">❌ Failed Updates:</h3>
            <ul class="user-list">
                <?php foreach ($result['errors'] as $error): ?>
                <li class="user-item error">
                    <span class="user-name"><?php echo htmlspecialchars($error['username']); ?></span>
                    <span style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($error['error']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="section" style="background: #EFF6FF;">
            <h2>🔑 Test Login Now</h2>
            <p>Use these credentials to test the login:</p>
            <table>
                <thead>
                    <tr><th>Username</th><th>Password</th><th>Role</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>admin</code></td><td><code>Admin@123456</code></td><td>Admin</td></tr>
                    <tr><td><code>teller</code></td><td><code>Teller@123456</code></td><td>Staff</td></tr>
                    <tr><td><code>client_demo</code></td><td><code>Client@123456</code></td><td>Client</td></tr>
                </tbody>
            </table>
            <p style="margin-top: 15px;">
                <a href="client_login.php" class="btn btn-success">Go to Login Page</a>
            </p>
        </div>

        <?php elseif ($result && !$result['success']): ?>
        <div class="status-banner status-error">
            <h2>❌ Error During Update</h2>
            <p><?php echo htmlspecialchars($result['error']); ?></p>
        </div>

        <div class="section">
            <p>Try one of these alternatives:</p>
            <ul style="margin-left: 20px;">
                <li>Check that Supabase credentials in <code>config.php</code> are correct</li>
                <li>Verify the users table exists in your Supabase database</li>
                <li>Run <code>schema.sql</code> in Supabase if the table doesn't exist</li>
            </ul>
        </div>

        <?php else: ?>
        <div class="section">
            <h2>📋 What This Does</h2>
            <p>This will update all 9 test user accounts with valid bcrypt password hashes so they can log in.</p>
            
            <table>
                <thead>
                    <tr><th>Username</th><th>New Password</th><th>Role</th></tr>
                </thead>
                <tbody>
                    <tr><td>admin</td><td>Admin@123456</td><td>Admin</td></tr>
                    <tr><td>branch_manager</td><td>Manager@123456</td><td>Portfolio Manager</td></tr>
                    <tr><td>kyc_officer</td><td>KYC@123456</td><td>KYC Officer</td></tr>
                    <tr><td>loan_officer</td><td>Loan@123456</td><td>Loan Officer</td></tr>
                    <tr><td>teller</td><td>Teller@123456</td><td>Staff/Teller</td></tr>
                    <tr><td>collector</td><td>Collector@123456</td><td>Loan Collector</td></tr>
                    <tr><td>savings_officer</td><td>Savings@123456</td><td>Savings Officer</td></tr>
                    <tr><td>compliance_officer</td><td>Compliance@123456</td><td>Compliance Officer</td></tr>
                    <tr><td>client_demo</td><td>Client@123456</td><td>Client</td></tr>
                </tbody>
            </table>
        </div>

        <div class="action-area">
            <a href="?action=execute" class="btn btn-primary" onclick="return confirm('Update all user passwords in Supabase?')">
                🔄 Update Supabase Database Now
            </a>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
