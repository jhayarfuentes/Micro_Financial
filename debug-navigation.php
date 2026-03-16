<?php
// Simple debug page to test navigation filtering
session_start();

// Get user role from session
$userRole = $_SESSION['role'] ?? 'NOT SET';
$userRole = trim($userRole);

// Define all navigation items (same as layout.php)
$all_nav_items = [
    'CT1 · CLIENT SERVICES' => [
        ['icon' => 'bx-tachometer', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
        ['icon' => 'bx-user-plus', 'label' => 'Client Registration & KYC', 'href' => 'client_registration.php'],
    ],
    'CT2 · INSTITUTIONAL OVERSIGHT' => [
        ['icon' => 'bx-bar-chart-alt-2', 'label' => 'Loan Portfolio & Risk', 'href' => 'loan_portfolio.php'],
    ],
    'CT3 · STAFF OPERATIONS' => [
        ['icon' => 'bx-id-card', 'label' => 'KYC Verification', 'href' => 'kyc_verification.php'],
    ],
];

?>
<!DOCTYPE html>
<html>
<head>
<title>Navigation Debug</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1 { color: #333; }
.debug-box { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3B82F6; }
.label { font-weight: bold; color: #666; }
.value { color: #333; font-family: monospace; background: #f0f0f0; padding: 5px 10px; border-radius: 3px; display: inline-block; }
.success { color: green; }
.error { color: red; }
.test-result { margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #ddd; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
th { background: #f0f0f0; font-weight: bold; }
</style>
</head>
<body>

<h1>Navigation Filtering Debug</h1>

<div class="debug-box">
  <div class="label">Session Role:</div>
  <div class="value" style="background: <?= $_SESSION['role'] ?? 'NOT SET' ? '#e8f5e9' : '#ffebee' ?>"><?php 
    echo $_SESSION['role'] ?? 'NOT SET'; 
  ?></div>
</div>

<div class="debug-box">
  <div class="label">Trimmed Role:</div>
  <div class="value"><?= htmlspecialchars($userRole) ?></div>
  <div class="test-result">
    Length: <?= strlen($userRole) ?> characters<br>
    Hex: <?= bin2hex($userRole) ?><br>
    (Helps detect invisible characters)
  </div>
</div>

<h2>Condition Test Results</h2>
<table>
<tr>
  <th>Condition</th>
  <th>Result</th>
  <th>Code</th>
</tr>
<tr style="<?= strcasecmp($userRole, 'Client') === 0 ? 'background: #e8f5e9' : '' ?>">
  <td>Client Check</td>
  <td class="<?= strcasecmp($userRole, 'Client') === 0 ? 'success' : 'error' ?>">
    <?= strcasecmp($userRole, 'Client') === 0 ? '✓ MATCH' : '✗ no match' ?>
  </td>
  <td><code>strcasecmp('<?= htmlspecialchars($userRole) ?>', 'Client') === 0</code></td>
</tr>
<tr style="<?= (strcasecmp($userRole, 'Admin') === 0 || strcasecmp($userRole, 'Administrator') === 0) ? 'background: #e8f5e9' : '' ?>">
  <td>Admin Check</td>
  <td class="<?= (strcasecmp($userRole, 'Admin') === 0 || strcasecmp($userRole, 'Administrator') === 0) ? 'success' : 'error' ?>">
    <?= (strcasecmp($userRole, 'Admin') === 0 || strcasecmp($userRole, 'Administrator') === 0) ? '✓ MATCH' : '✗ no match' ?>
  </td>
  <td><code>strcasecmp('<?= htmlspecialchars($userRole) ?>', 'Admin||Administrator') === 0</code></td>
</tr>
<tr style="<?= strcasecmp($userRole, 'Portfolio Manager') === 0 ? 'background: #e8f5e9' : '' ?>">
  <td>Portfolio Manager Check</td>
  <td class="<?= strcasecmp($userRole, 'Portfolio Manager') === 0 ? 'success' : 'error' ?>">
    <?= strcasecmp($userRole, 'Portfolio Manager') === 0 ? '✓ MATCH' : '✗ no match' ?>
  </td>
  <td><code>strcasecmp('<?= htmlspecialchars($userRole) ?>', 'Portfolio Manager') === 0</code></td>
</tr>
<tr style="<?= strcasecmp($userRole, 'Compliance Officer') === 0 ? 'background: #e8f5e9' : '' ?>">
  <td>Compliance Officer Check</td>
  <td class="<?= strcasecmp($userRole, 'Compliance Officer') === 0 ? 'success' : 'error' ?>">
    <?= strcasecmp($userRole, 'Compliance Officer') === 0 ? '✓ MATCH' : '✗ no match' ?>
  </td>
  <td><code>strcasecmp('<?= htmlspecialchars($userRole) ?>', 'Compliance Officer') === 0</code></td>
</tr>
</table>

<h2>Navigation Items That Should Appear</h2>
<?php

// Simulate the filtering logic from layout.php
if (strcasecmp($userRole, 'Client') === 0) {
    $nav_items = [
        'MY ACCOUNT' => ['My Portal (4 sub-items)']
    ];
    $reason = "Client role detected";
} elseif (strcasecmp($userRole, 'Admin') === 0 || strcasecmp($userRole, 'Administrator') === 0) {
    $nav_items = $all_nav_items;
    $nav_items['CT4 · SYSTEM ADMINISTRATION'] = ['(admin section)'];
    $reason = "Admin role detected";
} elseif (strcasecmp($userRole, 'Portfolio Manager') === 0) {
    $nav_items = [
        'CT1 · CLIENT SERVICES' => ['(from all_nav_items)'],
        'CT2 · INSTITUTIONAL OVERSIGHT' => ['(from all_nav_items)'],
    ];
    $reason = "Portfolio Manager role detected";
} elseif (strcasecmp($userRole, 'Compliance Officer') === 0) {
    $nav_items = [
        'CT1 · CLIENT SERVICES' => ['(from all_nav_items)'],
        'CT2 · INSTITUTIONAL OVERSIGHT' => ['(from all_nav_items)'],
        'CT3 · STAFF OPERATIONS' => ['(from all_nav_items)'],
    ];
    $reason = "Compliance Officer role detected";
} else {
    $nav_items = [
        'CT1 · CLIENT SERVICES' => ['(from all_nav_items)'],
        'CT3 · STAFF OPERATIONS' => ['(from all_nav_items)'],
    ];
    $reason = "Staff/Other role detected (default)";
}

?>
<div class="debug-box">
  <div class="label">Applied Rule:</div>
  <div class="value" style="background: #fff3e0"><?= $reason ?></div>
</div>

<div class="debug-box">
  <div class="label">Section Count:</div>
  <div class="value" style="font-size: 24px; background: #e3f2fd; padding: 15px"><?= count($nav_items) ?></div>
</div>

<div class="debug-box">
  <div class="label">Sections:</div>
  <ul>
    <?php foreach (array_keys($nav_items) as $section): ?>
    <li><?= htmlspecialchars($section) ?></li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="debug-box" style="background: #fff3cd; border-left: 4px solid #ff9800;">
  <h3>View PHP Error Logs</h3>
  <p>The detailed filtering logs are written to the PHP error log. Check:</p>
  <ul>
    <li>c:/xampp/logs/php_error.log</li>
    <li>Or use: <code>tail -f c:/xampp/logs/php_error.log</code> in a terminal</li>
  </ul>
  <p>The logs will show exactly which condition matched and the final section count.</p>
</div>

<hr>
<p>🔍 <strong>Interpretation:</strong><br>
If the "Section Count" above is less than 4, then the filtering logic IS working!<br>
If it shows 4 sections (CT1, CT2, CT3, and one more), then the filtering is the problem.<br>
<br>
For Clients: Should show 1 section (MY ACCOUNT)<br>
For Admins: Should show 4 sections (CT1, CT2, CT3, CT4)<br>
For Portfolio Managers: Should show 2 sections (CT1, CT2)<br>
For Compliance Officers: Should show 3 sections (CT1, CT2, CT3)<br>
For Staff/Other: Should show 2 sections (CT1, CT3)
</p>

</body>
</html>
