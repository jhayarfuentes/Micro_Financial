<?php
/**
 * Quick Session Check
 * Verifies the session is properly set up
 */

// This will trigger session_start exactly as layout.php now does
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html>";
echo "<html><head><title>Session Check</title>";
echo "<style>body { font-family: Arial; margin: 20px; background: #f5f5f5; }";
echo ".box { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #3B82F6; }";
echo ".success { border-left-color: #4CAF50; }";
echo ".warning { border-left-color: #ff9800; }";
echo "code { background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-family: monospace; }";
echo "</style></head><body>";

echo "<h1>Session Status Check</h1>";

echo "<div class='box'>";
echo "<h3>Session ID</h3>";
echo "<code>" . session_id() . "</code>";
echo "</div>";

echo "<div class='box'>";
echo "<h3>Session Status</h3>";
echo "<p>Status: " . (session_status() === PHP_SESSION_ACTIVE ? "✓ ACTIVE" : "✗ NOT ACTIVE") . "</p>";
echo "</div>";

echo "<div class='box'>";
echo "<h3>Session Variables</h3>";
if (empty($_SESSION)) {
    echo "<p style='color: orange;'>⚠ No session variables found</p>";
} else {
    echo "<pre>";
    foreach ($_SESSION as $key => $value) {
        echo "$key = " . (is_array($value) ? json_encode($value) : htmlspecialchars($value)) . "\n";
    }
    echo "</pre>";
}
echo "</div>";

echo "<div class='box success'>";
echo "<h3>Role Information</h3>";
echo "<p><strong>Current Role:</strong> <code>" . ($_SESSION['role'] ?? 'NOT SET') . "</code></p>";
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'client') {
    echo "<p style='color: green;'>✓ This is a CLIENT role - sidebar should show MY ACCOUNT only!</p>";
} else if (isset($_SESSION['role'])) {
    echo "<p style='color: orange;'>⚠ Role is '" . htmlspecialchars($_SESSION['role']) . "' - check if this is correct</p>";
} else {
    echo "<p style='color: red;'>✗ Role not set in session - try logging in again</p>";
}
echo "</div>";

echo "<div class='box'>";
echo "<h3>Next Steps</h3>";
echo "<ul>";
echo "<li>If role shows 'Client' here, the session is working!</li>";
echo "<li>Go to <a href='client_portal.php'><strong>client_portal.php</strong></a> and check the sidebar</li>";
echo "<li>The sidebar role badge should now show 'Client' (not 'Guest')</li>";
echo "<li>Only the 'MY ACCOUNT' section should be visible</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?>
