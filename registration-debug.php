<?php
/**
 * Client Registration Debug Page
 * Checks if all required fields and services are available
 */

require_once 'init.php';

$checks = [
    'Services' => [],
    'Database' => [],
    'Roles' => []
];

// Test service availability
try {
    $checks['Services']['database'] = [
        'status' => 'OK',
        'message' => 'Database service available'
    ];
    $db = service('database');
} catch (Exception $e) {
    $checks['Services']['database'] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
}

try {
    $checks['Services']['user'] = [
        'status' => 'OK',
        'message' => 'User service available'
    ];
    $user = service('user');
} catch (Exception $e) {
    $checks['Services']['user'] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
}

try {
    $checks['Services']['client'] = [
        'status' => 'OK',
        'message' => 'Client service available'
    ];
    $client = service('client');
} catch (Exception $e) {
    $checks['Services']['client'] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
}

// Test database connectivity
try {
    $db = service('database');
    $tableCheck = $db->fetchOne("SELECT * FROM users LIMIT 1");
    $checks['Database']['users_table'] = [
        'status' => 'OK',
        'message' => 'Users table accessible'
    ];
} catch (Exception $e) {
    $checks['Database']['users_table'] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
}

// Test client role exists
try {
    $db = service('database');
    $clientRole = $db->fetchOne(
        "SELECT role_id FROM user_roles WHERE role_name = ?",
        ['Client']
    );
    if ($clientRole) {
        $checks['Roles']['client'] = [
            'status' => 'OK',
            'message' => "Client role found (ID: {$clientRole['role_id']})"
        ];
    } else {
        $checks['Roles']['client'] = [
            'status' => 'FAIL',
            'message' => 'Client role not found in database'
        ];
    }
} catch (Exception $e) {
    $checks['Roles']['client'] = [
        'status' => 'FAIL',
        'message' => $e->getMessage()
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Client Registration Debug - Micro Financial</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #0F1E4A; margin-bottom: 10px; }
        .section { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #0F1E4A; margin-bottom: 15px; border-bottom: 2px solid #3B82F6; padding-bottom: 10px; }
        .check-item { padding: 12px; margin: 10px 0; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
        .check-ok { background: #ECFDF5; border-left: 4px solid #10B981; }
        .check-fail { background: #FEF2F2; border-left: 4px solid #EF4444; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 3px; font-weight: 600; font-size: 12px; }
        .badge-ok { background: #D1FAE5; color: #065F46; }
        .badge-fail { background: #FEE2E2; color: #991B1B; }
        .message { font-size: 12px; color: #666; margin-top: 5px; }
        .actions { margin-top: 15px; }
        .btn { display: inline-block; padding: 10px 20px; background: #3B82F6; color: white; border-radius: 5px; text-decoration: none; margin-right: 10px; }
        .btn:hover { background: #2563EB; }
        .summary { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .summary-ok { background: #ECFDF5; border-left: 4px solid #10B981; }
        .summary-fail { background: #FEF2F2; border-left: 4px solid #EF4444; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Client Registration Debug</h1>
            <p>Check if all services and database dependencies are properly configured</p>
        </div>

        <?php 
            $allOk = true;
            foreach ($checks as $category) {
                foreach ($category as $check) {
                    if ($check['status'] !== 'OK') {
                        $allOk = false;
                    }
                }
            }
        ?>

        <div class="summary <?php echo $allOk ? 'summary-ok' : 'summary-fail'; ?>">
            <strong><?php echo $allOk ? '✅ All Systems OK' : '❌ Issues Detected'; ?></strong><br/>
            <small><?php echo $allOk ? 'Registration should work properly' : 'Please fix the issues below before testing registration'; ?></small>
        </div>

        <?php foreach ($checks as $categoryName => $categoryChecks): ?>
        <div class="section">
            <h2><?php echo $categoryName; ?></h2>
            
            <?php foreach ($categoryChecks as $checkName => $checkResult): ?>
            <div class="check-item <?php echo $checkResult['status'] === 'OK' ? 'check-ok' : 'check-fail'; ?>">
                <div>
                    <strong><?php echo ucfirst(str_replace('_', ' ', $checkName)); ?></strong>
                    <div class="message"><?php echo $checkResult['message']; ?></div>
                </div>
                <span class="status-badge badge-<?php echo strtolower($checkResult['status']); ?>">
                    <?php echo $checkResult['status']; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div class="section">
            <h2>🧪 Test Registration</h2>
            <p>If all checks pass, you can now test the registration:</p>
            <div class="actions">
                <a href="client_register.php" class="btn">Go to Registration Form</a>
                <a href="client_login.php" class="btn">Back to Login</a>
            </div>
        </div>

        <div class="section" style="background: #EFF6FF;">
            <h2>📝 Form Fields Checklist</h2>
            <p>The registration form should include these fields:</p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>✓ First Name</li>
                <li>✓ Last Name</li>
                <li>✓ Email</li>
                <li>✓ Mobile</li>
                <li>✓ Gender</li>
                <li>✓ Date of Birth</li>
                <li>✓ Civil Status</li>
                <li>✓ Street Address</li>
                <li>✓ City</li>
                <li>✓ Province</li>
                <li>✓ Zip Code</li>
                <li>✓ Password</li>
                <li>✓ Confirm Password</li>
            </ul>
        </div>
    </div>
</body>
</html>
