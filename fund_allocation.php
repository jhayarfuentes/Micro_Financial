<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/init.php';

// Fund Allocation & Disbursement Tracker
// Roles: Admin, Portfolio Manager, Compliance Officer

if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    $allowedRoles = ['Admin', 'Portfolio Manager', 'Compliance Officer'];
    if (!in_array($role, $allowedRoles)) {
        die('Access Denied: Portfolio Manager, Compliance Officer, or Admin role required');
    }
}

// CT2/CT3 portal connection context
$portalPage = 'client_portal.php';
$ct2count = 0;
$ct3count = 0;
$ctConnectionStatus = 'OK';
$ctConnectionMessage = 'CT2/CT3 lookup successful';

try {
    $db = Database::getInstance();
    $ct2count = $db->count('compliance_audit');
    $ct3count = $db->count('loan_applications');
} catch (Exception $e) {
    $ctConnectionStatus = 'DOWN';
    $ctConnectionMessage = 'CT2/CT3 connect failed: ' . $e->getMessage();
}

// Shared helpers
function enrichClientInfo(array $records, $db) {
    foreach ($records as &$record) {
        if (!empty($record['client_id'])) {
            $client = $db->fetchOne('SELECT first_name, last_name, email FROM clients WHERE client_id = ?', [$record['client_id']]);
            if ($client) {
                $record['first_name'] = $client['first_name'];
                $record['last_name'] = $client['last_name'];
                $record['email'] = $client['email'];
            }
        }
    }
    return $records;
}

function auditAndCompliance($db, $userId, $actionType, $tableName, $recordId, $message, $payload = [], $checkType = 'Fund_Allocation', $status = 'pending') {
    $audit = $db->auditLog($userId, $actionType, $tableName, $recordId, null, array_merge(['details' => $message], $payload));
    if ($audit && is_array($audit) && !empty($audit['audit_id'])) {
        $db->insert('compliance_audit', [
            'audit_id' => $audit['audit_id'],
            'compliance_check_type' => $checkType,
            'compliance_status' => $status,
            'notes' => $message,
            'check_date' => date('Y-m-d H:i:s')
        ]);
    }
    return $audit;
}

