<?php
/**
 * Test page to verify clients table data
 */
require_once __DIR__ . '/init.php';

header('Content-Type: text/html; charset=utf-8');

echo '<style>
  body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
  .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
  h1 { color: #333; border-bottom: 2px solid #3B82F6; padding-bottom: 10px; }
  h2 { color: #0f246c; font-size: 1.1rem; margin-top: 20px; }
  .info { background: #e3f2fd; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #3B82F6; }
  .error { background: #ffebee; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ef4444; color: #c62828; }
  .success { background: #e8f5e9; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #22c55e; color: #2e7d32; }
  table { width: 100%; border-collapse: collapse; margin: 15px 0; }
  th { background: #f5f5f5; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold; }
  td { padding: 10px; border-bottom: 1px solid #ddd; }
  tr:hover { background: #fafafa; }
  code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>

<div class="container">
  <h1>🔍 Client Table Diagnostic</h1>
  <p>Testing database connectivity and data retrieval...</p>
';

$db = Database::getInstance();

try {
  // Test 1: Count total clients
  echo '<h2>1. Database Connection</h2>';
  echo '<div class="success">✓ Database instance created successfully</div>';
  
  // Test 2: Query all clients
  echo '<h2>2. Fetching All Clients</h2>';
  $query = 'SELECT * FROM clients ORDER BY registration_date DESC LIMIT 100';
  echo '<div class="info">Running query: <code>' . htmlspecialchars($query) . '</code></div>';
  
  $clients = $db->fetchAll($query);
  
  if (empty($clients)) {
    echo '<div class="error">❌ No clients found in database</div>';
  } else {
    echo '<div class="success">✓ Found ' . count($clients) . ' client(s)</div>';
    
    echo '<h2>3. Client Data Table</h2>';
    echo '<table>';
    echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>KYC Status</th><th>Registration Date</th></tr>';
    
    foreach ($clients as $client) {
      $name = htmlspecialchars(($client['first_name'] ?? 'N/A') . ' ' . ($client['last_name'] ?? ''));
      $email = htmlspecialchars($client['email'] ?? 'N/A');
      $phone = htmlspecialchars($client['contact_number'] ?? 'N/A');
      $status = htmlspecialchars($client['client_status'] ?? 'N/A');
      $kyc = htmlspecialchars($client['kyc_status'] ?? 'N/A');
      $date = htmlspecialchars($client['registration_date'] ?? 'N/A');
      
      echo "<tr>";
      echo "<td>{$client['client_id']}</td>";
      echo "<td><strong>$name</strong></td>";
      echo "<td>$email</td>";
      echo "<td>$phone</td>";
      echo "<td>$status</td>";
      echo "<td>$kyc</td>";
      echo "<td>$date</td>";
      echo "</tr>";
    }
    echo '</table>';
  }
  
  // Test 3: Check for the most recent client
  if (!empty($clients)) {
    echo '<h2>4. Most Recent Client Details</h2>';
    $latest = $clients[0];
    echo '<table>';
    echo '<tr><th>Field</th><th>Value</th></tr>';
    foreach ($latest as $key => $value) {
      $displayValue = is_null($value) ? '<em>null</em>' : htmlspecialchars((string)$value);
      echo "<tr><td><code>$key</code></td><td>$displayValue</td></tr>";
    }
    echo '</table>';
  }
  
} catch (Exception $e) {
  echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
  echo '<h2>Stack Trace</h2>';
  echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo '
  <hr style="margin-top: 30px; border: none; border-top: 1px solid #ddd;">
  <p style="color: #666; font-size: 0.9rem;">
    <strong>Debugging Tips:</strong><br>
    • If no clients show, check the app logs at <code>/logs/app.log</code><br>
    • Verify the clients table exists in your database<br>
    • Check that the email field is unique to prevent duplicates<br>
    • Click "Add Client" in the main page and then return here to see if new client appears
  </p>
  <p><a href="client_registration.php" style="color: #3B82F6; text-decoration: none;">← Back to Client Registration</a></p>
</div>
';
?>
