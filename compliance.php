<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/init.php';
if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    $allowedRoles = ['Admin', 'Portfolio Manager', 'Compliance Officer'];
    if (!in_array($role, $allowedRoles)) {
        die('Access Denied: Portfolio Manager, Compliance Officer, or Admin role required');
    }
}

$ct2Status = getCt2Ct3Status();
$ct2count = $ct2Status['ct2count'];
$ct3count = $ct2Status['ct3count'];
$ctConnectionStatus = $ct2Status['ctConnectionStatus'];
$ctConnectionMessage = $ct2Status['ctConnectionMessage'];

$savingsService = service('savings');
$clientService = service('client');
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    try {
        switch ($action) {
            case 'fetch_compliance_stats':
                // Total audit logs, prefer fast count; fallback to row set if needed
                $totalAuditLogs = $db->count('audit_trail');
                $auditRows = $db->fetchAll('SELECT * FROM audit_trail ORDER BY action_timestamp DESC LIMIT 1000');
                if ($totalAuditLogs === 0 && is_array($auditRows)) {
                    $totalAuditLogs = count($auditRows);
                }

                // Compliance counts by status (case-insensitive)
                $complianceRows = $db->fetchAll('SELECT * FROM compliance_audit ORDER BY check_date DESC LIMIT 1000');
                if (!is_array($complianceRows)) {
                    $complianceRows = [];
                }
                $compliantCount = 0;
                $violationsCount = 0;
                foreach ($complianceRows as $row) {
                    $st = strtolower(trim($row['compliance_status'] ?? ''));
                    if ($st === 'compliant') {
                        $compliantCount++;
                    }
                    if (in_array($st, ['non_compliant', 'non-compliant', 'violation', 'violations', 'non compliant'])) {
                        $violationsCount++;
                    }
                }

                // Today's logs (from loaded audit rows)
                $today = date('Y-m-d');
                $todaysLogs = 0;
                if (is_array($auditRows)) {
                    foreach ($auditRows as $row) {
                        $ts = $row['action_timestamp'] ?? '';
                        if (strpos($ts, $today) === 0) {
                            $todaysLogs++;
                        }
                    }
                }

                echo json_encode([
                    'status' => 200,
                    'data' => [
                        'totalAuditLogs' => intval($totalAuditLogs),
                        'compliantRecords' => intval($compliantCount),
                        'violations' => intval($violationsCount),
                        'logsToday' => intval($todaysLogs)
                    ]
                ]);
                break;
            case 'fetch_audit_trail':
                $auditRows = $db->fetchAll('SELECT * FROM audit_trail ORDER BY action_timestamp DESC LIMIT 200');
                foreach ($auditRows as &$row) {
                    if (!empty($row['user_id'])) {
                        $user = $db->fetchOne('SELECT username FROM users WHERE user_id = ?', [$row['user_id']]);
                        if ($user) {
                            $row['username'] = $user['username'];
                        }
                    }
                }
                unset($row);
                echo json_encode(['status' => 200, 'data' => ['auditTrail' => $auditRows]]);
                break;
            case 'fetch_compliance_records':
                $complianceRows = $db->fetchAll('SELECT * FROM compliance_audit ORDER BY check_date DESC LIMIT 200');
                echo json_encode(['status' => 200, 'data' => ['complianceRecords' => $complianceRows]]);
                break;
            case 'log_action':
                $actionType = trim($_REQUEST['action_type'] ?? 'UNKNOWN');
                $tableName = trim($_REQUEST['table_name'] ?? 'unknown');
                $recordId = isset($_REQUEST['record_id']) ? intval($_REQUEST['record_id']) : null;
                $notes = trim($_REQUEST['notes'] ?? '');
                $oldValues = isset($_REQUEST['old_values']) ? json_decode($_REQUEST['old_values'], true) : null;
                $newValues = isset($_REQUEST['new_values']) ? json_decode($_REQUEST['new_values'], true) : null;

                if ($actionType === '' || $tableName === '') {
                    throw new Exception('action_type and table_name are required for log_action');
                }

                $audit = $db->auditLog(
                    $_SESSION['user_id'] ?? null,
                    $actionType,
                    $tableName,
                    $recordId,
                    $oldValues,
                    $newValues ?? ['notes' => $notes]
                );

                if (!$audit) {
                    throw new Exception('Failed to record audit event');
                }

                echo json_encode(['status' => 200, 'message' => 'Audit action logged', 'audit' => $audit]);
                break;
            case 'add_compliance_record':
                $checkType = trim($_REQUEST['check_type'] ?? 'General');
                $status = trim($_REQUEST['status'] ?? 'pending');
                $notes = trim($_REQUEST['notes'] ?? '');
                $auditId = isset($_REQUEST['audit_id']) ? intval($_REQUEST['audit_id']) : null;

                if ($checkType === '' || $status === '') {
                    throw new Exception('Compliance type and status are required');
                }

                // Ensure we have a referential audit_trail row for this compliance record.
                if (!$auditId) {
                    $audit = $db->auditLog(
                        $_SESSION['user_id'] ?? null,
                        'CREATE',
                        'compliance_audit',
                        null,
                        null,
                        ['check_type' => $checkType, 'status' => $status, 'notes' => $notes]
                    );
                    if (!$audit || !is_array($audit) || empty($audit['audit_id'])) {
                        throw new Exception('Failed to create audit trail record');
                    }
                    $auditId = $audit['audit_id'];
                }

                $newRow = [
                    'audit_id' => $auditId,
                    'compliance_check_type' => $checkType,
                    'compliance_status' => $status,
                    'notes' => $notes,
                    'check_date' => date('Y-m-d H:i:s')
                ];

                $inserted = $db->insert('compliance_audit', $newRow);
                if (!$inserted) {
                    throw new Exception('Failed to add compliance record');
                }

                echo json_encode(['status' => 200, 'message' => 'Compliance record added', 'record' => $inserted]);
                break;
            case 'update_compliance_status':
                $complianceId = intval($_REQUEST['compliance_id'] ?? 0);
                $newStatus = trim($_REQUEST['status'] ?? '');
                $notes = trim($_REQUEST['notes'] ?? '');
                if (!$complianceId || $newStatus === '') {
                    throw new Exception('compliance_id and status are required');
                }

                $validStatuses = ['compliant', 'pending_review', 'violation', 'archived'];
                if (!in_array(strtolower($newStatus), $validStatuses)) {
                    throw new Exception('Invalid status value');
                }

                $updated = $db->update('compliance_audit', [
                    'compliance_status' => $newStatus,
                    'notes' => $notes,
                    'check_date' => date('Y-m-d H:i:s')
                ], 'compliance_id = ?', [$complianceId]);

                $auditEntry = $db->auditLog(
                    $_SESSION['user_id'] ?? null,
                    'UPDATE',
                    'compliance_audit',
                    $complianceId,
                    null,
                    ['compliance_status' => $newStatus, 'notes' => $notes]
                );

                echo json_encode(['status' => 200, 'message' => 'Compliance status updated', 'updated' => $updated, 'audit' => $auditEntry]);
                break;
            case 'run_proactive_compliance_check':
                $highRiskTx = $db->fetchAll("SELECT t.transaction_id,t.savings_id,t.transaction_amount,a.account_number,c.first_name,c.last_name FROM savings_transactions t JOIN savings_accounts a ON t.savings_id=a.savings_id JOIN clients c ON a.client_id=c.client_id WHERE t.transaction_amount > ? ORDER BY t.transaction_date DESC LIMIT 20", [100000]);
                $unverifiedClients = $db->fetchAll("SELECT c.client_id,c.first_name,c.last_name FROM clients c LEFT JOIN kyc_verification k ON c.client_id = k.client_id WHERE k.verification_status != 'verified' OR k.verification_status IS NULL ORDER BY c.client_id LIMIT 20");

                // Write proactive findings into compliance_audit table
                foreach ($highRiskTx as $tx) {
                    $audit = $db->auditLog(
                        $_SESSION['user_id'] ?? null,
                        'ALERT',
                        'savings_transactions',
                        $tx['transaction_id'],
                        null,
                        ['note' => 'High-risk transaction detected']
                    );
                    $auditId = $audit['audit_id'] ?? null;

                    if ($auditId) {
                        $db->insert('compliance_audit', [
                            'audit_id' => $auditId,
                            'compliance_check_type' => 'High_Risk_Transaction',
                            'compliance_status' => 'non_compliant',
                            'notes' => sprintf('High-risk transaction #%s for account %s (₱%s)', $tx['transaction_id'], $tx['account_number'] ?? '', $tx['transaction_amount']),
                            'check_date' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                foreach ($unverifiedClients as $client) {
                    $audit = $db->auditLog(
                        $_SESSION['user_id'] ?? null,
                        'ALERT',
                        'clients',
                        $client['client_id'],
                        null,
                        ['note' => 'Unverified client detected']
                    );
                    $auditId = $audit['audit_id'] ?? null;

                    if ($auditId) {
                        $db->insert('compliance_audit', [
                            'audit_id' => $auditId,
                            'compliance_check_type' => 'Unverified_Client',
                            'compliance_status' => 'non_compliant',
                            'notes' => sprintf('Unverified client %s %s (ID %s)', $client['first_name'] ?? '', $client['last_name'] ?? '', $client['client_id']),
                            'check_date' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                echo json_encode(['status' => 200, 'data' => ['highRiskTx' => $highRiskTx, 'unverifiedClients' => $unverifiedClients]]);
                break;
            default:
                echo json_encode(['status' => 400, 'message' => 'Unknown action']);
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

$page_content = <<<HTML

<!-- Page Content -->
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-shield-alt-2'></i></div>
    <div class="page-header-text">
      <h2>Compliance &amp; Audit Trail</h2>
      <p>Monitor regulatory compliance records and system-wide audit logs.</p>
      <p style="font-size:0.9rem; color:#4B5E8A; margin-top: 0.35rem;">
        CT2 KYC audits: <strong>$ct2count</strong>, CT3 applications: <strong>$ct3count</strong> &nbsp;|&nbsp; DB status: <strong>$ctConnectionStatus</strong>
        <small style="display:block; color:#6B7280; margin-top:0.2rem;">$ctConnectionMessage</small>
      </p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-outline btn-sm" onclick="refreshComplianceDashboard();"><i class='bx bx-refresh'></i> Refresh Stats</button>
      <button class="btn btn-outline btn-sm" onclick="runProactiveComplianceCheck();"><i class='bx bx-bolt'></i> Proactive Check</button>
      <button class="btn btn-primary" onclick="openComplianceModal();"><i class='bx bx-plus'></i> Add Compliance Record</button>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-file-find'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Audit Logs</div>
        <div class="stat-value" id="statTotalAuditLogs">0</div>
        <div class="stat-sub">All recorded actions</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-shield-check'></i></div>
      <div class="stat-info">
        <div class="stat-label">Compliant Records</div>
        <div class="stat-value" id="statCompliantRecords">0</div>
        <div class="stat-sub">Passed compliance check</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-error-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Flagged / Violations</div>
        <div class="stat-value" id="statViolations">0</div>
        <div class="stat-sub">Requires review</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-calendar-event'></i></div>
      <div class="stat-info">
        <div class="stat-label">Logs Today</div>
        <div class="stat-value" id="statLogsToday">0</div>
        <div class="stat-sub">Actions recorded today</div>
      </div>
    </div>
  </div>

  <!-- Add Compliance Record Modal -->
  <div id="addComplianceModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Add Compliance Record</h3>
        <button class="modal-close" onclick="closeComplianceModal();">×</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Compliance Check Type</label>
          <input type="text" id="complianceCheckType" placeholder="e.g. KYC, AML, Loan_Policy" />
        </div>
        <div class="form-group">
          <label>Status</label>
          <select id="complianceStatus">
            <option value="pending">Pending</option>
            <option value="compliant">Compliant</option>
            <option value="non_compliant">Non-Compliant</option>
            <option value="violation">Violation</option>
          </select>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea id="complianceNotes" rows="4" placeholder="Add notes"></textarea>
        </div>
        <div class="form-group">
          <label>Audit ID (optional)</label>
          <input type="number" id="complianceAuditId" placeholder="Optional related audit id" />
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline btn-sm" onclick="closeComplianceModal();">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="submitComplianceRecord();">Save</button>
      </div>
    </div>
  </div>

  <!-- View Compliance Record Modal -->
  <div id="viewComplianceModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
      <div class="modal-header">
        <h3>Compliance Record Details</h3>
        <button class="modal-close" onclick="closeComplianceViewModal();">×</button>
      </div>
      <div class="modal-body" id="viewComplianceBody">
        <p>Loading...</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline btn-sm" onclick="closeComplianceViewModal();">Close</button>
      </div>
    </div>
  </div>

  <!-- Tabbed Card -->
  <div class="card">

    <!-- Tab Bar -->
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab(this,'tab-audit')">Audit Trail</button>
      <button class="tab-btn" onclick="switchTab(this,'tab-compliance')">Compliance Records</button>
    </div>

    <!-- Tab: Audit Trail -->
    <div id="tab-audit" class="tab-pane active">

      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-history'></i> System Audit Trail</div>
        <div class="card-actions">
          <button class="btn btn-outline btn-sm"><i class='bx bx-filter-alt'></i> Filter</button>
          <button class="btn btn-outline btn-sm"><i class='bx bx-export'></i> Export</button>
        </div>
      </div>

      <!-- Severity Legend -->
      <div class="legend-bar">
        <span class="legend-label">Severity:</span>
        <span class="badge badge-gray">Info</span>
        <span class="badge badge-blue">Low</span>
        <span class="badge badge-orange">Medium</span>
        <span class="badge badge-red">High</span>
        <span class="legend-divider"></span>
        <span class="legend-label">Action = Operation performed on the system</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Log ID</th>
              <th>Timestamp</th>
              <th>User</th>
              <th>Module</th>
              <th>Action</th>
              <th>Record Affected</th>
              <th>IP Address</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody id="auditTrailBody">
            <tr><td colspan="8" style="text-align:center;color:#8EA0C4;padding:18px;">Loading audit trail...</td></tr>
          </tbody>
        </table>
      </div>

    </div><!-- /tab-audit -->

    <!-- Tab: Compliance Records -->
    <div id="tab-compliance" class="tab-pane">

      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-shield-alt-2'></i> Compliance Records</div>
        <div class="card-actions">
          <button class="btn btn-outline btn-sm"><i class='bx bx-filter-alt'></i> Filter</button>
          <button class="btn btn-primary btn-sm"><i class='bx bx-plus'></i> Add Record</button>
        </div>
      </div>

      <!-- Status Legend -->
      <div class="legend-bar">
        <span class="legend-label">Status:</span>
        <span class="badge badge-green">Compliant</span>
        <span class="badge badge-orange">Pending Review</span>
        <span class="badge badge-red">Violation</span>
        <span class="badge badge-gray">Archived</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Record ID</th>
              <th>Category</th>
              <th>Description</th>
              <th>Reviewed By</th>
              <th>Review Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="complianceRecordsBody">
            <tr><td colspan="7" style="text-align:center;color:#8EA0C4;padding:18px;">Loading compliance records...</td></tr>
          </tbody>
        </table>
      </div>

    </div><!-- /tab-compliance -->

  </div><!-- /card -->

  <div class="card" id="proactiveResultsCard" style="display:none;">
    <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
      <div class="card-title"><i class='bx bx-bolt'></i> Proactive Compliance Findings</div>
    </div>
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Type</th><th>Details</th></tr>
          </thead>
          <tbody id="proactiveResultsBody"><tr><td colspan="2">No proactive check run yet.</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /page-content -->

HTML;
include 'layout.php';
?>

<!-- Boxicons CDN -->
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Page Styles -->
<style>
:root {
  --navy:        #0f246c;
  --blue-500:    #3B82F6;
  --blue-600:    #2563EB;
  --blue-700:    #1E40AF;
  --blue-light:  #93C5FD;
  --bg:          #F0F4FF;
  --surface:     #FFFFFF;
  --border:      rgba(59,130,246,0.14);
  --text-900:    #0F1E4A;
  --text-600:    #4B5E8A;
  --text-400:    #8EA0C4;
  --sidebar-w:   300px;
  --topbar-h:    64px;
  --green:       #22c55e;
  --orange:      #f97316;
  --teal:        #14b8a6;
  --red:         #ef4444;
  --icon-blue:   rgba(59,130,246,0.12);
  --icon-green:  rgba(34,197,94,0.12);
  --icon-orange: rgba(249,115,22,0.12);
  --icon-teal:   rgba(20,184,166,0.12);
  --radius:      14px;
  --card-shadow: 0 2px 16px rgba(59,130,246,0.08);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text-900);}
a{text-decoration:none;color:inherit;}

.page-content{padding:1rem;animation:fadeIn .4s ease both;}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

.page-header{display:flex;align-items:flex-start;gap:16px;margin-bottom:2rem;}
.page-header-icon{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,var(--blue-600),var(--blue-500));display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 18px rgba(59,130,246,0.35);}
.page-header-icon i{font-size:26px;color:#fff;}
.page-header-text h2{font-family:'Space Grotesk',sans-serif;font-size:1.35rem;font-weight:700;color:var(--text-900);line-height:1.2;}
.page-header-text p{font-size:.85rem;color:var(--text-600);margin-top:4px;line-height:1.5;}
.page-header-actions{margin-left:auto;display:flex;gap:10px;align-items:center;flex-shrink:0;}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
@media(max-width:1200px){.stat-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:600px){.stat-grid{grid-template-columns:1fr;}}

.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem 1.4rem;box-shadow:var(--card-shadow);display:flex;align-items:center;gap:14px;transition:transform .2s,box-shadow .2s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(59,130,246,0.13);}
.stat-icon{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon.blue{background:var(--icon-blue);}.stat-icon.blue i{color:var(--blue-500);}
.stat-icon.green{background:var(--icon-green);}.stat-icon.green i{color:var(--green);}
.stat-icon.orange{background:var(--icon-orange);}.stat-icon.orange i{color:var(--orange);}
.stat-icon.teal{background:var(--icon-teal);}.stat-icon.teal i{color:var(--teal);}
.stat-icon i{font-size:22px;}
.stat-info{flex:1;min-width:0;}
.stat-label{font-size:.75rem;font-weight:500;color:var(--text-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;}
.stat-value{font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:700;color:var(--text-900);line-height:1;}
.stat-sub{font-size:.75rem;color:var(--text-400);margin-top:3px;}

.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--card-shadow);margin-bottom:1.5rem;overflow:hidden;}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);}
.card-title{font-family:'Space Grotesk',sans-serif;font-size:.95rem;font-weight:600;color:var(--text-900);display:flex;align-items:center;gap:8px;}
.card-title i{font-size:18px;color:var(--blue-500);}
.card-actions{display:flex;gap:8px;align-items:center;}

.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;}
.btn-primary{background:var(--blue-600);color:#fff;}
.btn-primary:hover{background:var(--blue-700);box-shadow:0 3px 12px rgba(37,99,235,.35);}
.btn-outline{background:transparent;color:var(--blue-600);border:1px solid rgba(59,130,246,.35);}
.btn-outline:hover{background:rgba(59,130,246,.06);}
.btn-sm{padding:5px 11px;font-size:.78rem;}
.btn i{font-size:15px;}

.tab-bar{display:flex;gap:4px;padding:.75rem 1.4rem .5rem;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.tab-btn{padding:6px 16px;border-radius:7px;font-size:.82rem;font-weight:500;color:var(--text-600);cursor:pointer;border:none;background:transparent;transition:all .2s;}
.tab-btn.active{background:rgba(59,130,246,.1);color:var(--blue-600);font-weight:600;}
.tab-btn:hover:not(.active){background:rgba(59,130,246,.05);}
.tab-pane{display:none;}.tab-pane.active{display:block;}

/* Legend Bar */
.legend-bar{display:flex;align-items:center;gap:8px;padding:.75rem 1.4rem;background:rgba(240,244,255,.5);border-bottom:1px solid var(--border);flex-wrap:wrap;}
.legend-label{font-size:.75rem;font-weight:500;color:var(--text-400);}
.legend-divider{width:1px;height:14px;background:var(--border);margin:0 4px;}

.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead tr{background:rgba(240,244,255,.8);}
th{padding:.7rem 1.1rem;text-align:left;font-size:.7rem;font-weight:700;color:var(--text-400);text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:.78rem 1.1rem;font-size:.83rem;color:var(--text-600);border-bottom:1px solid rgba(59,130,246,.06);vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:rgba(240,244,255,.5);}

.empty-state{padding:3.5rem 1rem;text-align:center;}
.empty-state i{font-size:38px;color:var(--text-400);margin-bottom:10px;display:block;}
.empty-state p{font-size:.85rem;color:var(--text-400);}

.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;letter-spacing:.03em;}
.badge-blue{background:rgba(59,130,246,.1);color:var(--blue-600);}
.badge-green{background:rgba(34,197,94,.1);color:#16a34a;}
.badge-orange{background:rgba(249,115,22,.1);color:#c2410c;}
.badge-red{background:rgba(239,68,68,.1);color:#dc2626;}
.badge-gray{background:rgba(100,116,139,.1);color:#475569;}
.badge-teal{background:rgba(20,184,166,.1);color:#0f766e;}

.action-btns{display:flex;gap:6px;}
.action-btn{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;cursor:pointer;border:1px solid var(--border);background:var(--bg);color:var(--text-400);transition:all .15s;}
.action-btn:hover{border-color:var(--blue-500);color:var(--blue-500);background:rgba(59,130,246,.06);}
.action-btn.danger:hover{border-color:var(--red);color:var(--red);background:rgba(239,68,68,.06);}
.action-btn i{font-size:14px;}

/* Compliance modal styling */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:9999;padding:16px;}
.modal.show{display:flex;}
.modal-content{background:var(--surface);border-radius:14px;width:100%;max-width:560px;box-shadow:0 20px 50px rgba(15,23,42,.35);border:1px solid rgba(59,130,246,.2);overflow:hidden;}
.modal-header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:rgba(59,130,246,.08);border-bottom:1px solid rgba(59,130,246,.16);}
.modal-header h3{margin:0;font-size:1.15rem;font-weight:700;color:var(--navy);}
.modal-close{font-size:1.1rem;width:30px;height:30px;line-height:30px;border:none;border-radius:8px;background:rgba(15,23,42,0.06);color:var(--text-600);cursor:pointer;transition:all .2s;}
.modal-close:hover{background:rgba(15,23,42,0.12);}
.modal-body{padding:16px 18px;display:grid;gap:12px;}
.modal-body .form-group{margin-bottom:0;}
.modal-body .form-group label{font-size:.85rem;font-weight:600;color:var(--text-600);margin-bottom:4px;display:block;}
.modal-body .form-group input,.modal-body .form-group select,.modal-body .form-group textarea{width:100%;padding:.6rem .72rem;border:1px solid rgba(59,130,246,.24);border-radius:9px;background:#fff;color:var(--text-900);transition:border .2s;}
.modal-body .form-group input:focus,.modal-body .form-group select:focus,.modal-body .form-group textarea:focus{outline:none;border-color:var(--blue-500);box-shadow:0 0 0 2px rgba(59,130,246,.12);}
.modal-body .form-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
@media(max-width:680px){.modal-body .form-row{grid-template-columns:1fr;}}
.modal-footer{display:flex;justify-content:flex-end;gap:.7rem;padding:12px 18px;background:rgba(240,244,255,.6);border-top:1px solid rgba(59,130,246,.12);}
.modal-footer .btn{min-width:100px;padding:.55rem .95rem;}
</style>

<script>
function switchTab(btn,id){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(id).classList.add('active');
}

function showAlert(msg){ alert(msg); }

function loadComplianceStats(){
  fetch('compliance.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=fetch_compliance_stats' })
    .then(r=>r.json())
    .then(data=>{
      if(data.status!==200){ return; }
      const d=data.data;
      document.getElementById('statTotalAuditLogs').textContent = d.totalAuditLogs;
      document.getElementById('statCompliantRecords').textContent = d.compliantRecords;
      document.getElementById('statViolations').textContent = d.violations;
      document.getElementById('statLogsToday').textContent = d.logsToday;
    })
    .catch(()=>{});
}

function loadAuditTrail(){
  fetch('compliance.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=fetch_audit_trail' })
    .then(r=>r.json())
    .then(data=>{
      if(data.status!==200){ return; }
      const body = document.getElementById('auditTrailBody');
      const rows = data.data.auditTrail;
      if(!rows || !rows.length){
        body.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#8EA0C4;padding:18px;">No audit logs found.</td></tr>';
        return;
      }
      body.innerHTML = rows.map(item => {
        return `<tr>
          <td>${item.audit_id}</td>
          <td>${new Date(item.action_timestamp).toLocaleString()}</td>
          <td>${item.username || item.user_id || 'System'}</td>
          <td>${item.table_name}</td>
          <td>${item.action_type}</td>
          <td>${item.record_id || ''}</td>
          <td>${item.ip_address || ''}</td>
          <td>${item.new_values || item.old_values || ''}</td>
        </tr>`;
      }).join('');
    })
    .catch(()=>{});
}

var complianceRecordsData = [];

function openComplianceViewModal() {
  document.getElementById('viewComplianceModal').classList.add('show');
}

function closeComplianceViewModal() {
  document.getElementById('viewComplianceModal').classList.remove('show');
}

function viewComplianceRecord(id) {
  const record = complianceRecordsData.find(r => String(r.compliance_id) === String(id));
  if (!record) {
    showAlert('Compliance record not found.');
    return;
  }

  const statusOptions = ['compliant', 'pending_review', 'violation', 'archived'];
  const statusSelect = statusOptions.map(status => {
    const selected = (String(status).toLowerCase() === String(record.compliance_status || '').toLowerCase()) ? 'selected' : '';
    const label = status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
    return `<option value="${status}" ${selected}>${label}</option>`;
  }).join('');

  const detailHtml = `
    <div style="margin-bottom: 0.7rem;"><strong>ID:</strong> ${record.compliance_id}</div>
    <div style="margin-bottom: 0.7rem;"><strong>Check Type:</strong> ${record.compliance_check_type || 'N/A'}</div>
    <div style="margin-bottom: 0.7rem;"><strong>Audit ID:</strong> ${record.audit_id || 'N/A'}</div>
    <div style="margin-bottom: 0.7rem;"><strong>Checked At:</strong> ${record.check_date ? new Date(record.check_date).toLocaleString() : 'N/A'}</div>
    <div style="margin-bottom: 0.7rem;"><strong>Notes:</strong> <textarea id="viewComplianceNotes" style="width:100%;min-height:80px;">${record.notes || ''}</textarea></div>
    <div style="margin-bottom: 0.7rem;"><strong>Status:</strong> <select id="viewComplianceStatus" style="width:100%;padding:.5rem;margin-top:.25rem;">${statusSelect}</select></div>
    <div style="margin-top:1rem;display:flex;justify-content:flex-end;gap:.5rem;">
      <button class="btn btn-outline btn-sm" onclick="closeComplianceViewModal();">Close</button>
      <button class="btn btn-primary btn-sm" onclick="updateComplianceStatus(${record.compliance_id});">Save Status</button>
    </div>
  `;

  document.getElementById('viewComplianceBody').innerHTML = detailHtml;
  openComplianceViewModal();
}

function updateComplianceStatus(complianceId) {
  const selectedStatus = document.getElementById('viewComplianceStatus').value;
  const notes = document.getElementById('viewComplianceNotes').value.trim();

  fetch('compliance.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=update_compliance_status&compliance_id=' + encodeURIComponent(complianceId) + '&status=' + encodeURIComponent(selectedStatus) + '&notes=' + encodeURIComponent(notes)
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === 200) {
      showAlert('Status updated successfully');
      closeComplianceViewModal();
      refreshComplianceDashboard();
      proactiveComplianceSignal();
    } else {
      showAlert(data.message || 'Failed to update status');
    }
  })
  .catch(err => {
    console.error(err);
    showAlert('Error updating status');
  });
}

function loadComplianceRecords(){
  fetch('compliance.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=fetch_compliance_records' })
    .then(r=>r.json())
    .then(data=>{
      if(data.status!==200){ return; }
      const body = document.getElementById('complianceRecordsBody');
      const rows = data.data.complianceRecords;
      if(!rows || !rows.length){
        body.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#8EA0C4;padding:18px;">No compliance records found.</td></tr>';
        complianceRecordsData = [];
        return;
      }
      complianceRecordsData = rows;
      body.innerHTML = rows.map(item => {
        return `<tr>
          <td>${item.compliance_id}</td>
          <td>${item.compliance_check_type || ''}</td>
          <td>${item.notes || ''}</td>
          <td>${item.audit_id || ''}</td>
          <td>${item.check_date ? new Date(item.check_date).toLocaleString() : ''}</td>
          <td>${item.compliance_status || ''}</td>
          <td><button class="btn btn-sm btn-outline" onclick="viewComplianceRecord(${item.compliance_id})">View</button></td>
        </tr>`;
      }).join('');
    })
    .catch(()=>{});
}

function openComplianceModal(){
  document.getElementById('addComplianceModal').classList.add('show');
}

function closeComplianceModal(){
  document.getElementById('addComplianceModal').classList.remove('show');
}

function submitComplianceRecord(){
  const type = document.getElementById('complianceCheckType').value.trim();
  const status = document.getElementById('complianceStatus').value;
  const notes = document.getElementById('complianceNotes').value.trim();
  const auditId = document.getElementById('complianceAuditId').value;

  if(!type || !status){ showAlert('Type and status are required'); return; }

  const body = `action=add_compliance_record&check_type=${encodeURIComponent(type)}&status=${encodeURIComponent(status)}&notes=${encodeURIComponent(notes)}&audit_id=${encodeURIComponent(auditId)}`;
  fetch('compliance.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r=>r.json())
    .then(data=>{
      if(data.status===200){
        showAlert(data.message || 'Record added');
        closeComplianceModal();
        refreshComplianceDashboard();
        proactiveComplianceSignal();
      } else {
        showAlert(data.message || 'Failed to add record');
      }
    })
    .catch(err=>{ console.error(err); showAlert('Error adding compliance record'); });
}

function refreshComplianceDashboard(){
  loadComplianceStats();
  loadAuditTrail();
  loadComplianceRecords();
}

function runProactiveComplianceCheck(){
  fetch('compliance.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=run_proactive_compliance_check' })
    .then(r=>r.json())
    .then(data=>{
      if(data.status!==200){ showAlert('Unable to run proactive check'); return; }
      const body = document.getElementById('proactiveResultsBody');
      const card = document.getElementById('proactiveResultsCard');
      card.style.display='block';
      const {highRiskTx, unverifiedClients} = data.data;
      let html = '';
      html += '<tr><td>High-risk transactions</td><td>' + (highRiskTx.length ? highRiskTx.map(t=>`#${t.transaction_id} (${t.account_number}) ₱${Number(t.transaction_amount).toLocaleString()}`).join('<br>') : 'None found') + '</td></tr>';
      html += '<tr><td>Unverified clients</td><td>' + (unverifiedClients.length ? unverifiedClients.map(c=>`#${c.client_id} ${c.first_name} ${c.last_name}`).join('<br>') : 'None') + '</td></tr>';
      body.innerHTML = html;
      showAlert('Proactive compliance scan complete.');
      refreshComplianceDashboard();
    })
    .catch(err=>{ console.error(err); showAlert('Error running proactive compliance check'); });
}

document.addEventListener('DOMContentLoaded', function(){
  refreshComplianceDashboard();
  setInterval(refreshComplianceDashboard, 15000); // Periodic update every 15 sec
});

window.addEventListener('storage', function(event){
  if (event.key === 'compliance_event') {
    refreshComplianceDashboard();
  }
});
</script>