// Handle AJAX actions for workflow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;

    try {
        $db = Database::getInstance();

        switch ($action) {
            // ===== ASSETS OFFICER WORKFLOW =====
            case 'review_loan_request':
                // Assets Officer reviews loan application
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $loanAppId = $_REQUEST['loan_application_id'] ?? null;
                $status = $_REQUEST['status'] ?? null; // 'approved' or 'rejected'
                $comments = $_REQUEST['comments'] ?? '';

                if (!$loanAppId || !in_array($status, ['approved', 'rejected'])) {
                    throw new Exception('Invalid loan application ID or status');
                }

                $db->update('loan_applications', [
                    'loan_status' => $status === 'approved' ? 'assets_approved' : 'assets_rejected',
                    'reviewed_by' => $userId,
                    'review_comments' => $comments,
                    'reviewed_at' => date('Y-m-d H:i:s')
                ], 'application_id = ?', [$loanAppId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'loan_applications', $loanAppId,
                    "Assets Officer {$status} loan request", ['status' => $status, 'comments' => $comments]);

                echo json_encode(['status' => 200, 'message' => "Loan request {$status}"]);
                break;

            // ===== FINANCE OFFICER WORKFLOW =====
            case 'conduct_final_review':
                // Portfolio Manager conducts final review
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $loanAppId = $_REQUEST['loan_application_id'] ?? null;
                $decision = $_REQUEST['decision'] ?? null; // 'approved' or 'rejected'
                $comments = $_REQUEST['comments'] ?? '';

                if (!$loanAppId) {
                    throw new Exception('Invalid loan application ID');
                }

                // Get application details
                $app = $db->fetchOne('SELECT * FROM loan_applications WHERE application_id = ?', [$loanAppId]);
                if (!$app) {
                    throw new Exception('Loan application not found');
                }

                $newStatus = $decision === 'approved' ? 'finance_approved' : 'finance_rejected';
                $db->update('loan_applications', [
                    'loan_status' => $newStatus,
                    'final_reviewed_by' => $userId,
                    'final_review_comments' => $comments,
                    'final_reviewed_at' => date('Y-m-d H:i:s')
                ], 'application_id = ?', [$loanAppId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'loan_applications', $loanAppId,
                    "Finance Officer conducted final review: {$decision}", ['decision' => $decision]);

                echo json_encode(['status' => 200, 'message' => "Final review completed: {$decision}"]);
                break;

            case 'generate_disbursement_documents':
                // Finance Officer generates disbursement documents
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $loanAppId = $_REQUEST['loan_application_id'] ?? null;

                if (!$loanAppId) {
                    throw new Exception('Invalid loan application ID');
                }

                // Create disbursement record
                $app = $db->fetchOne('SELECT * FROM loan_applications WHERE application_id = ?', [$loanAppId]);
                $disbursement = $db->insert('disbursement', [
                    'loan_application_id' => $loanAppId,
                    'client_id' => $app['client_id'],
                    'disbursement_amount' => $app['loan_amount_requested'],
                    'disbursement_date' => date('Y-m-d'),
                    'disbursement_status' => 'generated',
                    'generated_by' => $userId
                ]);

                auditAndCompliance($db, $userId, 'CREATE', 'disbursement', $disbursement['disbursement_id'],
                    'Finance Officer generated disbursement documents', ['amount' => $app['loan_amount_requested']], 'Disbursement', 'pending');

                echo json_encode(['status' => 200, 'message' => 'Disbursement documents generated', 'disbursement_id' => $disbursement['disbursement_id']]);
                break;

            case 'release_loan_allocation':
                // Finance Officer releases loan to client
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                $db->update('disbursement', [
                    'disbursement_status' => 'released_to_client',
                    'released_by' => $userId,
                    'released_at' => date('Y-m-d H:i:s')
                ], 'disbursement_id = ?', [$disbursementId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'disbursement', $disbursementId,
                    'Finance Officer released loan allocation to client', [], 'Disbursement', 'pending');

                echo json_encode(['status' => 200, 'message' => 'Loan allocation released to client']);
                break;

            case 'update_fund_balance':
                // Finance Officer updates total balance of funds
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $fundAllocationId = $_REQUEST['fund_allocation_id'] ?? null;
                $amount = $_REQUEST['amount'] ?? 0;

                if (!$fundAllocationId || $amount <= 0) {
                    throw new Exception('Invalid fund allocation ID or amount');
                }

                // Update fund allocation balance (fetch current and update via REST wrapper)
                $fund = $db->fetchOne('SELECT * FROM fund_allocation WHERE fund_allocation_id = ?', [$fundAllocationId]);
                if (!$fund) {
                    throw new Exception('Fund allocation not found');
                }
                $newAmount = floatval($fund['allocated_amount'] ?? 0) + floatval($amount);

                $db->update('fund_allocation', [
                    'allocated_amount' => $newAmount,
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'fund_allocation_id = ?', [$fundAllocationId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'fund_allocation', $fundAllocationId,
                    'Finance Officer updated fund balance', ['amount_added' => $amount], 'Fund_Balance', 'pending');

                echo json_encode(['status' => 200, 'message' => 'Fund balance updated']);
                break;

            case 'update_tracker':
                // Finance Officer updates disbursement tracker
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                $db->update('disbursement_tracker', [
                    'status' => 'tracked',
                    'updated_by' => $userId,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'disbursement_id = ?', [$disbursementId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'disbursement_tracker', $disbursementId,
                    'Finance Officer updated tracker', [], 'Tracker', 'pending');

                echo json_encode(['status' => 200, 'message' => 'Disbursement tracker updated']);
                break;

            // ===== BUDGET OFFICER WORKFLOW =====
            case 'receive_funds_request':
                // Budget Officer receives funds request
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                $db->update('disbursement', [
                    'budget_officer_received' => true,
                    'budget_officer_received_by' => $userId,
                    'budget_officer_received_at' => date('Y-m-d H:i:s')
                ], ['disbursement_id' => $disbursementId]);

                $db->auditLog($userId, 'UPDATE', 'disbursement', $disbursementId,
                    'Budget Officer received funds request', []);

                echo json_encode(['status' => 200, 'message' => 'Funds request received']);
                break;

            case 'review_funds_request':
                // Budget Officer reviews funds request
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;
                $decision = $_REQUEST['decision'] ?? null; // 'approved' or 'rejected'
                $comments = $_REQUEST['comments'] ?? '';

                if (!$disbursementId || !in_array($decision, ['approved', 'rejected'])) {
                    throw new Exception('Invalid disbursement ID or decision');
                }

                $newStatus = $decision === 'approved' ? 'budget_approved' : 'budget_rejected';
                $db->update('disbursement', [
                    'disbursement_status' => $newStatus,
                    'budget_officer_reviewed_by' => $userId,
                    'budget_officer_review_comments' => $comments,
                    'budget_officer_reviewed_at' => date('Y-m-d H:i:s')
                ], ['disbursement_id' => $disbursementId]);

                $db->auditLog($userId, 'UPDATE', 'disbursement', $disbursementId,
                    "Budget Officer review: {$decision}", ['decision' => $decision, 'comments' => $comments]);

                // Notify Finance Officer
                $notificationMsg = "Budget Officer decision on funds request (Disbursement ID: {$disbursementId}): {$decision}";
                $db->auditLog($userId, 'NOTIFICATION', 'disbursement', $disbursementId,
                    'Notify Finance Officer', ['message' => $notificationMsg]);

                echo json_encode(['status' => 200, 'message' => "Funds request reviewed: {$decision}"]);
                break;

            case 'notify_finance_officer':
                // Budget Officer notifies Finance Officer
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;
                $message = $_REQUEST['message'] ?? '';

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                $db->auditLog($userId, 'NOTIFICATION', 'disbursement', $disbursementId,
                    "Budget Officer notification to Finance Officer: {$message}", ['message' => $message]);

                echo json_encode(['status' => 200, 'message' => 'Finance Officer notified']);
                break;

            // ===== CASHIER WORKFLOW =====
            case 'receive_funds_request_cashier':
                // Cashier receives funds request
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                $db->update('disbursement', [
                    'cashier_received' => true,
                    'cashier_received_by' => $userId,
                    'cashier_received_at' => date('Y-m-d H:i:s')
                ], ['disbursement_id' => $disbursementId]);

                $db->auditLog($userId, 'UPDATE', 'disbursement', $disbursementId,
                    'Cashier received funds request', []);

                echo json_encode(['status' => 200, 'message' => 'Funds request received by Cashier']);
                break;

            case 'review_funds_request_cashier':
                // Cashier reviews funds request
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;
                $comments = $_REQUEST['comments'] ?? '';

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                $db->update('disbursement', [
                    'cashier_reviewed_by' => $userId,
                    'cashier_review_comments' => $comments,
                    'cashier_reviewed_at' => date('Y-m-d H:i:s')
                ], 'disbursement_id = ?', [$disbursementId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'disbursement', $disbursementId,
                    'Cashier reviewed funds request', ['comments' => $comments], 'Cashier_Review', 'pending');

                echo json_encode(['status' => 200, 'message' => 'Funds request reviewed by Cashier']);
                break;

            case 'reconcile_funds':
                // Cashier reconciles funds (matched/mismatched)
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;
                $reconcileStatus = $_REQUEST['reconcile_status'] ?? null; // 'matched' or 'mismatched'

                if (!$disbursementId || !in_array($reconcileStatus, ['matched', 'mismatched'])) {
                    throw new Exception('Invalid disbursement ID or reconcile status');
                }

                $newStatus = $reconcileStatus === 'matched' ? 'reconciled_matched' : 'reconciled_mismatched';
                $db->update('disbursement', [
                    'disbursement_status' => $newStatus,
                    'cashier_reconciled_by' => $userId,
                    'cashier_reconciled_at' => date('Y-m-d H:i:s')
                ], 'disbursement_id = ?', [$disbursementId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'disbursement', $disbursementId,
                    "Cashier reconciliation: {$reconcileStatus}", ['status' => $reconcileStatus], 'Reconciliation', 'pending');

                echo json_encode(['status' => 200, 'message' => "Reconciliation completed: {$reconcileStatus}"]);
                break;

            case 'report_to_finance':
                // Cashier reports mismatch to Finance Officer
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;
                $discrepancyDetails = $_REQUEST['discrepancy_details'] ?? '';

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                auditAndCompliance($db, $userId, 'NOTIFICATION', 'disbursement', $disbursementId,
                    'Cashier reported mismatch to Finance Officer', ['discrepancy_details' => $discrepancyDetails], 'Mismatch_Report', 'pending');

                echo json_encode(['status' => 200, 'message' => 'Mismatch reported to Finance Officer']);
                break;

            case 'settlement':
                // Cashier performs settlement
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                $db->update('disbursement', [
                    'disbursement_status' => 'settled',
                    'cashier_settled_by' => $userId,
                    'cashier_settled_at' => date('Y-m-d H:i:s')
                ], 'disbursement_id = ?', [$disbursementId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'disbursement', $disbursementId,
                    'Cashier completed settlement', [], 'Settlement', 'pending');

                echo json_encode(['status' => 200, 'message' => 'Settlement completed']);
                break;

            case 'post_to_general_ledger':
                // Cashier posts to general ledger
                if ($role !== 'Portfolio Manager' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }
                $disbursementId = $_REQUEST['disbursement_id'] ?? null;

                if (!$disbursementId) {
                    throw new Exception('Invalid disbursement ID');
                }

                $db->update('disbursement', [
                    'posted_to_ledger' => true,
                    'posted_by' => $userId,
                    'posted_at' => date('Y-m-d H:i:s')
                ], 'disbursement_id = ?', [$disbursementId]);

                auditAndCompliance($db, $userId, 'UPDATE', 'disbursement', $disbursementId,
                    'Cashier posted to general ledger', [], 'Ledger_Posting', 'pending');

                echo json_encode(['status' => 200, 'message' => 'Posted to general ledger']);
                break;

            // ===== AUDITOR WORKFLOW =====
            case 'generate_audit_logs':
                // Auditor generates audit logs
                if ($role !== 'Compliance Officer' && $role !== 'Admin') {
                    throw new Exception('Insufficient permissions');
                }

                // Query audit trail for fund allocation and disbursement activities
                $auditLogs = $db->fetchAll(
                    'SELECT * FROM audit_trail WHERE table_name IN (?, ?, ?, ?) ORDER BY action_timestamp DESC LIMIT 500',
                    ['loan_applications', 'disbursement', 'fund_allocation', 'disbursement_tracker']
                );

                echo json_encode(['status' => 200, 'message' => 'Audit logs generated', 'audit_logs' => $auditLogs]);
                break;

            default:
                throw new Exception('Unknown action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 400, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<?php
// Fetch data based on role
$db = Database::getInstance();
$role = $_SESSION['role'] ?? 'Guest';
$userId = $_SESSION['user_id'] ?? null;

// Common: Get pending loan applications for Portfolio Manager view
$pendingLoans = [];
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $pendingLoans = $db->fetchAll(
        'SELECT * FROM loan_applications WHERE loan_status IN (?, ?) ORDER BY application_date DESC',
        ['pending', 'assets_approved']
    );
    $pendingLoans = enrichClientInfo($pendingLoans, $db);
}

// Get disbursements for Finance Officer view
$pendingDisbursements = [];
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $pendingDisbursements = $db->fetchAll(
        'SELECT * FROM disbursement WHERE disbursement_status NOT IN (?, ?, ?) ORDER BY disbursement_date DESC',
        ['settled', 'finance_rejected', 'cancelled']
    );
    $pendingDisbursements = enrichClientInfo($pendingDisbursements, $db);
}

// Get disbursements for Budget Officer view
$budgetPendingDisbursements = [];
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $budgetPendingDisbursements = $db->fetchAll(
        'SELECT * FROM disbursement WHERE disbursement_status IN (?, ?) ORDER BY disbursement_date DESC',
        ['released_to_client', 'finance_approved']
    );
    $budgetPendingDisbursements = enrichClientInfo($budgetPendingDisbursements, $db);
}

// Get disbursements for Cashier view
$cashierDisbursements = [];
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $cashierDisbursements = $db->fetchAll(
        'SELECT * FROM disbursement WHERE disbursement_status IN (?, ?, ?) ORDER BY disbursement_date DESC',
        ['budget_approved', 'reconciled_matched', 'reconciled_mismatched']
    );
    $cashierDisbursements = enrichClientInfo($cashierDisbursements, $db);
}

// Get audit logs for Auditor view
$auditLogs = [];
if ($role === 'Compliance Officer' || $role === 'Admin') {
    $auditLogs = $db->fetchAll(
        'SELECT * FROM audit_trail WHERE table_name IN (?, ?, ?, ?) ORDER BY action_timestamp DESC LIMIT 100',
        ['loan_applications', 'disbursement', 'fund_allocation', 'disbursement_tracker']
    );
}

$page_content = <<<HTML

<!-- Page Content -->
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-transfer-alt'></i></div>
    <div class="page-header-text">
      <h2>Disbursement &amp; Fund Allocation Tracker</h2>
      <p>BPMN Workflow: Process loan requests, approvals, and fund disbursements</p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-outline btn-sm" onclick="location.reload()"><i class='bx bx-refresh'></i> Refresh</button>
    </div>
  </div>

<!-- Stats Grid -->
HTML;
$page_content .= '<div class="stat-grid">';

// Stats: Portfolio Manager View
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $pendingCount = count($pendingLoans);
    $page_content .= <<<HTML
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-task'></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Loan Requests</div>
        <div class="stat-value">$pendingCount</div>
        <div class="stat-sub">Awaiting review</div>
      </div>
    </div>
HTML;
}

// Stats: Portfolio Manager View - Disbursements
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $pendingFin = count($pendingDisbursements);
    $page_content .= <<<HTML
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-transfer-alt'></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Disbursements</div>
        <div class="stat-value">$pendingFin</div>
        <div class="stat-sub">Awaiting final processing</div>
      </div>
    </div>
HTML;
}

// Stats: Budget Officer View
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $budgetPending = count($budgetPendingDisbursements);
    $page_content .= <<<HTML
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-coin-stack'></i></div>
      <div class="stat-info">
        <div class="stat-label">Funds Review Required</div>
        <div class="stat-value">$budgetPending</div>
        <div class="stat-sub">Awaiting budget approval</div>
      </div>
    </div>
HTML;
}

// Stats: Cashier View
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $cashierPending = count($cashierDisbursements);
    $page_content .= <<<HTML
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-building-house'></i></div>
      <div class="stat-info">
        <div class="stat-label">Funds to Process</div>
        <div class="stat-value">$cashierPending</div>
        <div class="stat-sub">Awaiting reconciliation & settlement</div>
      </div>
    </div>
HTML;
}

// Stats: Compliance Officer View
if ($role === 'Compliance Officer' || $role === 'Admin') {
    $auditCount = count($auditLogs);
    $page_content .= <<<HTML
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-clipboard'></i></div>
      <div class="stat-info">
        <div class="stat-label">Audit Trail Entries</div>
        <div class="stat-value">$auditCount</div>
        <div class="stat-sub">Recent transactions</div>
      </div>
    </div>
HTML;
}

$page_content .= '</div>';
$page_content .= <<<'HTML'

  <!-- ===== PORTFOLIO MANAGER VIEW ===== -->
HTML;
  if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $page_content .= <<<'HTML'
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class='bx bx-task'></i> Loan Application Review (Assets Officer)</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Amount Requested</th>
            <th>Purpose</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
HTML;
    if (empty($pendingLoans)) {
      $page_content .= '<tr><td colspan="6"><div class="empty-state"><i class="bx bx-task"></i><p>No pending loan applications.</p></div></td></tr>';
    } else {
      foreach ($pendingLoans as $loan) {
        $clientName = htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']);
        $amount = number_format($loan['loan_amount_requested'], 2);
        $purpose = htmlspecialchars($loan['loan_purpose'] ?? 'N/A');
        $status = $loan['loan_status'];
        $statusColor = $status === 'pending' ? 'badge-blue' : 'badge-green';
        $appId = $loan['application_id'];
        $submitted = htmlspecialchars($loan['application_date'] ?? $loan['reviewed_at'] ?? $loan['created_at'] ?? 'N/A');
        $page_content .= <<<HTML
          <tr>
            <td>$clientName</td>
            <td>₱$amount</td>
            <td>$purpose</td>
            <td><span class="badge $statusColor">$status</span></td>
            <td>$submitted</td>
            <td>
              <div class="action-btns">
                <button class="action-btn" title="Review" onclick="openReviewLoanModal($appId, '$clientName', $amount)"><i class='bx bx-edit'></i></button>
              </div>
            </td>
          </tr>
HTML;
      }
    }
    $page_content .= <<<HTML
        </tbody>
      </table>
    </div>
  </div>

  <!-- Loan Application Review Modal -->
  <div id="reviewLoanModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Review Loan Application</h3>
        <button class="modal-close" onclick="closeReviewLoanModal()">✕</button>
      </div>
      <form onsubmit="submitLoanReview(event)">
        <input type="hidden" id="reviewLoanAppId" value="">
        <div class="form-group">
          <label>Client Name:</label>
          <input type="text" id="reviewClientName" readonly>
        </div>
        <div class="form-group">
          <label>Amount (₱):</label>
          <input type="text" id="reviewAmount" readonly>
        </div>
        <div class="form-group">
          <label>Decision:</label>
          <select id="reviewDecision" required>
            <option value="">-- Select --</option>
            <option value="approved">✓ Approved</option>
            <option value="rejected">✗ Rejected</option>
          </select>
        </div>
        <div class="form-group">
          <label>Comments:</label>
          <textarea id="reviewComments" placeholder="Optional review comments"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeReviewLoanModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Review</button>
        </div>
      </form>
    </div>
  </div>
HTML;
}

// ===== FINANCE OFFICER VIEW =====
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $page_content .= <<<HTML
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class='bx bx-transfer-alt'></i> Disbursement Management (Finance Officer)</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
HTML;
    if (empty($pendingDisbursements)) {
      $page_content .= '<tr><td colspan="5"><div class="empty-state"><i class="bx bx-transfer-alt"></i><p>No pending disbursements.</p></div></td></tr>';
    } else {
      foreach ($pendingDisbursements as $d) {
        $clientName = htmlspecialchars($d['first_name'] . ' ' . $d['last_name']);
        $amount = number_format($d['disbursement_amount'], 2);
        $status = $d['disbursement_status'];
        $statusClass = strpos($status, 'approved') !== false ? 'badge-green' : 'badge-blue';
        $disbId = $d['disbursement_id'];
        $appId = $d['loan_application_id'];
        $submitted = htmlspecialchars($d['disbursement_date'] ?? $d['created_at'] ?? 'N/A');
        $page_content .= <<<HTML
          <tr>
            <td>$clientName</td>
            <td>₱$amount</td>
            <td><span class="badge $statusClass">$status</span></td>
            <td>$submitted</td>
            <td>
              <div class="action-btns">
                <button class="action-btn" title="Final Review" onclick="openFinanceFinalReviewModal($appId, '$clientName')"><i class='bx bx-check'></i></button>
                <button class="action-btn" title="Release Funds" onclick="releaseLoanAllocation($disbId)"><i class='bx bx-send'></i></button>
              </div>
            </td>
          </tr>
HTML;
      }
    }
    $page_content .= <<<HTML
        </tbody>
      </table>
    </div>
  </div>

  <!-- Final Review Modal -->
  <div id="financeReviewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Conduct Final Review (Finance Officer)</h3>
        <button class="modal-close" onclick="closeFinanceFinalReviewModal()">✕</button>
      </div>
      <form onsubmit="submitFinanceFinalReview(event)">
        <input type="hidden" id="finalReviewAppId" value="">
        <div class="form-group">
          <label>Client:</label>
          <input type="text" id="finalReviewClient" readonly>
        </div>
        <div class="form-group">
          <label>Final Decision:</label>
          <select id="finalReviewDecision" required>
            <option value="">-- Select --</option>
            <option value="approved">✓ Approved for Disbursement</option>
            <option value="rejected">✗ Rejected</option>
          </select>
        </div>
        <div class="form-group">
          <label>Comments:</label>
          <textarea id="finalReviewComments" placeholder="Final review comments"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeFinanceFinalReviewModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Final Review</button>
        </div>
      </form>
    </div>
  </div>
HTML;
}

// ===== BUDGET OFFICER VIEW =====
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $page_content .= <<<HTML
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class='bx bx-coin-stack'></i> Budget Review & Approval (Budget Officer)</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
HTML;
    if (empty($budgetPendingDisbursements)) {
      $page_content .= '<tr><td colspan="5"><div class="empty-state"><i class="bx bx-coin-stack"></i><p>No funds requests to review.</p></div></td></tr>';
    } else {
      foreach ($budgetPendingDisbursements as $d) {
        $clientName = htmlspecialchars($d['first_name'] . ' ' . $d['last_name']);
        $amount = number_format($d['disbursement_amount'], 2);
        $disbId = $d['disbursement_id'];
        $submitted = htmlspecialchars($d['disbursement_date'] ?? $d['created_at'] ?? 'N/A');
        $page_content .= <<<HTML
          <tr>
            <td>$clientName</td>
            <td>₱$amount</td>
            <td><span class="badge badge-orange">Finance Approved</span></td>
            <td>$submitted</td>
            <td>
              <div class="action-btns">
                <button class="action-btn" title="Review Funds Request" onclick="openBudgetReviewModal($disbId, '$clientName', {$amount})"><i class='bx bx-edit'></i></button>
              </div>
            </td>
          </tr>
HTML;
      }
    }
    $page_content .= <<<HTML
        </tbody>
      </table>
    </div>
  </div>

  <!-- Fund Request Review Modal -->
  <div id="budgetReviewModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Review Funds Request (Budget Officer)</h3>
        <button class="modal-close" onclick="closeBudgetReviewModal()">✕</button>
      </div>
      <form onsubmit="submitBudgetReview(event)">
        <input type="hidden" id="budgetDisbId" value="">
        <div class="form-group">
          <label>Client:</label>
          <input type="text" id="budgetClientName" readonly>
        </div>
        <div class="form-group">
          <label>Amount (₱):</label>
          <input type="text" id="budgetAmount" readonly>
        </div>
        <div class="form-group">
          <label>Budget Decision:</label>
          <select id="budgetDecision" required>
            <option value="">-- Select --</option>
            <option value="approved">✓ Budget Approved</option>
            <option value="rejected">✗ Budget Rejected</option>
          </select>
        </div>
        <div class="form-group">
          <label>Comments:</label>
          <textarea id="budgetComments" placeholder="Budget review comments"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeBudgetReviewModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Decision</button>
        </div>
      </form>
    </div>
  </div>
HTML;
}

// ===== CASHIER VIEW =====
if ($role === 'Portfolio Manager' || $role === 'Admin') {
    $page_content .= <<<HTML
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class='bx bx-building-house'></i> Cash & Reconciliation (Cashier)</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
HTML;
    if (empty($cashierDisbursements)) {
      $page_content .= '<tr><td colspan="5"><div class="empty-state"><i class="bx bx-building-house"></i><p>No disbursements to process.</p></div></td></tr>';
    } else {
      foreach ($cashierDisbursements as $d) {
        $clientName = htmlspecialchars($d['first_name'] . ' ' . $d['last_name']);
        $amount = number_format($d['disbursement_amount'], 2);
        $disbId = $d['disbursement_id'];
        $submitted = htmlspecialchars($d['disbursement_date'] ?? $d['created_at'] ?? 'N/A');
        $page_content .= <<<HTML
          <tr>
            <td>$clientName</td>
            <td>₱$amount</td>
            <td><span class="badge badge-teal">Budget Approved</span></td>
            <td>$submitted</td>
            <td>
              <div class="action-btns">
                <button class="action-btn" title="Reconcile" onclick="openCashierReconcileModal($disbId, '$clientName')"><i class='bx bx-check'></i></button>
                <button class="action-btn" title="Post to Ledger" onclick="postToLedger($disbId)"><i class='bx bx-book'></i></button>
              </div>
            </td>
          </tr>
HTML;
      }
    }
    $page_content .= <<<HTML
        </tbody>
      </table>
    </div>
  </div>

  <!-- Reconciliation Modal -->
  <div id="cashierReconcileModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Reconcile Funds (Cashier)</h3>
        <button class="modal-close" onclick="closeCashierReconcileModal()">✕</button>
      </div>
      <form onsubmit="submitCashierReconcile(event)">
        <input type="hidden" id="cashierDisbId" value="">
        <div class="form-group">
          <label>Client:</label>
          <input type="text" id="cashierClientName" readonly>
        </div>
        <div class="form-group">
          <label>Reconciliation Status:</label>
          <select id="cashierReconcileStatus" required onchange="toggleMismatchDetails()">
            <option value="">-- Select --</option>
            <option value="matched">✓ Matched</option>
            <option value="mismatched">⚠ Mismatched</option>
          </select>
        </div>
        <div class="form-group" id="mismatchDetailsGroup" style="display:none;">
          <label>Mismatch Details:</label>
          <textarea id="mismatchDetails" placeholder="Describe the discrepancy"></textarea>
        </div>
        <div class="form-group">
          <label>Comments:</label>
          <textarea id="cashierReconcileComments" placeholder="Reconciliation notes"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeCashierReconcileModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Reconciliation</button>
        </div>
      </form>
    </div>
  </div>
HTML;
}

// ===== AUDITOR VIEW =====
if ($role === 'Compliance Officer' || $role === 'Admin') {
    $page_content .= <<<HTML
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class='bx bx-clipboard'></i> Audit Trail (Auditor)</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>User ID</th>
            <th>Action</th>
            <th>Table</th>
            <th>Record ID</th>
            <th>Timestamp</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
HTML;
    if (empty($auditLogs)) {
      $page_content .= '<tr><td colspan="6"><div class="empty-state"><i class="bx bx-clipboard"></i><p>No audit logs available.</p></div></td></tr>';
    } else {
      foreach ($auditLogs as $log) {
        $userId = $log['user_id'];
        $action = htmlspecialchars($log['action']);
        $table = htmlspecialchars($log['table_name']);
        $recordId = $log['record_id'];
        $timestamp = $log['action_timestamp'] ?? $log['created_at'] ?? 'N/A';
        $details = htmlspecialchars(substr($log['details'] ?? '', 0, 100));
        $page_content .= <<<HTML
          <tr>
            <td>$userId</td>
            <td>$action</td>
            <td>$table</td>
            <td>$recordId</td>
            <td>$timestamp</td>
            <td title="$details">$details...</td>
          </tr>
HTML;
      }
    }
    $page_content .= <<<HTML
        </tbody>
      </table>
    </div>
  </div>
HTML;
}

$page_content .= <<<HTML
              </td>
            </tr>
          </tbody>
        </table>
      </div><!-- /Disbursement Tracker Table -->

    </div><!-- /tab-disbursements -->

  </div><!-- /Tabbed Card -->

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
/* CSS Variables */
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

/* Reset */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text-900); }
a { text-decoration: none; color: inherit; }

