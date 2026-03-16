<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/init.php';
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'client') {
    die('Access Denied: Staff role required for this page');
}

$savingsService = service('savings');
$clientService = service('client');
$db = Database::getInstance();

$clientOptions = '';
$clientRows = $db->fetchAll('SELECT client_id, first_name, last_name FROM clients ORDER BY first_name, last_name');
foreach ($clientRows as $cl) {
    $fullName = trim(($cl['first_name'] ?? '') . ' ' . ($cl['last_name'] ?? ''));
    $clientOptions .= "<option value=\"{$cl['client_id']}\">#{$cl['client_id']} - {$fullName}</option>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];

    try {
        switch ($action) {
            case 'fetch_dashboard':
                $accounts = $db->fetchAll('SELECT * FROM savings_accounts ORDER BY opening_date DESC');
                foreach ($accounts as &$a) {
                    $client = $clientService->getClient($a['client_id']);
                    $a['client_name'] = $client ? trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')) : 'Unknown';
                }
                unset($a);

                $transactions = $db->fetchAll('SELECT * FROM savings_transactions ORDER BY transaction_date DESC');
                $today = date('Y-m-d');
                $transactionsToday = array_filter($transactions, function ($t) use ($today) {
                    return strpos($t['transaction_date'], $today) === 0;
                });

                $totalAccounts = count($accounts);
                $activeAccounts = count(array_filter($accounts, function ($a) {
                    return strtolower($a['account_status']) === 'active';
                }));
                $totalBalance = array_reduce($accounts, function ($carry, $a) {
                    return $carry + floatval($a['balance']);
                }, 0);

                echo json_encode([
                    'status' => 200,
                    'data' => [
                        'accounts' => $accounts,
                        'transactions' => array_slice($transactions, 0, 100),
                        'total_accounts' => $totalAccounts,
                        'active_accounts' => $activeAccounts,
                        'total_balance' => $totalBalance,
                        'transactions_today' => count($transactionsToday),
                    ]
                ]);
                break;

            case 'fetch_clients':
                $clients = $db->fetchAll('SELECT client_id, first_name, last_name FROM clients ORDER BY first_name, last_name');
                echo json_encode(['status' => 200, 'clients' => $clients]);
                break;

            case 'open_account':
                $clientId = intval($_REQUEST['client_id'] ?? 0);
                $accountType = trim($_REQUEST['account_type'] ?? 'savings');
                $initialBalance = floatval($_REQUEST['balance'] ?? 0);
                if (!$clientId) throw new Exception('client_id is required');
                $client = $clientService->getClient($clientId);
                if (!$client) throw new Exception('Client does not exist (verify KYC first)');

                $account = $savingsService->createSavingsAccount($clientId, ['account_type' => $accountType, 'balance' => $initialBalance]);
                echo json_encode(['status' => 200, 'message' => 'Account created successfully', 'account' => $account]);
                break;

            case 'process_transaction':
                $channel = trim($_REQUEST['channel'] ?? 'branch');
                $type = trim($_REQUEST['transaction_type'] ?? 'deposit');
                $accountNumber = trim($_REQUEST['account_number'] ?? '');
                $amount = floatval($_REQUEST['amount'] ?? 0);
                $clientId = intval($_REQUEST['client_id'] ?? 0);
                $description = trim($_REQUEST['description'] ?? '');

                if (!$accountNumber) throw new Exception('Account number is required');
                if ($amount <= 0) throw new Exception('Amount must be greater than zero');

                $account = $db->fetchOne('SELECT * FROM savings_accounts WHERE account_number = ?', [$accountNumber]);
                if (!$account) throw new Exception('Savings account not found');
                if ($clientId && intval($account['client_id']) !== $clientId) throw new Exception('Account does not belong to provided client id');

                $reference = 'TRX-' . time() . '-' . rand(1000,9999);
                if ($type === 'deposit') {
                    $transaction = $savingsService->deposit($account['savings_id'], $amount, "[{$channel}] " . ($description ?: 'Deposit'));
                } elseif ($type === 'withdrawal') {
                    $transaction = $savingsService->withdraw($account['savings_id'], $amount, "[{$channel}] " . ($description ?: 'Withdrawal'));
                } else {
                    throw new Exception('Invalid transaction type');
                }

                echo json_encode(['status' => 200, 'message' => "Transaction succeeded, reference {$reference}", 'transaction' => $transaction, 'reference' => $reference]);
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

$page_content = <<<HTML

<!-- Page Content -->
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-wallet'></i></div>
    <div class="page-header-text">
      <h2>Savings Account Management</h2>
      <p>Manage client savings accounts, deposits, withdrawals, and interest.</p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary" onclick="openModal('openAccountModal')"><i class='bx bx-plus'></i> Open Account</button>
      <button class="btn btn-outline" onclick="openModal('txnModal')" style="color:#3B82F6;background:#fff;margin-left:6px;"><i class='bx bx-transfer-alt'></i> New Transaction</button>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-wallet'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Accounts</div>
        <div class="stat-value" id="totalAccounts">0</div>
        <div class="stat-sub">All registered</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-check-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Active Accounts</div>
        <div class="stat-value" id="activeAccounts">0</div>
        <div class="stat-sub">Currently active</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-coin-stack'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Balance</div>
        <div class="stat-value" id="totalBalance">&#8369;0.00</div>
        <div class="stat-sub">Across all accounts</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-transfer-alt'></i></div>
      <div class="stat-info">
        <div class="stat-label">Transactions Today</div>
        <div class="stat-value" id="transactionsToday">0</div>
        <div class="stat-sub">Deposits &amp; withdrawals</div>
      </div>
    </div>
  </div>

  <!-- Tabbed Card -->
  <div class="card">

    <!-- Tab Bar -->
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab(this,'tab-accounts')">Savings Accounts</button>
      <button class="tab-btn" onclick="switchTab(this,'tab-transactions')">Transaction History</button>
    </div>

    <!-- Tab: Savings Accounts -->
    <div id="tab-accounts" class="tab-pane active">

      <!-- Tab Card Header -->
      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-wallet'></i> Savings Accounts</div>
        <div class="card-actions">
          <button class="btn btn-primary btn-sm" onclick="clearOpenAccountForm(); openModal('openAccountModal')"><i class='bx bx-plus'></i> Open Account</button>
        </div>
      </div>

      <!-- Savings Accounts Table -->
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Account No.</th>
              <th>Client</th>
              <th>Type</th>
              <th>Balance</th>
              <th>Status</th>
              <th>Opened</th>
            </tr>
          </thead>
          <tbody id="accountsBody">
            <tr>
              <td colspan="6">
                <div class="empty-state">
                  <i class='bx bx-wallet'></i>
                  <p>Loading accounts...</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div><!-- /tab-accounts -->

    <!-- Tab: Transaction History -->
    <div id="tab-transactions" class="tab-pane">

      <!-- Tab Card Header -->
      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-transfer-alt'></i> Transaction History</div>
        <div class="card-actions">
          <button class="btn btn-primary btn-sm" onclick="openModal('txnModal')"><i class='bx bx-plus'></i> Add Transaction</button>
        </div>
      </div>

      <!-- Transaction History Table -->
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Ref</th>
              <th>Account No.</th>
              <th>Client</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Date</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody id="transactionsBody">
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <i class='bx bx-transfer'></i>
                  <p>Loading transactions...</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div><!-- /tab-transactions -->

  </div><!-- /card -->


  <!-- Modals -->
  <div id="openAccountModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Open Savings Account</h3>
        <button class="modal-close" onclick="closeModal('openAccountModal')">✕</button>
      </div>
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group"><label>Account No.</label><input type="text" id="openAccountNumber" readonly placeholder="Auto-generated" /></div>
          <div class="form-group"><label>Client ID</label><select id="openClientId">
              <option value="">Select client</option>
              {$clientOptions}
            </select></div>
          <div class="form-group full"><label>Client Name</label><input type="text" id="openClientName" readonly placeholder="Auto-filled if account selected" /></div>
          <div class="form-group"><label>Account Type</label><select id="openAccountType"><option value="savings">Savings</option><option value="fixed_deposit">Fixed Deposit</option><option value="current">Current</option></select></div>
          <div class="form-group"><label>Balance</label><input type="number" step="0.01" min="0" id="openAccountBalance" value="0.00" /></div>
          <div class="form-group"><label>Status</label><input type="text" id="openAccountStatus" readonly value="Active" /></div>
          <div class="form-group full"><label>Opened</label><input type="text" id="openAccountOpened" readonly placeholder="YYYY-MM-DD HH:MM" /></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline btn-sm" onclick="clearOpenAccountForm();closeModal('openAccountModal')">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="submitOpenAccount()">Save</button>
      </div>
    </div>
  </div>

  <div id="txnModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Process Transaction</h3>
        <button class="modal-close" onclick="closeModal('txnModal')">✕</button>
      </div>
      <div class="modal-body">
        <div class="form-group"><label>Channel</label><select id="txnChannel"><option value="branch">Branch</option><option value="atm">ATM</option><option value="web">Web</option><option value="mobile">Mobile</option></select></div>
        <div class="form-group"><label>Transaction Type</label><select id="txnType"><option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option></select></div>
        <div class="form-group"><label>Account Number</label><input type="text" id="txnAccountNumber" /></div>
        <div class="form-group"><label>Client ID (optional)</label><input type="number" id="txnClientId" /></div>
        <div class="form-group"><label>Amount</label><input type="number" step="0.01" id="txnAmount" /></div>
        <div class="form-group"><label>Description</label><input type="text" id="txnDescription" /></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline btn-sm" onclick="closeModal('txnModal')">Cancel</button>
        <button class="btn btn-sm" onclick="submitTransaction()">Submit</button>
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

/* Modal overlay */
.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(250, 247, 247, 0.6);
  align-items: center;
  justify-content: center;
  z-index: 99999;
  padding: 1.2rem;
  opacity: 0;
  visibility: hidden;
  transition: opacity .22s ease, visibility .22s ease;
}
.modal.show {
  display: flex;
  opacity: 1;
  visibility: visible;
}

/* Modal card */
.modal-content {
  width: min(540px, 100%);
  max-height: min(90vh, 700px);
  overflow-y: auto;
  border-radius: 18px;
  background: rgba(255,255,255,0.98);
  backdrop-filter: blur(8px);
  padding: 1.25rem;
  box-shadow: 0 24px 64px rgba(15,23,42,.24), 0 0 0 1px rgba(255, 255, 255, 0.55);
  border: 1px solid rgba(15,23,42,.12);
  transform: translateY(-18px) scale(0.99);
  transition: transform .25s ease, opacity .25s ease;
  opacity: 0;
}
.modal.show .modal-content {
  transform: translateY(0) scale(1);
  opacity: 1;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.8rem;
  padding-bottom: 0.55rem;
  border-bottom: 1px solid rgba(14,56,128,.08);
}
.modal-header h3 {
  font-size: 1.16rem;
  line-height: 1.25;
  color: var(--text-900);
}
.modal-close {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: none;
  background: rgba(59,130,246,.10);
  color: var(--blue-700);
  cursor: pointer;
  font-size: 0.95rem;
  font-weight: 700;
  transition: background .2s ease;
}
.modal-close:hover {
  background: rgba(59,130,246,.22);
}
.modal-body {
  margin-bottom: 0.9rem;
}
.modal-body .form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(130px, 1fr));
  gap: 0.75rem 0.9rem;
}
.modal-body .form-group.full {
  grid-column: 1 / -1;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
  padding-top: 0.4rem;
  border-top: 1px solid rgba(14,56,128,.08);
}

