<?php
require_once 'init.php';

try {
    $db = Database::getInstance();

    $auditRows = $db->fetchAll('SELECT * FROM audit_trail ORDER BY action_timestamp DESC LIMIT 1000') ?? [];
    $complianceRows = $db->fetchAll('SELECT * FROM compliance_audit ORDER BY check_date DESC LIMIT 1000') ?? [];

    echo 'audit rows=' . count($auditRows) . ' compliance rows=' . count($complianceRows) . "\n";

    echo "<pre>";
    echo "audit sample: " . htmlspecialchars(json_encode(array_slice($auditRows, 0, 5))) . "\n";
    echo "compliance sample: " . htmlspecialchars(json_encode(array_slice($complianceRows, 0, 5))) . "\n";
    echo "</pre>";

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage()) . "\n";
}