/* Page Content */
.page-content {
  padding: 1rem;
  animation: fadeIn .4s ease both;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* Page Header */
.page-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 2rem;
}

.page-header-icon {
  width: 52px; height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, var(--blue-600), var(--blue-500));
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 18px rgba(59,130,246,0.35);
}

.page-header-icon i { font-size: 26px; color: #fff; }

.page-header-text h2 {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--text-900);
  line-height: 1.2;
}

.page-header-text p {
  font-size: .85rem;
  color: var(--text-600);
  margin-top: 4px;
  line-height: 1.5;
}

.page-header-actions {
  margin-left: auto;
  display: flex;
  gap: 10px;
  align-items: center;
  flex-shrink: 0;
}

/* Stat Grid */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

@media (max-width: 1200px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px)  { .stat-grid { grid-template-columns: 1fr; } }

/* Stat Card */
.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1.25rem 1.4rem;
  box-shadow: var(--card-shadow);
  display: flex;
  align-items: center;
  gap: 14px;
  transition: transform .2s, box-shadow .2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(59,130,246,0.13);
}

/* Stat Icon */
.stat-icon {
  width: 46px; height: 46px;
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.stat-icon.blue    { background: var(--icon-blue); }
.stat-icon.blue i  { color: var(--blue-500); }
.stat-icon.green   { background: var(--icon-green); }
.stat-icon.green i { color: var(--green); }
.stat-icon.orange   { background: var(--icon-orange); }
.stat-icon.orange i { color: var(--orange); }
.stat-icon.teal    { background: var(--icon-teal); }
.stat-icon.teal i  { color: var(--teal); }
.stat-icon i { font-size: 22px; }

/* Stat Info */
.stat-info { flex: 1; min-width: 0; }

.stat-label {
  font-size: .75rem;
  font-weight: 500;
  color: var(--text-400);
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 2px;
}

.stat-value {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--text-900);
  line-height: 1;
}

