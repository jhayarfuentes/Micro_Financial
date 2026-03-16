<?php
/**
 * Generate Valid Bcrypt Password Hashes for Test Users
 * Use these hashes to update schema.sql
 */

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

?>
<!DOCTYPE html>
<html>
<head>
    <title>Generate Valid Bcrypt Hashes</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #0F1E4A; }
        .user-hash { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #3B82F6; }
        .user-hash .username { font-weight: bold; color: #0F1E4A; margin-bottom: 5px; }
        .user-hash .password { font-size: 12px; color: #666; margin-bottom: 5px; }
        .user-hash .hash { background: #1e293b; color: #e2e8f0; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 11px; word-break: break-all; }
        .sql-box { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 3px; font-family: monospace; font-size: 12px; overflow-x: auto; margin-top: 15px; }
        .copy-btn { background: #3B82F6; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer; margin-top: 10px; }
        .copy-btn:hover { background: #2563EB; }
        .warning { background: #FEF2F2; border-left: 4px solid #EF4444; padding: 15px; border-radius: 3px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Generate Valid Bcrypt Password Hashes</h1>
        
        <div class="warning">
            <strong>⚠️ Problem Found:</strong> The password hashes in schema.sql are invalid placeholders. This is why all accounts have "invalid credentials" errors.
        </div>
        
        <div class="section">
            <h2>✅ Valid Bcrypt Hashes for Test Users</h2>
            
            <?php foreach ($testUsers as $user): ?>
                <?php $hash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 10]); ?>
                <div class="user-hash">
                    <div class="username">👤 <?php echo $user['username']; ?></div>
                    <div class="password">Password: <code><?php echo $user['password']; ?></code></div>
                    <div class="hash"><?php echo $hash; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section">
            <h2>🔄 Solution: Update schema.sql</h2>
            
            <p><strong>Option 1: Run create-users.php (Recommended)</strong></p>
            <p>After Supabase initialization, visit:</p>
            <p><code>http://localhost/Micro_Financial/Micro_Financial-main/create-users.php</code></p>
            <p>This will create all 9 user accounts with proper bcrypt hashes automatically.</p>
            
            <p style="margin-top: 20px;"><strong>Option 2: Update schema.sql with valid hashes</strong></p>
            <p>Replace the invalid placeholder hashes with the valid ones above in the INSERT statement (around line 307).</p>
        </div>
        
        <div class="section">
            <h2>🔑 Test Login Credentials</h2>
            
            <?php foreach ($testUsers as $user): ?>
                <div class="user-hash">
                    <div class="username">👤 <?php echo $user['username']; ?></div>
                    <div style="font-size: 12px; color: #666;">
                        <div>Username: <code><?php echo $user['username']; ?></code></div>
                        <div>Password: <code><?php echo $user['password']; ?></code></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section" style="background: #ECFDF5; border-left: 4px solid #10B981;">
            <h2>✅ Recommended Fix Steps</h2>
            <ol>
                <li>Visit <code>create-users.php</code> in your browser</li>
                <li>This will create all 9 user accounts with proper bcrypt hashes</li>
                <li>Test login with any of the usernames and passwords above</li>
                <li>Verify navigation displays correctly for each role</li>
            </ol>
        </div>
    </div>
</body>
</html>
