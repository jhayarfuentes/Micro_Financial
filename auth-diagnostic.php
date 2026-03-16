<?php
/**
 * Authentication Diagnostic Page
 * Checks current user account status and password hash validity
 */

require_once 'config.php';

try {
    $db = Database::getInstance();
    
    // Get all users
    $usersQuery = "SELECT user_id, username, email, password_hash, is_active FROM users";
    $users = $db->fetchAll($usersQuery) ?? [];
    
} catch (Exception $e) {
    $dbError = $e->getMessage();
    $users = [];
}

function isValidBcryptHash($hash) {
    // Valid bcrypt hash must:
    // 1. Start with $2a$, $2b$, or $2y$
    // 2. Be exactly 60 characters
    if (!is_string($hash)) return false;
    if (strlen($hash) !== 60) return false;
    if (!preg_match('/^\$2[aby]\$\d{2}\$/', $hash)) return false;
    return true;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Authentication Diagnostic - Micro Financial</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DM Sans', Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #0F1E4A; margin-bottom: 10px; }
        .status-banner { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .status-good { background: #ECFDF5; border-left: 4px solid #10B981; color: #065F46; }
        .status-bad { background: #FEF2F2; border-left: 4px solid #EF4444; color: #7F1D1D; }
        .status-warning { background: #FEF7EE; border-left: 4px solid #F59E0B; color: #92400E; }
        .section { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #0F1E4A; margin-bottom: 15px; border-bottom: 2px solid #3B82F6; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0F1E4A; color: white; font-weight: 500; }
        tr:hover { background: #f5f5f5; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
        .badge-valid { background: #D1FAE5; color: #065F46; }
        .badge-invalid { background: #FEE2E2; color: #991B1B; }
        .badge-active { background: #DBEAFE; color: #0C4A6E; }
        .badge-inactive { background: #F3F4F6; color: #374151; }
        .hash-display { font-family: monospace; font-size: 11px; background: #f9f9f9; padding: 8px; border-radius: 3px; word-break: break-all; }
        .action-buttons { margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin-right: 10px; margin-bottom: 10px; font-weight: 500; }
        .btn-primary { background: #3B82F6; color: white; }
        .btn-primary:hover { background: #2563EB; }
        .btn-success { background: #10B981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-warning { background: #F59E0B; color: white; }
        .btn-warning:hover { background: #D97706; }
        .error-box { background: #FEF2F2; border-left: 4px solid #EF4444; padding: 15px; border-radius: 5px; margin: 15px 0; color: #7F1D1D; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Authentication Diagnostic</h1>
            <p>Check the status of all user accounts and password hashes</p>
        </div>

        <?php
        $validCount = 0;
        $invalidCount = 0;
        $activeCount = 0;
        
        if (!empty($users)) {
            foreach ($users as $user) {
                if (isValidBcryptHash($user['password_hash'])) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }
                if ($user['is_active']) {
                    $activeCount++;
                }
            }
        }
        ?>

        <!-- Status Overview -->
        <div class="section">
            <h2>📊 Account Status Overview</h2>
            
            <div class="status-banner <?php echo ($invalidCount === 0) ? 'status-good' : 'status-bad'; ?>">
                <strong><?php echo ($invalidCount === 0) ? '✅ All Accounts Valid' : '❌ Invalid Password Hashes Found'; ?></strong><br/>
                <small>Valid Hashes: <?php echo $validCount; ?>/<?php echo count($users); ?> | Active: <?php echo $activeCount; ?>/<?php echo count($users); ?></small>
            </div>

            <?php if ($invalidCount > 0): ?>
            <div class="error-box">
                <strong>⚠️ Issue Detected:</strong> <?php echo $invalidCount; ?> account(s) have invalid password hashes. These accounts cannot be used for login.
            </div>
            <?php endif; ?>
        </div>

        <!-- User Accounts Table -->
        <?php if (!empty($users)): ?>
        <div class="section">
            <h2>👥 User Accounts Status</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Hash Status</th>
                        <th>Active</th>
                        <th>Hash Preview</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php if (isValidBcryptHash($user['password_hash'])): ?>
                                <span class="badge badge-valid">✅ Valid</span>
                            <?php else: ?>
                                <span class="badge badge-invalid">❌ Invalid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="hash-display" title="<?php echo htmlspecialchars($user['password_hash']); ?>">
                                <?php echo substr($user['password_hash'], 0, 25); ?>...
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Solutions -->
        <div class="section">
            <h2>🔧 Fix Invalid Passwords</h2>
            
            <p><strong>Choose one of these solutions to fix the invalid credential error:</strong></p>
            
            <div class="action-buttons">
                <a href="fix-invalid-passwords.php" class="btn btn-success">
                    ✅ Option 1: Run Auto-Fix (Recommended)
                </a>
                <a href="create-users.php" class="btn btn-primary">
                    👤 Option 2: Create Users Script
                </a>
                <a href="generate-hashes.php" class="btn btn-warning">
                    🔐 Option 3: View Valid Hashes
                </a>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0F1E4A;">📋 What to do:</h3>
                <ol style="margin-left: 20px;">
                    <li>Click "Run Auto-Fix" button above</li>
                    <li>Wait for confirmation message</li>
                    <li>Test login with: <code>admin</code> / <code>Admin@123456</code></li>
                    <li>Other test accounts will also be available with their respective passwords</li>
                </ol>
            </div>
        </div>

        <!-- Test Credentials -->
        <div class="section" style="background: #EFF6FF; border-left: 4px solid #3B82F6;">
            <h2>🔑 Test Credentials</h2>
            
            <p>After running the auto-fix, use these credentials to test login:</p>
            
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>admin</code></td>
                        <td><code>Admin@123456</code></td>
                        <td>Admin (All Access)</td>
                    </tr>
                    <tr>
                        <td><code>branch_manager</code></td>
                        <td><code>Manager@123456</code></td>
                        <td>Portfolio Manager</td>
                    </tr>
                    <tr>
                        <td><code>teller</code></td>
                        <td><code>Teller@123456</code></td>
                        <td>Staff / Teller</td>
                    </tr>
                    <tr>
                        <td><code>kyc_officer</code></td>
                        <td><code>KYC@123456</code></td>
                        <td>KYC Officer</td>
                    </tr>
                    <tr>
                        <td><code>client_demo</code></td>
                        <td><code>Client@123456</code></td>
                        <td>Client User</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="text-align: center; font-size: 12px; font-style: italic;">...and 4 more roles</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if (isset($dbError)): ?>
        <div class="section" style="background: #FEF2F2; border-left: 4px solid #EF4444;">
            <h2>⚠️ Database Error</h2>
            <p><?php echo htmlspecialchars($dbError); ?></p>
            <p style="font-size: 12px; margin-top: 10px;">Make sure Supabase is properly configured in <code>config.php</code></p>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
