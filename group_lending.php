<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/init.php';

// Group Lending & Solidarity Mechanisms BPMN Workflow
// Roles: Client, Group Registration KYC Officer, Group Lending Officer, Savings Officer, Loan Officer, Finance Officer

if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    // Allow all authenticated users to see group lending
}

// Handle AJAX workflow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;

    try {
        $db = Database::getInstance();

        switch ($action) {
            case 'register_group':
                $groupName = $_REQUEST['group_name'] ?? null;
                $groupLeaderId = $_REQUEST['group_leader_id'] ?? null;
                $groupType = $_REQUEST['group_type'] ?? 'solidarity';
                $groupSize = intval($_REQUEST['group_size'] ?? 0);
                $meetingFrequency = $_REQUEST['meeting_frequency'] ?? 'monthly';
                $groupNotes = $_REQUEST['group_notes'] ?? '';

                if (!$groupName || !$groupLeaderId || !$groupSize) throw new Exception('Missing required fields');

                $insertPayload = [
                    'group_name' => $groupName,
                    'group_leader_id' => $groupLeaderId,
                    'group_status' => 'registration_pending'
                ];

                $db->insert('lending_groups', $insertPayload);

                $db->auditLog($userId, 'CREATE', 'lending_groups', $groupLeaderId,
                    "Group registered: {$groupName}", [
                        'type' => $groupType,
                        'size' => $groupSize,
                        'meeting_frequency' => $meetingFrequency,
                        'notes' => $groupNotes
                    ]);
                
                echo json_encode(['status' => 200, 'message' => 'Group registered']);
                break;

            case 'collect_documents':
                $groupId = $_REQUEST['group_id'] ?? null;
                if (!$groupId) throw new Exception('Invalid group ID');
                
                $db->update('lending_groups', [
                    'documents_collected' => true,
                    'documents_collected_at' => date('Y-m-d H:i:s')
                ], ['group_id' => $groupId]);
                
                $db->auditLog($userId, 'UPDATE', 'lending_groups', $groupId,
                    'Group documents collected', []);
                
                echo json_encode(['status' => 200, 'message' => 'Documents collected']);
                break;

            case 'perform_kyc':
                $groupId = $_REQUEST['group_id'] ?? null;
                $verified = $_REQUEST['verified'] === 'true';
                if (!$groupId) throw new Exception('Invalid group ID');
                
                $db->update('lending_groups', [
                    'kyc_status' => $verified ? 'verified' : 'rejected',
                    'kyc_verified_at' => date('Y-m-d H:i:s')
                ], ['group_id' => $groupId]);
                
                $db->auditLog($userId, 'UPDATE', 'lending_groups', $groupId,
                    "KYC verification: " . ($verified ? 'verified' : 'rejected'), []);
                
                echo json_encode(['status' => 200, 'message' => 'KYC verification completed']);
                break;

            case 'submit_loan_request':
                $memberId = $_REQUEST['member_id'] ?? null;
                $amount = floatval($_REQUEST['amount'] ?? 0);
                $purpose = $_REQUEST['purpose'] ?? null;
                if (!$memberId || !$amount) throw new Exception('Missing required fields');
                
                $db->insert('loan_applications', [
                    'client_id' => $memberId,
                    'loan_amount_requested' => $amount,
                    'loan_purpose' => $purpose,
                    'loan_status' => 'group_pending'
                ]);
                
                $db->auditLog($userId, 'CREATE', 'loan_applications', $memberId,
                    "Loan request: ₱{$amount}", []);
                
                echo json_encode(['status' => 200, 'message' => 'Loan request submitted']);
                break;

            case 'evaluate_loan':
                $appId = $_REQUEST['app_id'] ?? null;
                $approved = $_REQUEST['approved'] === 'true';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'loan_status' => $approved ? 'group_approved' : 'group_rejected'
                ], ['application_id' => $appId]);
                
                echo json_encode(['status' => 200, 'message' => 'Evaluation completed']);
                break;

            case 'finance_approval':
                $appId = $_REQUEST['app_id'] ?? null;
                $approved = $_REQUEST['approved'] === 'true';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $newStatus = $approved ? 'loan_approved' : 'loan_rejected';
                $db->update('loan_applications', [
                    'loan_status' => $newStatus
                ], ['application_id' => $appId]);
                
                echo json_encode(['status' => 200, 'message' => 'Finance decision: ' . $newStatus]);
                break;

            case 'release_loan':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $app = $db->fetchOne('SELECT * FROM loan_applications WHERE application_id = ?', [$appId]);
                
                $db->insert('loan', [
                    'application_id' => $appId,
                    'client_id' => $app['client_id'],
                    'loan_amount' => $app['loan_amount_requested'],
                    'interest_rate' => 12.5,
                    'loan_term_months' => 12,
                    'loan_status' => 'active',
                    'current_balance' => $app['loan_amount_requested'],
                    'disbursement_status' => 'completed'
                ]);
                
                $db->update('loan_applications', [
                    'loan_status' => 'disbursed'
                ], ['application_id' => $appId]);
                
                echo json_encode(['status' => 200, 'message' => 'Loan released']);
                break;

            default:
                throw new Exception('Unknown action');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch dashboard data
