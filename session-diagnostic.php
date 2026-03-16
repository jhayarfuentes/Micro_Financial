<?php
/**
 * Session Diagnostic
 * Checks if session is properly set after registration
 */

// Start OR resume session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Session Diagnostic</h1>";

echo "<h2>Session Status</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><td><strong>Session ID:</strong></td><td>" . session_id() . "</td></tr>";
echo "<tr><td><strong>Session Status:</strong></td><td>" . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "</td></tr>";
echo "<tr><td><strong>Session Name:</strong></td><td>" . session_name() . "</td></tr>";
echo "</table>";

echo "<h2>Session Variables</h2>";
if (empty($_SESSION)) {
    echo "<p style='color: orange;'><strong>⚠ Session is EMPTY</strong></p>";
} else {
    echo "<table border='1' cellpadding='10' style='margin: 20px 0;'>";
    echo "<tr style='background: #f0f0f0;'><th>Variable</th><th>Value</th></tr>";
    foreach ($_SESSION as $key => $value) {
        echo "<tr>";
        echo "<td><code>$" . htmlspecialchars($key) . "</code></td>";
        if (is_array($value)) {
            echo "<td><pre>" . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . "</pre></td>";
        } else {
            echo "<td><strong>" . htmlspecialchars($value) . "</strong></td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Cookies</h2>";
echo "<table border='1' cellpadding='10' style='margin: 20px 0;'>";
echo "<tr style='background: #f0f0f0;'><th>Cookie Name</th><th>Value (first 50 chars)</th></tr>";
if (empty($_COOKIE)) {
    echo "<tr><td colspan='2'>No cookies found</td></tr>";
} else {
    foreach ($_COOKIE as $key => $value) {
        $val = is_array($value) ? json_encode($value) : (string)$value;
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($key) . "</code></td>";
        echo "<td><code>" . htmlspecialchars(substr($val, 0, 50)) . (strlen($val) > 50 ? '...' : '') . "</code></td>";
        echo "</tr>";
    }
}
echo "</table>";

echo "<h2>What This Means</h2>";
if (!empty($_SESSION) && isset($_SESSION['role'])) {
    echo "<p style='color: green;'>";
    echo "<strong>✓ GOOD:</strong> Session has role set to: <strong>" . htmlspecialchars($_SESSION['role']) . "</strong>";
    echo "</p>";
    if ($_SESSION['role'] === 'Client') {
        echo "<p style='color: green;'>✓ Role is correctly set to <strong>Client</strong></p>";
        echo "<p>Try going to: <a href='client_portal.php'>client_portal.php</a></p>";
    } else {
        echo "<p style='color: orange;'>⚠ WARNING: Role is <strong>" . htmlspecialchars($_SESSION['role']) . "</strong> (not Client)</p>";
    }
} else {
    echo "<p style='color: red;'>";
    echo "<strong>✗ PROBLEM:</strong> Session role is not set or session is empty!";
    echo "</p>";
    echo "<p>This means the registration didn't properly save the session, or the session cookie isn't being sent to this page.</p>";
}

echo "<hr>";
if (!empty($_SESSION) && isset($_SESSION['role']) && $_SESSION['role'] === 'Client') {
    echo "<p><a href='client_portal.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Proceed to Client Portal →</a></p>";
} else {
    echo "<p><strong>Contact support or <a href='client_register.php'>try registering again</a></strong></p>";
}
?>
