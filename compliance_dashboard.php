<?php
/**
 * Compliance Dashboard Module - Staff Dashboard  
 * Monitor compliance metrics and audit trails
 */

require_once __DIR__ . '/init.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasRole('Compliance Officer')) {
    die('Access Denied: Compliance Officer role required');
}

$currentUser = getCurrentUser();

try {
    $db = service('database');
    
    // Get statistics
    $stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT ka.kyc_id) as total_kyc,
            COUNT(DISTINCT CASE WHEN ka.verification_status = 'pending' THEN ka.kyc_id END) as pending_kyc,
            COUNT(DISTINCT at.audit_id) as total_audits,
            COUNT(DISTINCT CASE WHEN at.action_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN at.audit_id END) as recent_audits
        FROM kyc_verification ka
        CROSS JOIN audit_trail at
    ");
    
    // Get recent audit trail
    $auditLogs = $db->fetchAll("
        SELECT 
            at.audit_id, at.user_id, u.username, at.action, at.action_date,
            at.severity, u.role_name
        FROM audit_trail at
        JOIN system_users u ON at.user_id = u.user_id
        ORDER BY at.action_date DESC
        LIMIT 15
    ");
    
    // Get KYC compliance status
    $complianceStatus = $db->fetchAll("
        SELECT 
            ka.kyc_id, c.first_name, c.last_name, ka.verification_status, ka.submitted_date
        FROM kyc_verification ka
        JOIN clients c ON ka.client_id = c.client_id
        ORDER BY ka.submitted_date DESC
        LIMIT 15
    ");
} catch (Exception $e) {
    $error_msg = htmlspecialchars($e->getMessage());
    $stats = null;
    $auditLogs = [];
    $complianceStatus = [];
}

$page_content = <<<HTML
<div class="page-content">

  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-clipboard'></i></div>
    <div class="page-header-text">
      <h2>Compliance Dashboard</h2>
      <p>Monitor compliance metrics, audit trails, and regulatory requirements.</p>
    </div>
  </div>
HTML;

if (isset($error_msg)) {
    $page_content .= <<<HTML
  <div style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:1rem; border-radius:4px; margin-bottom:1.5rem;">
    Error: $error_msg
  </div>
HTML;
}

if ($stats) {
    $page_content .= <<<HTML
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #3B82F6;">
      <div style="font-size:2rem; font-weight:bold; color:#3B82F6;">{$stats['total_kyc']}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Total KYC Records</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #f59e0b;">
      <div style="font-size:2rem; font-weight:bold; color:#f59e0b;">{$stats['pending_kyc']}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Pending Verification</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #06b6d4;">
      <div style="font-size:2rem; font-weight:bold; color:#06b6d4;">{$stats['total_audits']}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Total Audit Events</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #22c55e;">
      <div style="font-size:2rem; font-weight:bold; color:#22c55e;">{$stats['recent_audits']}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Last 30 Days</div>
    </div>
  </div>
HTML;
}

$page_content .= <<<HTML

  <!-- Tabs -->
  <div style="display:flex; gap:0; margin-bottom:2rem; border-bottom:2px solid rgba(59,130,246,0.14);">
    <button class="tab-btn active" onclick="switchTab(event, 'audit')" style="padding:1rem 1.5rem; background:none; border:none; cursor:pointer; font-size:1rem; border-bottom:3px solid #3B82F6; color:#3B82F6; font-weight:500;">Audit Trail</button>
    <button class="tab-btn" onclick="switchTab(event, 'compliance')" style="padding:1rem 1.5rem; background:none; border:none; cursor:pointer; font-size:1rem; border-bottom:3px solid transparent; color:#8EA0C4; font-weight:500;">Compliance Status</button>
  </div>

  <!-- Tab: Audit Trail -->
  <div id="audit" class="tab-content" style="display:block;">
    <div style="background:white; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); overflow:hidden; margin-bottom:2rem;">
HTML;

if (count($auditLogs) > 0) {
    $page_content .= '<table style="width:100%; border-collapse:collapse;"><thead style="background:rgba(240,244,255,0.8);"><tr><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Date & Time</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">User</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Role</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Action</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Severity</th></tr></thead><tbody>';
    
    foreach ($auditLogs as $log) {
        $username = htmlspecialchars($log['username']);
        $role = htmlspecialchars($log['role_name']);
        $action = htmlspecialchars($log['action']);
        $date = date('M d, Y H:i', strtotime($log['action_date']));
        $severity = strtolower($log['severity']);
        $severity_class = $severity === 'low' ? 'background:#d4edda; color:#155724;' : ($severity === 'medium' ? 'background:#fff3cd; color:#856404;' : 'background:#f8d7da; color:#721c24;');
        
        $page_content .= "<tr style=\"border-bottom:1px solid rgba(59,130,246,0.06);\"><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$date</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$username</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$role</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$action</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><span style=\"padding:0.25rem 0.5rem; border-radius:4px; font-size:0.75rem; font-weight:600; $severity_class\">".ucfirst($severity)."</span></td></tr>";
    }
    
    $page_content .= '</tbody></table>';
} else {
    $page_content .= '<div style="padding:2rem; text-align:center; color:#8EA0C4;">No audit logs found.</div>';
}

$page_content .= <<<HTML
    </div>
  </div>

  <!-- Tab: Compliance Status -->
  <div id="compliance" class="tab-content" style="display:none;">
    <div style="background:white; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); overflow:hidden; margin-bottom:2rem;">
HTML;

if (count($complianceStatus) > 0) {
    $page_content .= '<table style="width:100%; border-collapse:collapse;"><thead style="background:rgba(240,244,255,0.8);"><tr><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Client Name</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Submitted</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Status</th></tr></thead><tbody>';
    
    foreach ($complianceStatus as $comp) {
        $client_name = htmlspecialchars($comp['first_name'] . ' ' . $comp['last_name']);
        $submitted = date('M d, Y', strtotime($comp['submitted_date']));
        $status = strtolower($comp['verification_status']);
        $status_class = $status === 'verified' ? 'background:#d4edda; color:#155724;' : ($status === 'pending' ? 'background:#fff3cd; color:#856404;' : 'background:#f8d7da; color:#721c24;');
        
        $page_content .= "<tr style=\"border-bottom:1px solid rgba(59,130,246,0.06);\"><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><strong>$client_name</strong></td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$submitted</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><span style=\"padding:0.25rem 0.5rem; border-radius:4px; font-size:0.75rem; font-weight:600; $status_class\">".ucfirst($status)."</span></td></tr>";
    }
    
    $page_content .= '</tbody></table>';
} else {
    $page_content .= '<div style="padding:2rem; text-align:center; color:#8EA0C4;">No compliance records found.</div>';
}

$page_content .= <<<HTML
    </div>
  </div>

</div>

<style>
  .tab-content { display: none; }
  .tab-content.active { display: block; }
  .tab-btn.active { color: #3B82F6 !important; border-bottom-color: #3B82F6 !important; }
  .tab-btn { transition: all 0.3s ease; }
  .tab-btn:hover { color: #3B82F6; }
</style>

<script>
  function switchTab(event, tabName) {
    event.preventDefault();
    document.querySelectorAll('.tab-content').forEach(tab => {
      tab.style.display = 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    document.getElementById(tabName).style.display = 'block';
    event.target.classList.add('active');
  }
</script>

HTML;
include 'layout.php';
?>