.form-group label {
  color: #334155;
  font-size: 0.88rem;
  font-weight: 600;
  margin-bottom: 0.22rem;
  display: inline-block;
}
.form-group input,
.form-group select {
  width: 100%;
  height: 2.2rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid rgba(59,130,246,.3);
  border-radius: 8px;
  background: #F8FAFC;
  box-shadow: inset 0 1px 2px rgba(15,23,42,.05);
  color: #0F1E4A;
  font-size: 0.95rem;
}
.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: var(--blue-600);
  box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}

.account-row:hover {
  background: rgba(59,130,246,.08);
  cursor: pointer;
}

.btn-primary { background: var(--blue-600); color: #fff; }
.btn-primary:hover { background: var(--blue-700); }
.btn-outline { color: var(--blue-600); border-color: rgba(59,130,246,.45); }
.btn-outline:hover { background: rgba(59,130,246,.09); }

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

function openModal(id){
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeModal(id){
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove('show');
  document.body.style.overflow = '';
}

function showAlert(msg){
  alert(msg);
}

function loadClients(){
  const select = document.getElementById('openClientId');
  if (!select) return;
  fetch('savings_account.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=fetch_clients'})
    .then(r => r.json())
    .then(d => {
      if (d.status !== 200) { return; }
      select.innerHTML = '<option value="">Select client</option>';
      d.clients.forEach(c => {
        const fullName = `${c.first_name || ''} ${c.last_name || ''}`.trim();
        const opt = document.createElement('option');
        opt.value = c.client_id;
        opt.textContent = `#${c.client_id} - ${fullName}`;
        select.appendChild(opt);
      });
    })
    .catch(() => {
      select.innerHTML = '<option value="">Unable to load clients</option>';
    });

  select.addEventListener('change', function(){
    const option = select.options[select.selectedIndex];
    const text = option ? option.textContent || '' : '';
    const nameInput = document.getElementById('openClientName');
    if (nameInput) {
      nameInput.value = text.replace(/^#\d+\s*-\s*/,'');
    }
  });
}

function refreshDashboard(){
  fetch('savings_account.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=fetch_dashboard'
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.status !== 200){ showAlert(data.message||'Unable to load dashboard'); return; }

    const d = data.data;
    document.getElementById('totalAccounts').textContent = d.total_accounts;
    document.getElementById('activeAccounts').textContent = d.active_accounts;
    document.getElementById('totalBalance').textContent = '₱'+Number(d.total_balance).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});
    document.getElementById('transactionsToday').textContent = d.transactions_today;

    const accountsBody = document.getElementById('accountsBody');
    const openAccountListBody = document.getElementById('openAccountListBody');
    if(!d.accounts.length){
      accountsBody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#8EA0C4;padding:18px;">No accounts yet</td></tr>';
      if(openAccountListBody) openAccountListBody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#64748b;padding:10px;">No accounts yet</td></tr>';
    } else {
      const rows = d.accounts.map(a => `<tr class="account-row" data-account-number="${a.account_number}" data-client-id="${a.client_id}" data-client-name="${a.client_name}" data-account-type="${a.account_type}" data-balance="${Number(a.balance).toFixed(2)}" data-account-status="${a.account_status}" data-opening-date="${new Date(a.opening_date).toLocaleString()}"><td>${a.account_number}</td><td>${a.client_name}</td><td>${a.account_type}</td><td>₱${Number(a.balance).toFixed(2)}</td><td>${a.account_status}</td><td>${new Date(a.opening_date).toLocaleString()}</td></tr>`).join('');
      accountsBody.innerHTML = rows;
      if(openAccountListBody) openAccountListBody.innerHTML = rows;
      document.querySelectorAll('.account-row').forEach(row => row.addEventListener('click', () => fillOpenAccountForm(row)));
    }

    const txnBody = document.getElementById('transactionsBody');
    if(!d.transactions.length){
      txnBody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#8EA0C4;padding:18px;">No transactions yet</td></tr>';
    } else {
      txnBody.innerHTML = d.transactions.slice(0,100).map(t => {
        const acc = d.accounts.find(a => a.savings_id == t.savings_id) || {};
        return `<tr><td>TRX-${t.transaction_id}</td><td>${acc.account_number||''}</td><td>${acc.client_name||''}</td><td>${t.transaction_type}</td><td>₱${Number(t.transaction_amount).toFixed(2)}</td><td>${new Date(t.transaction_date).toLocaleString()}</td><td>${t.description || ''}</td></tr>`;
      }).join('');
    }
  })
  .catch(err=>{console.error(err); showAlert('Error loading dashboard');});
}