.stat-sub {
  font-size: .75rem;
  color: var(--text-400);
  margin-top: 3px;
}

/* Card */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--card-shadow);
  margin-bottom: 1.5rem;
  overflow: hidden;
}

/* Card Header */
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.1rem 1.4rem;
  border-bottom: 1px solid var(--border);
}

.card-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: .95rem;
  font-weight: 600;
  color: var(--text-900);
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-title i { font-size: 18px; color: var(--blue-500); }

.card-actions { display: flex; gap: 8px; align-items: center; }

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px;
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all .2s;
}

.btn-primary { background: var(--blue-600); color: #fff; }
.btn-primary:hover { background: var(--blue-700); box-shadow: 0 3px 12px rgba(37,99,235,.35); }

.btn-outline { background: transparent; color: var(--blue-600); border: 1px solid rgba(59,130,246,.35); }
.btn-outline:hover { background: rgba(59,130,246,.06); }

.btn-sm { padding: 5px 11px; font-size: .78rem; }
.btn i { font-size: 15px; }

/* Tab Bar */
.tab-bar {
  display: flex;
  gap: 4px;
  padding: .75rem 1.4rem .5rem;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}

.tab-btn {
  padding: 6px 16px;
  border-radius: 7px;
  font-size: .82rem;
  font-weight: 500;
  color: var(--text-600);
  cursor: pointer;
  border: none;
  background: transparent;
  transition: all .2s;
}

.tab-btn.active { background: rgba(59,130,246,.1); color: var(--blue-600); font-weight: 600; }
.tab-btn:hover:not(.active) { background: rgba(59,130,246,.05); }

/* Tab Panes */
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* Progress Bar */
.progress-wrap {
  width: 100%;
  min-width: 100px;
  max-width: 160px;
}

.progress-bar-track {
  width: 100%;
  height: 6px;
  background: rgba(59,130,246,.1);
  border-radius: 99px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 99px;
  background: linear-gradient(90deg, var(--blue-600), var(--blue-500));
  width: 0%;
  transition: width .4s ease;
}

.progress-label {
  font-size: .72rem;
  color: var(--text-400);
  margin-top: 3px;
  text-align: right;
}

/* Table */
.table-wrap { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; }

thead tr { background: rgba(240,244,255,.8); }

th {
  padding: .7rem 1.1rem;
  text-align: left;
  font-size: .7rem;
  font-weight: 700;
  color: var(--text-400);
  text-transform: uppercase;
  letter-spacing: .08em;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}

td {
  padding: .78rem 1.1rem;
  font-size: .83rem;
  color: var(--text-600);
  border-bottom: 1px solid rgba(59,130,246,.06);
  vertical-align: middle;
}

tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: rgba(240,244,255,.5); }

