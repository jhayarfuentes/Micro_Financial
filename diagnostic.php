<?php
/**
 * Supabase Configuration Diagnostic
 * Helps verify API keys and connectivity
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$diagnostics = [];

// Check API keys
$diagnostics['api_keys'] = [
    'label' => 'API Key Configuration',
    'checks' => [
        'supabase_host' => SUPABASE_HOST,
        'publishable_key_defined' => !empty(SUPABASE_PUBLISHABLE_KEY),
        'publishable_key_format' => strpos(SUPABASE_PUBLISHABLE_KEY, 'sb_publishable_') === 0 ? 'Valid format' : 'INVALID FORMAT',
        'secret_key_defined' => !empty(SUPABASE_SECRET_KEY),
        'secret_key_format' => strpos(SUPABASE_SECRET_KEY, 'sb_secret_') === 0 ? 'Valid format' : 'INVALID FORMAT',
        'secret_key_length' => strlen(SUPABASE_SECRET_KEY),
    ]
];

// Test REST API connectivity
$diagnostics['rest_api_test'] = [
    'label' => 'REST API Connectivity Test',
    'checks' => []
];

$url = SUPABASE_HOST . '/rest/v1/users?select=count()';
$headers = [
    'apikey: ' . SUPABASE_SECRET_KEY,
    'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
    'Content-Type: application/json',
    'Accept: application/json'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$diagnostics['rest_api_test']['checks'] = [
    'test_url' => $url,
    'http_status' => $httpCode,
    'curl_error' => $error ?: 'None',
    'response_preview' => substr($response, 0, 200),
];

if ($httpCode === 200) {
    $diagnostics['rest_api_test']['checks']['status'] = '✅ SUCCESS';
} elseif ($httpCode === 401) {
    $diagnostics['rest_api_test']['checks']['status'] = '❌ UNAUTHORIZED - Invalid API Key';
} else {
    $diagnostics['rest_api_test']['checks']['status'] = '⚠️ HTTP ' . $httpCode;
}

// Check logs
$diagnostics['logs'] = [
    'label' => 'Log Files',
    'checks' => [
        'app_log_exists' => file_exists(__DIR__ . '/../logs/app.log'),
        'error_log_exists' => file_exists(__DIR__ . '/../logs/error.log'),
    ]
];

if (file_exists(__DIR__ . '/../logs/app.log')) {
    $recent_logs = array_slice(file(__DIR__ . '/../logs/app.log'), -5);
    $diagnostics['logs']['recent_entries'] = $recent_logs;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Supabase Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3B82F6; }
        .section.error { border-left-color: #EF4444; }
        .section.warning { border-left-color: #F59E0B; }
        .section.success { border-left-color: #10B981; }
        h2 { margin-top: 0; color: #333; }
        .check { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .check:last-child { border-bottom: none; }
        .check-label { font-weight: bold; color: #666; }
        .check-value { color: #333; word-break: break-all; text-align: right; max-width: 60%; }
        .status { font-weight: bold; padding: 10px; border-radius: 3px; }
        .status.error { background: #FEE; color: #C00; }
        .status.success { background: #EFE; color: #0C0; }
        .status.warning { background: #FFE; color: #C80; }
        pre { background: #f9f9f9; padding: 10px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <h1>🔍 Supabase Configuration Diagnostic</h1>
    
    <?php foreach ($diagnostics as $section_key => $section): ?>
        <?php
            $class = 'section';
            if (isset($section['checks']['status'])) {
                if (strpos($section['checks']['status'], 'SUCCESS') !== false) {
                    $class .= ' success';
                } elseif (strpos($section['checks']['status'], 'INVALID') !== false) {
                    $class .= ' error';
                } else {
                    $class .= ' warning';
                }
            }
        ?>
        <div class="<?php echo $class; ?>">
            <h2><?php echo $section['label']; ?></h2>
            
            <?php foreach ($section['checks'] as $key => $value): ?>
                <div class="check">
                    <span class="check-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                    <span class="check-value">
                        <?php 
                            if (is_bool($value)) {
                                echo $value ? '✓ Yes' : '✗ No';
                            } else {
                                echo htmlspecialchars((string)$value);
                            }
                        ?>
                    </span>
                </div>
            <?php endforeach; ?>
            
            <?php if (isset($section['recent_entries'])): ?>
                <h3 style="margin-top: 15px;">Recent Log Entries:</h3>
                <pre><?php echo htmlspecialchars(implode('', $section['recent_entries'])); ?></pre>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <div class="section" style="margin-top: 20px;">
        <h2>📝 Instructions</h2>
        <p><strong>If you see "UNAUTHORIZED":</strong></p>
        <ol>
            <li>Log in to your Supabase dashboard: <a href="https://app.supabase.com" target="_blank">https://app.supabase.com</a></li>
            <li>Go to Settings → API</li>
            <li>Copy the <strong>service_role secret</strong> (NOT the anon key)</li>
            <li>Update <code>SUPABASE_SECRET_KEY</code> in <code>config.php</code></li>
            <li>Verify it starts with <code>sb_secret_</code></li>
        </ol>
        
        <p><strong>If you see other errors:</strong></p>
        <ol>
            <li>Check that <code>SUPABASE_HOST</code> matches your project URL</li>
            <li>Verify your Supabase project is active</li>
            <li>Ensure the <code>users</code> table exists in your database</li>
        </ol>
    </div>
</body>
</html>
<?php
?>