function submitOpenAccount(){
  const selectedAccountNumber = document.getElementById('openAccountNumber').value;
  if(selectedAccountNumber){ showAlert('Existing account selected. Clear form to open a new account.'); return; }

  const clientId = document.getElementById('openClientId').value;
  const accountTypeInput = document.getElementById('openAccountType').value;
  const allowedTypes = ['savings','fixed_deposit','current'];
  const accountType = allowedTypes.includes(accountTypeInput) ? accountTypeInput : 'savings';
  const balance = Number(document.getElementById('openAccountBalance').value) || 0;

  if(!clientId){ showAlert('Client ID is required'); return; }
  if(balance < 0){ showAlert('Balance cannot be negative'); return; }

  fetch('savings_account.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=open_account&client_id=${encodeURIComponent(clientId)}&account_type=${encodeURIComponent(accountType)}&balance=${encodeURIComponent(balance)}`
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.status === 200){
      showAlert(data.message);
      const account = data.account || {};
      if(account.account_number) document.getElementById('openAccountNumber').value = account.account_number;
      if(account.client_id) document.getElementById('openClientId').value = account.client_id;
      if(account.account_type) document.getElementById('openAccountType').value = account.account_type;
      if(account.balance !== undefined) document.getElementById('openAccountBalance').value = Number(account.balance).toFixed(2);
      document.getElementById('openAccountStatus').value = account.account_status || 'Active';
      if(account.opening_date) document.getElementById('openAccountOpened').value = new Date(account.opening_date).toLocaleString();
      closeModal('openAccountModal');
      refreshDashboard();
      proactiveComplianceSignal();
    } else {
      showAlert(data.message || 'Failed open account');
    }
  })
  .catch(err=>{console.error(err); showAlert('Error opening account');});
}