/* Empty State */
.empty-state { padding: 3.5rem 1rem; text-align: center; }
.empty-state i { font-size: 38px; color: var(--text-400); margin-bottom: 10px; display: block; }
.empty-state p { font-size: .85rem; color: var(--text-400); }

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .03em;
}

.badge-blue   { background: rgba(59,130,246,.1);  color: var(--blue-600); }
.badge-green  { background: rgba(34,197,94,.1);   color: #16a34a; }
.badge-orange { background: rgba(249,115,22,.1);  color: #c2410c; }
.badge-red    { background: rgba(239,68,68,.1);   color: #dc2626; }
.badge-gray   { background: rgba(100,116,139,.1); color: #475569; }
.badge-teal   { background: rgba(20,184,166,.1);  color: #0f766e; }

/* Action Buttons */
.action-btns { display: flex; gap: 6px; }

.action-btn {
  width: 30px; height: 30px;
  border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  border: 1px solid var(--border);
  background: var(--bg);
  color: var(--text-400);
  transition: all .15s;
}

.action-btn:hover { border-color: var(--blue-500); color: var(--blue-500); background: rgba(59,130,246,.06); }
.action-btn.danger:hover { border-color: var(--red); color: var(--red); background: rgba(239,68,68,.06); }
.action-btn i { font-size: 14px; }
</style>

<!-- Tab Switcher Script -->
<script>
function switchTab(btn, id) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(id).classList.add('active');
}

/**
 * ASSETS OFFICER WORKFLOWS
 */
function openReviewLoanModal(appId, clientName, amount) {
  document.getElementById('reviewLoanAppId').value = appId;
  document.getElementById('reviewClientName').value = clientName;
  document.getElementById('reviewAmount').value = amount;
  document.getElementById('reviewLoanModal').classList.add('active');
}

function closeReviewLoanModal() {
  document.getElementById('reviewLoanModal').classList.remove('active');
  document.getElementById('reviewDecision').value = '';
  document.getElementById('reviewComments').value = '';
}

function submitLoanReview(e) {
  e.preventDefault();
  const appId = document.getElementById('reviewLoanAppId').value;
  const status = document.getElementById('reviewDecision').value;
  const comments = document.getElementById('reviewComments').value;

  fetch('fund_allocation.php?action=review_loan_request', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `loan_application_id=${appId}&status=${status}&comments=${encodeURIComponent(comments)}`
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message);
    if (data.status === 200) {
      closeReviewLoanModal();
      location.reload();
    }
  })
  .catch(err => alert('Error: ' + err.message));
}

/**
 * FINANCE OFFICER WORKFLOWS
 */
function openFinanceFinalReviewModal(appId, clientName) {
  document.getElementById('finalReviewAppId').value = appId;
  document.getElementById('finalReviewClient').value = clientName;
  document.getElementById('financeReviewModal').classList.add('active');
}

function closeFinanceFinalReviewModal() {
  document.getElementById('financeReviewModal').classList.remove('active');
  document.getElementById('finalReviewDecision').value = '';
  document.getElementById('finalReviewComments').value = '';
}

function submitFinanceFinalReview(e) {
  e.preventDefault();
  const appId = document.getElementById('finalReviewAppId').value;
  const decision = document.getElementById('finalReviewDecision').value;
  const comments = document.getElementById('finalReviewComments').value;

  fetch('fund_allocation.php?action=conduct_final_review', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `loan_application_id=${appId}&decision=${decision}&comments=${encodeURIComponent(comments)}`
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message);
    if (data.status === 200) {
      closeFinanceFinalReviewModal();
      location.reload();
    }
  })
  .catch(err => alert('Error: ' + err.message));
}

