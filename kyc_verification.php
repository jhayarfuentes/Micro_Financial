<?php
/**
 * KYC Verification Module - Staff Dashboard
 * Review and approve client KYC submissions
 */

require_once __DIR__ . '/init.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasAnyRole(['KYC Officer', 'Compliance Officer'])) {
    die('Access Denied: KYC Officer or Compliance Officer role required');
}

$currentUser = getCurrentUser();
$page = $_GET['page'] ?? 1;

try {
    $db = service('database');
    
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $countResult = $db->fetchOne("SELECT COUNT(*) as total FROM kyc_verification WHERE verification_status = 'pending'");
    $total = $countResult['total'] ?? 0;
    $pages = ceil($total / $limit);
    
    // Get pending verifications
    $verifications = $db->fetchAll("
        SELECT 
            kv.kyc_id, c.client_id, c.first_name, c.last_name, c.email,
            kv.id_type, kv.id_number, kv.verification_status, kv.submitted_date
        FROM kyc_verification kv
        JOIN clients c ON kv.client_id = c.client_id
        WHERE kv.verification_status = 'pending'
        ORDER BY kv.submitted_date DESC
        LIMIT $limit OFFSET $offset
    ");
} catch (Exception $e) {
    $error_msg = htmlspecialchars($e->getMessage());
    $verifications = [];
    $total = 0;
    $pages = 0;
}

$page_content = <<<HTML
<div class="page-content">

  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-id-card'></i></div>
    <div class="page-header-text">
      <h2>KYC Verification</h2>
      <p>Review and approve pending client identity verification.</p>
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
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #3B82F6;">
      <div style="font-size:2rem; font-weight:bold; color:#3B82F6;">$total</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Pending Verifications</div>
    </div>
  </div>

  <div style="background:white; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); overflow:hidden;">
HTML;

if (count($verifications) > 0) {
    $page_content .= '<table style="width:100%; border-collapse:collapse;"><thead style="background:rgba(240,244,255,0.8);"><tr><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Client Name</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Email</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">ID Type</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">ID Number</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Submitted</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Actions</th></tr></thead><tbody>';
    
    foreach ($verifications as $kyc) {
        $client_name = htmlspecialchars($kyc['first_name'] . ' ' . $kyc['last_name']);
        $email = htmlspecialchars($kyc['email']);
        $id_type = htmlspecialchars(ucfirst(str_replace('_', ' ', $kyc['id_type'])));
        $idDisplay = htmlspecialchars(substr($kyc['id_number'], -4));
        $submitted = date('M d, Y', strtotime($kyc['submitted_date']));
        
        $page_content .= "<tr style=\"border-bottom:1px solid rgba(59,130,246,0.06);\"><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><strong>$client_name</strong></td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$email</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$id_type</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">****$idDisplay</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">$submitted</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><button onclick=\"openVerifyModal({$kyc['kyc_id']}, '$client_name')\" style=\"padding:0.4rem 0.8rem; background:#22c55e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.75rem;\">Review</button></td></tr>";
    }
    
    $page_content .= '</tbody></table>';
    
    // Pagination
    if ($pages > 1) {
        $page_content .= '<div style="display:flex; gap:0.5rem; justify-content:center; padding:1.5rem; border-top:1px solid rgba(59,130,246,0.14);">';
        for ($i = 1; $i <= $pages; $i++) {
            if ($i === intval($page)) {
                $page_content .= "<span style=\"padding:0.4rem 0.8rem; background:#3B82F6; color:white; border-radius:4px; font-size:0.75rem; cursor:default;\">$i</span>";
            } else {
                $page_content .= "<a href=\"kyc_verification.php?page=$i\" style=\"padding:0.4rem 0.8rem; background:#f0f4ff; color:#3B82F6; border:none; border-radius:4px; cursor:pointer; font-size:0.75rem; text-decoration:none;\">$i</a>";
            }
        }
        $page_content .= '</div>';
    }
} else {
    $page_content .= '<div style="padding:2rem; text-align:center; color:#8EA0C4;">No pending KYC verifications.</div>';
}

$page_content .= <<<HTML
  </div>

</div>

<!-- Modal - Review KYC Submission -->
<div id="verifyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
  <div style="background:white; padding:2rem; border-radius:8px; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
    <div style="margin-bottom:1.5rem; border-bottom:1px solid rgba(59,130,246,0.14); padding-bottom:1rem;">
      <h3 style="color:#0F246C; font-size:1.2rem; font-weight:600; margin-bottom:0.5rem;">Review KYC Submission</h3>
      <p style="color:#8EA0C4; font-size:0.9rem;">Client: <span id="clientNameLabel"></span></p>
    </div>

    <form id="verifyForm" onsubmit="handleVerifySubmit(event)">
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Verification Decision *</label>
        <select id="verificationStatus" required style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
          <option value="">-- Select Decision --</option>
          <option value="verified">Approve KYC</option>
          <option value="rejected">Reject KYC</option>
        </select>
      </div>
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Comments</label>
        <textarea id="verificationComments" style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem; resize:vertical; min-height:80px;"></textarea>
      </div>
      
      <input type="hidden" id="kycIdInput">

      <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:2rem;">
        <button type="button" onclick="closeVerifyModal()" style="padding:0.6rem 1.2rem; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500;">Cancel</button>
        <button type="submit" style="padding:0.6rem 1.2rem; background:#22c55e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500;">Submit Decision</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openVerifyModal(kycId, clientName) {
    document.getElementById('clientNameLabel').textContent = clientName;
    document.getElementById('kycIdInput').value = kycId;
    document.getElementById('verificationStatus').value = '';
    document.getElementById('verificationComments').value = '';
    document.getElementById('verifyModal').style.display = 'flex';
  }

  function closeVerifyModal() {
    document.getElementById('verifyModal').style.display = 'none';
    document.getElementById('verifyForm').reset();
  }

  function handleVerifySubmit(event) {
    event.preventDefault();
    const kycId = document.getElementById('kycIdInput').value;
    const status = document.getElementById('verificationStatus').value;

    if (!status) {
      alert('Please select a verification decision');
      return;
    }

    fetch('clients.php?action=verify_kyc', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        kyc_id: kycId,
        status: status
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 200) {
        alert('KYC verification processed successfully!');
        closeVerifyModal();
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => alert('Error: ' + error.message));
  }

  document.getElementById('verifyModal').addEventListener('click', function(e) {
    if (e.target === this) closeVerifyModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeVerifyModal();
  });
</script>

HTML;
include 'layout.php';
?>
