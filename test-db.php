<?php
/**
 * Database Connection Test
 * Testing Supabase REST API connectivity
 */

require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    log_message('INFO', 'Testing database connection...');
    
    $db = service('database');
    log_message('INFO', 'Database service instantiated');
    
    // Test a simple query
    $result = $db->fetchOne("SELECT * FROM users WHERE username = ?", ['admin']);
    log_message('INFO', 'Query executed, result: ' . json_encode($result));
    
    echo json_encode([
        'status' => 200,
        'success' => true,
        'message' => 'Database connection successful',
        'data' => [
            'user_found' => !is_null($result),
            'test_time' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    log_message('ERROR', 'Test failed: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ], JSON_PRETTY_PRINT);
}
?>