function clearOpenAccountForm(){
  document.getElementById('openAccountNumber').value = '';
  document.getElementById('openClientId').value = '';
  document.getElementById('openClientName').value = '';
  document.getElementById('openAccountType').value = 'savings';
  document.getElementById('openAccountBalance').value = '0.00';
  document.getElementById('openAccountStatus').value = 'Active';
  document.getElementById('openAccountOpened').value = '';
}

function fillOpenAccountForm(row){
  document.getElementById('openAccountNumber').value = row.dataset.accountNumber || '';
  document.getElementById('openClientId').value = row.dataset.clientId || '';
  document.getElementById('openClientName').value = row.dataset.clientName || '';

  const allowedTypes = ['savings','fixed_deposit','current'];
  const rowType = row.dataset.accountType || 'savings';
  document.getElementById('openAccountType').value = allowedTypes.includes(rowType) ? rowType : 'savings';

  document.getElementById('openAccountBalance').value = Number(row.dataset.balance || 0).toFixed(2);
  document.getElementById('openAccountStatus').value = row.dataset.accountStatus || '';
  document.getElementById('openAccountOpened').value = row.dataset.openingDate || '';
  openModal('openAccountModal');
}

function submitTransaction(){
  const channel = document.getElementById('txnChannel').value;
  const txnType = document.getElementById('txnType').value;
  const accountNumber = document.getElementById('txnAccountNumber').value;
  const clientId = document.getElementById('txnClientId').value;
  const amount = document.getElementById('txnAmount').value;
  const description = document.getElementById('txnDescription').value;

  if(!accountNumber || !amount || Number(amount)<=0){ showAlert('Account number and valid amount are required'); return; }

  fetch('savings_account.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=process_transaction&channel=${encodeURIComponent(channel)}&transaction_type=${encodeURIComponent(txnType)}&account_number=${encodeURIComponent(accountNumber)}&amount=${encodeURIComponent(amount)}&client_id=${encodeURIComponent(clientId)}&description=${encodeURIComponent(description)}`
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.status === 200){ showAlert(data.message); closeModal('txnModal'); refreshDashboard(); proactiveComplianceSignal(); }
    else { showAlert(data.message || 'Transaction failed'); }
  })
  .catch(err=>{ console.error(err); showAlert('Error processing transaction'); });
}

document.addEventListener('DOMContentLoaded', function(){
  loadClients();
  refreshDashboard();
  document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); }));
});
</script>