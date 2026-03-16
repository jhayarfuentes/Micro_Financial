<?php
/**
 * Loan Collection Module - Staff Dashboard
 * Manage loan collections and handle installment payments
 */

require_once __DIR__ . '/init.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasAnyRole(['Loan Collector', 'Compliance Officer'])) {
    die('Access Denied: Loan Collector or Compliance Officer role required');
}

$currentUser = getCurrentUser();

try {
    $db = service('database');
    
    // Get statistics
    $stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT l.loan_id) as total_active,
            COUNT(DISTINCT CASE WHEN li.due_date < CURDATE() AND li.payment_status = 'pending' THEN li.installment_id END) as overdue_count,
            COALESCE(SUM(CASE WHEN li.due_date < CURDATE() AND li.payment_status = 'pending' THEN li.installment_amount ELSE 0 END), 0) as overdue_amount,
            COALESCE(SUM(CASE WHEN li.due_date >= CURDATE() AND li.payment_status = 'pending' THEN li.installment_amount ELSE 0 END), 0) as upcoming_amount
        FROM loans l
        LEFT JOIN loan_installments li ON l.loan_id = li.loan_id
        WHERE l.loan_status = 'active'
    ");
    
    // Get overdue installments
    $overdue = $db->fetchAll("
        SELECT 
            li.installment_id, li.loan_id, li.installment_number, li.due_date, li.installment_amount,
            c.first_name, c.last_name, c.email, l.loan_amount
        FROM loan_installments li
        JOIN loans l ON li.loan_id = l.loan_id
        JOIN clients c ON l.client_id = c.client_id
        WHERE li.due_date < CURDATE() AND li.payment_status = 'pending' AND l.loan_status = 'active'
        ORDER BY li.due_date ASC
        LIMIT 20
    ");
} catch (Exception $e) {
    $error_msg = htmlspecialchars($e->getMessage());
    $stats = null;
    $overdue = [];
}

// Format stats values for display
$overdue_amount_display = $stats ? number_format($stats['overdue_amount'], 0) : '0';
$upcoming_amount_display = $stats ? number_format($stats['upcoming_amount'], 0) : '0';

$page_content = <<<HTML
<div class="page-content">

  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-receipt'></i></div>
    <div class="page-header-text">
      <h2>Loan Collection</h2>
      <p>Track loan collections and process installment payments.</p>
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
      <div style="font-size:2rem; font-weight:bold; color:#3B82F6;">{$stats['total_active']}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Active Loans</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #ef4444;">
      <div style="font-size:2rem; font-weight:bold; color:#ef4444;">{$stats['overdue_count']}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Overdue Installments</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #ef4444;">
      <div style="font-size:2rem; font-weight:bold; color:#ef4444;">₱ {$overdue_amount_display}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Overdue Amount</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #22c55e;">
      <div style="font-size:2rem; font-weight:bold; color:#22c55e;">₱ {$upcoming_amount_display}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Upcoming Payments</div>
    </div>
  </div>
HTML;
}

$page_content .= <<<HTML

  <!-- Tabs -->
  <div style="display:flex; gap:0; margin-bottom:2rem; border-bottom:2px solid rgba(59,130,246,0.14);">
    <button class="tab-btn active" onclick="switchTab(event, 'overdue')" style="padding:1rem 1.5rem; background:none; border:none; cursor:pointer; font-size:1rem; border-bottom:3px solid #3B82F6; color:#3B82F6; font-weight:500;">Overdue Installments</button>
    <button class="tab-btn" onclick="switchTab(event, 'payment')" style="padding:1rem 1.5rem; background:none; border:none; cursor:pointer; font-size:1rem; border-bottom:3px solid transparent; color:#8EA0C4; font-weight:500;">Quick Payment</button>
  </div>

  <!-- Tab: Overdue Installments -->
  <div id="overdue" class="tab-content" style="display:block;">
    <div style="background:white; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); overflow:hidden; margin-bottom:2rem;">
HTML;

if (count($overdue) > 0) {
    $page_content .= '<table style="width:100%; border-collapse:collapse;"><thead style="background:rgba(240,244,255,0.8);"><tr><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Client</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Loan ID</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Installment</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Due Date</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Amount</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Actions</th></tr></thead><tbody>';
    
    foreach ($overdue as $inst) {
        $client_name = htmlspecialchars($inst['first_name'] . ' ' . $inst['last_name']);
        $due_date = date('M d, Y', strtotime($inst['due_date']));
        $amount = '₱' . number_format($inst['installment_amount'], 2);
        $days_overdue = floor((strtotime('now') - strtotime($inst['due_date'])) / (60*60*24));
        
        $page_content .= "<tr style=\"border-bottom:1px solid rgba(59,130,246,0.06); background:#fff5f5;\"><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><strong>$client_name</strong></td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">#L{$inst['loan_id']}</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\">{$inst['installment_number']}</td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#ef4444; vertical-align:middle;\"><strong>$due_date</strong> <span style=\"font-size:0.7rem;\">(${days_overdue}d overdue)</span></td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><strong>$amount</strong></td><td style=\"padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;\"><button onclick=\"openPaymentModal({$inst['installment_id']}, '$client_name', {$inst['installment_amount']})\" style=\"padding:0.4rem 0.8rem; background:#22c55e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.75rem;\">Record Payment</button></td></tr>";
    }
    
    $page_content .= '</tbody></table>';
} else {
    $page_content .= '<div style="padding:2rem; text-align:center; color:#8EA0C4;">No overdue installments.</div>';
}

