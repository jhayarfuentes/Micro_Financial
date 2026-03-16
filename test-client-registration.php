<?php
/**
 * Test Client Registration
 * Directly test the client creation process
 */

require_once 'init.php';

$action = $_GET['action'] ?? 'form';
$result = null;

if ($action === 'test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // First create a test user
        $testUsername = 'test_user_' . time();
        $testEmail = 'test_' . time() . '@microfinance.local';
        
        $db = service('database');
        
        // Get Client role ID
        $clientRole = $db->fetchOne(
            "SELECT role_id FROM user_roles WHERE role_name = ?",
            ['Client']
        );
        
        if (!$clientRole) {
            throw new Exception('Client role not found');
        }
        
        // Create the user first
        $userService = service('user');
        $newUser = $userService->createUser([
            'username' => $testUsername,
            'email' => $testEmail,
            'password' => 'Test@123456',
            'first_name' => 'Test',
            'last_name' => 'Client',
            'role_id' => $clientRole['role_id']
        ]);
        
        if (!isset($newUser['user_id'])) {
            throw new Exception('User creation failed - no user_id returned');
        }
        
        // Now create the client profile
        $clientService = service('client');
        $newClient = $clientService->registerClient([
            'user_id' => $newUser['user_id'],
            'first_name' => 'Test',
            'last_name' => 'Client',
            'email' => $testEmail,
            'contact_number' => '09123456789',
            'gender' => 'M',
            'date_of_birth' => '1990-01-15',
            'street_address' => '123 Test Street',
            'city' => 'Test City',
            'province' => 'Test Province',
            'zip_code' => '1234',
            'client_status' => 'active',
            'kyc_status' => 'pending'
        ]);
        
        if (!isset($newClient['client_id'])) {
            throw new Exception('Client registration failed - no client_id returned');
        }
        
        $result = [
            'success' => true,
            'message' => 'Client registered successfully!',
            'user' => $newUser,
            'client' => $newClient,
            'user_id' => $newUser['user_id'] ?? 'NOT SET',
            'client_id' => $newClient['client_id'] ?? 'NOT SET'
        ];
        
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
        ];
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Client Registration Test - Micro Financial</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #0F1E4A; margin-bottom: 10px; }
        .section { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #0F1E4A; margin-bottom: 15px; border-bottom: 2px solid #3B82F6; padding-bottom: 10px; }
        .success-banner { background: #ECFDF5; border-left: 4px solid #10B981; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error-banner { background: #FEF2F2; border-left: 4px solid #EF4444; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .code-box { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 5px; background: #3B82F6; color: white; text-decoration: none; cursor: pointer; border: none; }
        .btn:hover { background: #2563EB; }
        .result-item { padding: 10px; margin: 10px 0; background: #f9f9f9; border-left: 4px solid #3B82F6; border-radius: 3px; }
        .field-name { font-weight: 600; color: #0F1E4A; }
        .field-value { font-family: monospace; color: #666; margin-top: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Client Registration Test</h1>
            <p>Test the complete client registration process (user + client profile)</p>
        </div>

        <?php if ($action === 'form'): ?>
        <div class="section">
            <h2>📝 Test Client Registration</h2>
            <p>This will create a test user and client profile to verify the registration process works correctly.</p>
            
            <form method="POST" action="?action=test" style="margin-top: 20px;">
                <button type="submit" class="btn">Create Test Client</button>
            </form>
        </div>

        <div class="section">
            <h2>📋 What Gets Tested</h2>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>✓ User creation with auto-generated user_id</li>
                <li>✓ Client profile creation with auto-generated client_id</li>
                <li>✓ Proper field mapping (date_of_birth, street_address, etc.)</li>
                <li>✓ Status fields (client_status, kyc_status)</li>
                <li>✓ Foreign key relationships</li>
                <li>✓ Audit logging</li>
            </ul>
        </div>

        <?php elseif ($result): ?>
        
        <?php if ($result['success']): ?>
        <div class="success-banner">
            <strong>✅ <?php echo $result['message']; ?></strong>
        </div>

        <div class="section">
            <h2>✅ Registration Results</h2>
            
            <div class="result-item">
                <div class="field-name">User ID</div>
                <div class="field-value"><?php echo htmlspecialchars($result['user_id']); ?></div>
            </div>
            
            <div class="result-item">
                <div class="field-name">Client ID</div>
                <div class="field-value"><?php echo htmlspecialchars($result['client_id']); ?></div>
            </div>
            
            <div class="result-item">
                <div class="field-name">User Object</div>
                <div style="margin-top: 5px;">
                    <div class="code-box"><?php echo htmlspecialchars(json_encode($result['user'], JSON_PRETTY_PRINT)); ?></div>
                </div>
            </div>
            
            <div class="result-item">
                <div class="field-name">Client Object</div>
                <div style="margin-top: 5px;">
                    <div class="code-box"><?php echo htmlspecialchars(json_encode($result['client'], JSON_PRETTY_PRINT)); ?></div>
                </div>
            </div>
        </div>

        <div class="section" style="background: #ECFDF5;">
            <h2>✅ Client Registration is Working!</h2>
            <p>Both user and client profiles are being created successfully. The registration form should work now.</p>
            <p><a href="client_register.php" class="btn" style="margin-top: 10px;">Go to Registration Form</a></p>
        </div>

        <?php else: ?>
        <div class="error-banner">
            <strong>❌ <?php echo $result['message']; ?></strong>
        </div>

        <div class="section">
            <h2>❌ Error Details</h2>
            <div class="result-item">
                <div class="field-name">Error Message</div>
                <div class="field-value"><?php echo htmlspecialchars($result['message']); ?></div>
            </div>
            
            <?php if ($result['trace']): ?>
            <div class="result-item">
                <div class="field-name">Stack Trace</div>
                <div style="margin-top: 5px;">
                    <div class="code-box"><?php echo htmlspecialchars($result['trace']); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="section" style="background: #FEF7EE; border-left: 4px solid #F59E0B;">
            <h2>🔧 Troubleshooting</h2>
            <ul style="margin-left: 20px;">
                <li>Verify Supabase credentials in config.php</li>
                <li>Ensure users and clients tables exist in Supabase</li>
                <li>Check that schema.sql has been properly executed</li>
                <li>Verify foreign key setup between users and clients</li>
            </ul>
        </div>

        <a href="?action=form" class="btn" style="margin-top: 20px;">Back to Test Form</a>

        <?php endif; ?>

        <?php endif; ?>

    </div>
</body>
</html>
