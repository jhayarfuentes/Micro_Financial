<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$response = [
    'supabase_host' => SUPABASE_HOST,
    'secret_key_length' => strlen(SUPABASE_SECRET_KEY),
    'secret_key_preview' => substr(SUPABASE_SECRET_KEY, 0, 20) . '...',
    'secret_key_prefix' => substr(SUPABASE_SECRET_KEY, 0, 10),
    'headers_that_will_be_sent' => [
        'apikey' => substr(SUPABASE_SECRET_KEY, 0, 20) . '...',
        'Authorization' => 'Bearer ' . substr(SUPABASE_SECRET_KEY, 0, 20) . '...'
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