$page_content .= <<<HTML
    </div>
  </div>

  <!-- Tab: Quick Payment -->
  <div id="payment" class="tab-content" style="display:none;">
    <div style="background:white; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); padding:2rem;">
      <h3 style="color:#0F246C; font-size:1.1rem; font-weight:600; margin-bottom:1rem;">Record Installment Payment</h3>
      
      <form id="quickPaymentForm" onsubmit="handleQuickPayment(event)">
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Installment ID or Loan ID</label>
          <input type="number" id="paymentInstallmentId" required style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
        </div>
        
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Payment Amount</label>
          <input type="number" id="paymentAmount" step="0.01" min="0" required style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
        </div>
        
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Payment Method</label>
          <select id="paymentMethod" required style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
            <option value="">-- Select Method --</option>
            <option value="cash">Cash</option>
            <option value="check">Check</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="mobile_money">Mobile Money</option>
          </select>
        </div>

        <div style="display:flex; gap:1rem; margin-top:2rem;">
          <button type="submit" style="padding:0.6rem 1.2rem; background:#22c55e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500; flex:1;">Record Payment</button>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- Modal - Record Payment -->
<div id="paymentModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
  <div style="background:white; padding:2rem; border-radius:8px; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
    <div style="margin-bottom:1.5rem; border-bottom:1px solid rgba(59,130,246,0.14); padding-bottom:1rem;">
      <h3 style="color:#0F246C; font-size:1.2rem; font-weight:600; margin-bottom:0.5rem;">Record Payment</h3>
      <p style="color:#8EA0C4; font-size:0.9rem;">Client: <span id="paymentClientLabel"></span></p>
    </div>

    <div id="paymentInfo" style="background:#f0f4ff; padding:1rem; border-radius:4px; margin-bottom:1.5rem; border-left:4px solid #06b6d4;">
      <p style="margin:0.25rem 0; color:#0F246C;"><strong>Payment Due:</strong> <span id="paymentAmountDisplay"></span></p>
    </div>

    <form id="paymentRecordForm" onsubmit="handlePaymentSubmit(event)">
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Payment Amount</label>
        <input type="number" id="modalPaymentAmount" step="0.01" min="0" required style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
      </div>
      
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Payment Method</label>
        <select id="modalPaymentMethod" required style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
          <option value="">-- Select Method --</option>
          <option value="cash">Cash</option>
          <option value="check">Check</option>
          <option value="bank_transfer">Bank Transfer</option>
          <option value="mobile_money">Mobile Money</option>
        </select>
      </div>

      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Notes</label>
        <textarea id="paymentNotes" style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem; resize:vertical; min-height:60px;"></textarea>
      </div>
      
      <input type="hidden" id="paymentInstIdInput">

      <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:2rem;">
        <button type="button" onclick="closePaymentModal()" style="padding:0.6rem 1.2rem; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500;">Cancel</button>
        <button type="submit" style="padding:0.6rem 1.2rem; background:#22c55e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500;">Record Payment</button>
      </div>
    </form>
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
  function openPaymentModal(instId, clientName, amount) {
    document.getElementById('paymentClientLabel').textContent = clientName;
    document.getElementById('paymentAmountDisplay').textContent = '₱' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits: 2});
    document.getElementById('paymentInstIdInput').value = instId;
    document.getElementById('modalPaymentAmount').value = parseFloat(amount).toFixed(2);
    document.getElementById('modalPaymentMethod').value = '';
    document.getElementById('paymentNotes').value = '';
    document.getElementById('paymentModal').style.display = 'flex';
  }

  function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
    document.getElementById('paymentRecordForm').reset();
  }

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

  function handlePaymentSubmit(event) {
    event.preventDefault();
    const instId = document.getElementById('paymentInstIdInput').value;
    const amount = parseFloat(document.getElementById('modalPaymentAmount').value);
    const method = document.getElementById('modalPaymentMethod').value;

    if (!method || !amount || amount <= 0) {
      alert('Please fill in all required fields');
      return;
    }

    fetch('loans.php?action=payment', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        installment_id: instId,
        amount: amount,
        payment_method: method
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 200) {
        alert('Payment recorded successfully!');
        closePaymentModal();
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => alert('Error: ' + error.message));
  }

  function handleQuickPayment(event) {
    event.preventDefault();
    const instId = parseFloat(document.getElementById('paymentInstallmentId').value);
    const amount = parseFloat(document.getElementById('paymentAmount').value);
    const method = document.getElementById('paymentMethod').value;

    if (!instId || !amount || !method || amount <= 0) {
      alert('Please fill in all required fields');
      return;
    }

    fetch('loans.php?action=payment', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        installment_id: instId,
        amount: amount,
        payment_method: method
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 200) {
        alert('Payment recorded successfully!');
        document.getElementById('quickPaymentForm').reset();
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(error => alert('Error: ' + error.message));
  }

  document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) closePaymentModal();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePaymentModal();
  });
</script>

HTML;
include 'layout.php';
?>