function releaseLoanAllocation(disbId) {
  if (confirm('Release loan allocation to client?')) {
    fetch('fund_allocation.php?action=release_loan_allocation', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `disbursement_id=${disbId}`
    })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.status === 200) location.reload();
    })
    .catch(err => alert('Error: ' + err.message));
  }
}

/**
 * BUDGET OFFICER WORKFLOWS
 */
function openBudgetReviewModal(disbId, clientName, amount) {
  document.getElementById('budgetDisbId').value = disbId;
  document.getElementById('budgetClientName').value = clientName;
  document.getElementById('budgetAmount').value = amount;
  document.getElementById('budgetReviewModal').classList.add('active');
}

function closeBudgetReviewModal() {
  document.getElementById('budgetReviewModal').classList.remove('active');
  document.getElementById('budgetDecision').value = '';
  document.getElementById('budgetComments').value = '';
}

function submitBudgetReview(e) {
  e.preventDefault();
  const disbId = document.getElementById('budgetDisbId').value;
  const decision = document.getElementById('budgetDecision').value;
  const comments = document.getElementById('budgetComments').value;

  fetch('fund_allocation.php?action=review_funds_request', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `disbursement_id=${disbId}&decision=${decision}&comments=${encodeURIComponent(comments)}`
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message);
    if (data.status === 200) {
      closeBudgetReviewModal();
      location.reload();
    }
  })
  .catch(err => alert('Error: ' + err.message));
}

