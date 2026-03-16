<?php
/**
 * Savings Management Module
 * Staff page for managing client savings accounts and transactions
 */

require_once __DIR__ . '/init.php';

// Require authentication and Savings Officer role
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasAnyRole(['Savings Officer', 'Compliance Officer'])) {
    die('Access Denied: Savings Officer or Compliance Officer role required');
}

$currentUser = getCurrentUser();

// Fetch savings statistics
try {
    $db = service('database');
    
    // Overall stats
    $stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT sa.account_id) as total_accounts,
            COUNT(DISTINCT CASE WHEN sa.account_status = 'active' THEN sa.account_id END) as active_accounts,
            COALESCE(SUM(sa.current_balance), 0) as total_savings,
            COALESCE(SUM(CASE WHEN sa.account_status = 'active' THEN sa.current_balance ELSE 0 END), 0) as active_balance
        FROM savings_accounts sa
    ");
    
    // Recent transactions
    $transactions = $db->fetchAll("
        SELECT st.*, c.first_name, c.last_name, c.email, sa.account_number
        FROM savings_transactions st
        JOIN savings_accounts sa ON st.account_id = sa.account_id
        JOIN clients c ON sa.client_id = c.client_id
        ORDER BY st.transaction_date DESC
        LIMIT 20
    ");
    
    // Active accounts
    $accounts = $db->fetchAll("
        SELECT sa.*, c.first_name, c.last_name, c.email
        FROM savings_accounts sa
        JOIN clients c ON sa.client_id = c.client_id
        WHERE sa.account_status = 'active'
        ORDER BY sa.created_date DESC
        LIMIT 20
    ");
} catch (Exception $e) {
    $error_msg = htmlspecialchars($e->getMessage());
    $stats = null;
    $transactions = [];
    $accounts = [];
}

// Format stats values for display
$total_savings_display = $stats ? number_format($stats['total_savings'], 2) : '0.00';
$active_balance_display = $stats ? number_format($stats['active_balance'], 2) : '0.00';

$page_content = <<<HTML
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-wallet'></i></div>
    <div class="page-header-text">
      <h2>Savings Account Management</h2>
      <p>Manage client savings accounts, monitor balances, and process transactions.</p>
    </div>
  </div>
HTML;

if (isset($error_msg)) {
    $page_content .= <<<HTML
  <div style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:1rem; border-radius:4px; margin-bottom:1.5rem;">
    Error loading data: $error_msg
  </div>
HTML;
}

if ($stats) {
    $page_content .= <<<HTML
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #3B82F6;">
      <div style="font-size:2rem; font-weight:bold; color:#3B82F6;">{$stats['total_accounts']}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Total Accounts</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #22c55e;">
      <div style="font-size:2rem; font-weight:bold; color:#22c55e;">{$stats['active_accounts']}</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Active Accounts</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #f59e0b;">
      <div style="font-size:2rem; font-weight:bold; color:#f59e0b;">₱ $total_savings_display</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Total Savings</div>
    </div>
    <div style="background:white; padding:1.5rem; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); text-align:center; border-left:4px solid #06b6d4;">
      <div style="font-size:2rem; font-weight:bold; color:#06b6d4;">₱ $active_balance_display</div>
      <div style="color:#8EA0C4; margin-top:0.5rem; font-size:0.9rem;">Active Balance</div>
    </div>
  </div>
HTML;
}

$page_content .= <<<HTML

  <!-- Tabs Navigation -->
  <div style="display:flex; gap:0; margin-bottom:2rem; border-bottom:2px solid rgba(59,130,246,0.14);">
    <button class="tab-btn active" onclick="switchTab(event, 'accounts')" style="padding:1rem 1.5rem; background:none; border:none; cursor:pointer; font-size:1rem; border-bottom:3px solid #3B82F6; color:#3B82F6; font-weight:500;">Active Accounts</button>
    <button class="tab-btn" onclick="switchTab(event, 'transactions')" style="padding:1rem 1.5rem; background:none; border:none; cursor:pointer; font-size:1rem; border-bottom:3px solid transparent; color:#8EA0C4; font-weight:500;">Recent Transactions</button>
  </div>

  <!-- Tab: Active Accounts -->
  <div id="accounts" class="tab-content" style="display:block;">
    <div style="background:white; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); overflow:hidden; margin-bottom:2rem;">
HTML;

if (count($accounts) > 0) {
    $page_content .= '<table style="width:100%; border-collapse:collapse;"><thead style="background:rgba(240,244,255,0.8);"><tr><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Account Number</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Client Name</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Email</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Balance</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Status</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Actions</th></tr></thead><tbody>';
    foreach ($accounts as $acc) {
      $status_class = $acc['account_status'] === 'active' ? 'background:#d4edda; color:#155724;' : 'background:#e2e3e5; color:#383d41;';
      $page_content .= '<tr style="border-bottom:1px solid rgba(59,130,246,0.06);"><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;"><strong>' . htmlspecialchars($acc['account_number']) . '</strong></td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;">' . htmlspecialchars($acc['first_name'] . ' ' . $acc['last_name']) . '</td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;">' . htmlspecialchars($acc['email']) . '</td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;"><strong>₱ ' . number_format($acc['current_balance'], 2) . '</strong></td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;"><span style="padding:0.25rem 0.5rem; border-radius:12px; font-size:0.75rem; font-weight:600; ' . $status_class . '">' . ucfirst($acc['account_status']) . '</span></td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;"><button onclick="openTransactionModal(' . $acc['account_id'] . ', \'' . htmlspecialchars($acc['first_name'] . ' ' . $acc['last_name']) . '\', ' . $acc['current_balance'] . ', \'deposit\')" style="padding:0.4rem 0.8rem; background:#22c55e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.75rem; margin-right:0.5rem;">Deposit</button><button onclick="openTransactionModal(' . $acc['account_id'] . ', \'' . htmlspecialchars($acc['first_name'] . ' ' . $acc['last_name']) . '\', ' . $acc['current_balance'] . ', \'withdrawal\')" style="padding:0.4rem 0.8rem; background:#06b6d4; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.75rem;">Withdraw</button></td></tr>';
    }
    $page_content .= '</tbody></table>';
} else {
    $page_content .= '<div style="padding:2rem; text-align:center; color:#8EA0C4;">No active savings accounts found.</div>';
}

$page_content .= <<<HTML
    </div>
  </div>

  <!-- Tab: Recent Transactions -->
  <div id="transactions" class="tab-content" style="display:none;">
    <div style="background:white; border-radius:8px; box-shadow:0 2px 8px rgba(15,36,108,0.08); overflow:hidden; margin-bottom:2rem;">
HTML;

if (count($transactions) > 0) {
    $page_content .= '<table style="width:100%; border-collapse:collapse;"><thead style="background:rgba(240,244,255,0.8);"><tr><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Date & Time</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Client</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Account</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Type</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Amount</th><th style="padding:0.7rem 1.1rem; text-align:left; font-size:0.7rem; font-weight:700; color:#8EA0C4; text-transform:uppercase; letter-spacing:0.08em; border-bottom:1px solid rgba(59,130,246,0.14); white-space:nowrap;">Balance After</th></tr></thead><tbody>';
    foreach ($transactions as $trans) {
      $badge_style = $trans['transaction_type'] === 'deposit' ? 'background:#d4edda; color:#155724;' : 'background:#f8d7da; color:#721c24;';
      $page_content .= '<tr style="border-bottom:1px solid rgba(59,130,246,0.06);"><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;">' . date('M d, Y H:i', strtotime($trans['transaction_date'])) . '</td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;">' . htmlspecialchars($trans['first_name'] . ' ' . $trans['last_name']) . '</td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;">' . htmlspecialchars($trans['account_number']) . '</td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;"><span style="padding:0.25rem 0.5rem; border-radius:4px; font-size:0.75rem; font-weight:600; ' . $badge_style . '">' . ucfirst($trans['transaction_type']) . '</span></td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;"><strong>₱ ' . number_format($trans['transaction_amount'], 2) . '</strong></td><td style="padding:0.78rem 1.1rem; font-size:0.83rem; color:#4B5E8A; vertical-align:middle;">₱ ' . number_format($trans['balance_after'], 2) . '</td></tr>';
    }
    $page_content .= '</tbody></table>';
} else {
    $page_content .= '<div style="padding:2rem; text-align:center; color:#8EA0C4;">No transactions found.</div>';
}

$page_content .= <<<HTML
    </div>
  </div>

</div>

<!-- Modal - Record Transaction -->
<div id="transactionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
  <div style="background:white; padding:2rem; border-radius:8px; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
    <div style="margin-bottom:1.5rem; border-bottom:1px solid rgba(59,130,246,0.14); padding-bottom:1rem;">
      <h3 id="modalTitle" style="color:#0F246C; font-size:1.2rem; font-weight:600; margin-bottom:0.5rem;">Record Transaction</h3>
      <p style="color:#8EA0C4; font-size:0.9rem;">Client: <span id="clientName"></span></p>
    </div>

    <div id="accountInfo" style="background:#f0f4ff; padding:1rem; border-radius:4px; margin-bottom:1.5rem; border-left:4px solid #06b6d4;">
      <p style="margin:0.25rem 0; color:#0F246C;"><strong>Current Balance:</strong></p>
      <p id="balanceDisplay" style="margin:0.25rem 0; font-size:1.2rem; font-weight:bold; color:#22c55e;"></p>
    </div>

    <form id="transactionForm" onsubmit="handleTransactionSubmit(event)">
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Transaction Type</label>
        <input type="text" id="transTypeDisplay" readonly style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem; background:#f0f4ff; color:#4B5E8A;">
      </div>
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Amount</label>
        <input type="number" id="transAmount" step="0.01" min="0" required style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem;">
      </div>
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; margin-bottom:0.5rem; font-weight:600; color:#0F246C;">Description</label>
        <textarea id="transDescription" style="width:100%; padding:0.75rem; border:1px solid rgba(59,130,246,0.14); border-radius:4px; font-size:1rem; resize:vertical; min-height:80px;"></textarea>
      </div>
      
      <input type="hidden" id="accountId">
      <input type="hidden" id="transType">

      <div style="display:flex; gap:1rem; justify-content:flex-end; margin-top:2rem;">
        <button type="button" onclick="closeTransactionModal()" style="padding:0.6rem 1.2rem; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500;">Cancel</button>
        <button type="submit" style="padding:0.6rem 1.2rem; background:#22c55e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem; font-weight:500;">Record Transaction</button>
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
  function openTransactionModal(accountId, clientName, currentBalance, transType) {
    const title = transType === 'deposit' ? 'Record Deposit' : 'Record Withdrawal';
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('clientName').textContent = clientName;
    document.getElementById('transTypeDisplay').value = transType.charAt(0).toUpperCase() + transType.slice(1);
    document.getElementById('balanceDisplay').textContent = '₱ ' + parseFloat(currentBalance).toLocaleString('en-PH', {minimumFractionDigits: 2});
    document.getElementById('accountId').value = accountId;
    document.getElementById('transType').value = transType;
    document.getElementById('transAmount').value = '';
    document.getElementById('transDescription').value = '';
    document.getElementById('transactionModal').style.display = 'flex';
  }

  function closeTransactionModal() {
    document.getElementById('transactionModal').style.display = 'none';
    document.getElementById('transactionForm').reset();
  }

  function handleTransactionSubmit(event) {
    event.preventDefault();
    const accountId = document.getElementById('accountId').value;
    const transType = document.getElementById('transType').value;
    const amount = parseFloat(document.getElementById('transAmount').value);
    const description = document.getElementById('transDescription').value;

    if (!amount || amount <= 0) {
      alert('Please enter a valid amount');
      return;
    }

    alert('Transaction feature ready for API integration.\nAccount: ' + accountId + '\nType: ' + transType + '\nAmount: ₱' + amount.toFixed(2));
    closeTransactionModal();
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

  document.getElementById('transactionModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeTransactionModal();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeTransactionModal();
    }
  });
</script>

HTML;
include 'layout.php';
?>
