<?php
/**
 * Loan Approval Module - Staff Dashboard
 * Review and approve pending loan applications
 */

require_once __DIR__ . '/init.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasAnyRole(['Loan Officer', 'Compliance Officer'])) {
    die('Access Denied: Loan Officer or Compliance Officer role required');
}

$currentUser = getCurrentUser();
$page = $_GET['page'] ?? 1;

try {
    $db = service('database');
    
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Get total pending applications
    $countResult = $db->fetchOne("SELECT COUNT(*) as total FROM loan_applications WHERE application_status = 'pending'");
    $total = $countResult['total'] ?? 0;
    $pages = ceil($total / $limit);

    // Determine the loan term column available in the schema
    $termColumn = 'loan_term';
    $schemaCheck = $db->fetchOne("SELECT column_name FROM information_schema.columns WHERE table_name = 'loan_applications' AND column_name = 'loan_term_months'");
    if (!empty($schemaCheck)) {
        $termColumn = 'loan_term_months';
    } else {
        $schemaCheck = $db->fetchOne("SELECT column_name FROM information_schema.columns WHERE table_name = 'loan_applications' AND column_name = 'loan_term'");
        if (empty($schemaCheck)) {
            // fallback to a safe default if neither column exists
            $termColumn = null;
        }
    }

    // Get pending applications
    if ($termColumn !== null) {
        $applications = $db->fetchAll("
            SELECT 
                la.application_id, c.client_id, c.first_name, c.last_name, c.email,
                la.loan_amount, la.interest_rate, la.{$termColumn} AS loan_term, la.application_status, la.application_date
            FROM loan_applications la
            JOIN clients c ON la.client_id = c.client_id
            WHERE la.application_status = 'pending'
            ORDER BY la.application_date ASC
            LIMIT $limit OFFSET $offset
        ");
    } else {
        $applications = $db->fetchAll("
            SELECT 
                la.application_id, c.client_id, c.first_name, c.last_name, c.email,
                la.loan_amount, la.interest_rate, 12 AS loan_term, la.application_status, la.application_date
            FROM loan_applications la
            JOIN clients c ON la.client_id = c.client_id
            WHERE la.application_status = 'pending'
            ORDER BY la.application_date ASC
            LIMIT $limit OFFSET $offset
        ");
    }
} catch (Exception $e) {
    $error_msg = htmlspecialchars($e->getMessage());
    $applications = [];
    $total = 0;
    $pages = 0;
}

$page_content = <<<HTML
<div class="page-content">

  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-check-circle'></i></div>
    <div class="page-header-text">
      <h2>Loan Approval Review</h2>
      <p>Review and approve pending loan applications.</p>
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

$page_content .= <<<HTML
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #f59e0b;">
      <div style="font-size:2rem; font-weight:bold; color:#f59e0b;">$total</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Pending Applications</div>
    </div>
  </div>

  <div style="background:white; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); overflow:hidden;">
HTML;

if (count($applications) > 0) {
    $page_content .= '<table style="width:100%; border-collapse:collapse;"><thead style="background:rgba(240,244,255,0.8);"><tr><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Client Name</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Email</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Loan Amount</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Term</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Applied</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Actions</th></tr></thead><tbody>';
    
    foreach ($applications as $app) {
        $client_name = htmlspecialchars($app['first_name'] . ' ' . $app['last_name']);
        $email = htmlspecialchars($app['email']);
        $amount = '₱' . number_format($app['loan_amount'], 2);
        $term = $app['loan_term'] . ' months';
        $applied = date('M d, Y', strtotime($app['application_date']));
        
        $page_content .= "<tr style=\"border-bottom:1px solid rgba(59,130,246,0.06);\"><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><strong>$client_name</strong></td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$email</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><strong>$amount</strong></td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$term</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$applied</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><button onclick=\"openApprovalModal({$app['application_id']}, '$client_name', {$app['loan_amount']}, {$app['interest_rate']}, {$app['loan_term']})\" style=\"padding:0.4rem 0.8rem; background:#3B82F6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.75rem;\">Review</button></td></tr>";
    }
    
    $page_content .= '</tbody></table>';
    
    // Pagination
    if ($pages > 1) {
        $page_content .= '<div style="display:flex; gap:0.5rem; justify-content:center; padding:1.5rem; border-top:1px solid rgba(59,130,246,0.14);">';
        for ($i = 1; $i <= $pages; $i++) {
            if ($i === intval($page)) {
                $page_content .= "<span style=\"padding:0.4rem 0.8rem; background:#3B82F6; color:white; border-radius:4px; font-size:0.75rem; cursor:default;\">$i</span>";
            } else {
                $page_content .= "<a href=\"loan_approval.php?page=$i\" style=\"padding:0.4rem 0.8rem; background:#f0f4ff; color:#3B82F6; border:none; border-radius:4px; cursor:pointer; font-size:0.75rem; text-decoration:none;\">$i</a>";
            }
        }
        $page_content .= '</div>';
    }
} else {
    $page_content .= '<div style="padding:2rem; text-align:center; color:#8EA0C4;">No pending loan applications.</div>';
}

$page_content .= <<<HTML
  </div>

</div>

<!-- Modal - Loan Approval Decision -->
<div id="approvalModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
  <div style="background:white; padding:2rem; border-radius:8px; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
    <div style="margin-bottom:1.5rem; border-bottom:1px solid rgba(59,130,246,0.14); padding-bottom:1rem;">
      <h3 style="color:#0F246C; font-size:1.2rem; font-weight:600; margin-bottom:0.5rem;">Review Loan Application</h3>
      <p style="color:#8EA0C4; font-size:0.9rem;">Client: <span id="clientNameLabel"></span></p>
    </div>

    <div id="loanInfo" style="background:#f0f4ff; padding:1rem; border-radius:4px; margin-bottom:1.5rem; border-left:4px solid #3B82F6;">
      <p style="margin:0.25rem 0; color:#0F246C;"><strong>Requested Amount:</strong> <span id="amountDisplay"></span></p>
      <p style="margin:0.25rem 0; color:#0F246C;"><strong>Interest Rate:</strong> <span id="rateDisplay"></span>%</p>
      <p style="margin:0.25rem 0; color:#0F246C;"><strong>Term:</strong> <span id="termDisplay"></span> months</p>
    </div>

    <form id="approvalForm" onsubmit="handleApprovalSubmit(event)">
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Decision *</label>
        <select id="approvalDecision" onchange="toggleDecisionFields()" required style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
          <option value="">-- Select Decision --</option>
          <option value="approved">Approve Application</option>
          <option value="rejected">Reject Application</option>
        </select>
      </div>

      <div id="approveFields" style="display:none;">
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Approved Amount</label>
          <input type="number" id="approvedAmount" step="0.01" min="0" style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
        </div>
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Final Interest Rate (%)</label>
          <input type="number" id="approvedRate" step="0.01" min="0" style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
        </div>
      </div>

      <div id="rejectFields" style="display:none;">
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Rejection Reason</label>
          <textarea id="rejectionReason" style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem; resize:vertical; min-height:80px;"></textarea>
        </div>
      </div>

      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Comments</label>
        <textarea id="approvalComments" style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem; resize:vertical; min-height:60px;"></textarea>
      </div>
      
      <input type="hidden" id="appIdInput">

      <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:2rem;">
        <button type="button" onclick="closeApprovalModal()" style="padding:0.6rem 1.2rem; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500;">Cancel</button>
        <button type="submit" style="padding:0.6rem 1.2rem; background:#3B82F6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500;">Submit Decision</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openApprovalModal(appId, clientName, amount, rate, term) {
    document.getElementById('clientNameLabel').textContent = clientName;
    document.getElementById('amountDisplay').textContent = '₱' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
    document.getElementById('rateDisplay').textContent = parseFloat(rate).toFixed(2);
    document.getElementById('termDisplay').textContent = term;
    document.getElementById('appIdInput').value = appId;
    document.getElementById('approvalDecision').value = '';
    toggleDecisionFields();
    document.getElementById('approvalForm').reset();
    document.getElementById('approvalModal').style.display = 'flex';
  }

  function closeApprovalModal() {
    document.getElementById('approvalModal').style.display = 'none';
  }

  function toggleDecisionFields() {
    const decision = document.getElementById('approvalDecision').value;
    document.getElementById('approveFields').style.display = decision === 'approved' ? 'block' : 'none';
    document.getElementById('rejectFields').style.display = decision === 'rejected' ? 'block' : 'none';
  }

  function handleApprovalSubmit(event) {
    event.preventDefault();
    const appId = document.getElementById('appIdInput').value;
    const decision = document.getElementById('approvalDecision').value;

    if (!decision) {
      alert('Please select a decision');
      return;
    }

    const endpoint = decision === 'approved' ? 'loans.php?action=approve' : 'loans.php?action=reject';
    const payload = { application_id: appId };

    if (decision === 'approved') {
      payload.approved_amount = parseFloat(document.getElementById('approvedAmount').value);
      payload.interest_rate = parseFloat(document.getElementById('approvedRate').value);
    } else {
      payload.rejection_reason = document.getElementById('rejectionReason').value;
    }

    fetch(endpoint, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 200) {
        alert('Loan application ' + decision + '!');
        closeApprovalModal();
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => alert('Error: ' + error.message));
  }

  document.getElementById('approvalModal').addEventListener('click', function(e) {
    if (e.target === this) closeApprovalModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeApprovalModal();
  });
</script>

HTML;
include 'layout.php';
?>