$groups = [];
$groupLoans = [];
$dbError = null;
$db = Database::getInstance();

try {
    $groups = $db->fetchAll(
        'SELECT lg.*, c.first_name, c.last_name FROM lending_groups lg
         LEFT JOIN clients c ON lg.group_leader_id = c.client_id
         ORDER BY lg.created_at DESC LIMIT 50'
    );
} catch (Exception $e) {
    $groups = [];
}

try {
    $groupLoans = $db->fetchAll(
        'SELECT la.*, c.first_name, c.last_name FROM loan_applications la
         LEFT JOIN clients c ON la.client_id = c.client_id
         WHERE la.loan_status LIKE "%group%" OR la.loan_status = "loan_approved"
         ORDER BY la.application_date DESC LIMIT 50'
    );
} catch (Exception $e) {
    $groupLoans = [];
}

// Prepare server-side rows in case client JS fails
$groupsTableRows = '';
if (!empty($groups)) {
    foreach ($groups as $g) {
        $groupId = intval($g['group_id'] ?? 0);
        $groupName = htmlspecialchars($g['group_name'] ?? 'Unnamed Group');
        $leaderName = htmlspecialchars(trim(($g['first_name'] ?? 'Unknown') . ' ' . ($g['last_name'] ?? 'Leader')));
        $status = htmlspecialchars($g['group_status'] ?? 'pending');
        $kyc = htmlspecialchars($g['kyc_status'] ?? 'pending');
        $statusClass = $status === 'active' ? 'badge-green' : 'badge-orange';
        $kycClass = $kyc === 'verified' ? 'badge-green' : 'badge-orange';

        $groupsTableRows .= "<tr>"
            . "<td><strong>{$groupName}</strong></td>"
            . "<td>{$leaderName}</td>"
            . "<td><span class='badge {$statusClass}'>{$status}</span></td>"
            . "<td><span class='badge {$kycClass}'>{$kyc}</span></td>"
            . "<td><button class='action-btn' title='View' onclick='viewGroup({$groupId})'><i class='bx bx-show'></i></button></td>"
            . "</tr>";
    }
} else {
    $groupsTableRows = '<tr><td colspan="5" style="text-align:center;color:#8EA0C4;">No groups registered.</td></tr>';
}

$loansTableRows = '';
$approvedLoansCount = 0;
$pendingCount = 0;
if (!empty($groupLoans)) {
    foreach ($groupLoans as $loan) {
        $appId = intval($loan['application_id'] ?? 0);
        $memberName = htmlspecialchars(trim(($loan['first_name'] ?? 'Unknown') . ' ' . ($loan['last_name'] ?? 'Member')));
        $amount = number_format(floatval($loan['loan_amount_requested'] ?? 0), 2);
        $purpose = htmlspecialchars($loan['loan_purpose'] ?? 'Not specified');
        $status = htmlspecialchars($loan['loan_status'] ?? 'pending');
        $statusClass = strpos($status, 'approved') !== false ? 'badge-green' : 'badge-orange';

        if (strpos($status, 'approved') !== false) $approvedLoansCount++;
        if (strpos($status, 'pending') !== false) $pendingCount++;

        $loansTableRows .= "<tr>"
            . "<td><strong>{$memberName}</strong></td>"
            . "<td>₱{$amount}</td>"
            . "<td>{$purpose}</td>"
            . "<td><span class='badge {$statusClass}'>{$status}</span></td>"
            . "<td><button class='action-btn' title='Review' onclick='reviewLoan({$appId})'><i class='bx bx-check'></i></button></td>"
            . "</tr>";
    }
} else {
    $loansTableRows = '<tr><td colspan="5" style="text-align:center;color:#8EA0C4;">No loan requests.</td></tr>';
}

$activeGroupsCount = count($groups);
$totalMembersCount = 0; // advanced member tracking not implemented yet

