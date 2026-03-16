<?php
/**
 * Navigation Destinations Verification
 * Checks if all page files referenced in layout.php exist
 */

// All destination pages referenced in navigation
$destinations = [
    'dashboard.php',
    'client_registration.php',
    'loan_application.php',
    'loan_repayment.php',
    'savings_account.php',
    'group_lending.php',
    'client_portal.php',
    'loan_portfolio.php',
    'savings_monitoring.php',
    'fund_allocation.php',
    'compliance.php',
    'reports.php',
    'user_management.php',
    'kyc_verification.php',
    'loan_approval.php',
    'loan_collection.php',
    'savings_management.php',
    'compliance_dashboard.php',
    'portal_loans.php',
    'portal_repayments.php',
    'portal_savings.php',
    'portal_kyc.php',
];

$basePath = __DIR__;
$missing = [];
$exist = [];

foreach ($destinations as $file) {
    $fullPath = $basePath . '/' . $file;
    if (file_exists($fullPath)) {
        $exist[] = $file;
    } else {
        $missing[] = $file;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Navigation Destination Verification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .exist { border-left: 4px solid #10B981; }
        .missing { border-left: 4px solid #EF4444; }
        h2 { margin-top: 0; }
        .file-list { list-style: none; padding: 0; }
        .file-item { padding: 8px 0; display: flex; align-items: center; }
        .exist .file-item::before { content: "✅ "; margin-right: 8px; }
        .missing .file-item::before { content: "❌ "; margin-right: 8px; }
        .count { font-size: 18px; font-weight: bold; margin-top: 10px; }
        code { background: #f0f0f0; padding: 2px 8px; border-radius: 3px; font-family: monospace; }
        .status-good { color: #10B981; }
        .status-warning { color: #EF4444; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Navigation Destination Verification</h1>
        
        <div class="section exist">
            <h2>✅ Pages Found (<?php echo count($exist); ?>)</h2>
            <ul class="file-list">
                <?php foreach ($exist as $file): ?>
                    <li class="file-item"><code><?php echo $file; ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if (!empty($missing)): ?>
        <div class="section missing">
            <h2>❌ Pages Missing (<?php echo count($missing); ?>)</h2>
            <p>The following pages are referenced in navigation but don't exist yet:</p>
            <ul class="file-list">
                <?php foreach ($missing as $file): ?>
                    <li class="file-item"><code><?php echo $file; ?></code></li>
                <?php endforeach; ?>
            </ul>
            
            <div style="background: #FEF2F2; padding: 15px; margin-top: 15px; border-radius: 3px;">
                <strong>Create Missing Pages:</strong>
                <p>These files need to be created to avoid 404 errors when users click navigation links.</p>
                <p style="font-size: 12px; color: #666;">You can create them as:</p>
                <ul style="font-size: 12px; color: #666;">
                    <li>Simple template pages with page title and layout</li>
                    <li>Or implement full functionality for each role</li>
                </ul>
            </div>
        </div>
        <?php else: ?>
        <div class="section" style="background: #ECFDF5; border-left: 4px solid #10B981;">
            <h2 style="color: #10B981;">✅ All Pages Exist!</h2>
            <p>All the destination pages referenced in the navigation are present.</p>
        </div>
        <?php endif; ?>
        
        <div class="section" style="background: #EFF6FF; border-left: 4px solid #3B82F6;">
            <h2>📊 Summary</h2>
            <div class="count">
                Found: <span class="status-good"><?php echo count($exist); ?></span> / 
                Missing: <span class="status-warning"><?php echo count($missing); ?></span> / 
                Total: <strong><?php echo count($destinations); ?></strong>
            </div>
            <p><strong>Completion:</strong> <?php echo round((count($exist) / count($destinations)) * 100, 1); ?>%</p>
        </div>
    </div>
</body>
</html>