/**
 * CASHIER WORKFLOWS
 */
function openCashierReconcileModal(disbId, clientName) {
  document.getElementById('cashierDisbId').value = disbId;
  document.getElementById('cashierClientName').value = clientName;
  document.getElementById('cashierReconcileModal').classList.add('active');
}

function closeCashierReconcileModal() {
  document.getElementById('cashierReconcileModal').classList.remove('active');
  document.getElementById('cashierReconcileStatus').value = '';
  document.getElementById('mismatchDetails').value = '';
  document.getElementById('cashierReconcileComments').value = '';
}

function toggleMismatchDetails() {
  const status = document.getElementById('cashierReconcileStatus').value;
  const detailsGroup = document.getElementById('mismatchDetailsGroup');
  detailsGroup.style.display = status === 'mismatched' ? 'block' : 'none';
}

function submitCashierReconcile(e) {
  e.preventDefault();
  const disbId = document.getElementById('cashierDisbId').value;
  const status = document.getElementById('cashierReconcileStatus').value;
  const mismatchDetails = document.getElementById('mismatchDetails').value;
  const comments = document.getElementById('cashierReconcileComments').value;

  fetch('fund_allocation.php?action=reconcile_funds', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `disbursement_id=${disbId}&reconcile_status=${status}&discrepancy_details=${encodeURIComponent(mismatchDetails)}&comments=${encodeURIComponent(comments)}`
  })
  .then(r => r.json())
  .then(data => {
    alert(data.message);
    if (data.status === 200) {
      if (status === 'mismatched') {
        // Report to Finance
        fetch('fund_allocation.php?action=report_to_finance', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `disbursement_id=${disbId}&discrepancy_details=${encodeURIComponent(mismatchDetails)}`
        });
      }
      closeCashierReconcileModal();
      location.reload();
    }
  })
  .catch(err => alert('Error: ' + err.message));
}

function postToLedger(disbId) {
  if (confirm('Post this disbursement to general ledger?')) {
    fetch('fund_allocation.php?action=post_to_general_ledger', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `disbursement_id=${disbId}`
    })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.status === 200) location.reload();
    })
    .catch(err => alert('Error: ' + err.message));
  }
}

// Modal close on escape and outside click
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
  }
});

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal')) {
    e.target.classList.remove('active');
  }
});
</script>

<!-- Modal Styles -->
<style>
.modal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal.active {
  display: flex;
}

.modal-content {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: 0 10px 40px rgba(0,0,0,0.2);
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border);
}

.modal-header h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-900);
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--text-400);
  cursor: pointer;
}

.modal-close:hover {
  color: var(--text-900);
}

.modal-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  padding: 1.5rem;
  border-top: 1px solid var(--border);
}

form {
  padding: 1.5rem;
}

.form-group {
  margin-bottom: 1.2rem;
}

.form-group label {
  display: block;
  font-size: .85rem;
  font-weight: 600;
  color: var(--text-900);
  margin-bottom: 0.4rem;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.6rem;
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: .85rem;
  font-family: inherit;
  color: var(--text-900);
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--blue-500);
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}
</style>