if (empty($groups) && empty($groupLoans)) {
    $dbError = 'No group data found. Run LOAN_REPAYMENT_MIGRATION.sql to load sample data.';
}
?>

<?php
$page_content = <<<HTML
<style>
:root {
  --blue-500: #3B82F6;
  --blue-600: #2563EB;
  --blue-700: #1E40AF;
  --bg: #F0F4FF;
  --surface: #FFFFFF;
  --border: rgba(59,130,246,0.14);
  --text-900: #0F1E4A;
  --text-600: #4B5E8A;
  --text-400: #8EA0C4;
  --green: #22c55e;
  --orange: #f97316;
  --teal: #14b8a6;
  --red: #ef4444;
  --icon-blue: rgba(59,130,246,0.12);
  --icon-green: rgba(34,197,94,0.12);
  --icon-orange: rgba(249,115,22,0.12);
  --icon-teal: rgba(20,184,166,0.12);
  --radius: 14px;
  --card-shadow: 0 2px 16px rgba(59,130,246,0.08);
}

.page-content { padding: 1rem; }

.page-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 2rem; }

.page-header-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, var(--blue-600), var(--blue-500)); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 18px rgba(59,130,246,0.35); }

.page-header-icon i { font-size: 26px; color: #fff; }

.page-header-text { flex: 1; }

.page-header-text h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.35rem; font-weight: 700; color: var(--text-900); line-height: 1.2; }

.page-header-text p { font-size: .85rem; color: var(--text-600); margin-top: 4px; }

.page-header-actions { margin-left: auto; display: flex; gap: 10px; align-items: center; flex-shrink: 0; }

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }

@media (max-width: 1200px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px)  { .stat-grid { grid-template-columns: 1fr; } }

.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem 1.4rem; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 14px; transition: transform .2s, box-shadow .2s; }

.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(59,130,246,0.13); }

.stat-icon { width: 46px; height: 46px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.stat-icon.blue    { background: var(--icon-blue); }
.stat-icon.blue i  { color: var(--blue-500); }
.stat-icon.green   { background: var(--icon-green); }
.stat-icon.green i { color: #16a34a; }
.stat-icon.orange   { background: var(--icon-orange); }
.stat-icon.orange i { color: #c2410c; }
.stat-icon.teal    { background: var(--icon-teal); }
.stat-icon.teal i  { color: #0f766e; }
.stat-icon i { font-size: 22px; }

.stat-info { flex: 1; min-width: 0; }

.stat-label { font-size: .75rem; font-weight: 500; color: var(--text-400); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; }

.stat-value { font-family: 'Space Grotesk', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--text-900); line-height: 1; }

.stat-sub { font-size: .75rem; color: var(--text-400); margin-top: 3px; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--card-shadow); margin-bottom: 1.5rem; overflow: hidden; }

.card-header { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); }

.card-title { font-family: 'Space Grotesk', sans-serif; font-size: .95rem; font-weight: 600; color: var(--text-900); display: flex; align-items: center; gap: 8px; }

.card-title i { font-size: 18px; color: var(--blue-500); }

.card-header h3 { font-family: 'Space Grotesk', sans-serif; font-size: .95rem; font-weight: 600; color: var(--text-900); display: flex; align-items: center; gap: 8px; margin: 0; }

.card-body { padding: 0; }

table { width: 100%; border-collapse: collapse; }

thead tr { background: rgba(240,244,255,.8); }

th { padding: .7rem 1.1rem; text-align: left; font-size: .7rem; font-weight: 700; color: var(--text-400); text-transform: uppercase; letter-spacing: .08em; border-bottom: 1px solid var(--border); white-space: nowrap; }

td { padding: .78rem 1.1rem; font-size: .83rem; color: var(--text-600); border-bottom: 1px solid rgba(59,130,246,.06); vertical-align: middle; }

tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: rgba(240,244,255,.5); }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; transition: all .2s; }

.btn-primary { background: var(--blue-600); color: #fff; }
.btn-primary:hover { background: var(--blue-700); box-shadow: 0 3px 12px rgba(37,99,235,.35); }

.btn-outline { background: transparent; color: var(--blue-600); border: 1px solid rgba(59,130,246,.35); }
.btn-outline:hover { background: rgba(59,130,246,.06); }

.btn-sm { padding: 5px 11px; font-size: .78rem; }
.btn i { font-size: 15px; }

.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; letter-spacing: .03em; }

.badge-green  { background: rgba(34,197,94,.1);   color: #16a34a; }
.badge-orange { background: rgba(249,115,22,.1);  color: #c2410c; }

.action-btn { width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--border); background: var(--bg); color: var(--text-400); transition: all .15s; }

.action-btn:hover { border-color: var(--blue-500); color: var(--blue-500); background: rgba(59,130,246,.06); }
.action-btn i { font-size: 14px; }

.alert { padding: 15px; border-radius: var(--radius); border-left: 4px solid; }

.alert-warning { background: rgba(251,191,36,.1); border-color: #fbbf24; color: #92400e; }

@media (max-width: 768px) {
  .page-header { flex-direction: column; }
  .page-header-actions { margin-left: 0; width: 100%; }
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  z-index: 2000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,.5);
  animation: fadeIn .2s;
}

.modal.show { display: flex; align-items: center; justify-content: center; }

.modal-content {
  background-color: var(--surface);
  padding: 2rem;
  border-radius: var(--radius);
  width: 90%;
  max-width: 500px;
  box-shadow: 0 8px 32px rgba(0,0,0,.2);
  animation: slideUp .3s;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}

.modal-header h2 {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-900);
  margin: 0;
}

.modal-close {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 24px;
  color: var(--text-400);
  transition: all .2s;
}

.modal-close:hover { color: var(--text-900); }

.modal-body { margin-bottom: 1.5rem; }

.form-group {
  margin-bottom: 1.25rem;
}

.form-group label {
  display: block;
  font-size: .85rem;
  font-weight: 600;
  color: var(--text-900);
  margin-bottom: 0.5rem;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 0.65rem 0.9rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: .9rem;
  color: var(--text-900);
  background: var(--surface);
  transition: all .2s;
  font-family: 'DM Sans', sans-serif;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--blue-600);
  box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}

.modal-footer {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn-cancel { background: transparent; border: 1px solid var(--border); color: var(--text-600); }
.btn-cancel:hover { background: rgba(59,130,246,.06); }

</style>

<!-- Page Content -->
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-group'></i></div>
    <div class="page-header-text">
      <h2>Group Lending &amp; Solidarity Mechanisms</h2>
      <p>BPMN Workflow: Manage group registrations, KYC, deposits, and solidarity-backed loans</p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary btn-sm" onclick="openModal('groupModal')"><i class='bx bx-plus'></i> Register Group</button>
      <button class="btn btn-outline btn-sm" onclick="location.reload()"><i class='bx bx-refresh'></i> Refresh</button>
    </div>
  </div>

  <!-- Database Error Banner -->
HTML;

if ($dbError) {
    $page_content .= <<<'HTML'
  <div class="alert alert-warning">
    <div style="display: flex; align-items: center; gap: 10px;">
      <i class='bx bx-exclamation-circle'></i>
      <div><strong>Setup Required:</strong> No group data found. Load sample data to proceed.</div>
    </div>
  </div>
HTML;
}

$page_content .= <<<'HTML'

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-group'></i></div>
      <div class="stat-info">
        <div class="stat-label">Active Groups</div>
        <div class="stat-value" id="activeGroupsCount">{$activeGroupsCount}</div>
        <div class="stat-sub">Registered groups</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-check-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Approved Loans</div>
        <div class="stat-value" id="approvedLoansCount">{$approvedLoansCount}</div>
        <div class="stat-sub">From group members</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-time'></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Requests</div>
        <div class="stat-value" id="pendingCount">{$pendingCount}</div>
        <div class="stat-sub">Under review</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-wallet'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Members</div>
        <div class="stat-value" id="totalMembers">{$totalMembersCount}</div>
        <div class="stat-sub">Across all groups</div>
      </div>
    </div>
  </div>

  <!-- Lending Groups -->
  <div class="card">
    <div class="card-header">
      <h3><i class='bx bx-group'></i> Registered Lending Groups</h3>
    </div>
    <div class="card-body">
      <table>
        <thead>
          <tr>
            <th>Group Name</th>
            <th>Leader</th>
            <th>Status</th>
            <th>KYC</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="groupsTableBody">
          {$groupsTableRows}
        </tbody>
      </table>
    </div>
  </div>

  <!-- Group Loans -->
  <div class="card">
    <div class="card-header">
      <h3><i class='bx bx-money'></i> Group Member Loan Requests</h3>
    </div>
    <div class="card-body">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>Loan Amount</th>
            <th>Purpose</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="loansTableBody">
          {$loansTableRows}
        </tbody>
      </table>
    </div>
  </div>

</div><!-- page-content -->

<!-- Group Registration Modal -->
<div id="groupModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Register New Group</h2>
      <button class="modal-close" onclick="closeModal('groupModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Group Name</label>
        <input type="text" id="groupName" placeholder="Enter group name" />
      </div>
      <div class="form-group">
        <label>Group Leader ID</label>
        <input type="number" id="groupLeaderId" placeholder="Enter leader client ID" />
      </div>
      <div class="form-group">
        <label>Group Type</label>
        <select id="groupType">
          <option value="solidarity">Solidarity</option>
          <option value="income">Income Generation</option>
          <option value="agriculture">Agriculture</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Estimated Members</label>
        <input type="number" id="groupSize" min="1" placeholder="1" />
      </div>
      <div class="form-group">
        <label>Meeting Frequency</label>
        <select id="meetingFrequency">
          <option value="weekly">Weekly</option>
          <option value="biweekly">Bi-Weekly</option>
          <option value="monthly">Monthly</option>
        </select>
      </div>
      <div class="form-group">
        <label>Group Notes</label>
        <textarea id="groupNotes" rows="3" placeholder="Optional contextual notes"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-cancel btn-sm" onclick="closeModal('groupModal')">Cancel</button>
      <button class="btn btn-primary btn-sm" onclick="submitGroupForm()">Register Group</button>
    </div>
  </div>
</div>

<!-- View Group Modal -->
<div id="viewGroupModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="viewGroupTitle">Group Details</h2>
      <button class="modal-close" onclick="closeModal('viewGroupModal')">&times;</button>
    </div>
    <div class="modal-body" id="viewGroupBody">
      <!-- Populated by JavaScript -->
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary btn-sm" onclick="closeModal('viewGroupModal')">Close</button>
    </div>
  </div>
</div>

<!-- Loan Review Modal -->
<div id="loanModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="loanModalTitle">Review Loan Request</h2>
      <button class="modal-close" onclick="closeModal('loanModal')">&times;</button>
    </div>
    <div class="modal-body" id="loanModalBody">
      <!-- Populated by JavaScript -->
    </div>
    <div class="modal-footer">
      <button class="btn btn-cancel btn-sm" onclick="closeModal('loanModal')">Reject</button>
      <button class="btn btn-primary btn-sm" onclick="approveLoan()">Approve</button>
    </div>
  </div>
</div>

<script>
  const groupsData = [
HTML;

if (!empty($groups)) {
    $groupsJSON = [];
    foreach ($groups as $g) {
        $groupsJSON[] = [
            'id' => intval($g['group_id'] ?? 0),
            'name' => htmlspecialchars($g['group_name'] ?? 'Unknown'),
            'leader' => htmlspecialchars(($g['first_name'] ?? 'Unknown') . ' ' . ($g['last_name'] ?? 'Group')),
            'status' => htmlspecialchars($g['group_status'] ?? 'pending'),
            'kyc' => htmlspecialchars($g['kyc_status'] ?? 'pending')
        ];
    }
    $page_content .= json_encode($groupsJSON);
}

$page_content .= <<<'HTML'
  ];

  const loansData = [
HTML;

if (!empty($groupLoans)) {
    $loansJSON = [];
    foreach ($groupLoans as $loan) {
        $loansJSON[] = [
            'id' => intval($loan['application_id'] ?? 0),
            'member' => htmlspecialchars(($loan['first_name'] ?? 'Unknown') . ' ' . ($loan['last_name'] ?? 'Member')),
            'amount' => number_format(floatval($loan['loan_amount_requested'] ?? 0), 2),
            'purpose' => htmlspecialchars($loan['loan_purpose'] ?? 'Not specified'),
            'status' => htmlspecialchars($loan['loan_status'] ?? 'pending')
        ];
    }
    $page_content .= json_encode($loansJSON);
}

$page_content .= <<<'HTML'
  ];

  function renderGroups() {
    const tbody = document.getElementById('groupsTableBody');
    if (!groupsData || groupsData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #8EA0C4;">No groups registered.</td></tr>';
      return;
    }

    let html = '';
    for (const group of groupsData) {
      const statusClass = group.status === 'active' ? 'badge-green' : 'badge-orange';
      const kycClass = group.kyc === 'verified' ? 'badge-green' : 'badge-orange';
      
      html += `<tr>
        <td><strong>${group.name}</strong></td>
        <td>${group.leader}</td>
        <td><span class="badge ${statusClass}">${group.status}</span></td>
        <td><span class="badge ${kycClass}">${group.kyc}</span></td>
        <td><button class="action-btn" title="View" onclick="viewGroup(${group.id})"><i class='bx bx-show'></i></button></td>
      </tr>`;
    }
    tbody.innerHTML = html;
    document.getElementById('activeGroupsCount').textContent = groupsData.length;
  }

  function renderLoans() {
    const tbody = document.getElementById('loansTableBody');
    if (!loansData || loansData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #8EA0C4;">No loan requests.</td></tr>';
      return;
    }

    let html = '';
    let approvedCount = 0, pendingCount = 0;
    
    for (const loan of loansData) {
      const statusClass = loan.status.includes('approved') ? 'badge-green' : 'badge-orange';
      if (loan.status.includes('approved')) approvedCount++;
      if (loan.status.includes('pending')) pendingCount++;
      
      html += `<tr>
        <td><strong>${loan.member}</strong></td>
        <td>₱${loan.amount}</td>
        <td>${loan.purpose}</td>
        <td><span class="badge ${statusClass}">${loan.status}</span></td>
        <td><button class="action-btn" title="Review" onclick="reviewLoan(${loan.id})"><i class='bx bx-check'></i></button></td>
      </tr>`;
    }
    tbody.innerHTML = html;
    document.getElementById('approvedLoansCount').textContent = approvedCount;
    document.getElementById('pendingCount').textContent = pendingCount;
  }

  function viewGroup(id) { 
    const group = groupsData.find(g => g.id === id);
    if (group) {
      document.getElementById('viewGroupTitle').textContent = group.name;
      document.getElementById('viewGroupBody').innerHTML = `
        <div class="form-group">
          <label>Group Name</label>
          <input type="text" readonly value="${group.name}" />
        </div>
        <div class="form-group">
          <label>Leader</label>
          <input type="text" readonly value="${group.leader}" />
        </div>
        <div class="form-group">
          <label>Status</label>
          <input type="text" readonly value="${group.status}" />
        </div>
        <div class="form-group">
          <label>KYC Status</label>
          <input type="text" readonly value="${group.kyc}" />
        </div>
      `;
      openModal('viewGroupModal');
    }
  }

  function reviewLoan(id) {
    const loan = loansData.find(l => l.id === id);
    if (loan) {
      document.getElementById('loanModalTitle').textContent = 'Review: ' + loan.member;
      document.getElementById('loanModalBody').innerHTML = `
        <div class="form-group">
          <label>Member</label>
          <input type="text" readonly value="${loan.member}" />
        </div>
        <div class="form-group">
          <label>Loan Amount</label>
          <input type="text" readonly value="₱${loan.amount}" />
        </div>
        <div class="form-group">
          <label>Purpose</label>
          <input type="text" readonly value="${loan.purpose}" />
        </div>
        <div class="form-group">
          <label>Current Status</label>
          <input type="text" readonly value="${loan.status}" />
        </div>
      `;
      currentLoanId = id;
      openModal('loanModal');
    }
  }

  function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
  }

  function submitGroupForm() {
    const groupName = document.getElementById('groupName').value.trim();
    const groupLeaderId = document.getElementById('groupLeaderId').value.trim();
    const groupType = document.getElementById('groupType').value;
    const groupSize = document.getElementById('groupSize').value.trim();
    const meetingFrequency = document.getElementById('meetingFrequency').value;
    const groupNotes = document.getElementById('groupNotes').value.trim();

    if (!groupName || !groupLeaderId || !groupSize) {
      alert('Please fill in group name, leader ID and estimated members');
      return;
    }

    const payload = [
      'action=register_group',
      'group_name=' + encodeURIComponent(groupName),
      'group_leader_id=' + encodeURIComponent(groupLeaderId),
      'group_type=' + encodeURIComponent(groupType),
      'group_size=' + encodeURIComponent(groupSize),
      'meeting_frequency=' + encodeURIComponent(meetingFrequency),
      'group_notes=' + encodeURIComponent(groupNotes)
    ].join('&');

    fetch('group_lending.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: payload
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 200) {
        alert('Group registered successfully!');
        closeModal('groupModal');
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    });
  }

  function approveLoan() {
    if (!currentLoanId) return;
    fetch('group_lending.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=evaluate_loan&app_id=' + currentLoanId + '&approved=true'
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 200) {
        alert('Loan approved!');
        closeModal('loanModal');
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    });
  }

  let currentLoanId = null;

  renderGroups();
  renderLoans();
</script>

HTML;
include 'layout.php';
?>
