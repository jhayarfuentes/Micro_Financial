<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/init.php';

// Loan Repayment & Installment BPMN Workflow
// Roles: Admin, Portfolio Manager (Loan Officer), Client (via portal)

if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    $allowedRoles = ['Admin', 'Portfolio Manager', 'Compliance Officer'];
    if (!in_array($role, $allowedRoles) && strtolower($role) !== 'client') {
        die('Access Denied');
    }
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
            // ===== CLIENT REGISTRATION & KYC =====
            case 'verify_client_identity':
                // Client verifies identity in real time
                $clientId = $_REQUEST['client_id'] ?? null;
                $verificationMethod = $_REQUEST['method'] ?? 'biometric'; // biometric, bvn, etc
                
                if (!$clientId) throw new Exception('Invalid client ID');
                
                $db->update('clients', [
                    'identity_verified' => true,
                    'verification_method' => $verificationMethod,
                    'identity_verified_at' => date('Y-m-d H:i:s')
                ], ['client_id' => $clientId]);
                
                $db->auditLog($userId, 'UPDATE', 'clients', $clientId, 
                    "Client verified identity: {$verificationMethod}", []);
                
                echo json_encode(['status' => 200, 'message' => 'Identity verified successfully']);
                break;

            // ===== LOAN APPLICATOR & DISBURSEMENT =====
            case 'send_verification_result':
                // System sends verification result to allow repayment processing
                $loanId = $_REQUEST['loan_id'] ?? null;
                if (!$loanId) throw new Exception('Invalid loan ID');
                
                $db->update('loans', [
                    'verification_sent' => true,
                    'verification_sent_at' => date('Y-m-d H:i:s')
                ], ['loan_id' => $loanId]);
                
                $db->auditLog($userId, 'UPDATE', 'loans', $loanId,
                    'Verification result sent to allow repayment processing', []);
                
                echo json_encode(['status' => 200, 'message' => 'Verification result sent']);
                break;

            case 'send_loan_schedule':
                // System sends updated loan schedule automatically
                $loanId = $_REQUEST['loan_id'] ?? null;
                if (!$loanId) throw new Exception('Invalid loan ID');
                
                $db->update('loans', [
                    'schedule_sent' => true,
                    'schedule_sent_at' => date('Y-m-d H:i:s')
                ], ['loan_id' => $loanId]);
                
                $db->auditLog($userId, 'UPDATE', 'loans', $loanId,
                    'Updated loan schedule sent automatically', []);
                
                echo json_encode(['status' => 200, 'message' => 'Loan schedule sent']);
                break;

            // ===== LOAN REPAYMENT & INSTALLMENT =====
            case 'generate_repayment_schedule':
                // Supplies repayment terms and schedule for Automatic tracking
                $loanId = $_REQUEST['loan_id'] ?? null;
                if (!$loanId) throw new Exception('Invalid loan ID');
                
                $loan = $db->fetchOne('SELECT * FROM loan WHERE loan_id = ?', [$loanId]);
                $monthlyPayment = ($loan['loan_amount'] * (1 + $loan['interest_rate']/100)) / $loan['loan_term_months'];
                
                // Create repayment schedule
                for ($i = 1; $i <= $loan['loan_term_months']; $i++) {
                    $dueDate = date('Y-m-d', strtotime(date('Y-m-01') . " +{$i} months"));
                    $db->insert('installments', [
                        'loan_id' => $loanId,
                        'client_id' => $loan['client_id'],
                        'installment_number' => $i,
                        'due_date' => $dueDate,
                        'amount_due' => $monthlyPayment,
                        'status' => 'pending'
                    ]);
                }
                
                $db->update('loans', [
                    'repayment_schedule_generated' => true,
                    'repayment_schedule_generated_at' => date('Y-m-d H:i:s')
                ], ['loan_id' => $loanId]);
                
                $db->auditLog($userId, 'CREATE', 'installments', $loanId,
                    "Generated repayment schedule with {$loan['loan_term_months']} installments", ['monthly_payment' => $monthlyPayment]);
                
                echo json_encode(['status' => 200, 'message' => "Repayment schedule generated with {$loan['loan_term_months']} installments"]);
                break;

            case 'send_reminder':
                // Reminder notification informs client of upcoming due payment
                $installmentId = $_REQUEST['installment_id'] ?? null;
                if (!$installmentId) throw new Exception('Invalid installment ID');
                
                $inst = $db->fetchOne('SELECT * FROM installments WHERE installment_id = ?', [$installmentId]);
                
                $db->update('installments', [
                    'reminder_sent' => true,
                    'reminder_sent_at' => date('Y-m-d H:i:s')
                ], ['installment_id' => $installmentId]);
                
                // Log notification to client self-service portal
                $db->auditLog($userId, 'NOTIFICATION', 'installments', $installmentId,
                    "Reminder sent to client: Installment #{$inst['installment_number']} due on {$inst['due_date']}", []);
                
                echo json_encode(['status' => 200, 'message' => 'Reminder notification sent to client']);
                break;

            case 'process_payment_request':
                // Client-initiated payment request triggers validation
                $installmentId = $_REQUEST['installment_id'] ?? null;
                $paymentAmount = $_REQUEST['payment_amount'] ?? 0;
                
                if (!$installmentId || !$paymentAmount) throw new Exception('Missing required fields');
                
                $inst = $db->fetchOne('SELECT * FROM installments WHERE installment_id = ?', [$installmentId]);
                
                // Payment validation
                $isValid = $paymentAmount >= ($inst['amount_due'] * 0.95); // Allow 5% tolerance
                
                if (!$isValid) {
                    // Send rejection notification
                    $db->update('installments', [
                        'payment_attempted' => true,
                        'payment_status' => 'rejected',
                        'last_rejection_reason' => 'Insufficient payment amount',
                        'last_rejection_at' => date('Y-m-d H:i:s')
                    ], ['installment_id' => $installmentId]);
                    
                    $db->auditLog($userId, 'NOTIFICATION', 'installments', $installmentId,
                        "Payment rejected: Insufficient amount. Due: ₱{$inst['amount_due']}, Received: ₱{$paymentAmount}", []);
                    
                    echo json_encode(['status' => 400, 'message' => "Payment rejected: Amount too low. Required: ₱{$inst['amount_due']}, Received: ₱{$paymentAmount}"]);
                    exit;
                }
                
                // Valid payment - Post installment
                $repayment = $db->insert('repayments', [
                    'installment_id' => $installmentId,
                    'loan_id' => $inst['loan_id'],
                    'client_id' => $inst['client_id'],
                    'amount_paid' => $paymentAmount,
                    'payment_method' => $_REQUEST['payment_method'] ?? 'bank_transfer',
                    'payment_date' => date('Y-m-d'),
                    'status' => 'posted'
                ]);
                
                // Update installment status
                $db->update('installments', [
                    'status' => 'paid',
                    'amount_paid' => $paymentAmount,
                    'payment_date' => date('Y-m-d H:i:s')
                ], ['installment_id' => $installmentId]);
                
                // Update loan balance
                $loan = $db->fetchOne('SELECT current_balance FROM loans WHERE loan_id = ?', [$inst['loan_id']]);
                $newBalance = max(0, $loan['current_balance'] - $paymentAmount);
                
                $db->update('loans', [
                    'current_balance' => $newBalance,
                    'last_payment_date' => date('Y-m-d H:i:s')
                ], ['loan_id' => $inst['loan_id']]);
                
                // Generate receipt
                $receipt = $db->insert('receipts', [
                    'repayment_id' => $repayment['repayment_id'],
                    'loan_id' => $inst['loan_id'],
                    'client_id' => $inst['client_id'],
                    'amount' => $paymentAmount,
                    'receipt_date' => date('Y-m-d H:i:s'),
                    'receipt_number' => 'RCP-' . time()
                ]);
                
                $db->auditLog($userId, 'CREATE', 'repayments', $repayment['repayment_id'],
                    "Installment #{$inst['installment_number']} paid: ₱{$paymentAmount}", 
                    ['balance_after' => $newBalance]);
                
                echo json_encode([
                    'status' => 200, 
                    'message' => 'Payment processed successfully',
                    'receipt_number' => $receipt['receipt_number'],
                    'new_balance' => $newBalance
                ]);
                break;

            // ===== SAVINGS ACCOUNT MANAGEMENT =====
            case 'auto_deduct_from_savings':
                // Auto-deducts repayment from client savings
                $clientId = $_REQUEST['client_id'] ?? null;
                $amount = $_REQUEST['amount'] ?? 0;
                
                if (!$clientId || !$amount) throw new Exception('Invalid parameters');
                
                // Check client savings balance
                $savings = $db->fetchOne('SELECT balance FROM savings_accounts WHERE client_id = ? ORDER BY opening_date DESC LIMIT 1', [$clientId]);
                
                if (!$savings || $savings['balance'] < $amount) {
                    throw new Exception('Insufficient savings balance');
                }
                
                // Deduct from savings
                $db->update('savings', [
                    'balance' => $savings['balance'] - $amount
                ], ['client_id' => $clientId]);
                
                $db->auditLog($userId, 'UPDATE', 'savings', $clientId,
                    "Auto-deducted repayment from client savings: ₱{$amount}", []);
                
                echo json_encode(['status' => 200, 'message' => "Deducted ₱{$amount} from client savings"]);
                break;

            case 'fetch_loan_repayment_data':
                // Load loans, installments, repayments for the UI via AJAX
                $loans = $installments = $repayments = [];
                try {
                    $loans = $db->fetchAll('SELECT l.*, c.first_name, c.last_name FROM loan l LEFT JOIN clients c ON l.client_id = c.client_id ORDER BY l.created_at DESC LIMIT 50');
                } catch (Exception $e) {
                    $loans = [];
                }
                try {
                    $installments = $db->fetchAll('SELECT i.*, c.first_name, c.last_name FROM installments i LEFT JOIN clients c ON i.client_id = c.client_id ORDER BY i.due_date ASC LIMIT 50');
                } catch (Exception $e) {
                    $installments = [];
                }
                try {
                    $repayments = $db->fetchAll('SELECT r.*, c.first_name, c.last_name FROM repayments r LEFT JOIN clients c ON r.client_id = c.client_id ORDER BY r.created_at DESC LIMIT 50');
                } catch (Exception $e) {
                    $repayments = [];
                }

                echo json_encode(['status' => 200, 'loans' => $loans, 'installments' => $installments, 'repayments' => $repayments]);
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

// ===== DATA FETCHING =====
$db = Database::getInstance();
$role = $_SESSION['role'] ?? 'Guest';

$loans = [];
$installments = [];
$overdue = 0;
$repayments = [];
$dbError = null;
$loans = [];

// Always try to fetch data (don't gate on role)
try {
    // Get all loans with repayment activity (don't filter by status)
    $loans = $db->fetchAll(
        'SELECT l.*, c.first_name, c.last_name FROM loan l 
         LEFT JOIN clients c ON l.client_id = c.client_id 
         ORDER BY l.created_at DESC LIMIT 50'
    );
    if (empty($loans)) {
        $loans = [];
    }
} catch (Exception $e) {
    error_log('Loans fetch error: ' . $e->getMessage());
    $loans = [];
}

try {
    // Get all installments (don't filter by status)
    $installments = $db->fetchAll(
        'SELECT i.*, c.first_name, c.last_name FROM installments i 
         LEFT JOIN clients c ON i.client_id = c.client_id 
         ORDER BY i.due_date ASC LIMIT 50'
    );
    
    if (empty($installments)) {
        $installments = [];
    } else {
        // Count overdue
        $overdue = 0;
        foreach ($installments as $i) {
            if ($i['status'] === 'overdue' || (isset($i['due_date']) && strtotime($i['due_date']) < time())) {
                $overdue++;
            }
        }
    }
} catch (Exception $e) {
    error_log('Installments fetch error: ' . $e->getMessage());
    $installments = [];
    $overdue = 0;
}

try {
    // Get repayment history
    $repayments = $db->fetchAll(
        'SELECT r.*, c.first_name, c.last_name FROM repayments r 
         LEFT JOIN clients c ON r.client_id = c.client_id 
         ORDER BY r.created_at DESC LIMIT 50'
    );
    if (empty($repayments)) {
        $repayments = [];
    }
} catch (Exception $e) {
    error_log('Repayments fetch error: ' . $e->getMessage());
    $repayments = [];
}

// Only show error if user is Admin/Portfolio Manager AND there's no data
if (($role === 'Portfolio Manager' || $role === 'Admin') && empty($loans) && empty($installments)) {
    $dbError = 'No loan data found. Run LOAN_REPAYMENT_MIGRATION.sql in Supabase.';
}

$page_content = <<<HTML

<!-- Page Content -->
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-money'></i></div>
    <div class="page-header-text">
      <h2>Loan Repayment &amp; Installments</h2>
      <p>BPMN Workflow: Manage payments, schedule, and client notifications</p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-outline btn-sm" onclick="location.reload()"><i class='bx bx-refresh'></i> Refresh</button>
    </div>
  </div>

  

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-list-ol'></i></div>
      <div class="stat-info">
        <div class="stat-label">Active Loans</div>
        <div class="stat-value" id="totalLoans">0</div>
        <div class="stat-sub">In repayment</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-check-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Paid</div>
        <div class="stat-value" id="paidInstalls">0</div>
        <div class="stat-sub">Settled installments</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-error-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Overdue</div>
        <div class="stat-value" id="overdueCount">0</div>
        <div class="stat-sub">Past due date</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-coin-stack'></i></div>
      <div class="stat-info">
        <div class="stat-label">Collected</div>
        <div class="stat-value" id="totalCollected">₱0</div>
        <div class="stat-sub">Total payments</div>
      </div>
    </div>
  </div>

  <!-- Active Loans & Installments -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class='bx bx-transfer'></i> Loan Repayment Schedule & Installments</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Loan ID</th>
            <th>Client</th>
            <th>Amount</th>
            <th>Balance</th>
            <th>Installment #</th>
            <th>Due Date</th>
            <th>Amount Due</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="installmentTable">
          <tr><td colspan="9"><div class="empty-state"><i class='bx bx-inbox'></i><p>No active installments.</p></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Repayment Records -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class='bx bx-receipt'></i> Payment Records</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Receipt #</th>
            <th>Client</th>
            <th>Loan ID</th>
            <th>Amount</th>
            <th>Date Paid</th>
            <th>Method</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="repaymentTable">
          <tr><td colspan="7"><div class="empty-state"><i class='bx bx-receipt'></i><p>No payment records.</p></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Data Rendering -->
<script>
  const loansData = [
HTML;

// Add loans data
if (!empty($loans)) {
    $loanDataJSON = [];
    foreach ($loans as $loan) {
        // Safely convert to numeric values
        $loanAmount = floatval($loan['loan_amount'] ?? 0);
        $currentBalance = floatval($loan['current_balance'] ?? $loanAmount);
        $termMonths = intval($loan['loan_term_months'] ?? 12);
        $interestRate = floatval($loan['interest_rate'] ?? 0);
        
        // Count installments for this loan
        $paid = 0;
        $pending = 0;
        if (!empty($installments)) {
            foreach ($installments as $i) {
                if ($i['loan_id'] == $loan['loan_id']) {
                    if ($i['status'] === 'paid') $paid++;
                    else $pending++;
                }
            }
        }
        
        $loanDataJSON[] = [
            'id' => intval($loan['loan_id']),
            'client' => htmlspecialchars(($loan['first_name'] ?? 'Unknown') . ' ' . ($loan['last_name'] ?? 'Client')),
            'amount' => number_format($loanAmount, 2),
            'balance' => number_format($currentBalance, 2),
            'paid' => $paid,
            'pending' => $pending,
            'term' => $termMonths,
            'rate' => $interestRate
        ];
    }
    $page_content .= json_encode($loanDataJSON);
}

$page_content .= <<<'HTML'
  ];

  const installmentsData = [
HTML;

if (!empty($installments)) {
    $instDataJSON = [];
    foreach ($installments as $inst) {
        // Safely convert values
        $loanId = intval($inst['loan_id'] ?? 0);
        $installmentNumber = intval($inst['installment_number'] ?? 0);
        $amountDue = floatval($inst['amount_due'] ?? $inst['installment_amount'] ?? 0);
        $dueDate = trim($inst['due_date'] ?? '');
        
        // Calculate status
        $daysUntilDue = 0;
        if ($dueDate) {
            $daysUntilDue = (strtotime($dueDate) - time()) / (60*60*24);
        }
        $status = $daysUntilDue < 0 ? 'overdue' : 'pending';
        $isPaid = $inst['status'] === 'paid';
        
        $instDataJSON[] = [
            'id' => intval($inst['installment_id'] ?? 0),
            'loan_id' => $loanId,
            'client' => htmlspecialchars(($inst['first_name'] ?? 'Unknown') . ' ' . ($inst['last_name'] ?? 'Client')),
            'number' => $installmentNumber,
            'due' => $dueDate,
            'amount' => $amountDue,
            'amount_formatted' => number_format($amountDue, 2),
            'status' => $status,
            'paid' => $isPaid
        ];
    }
    $page_content .= json_encode($instDataJSON);
}

$page_content .= <<<'HTML'
  ];

  const repaymentsData = [
HTML;

if (!empty($repayments)) {
    $repDataJSON = [];
    foreach ($repayments as $rep) {
        $repDataJSON[] = [
            'id' => $rep['repayment_id'],
            'receipt' => $rep['receipt_number'] ?? ('RCP-' . str_pad($rep['repayment_id'], 6, '0', STR_PAD_LEFT)),
            'client' => htmlspecialchars(($rep['first_name'] ?? 'Unknown') . ' ' . ($rep['last_name'] ?? 'Client')),
            'loan_id' => $rep['loan_id'],
            'amount' => number_format($rep['payment_amount'], 2),
            'date' => $rep['repayment_date'],
            'method' => $rep['payment_method'],
            'status' => $rep['status'] ?? 'completed'
        ];
    }
    $page_content .= json_encode($repDataJSON);
}

$page_content .= <<<'HTML'
  ];

  function renderInstallments() {
    const tbody = document.getElementById('installmentTable');
    
    // Debug: Check if data exists
    if (!installmentsData || installmentsData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><i class="bx bx-inbox"></i><p>No active installments.</p></div></td></tr>';
      return;
    }

    let html = '';
    let totalPaid = 0;
    
    // Helper function to format currency
    const formatCurrency = (num) => {
      return parseFloat(num || 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };
    
    for (const inst of installmentsData) {
      if (!inst) continue; // Skip null entries
      
      // Find matching loan to get amount and balance
      const loanId = inst.loan_id || inst.id;
      const loan = loansData.find(l => l && (l.id === loanId || parseInt(l.id) === parseInt(loanId)));
      
      // Safely get values with defaults (prefer loan data, fallback to inst data)
      const loanAmount = loan ? loan.amount : 'N/A';
      const loanBalance = loan ? loan.balance : 'N/A';
      const loanTerm = loan ? loan.term : inst.number ? '?' : 'N/A';
      const instNumber = inst.number || '?';
      const dueDate = inst.due || 'N/A';
      const amountDue = inst.amount_formatted || formatCurrency(inst.amount || 0);
      const statusText = inst.status === 'overdue' ? 'Overdue' : (inst.paid ? 'Paid' : 'Pending');
      const statusBadge = inst.status === 'overdue' ? 'badge-red' : (inst.paid ? 'badge-green' : 'badge-orange');
      
      html += `<tr>
        <td><code>${loanId || 'N/A'}</code></td>
        <td>${inst.client || 'Unknown Client'}</td>
        <td>₱${loanAmount}</td>
        <td>₱${loanBalance}</td>
        <td><strong>#${instNumber}/${loanTerm}</strong></td>
        <td>${dueDate}</td>
        <td>₱${amountDue}</td>
        <td><span class="badge ${statusBadge}">${statusText}</span></td>
        <td>
          <div class="action-btns">
            ${!inst.paid ? `<button class="action-btn" title="Process Payment"><i class='bx bx-check'></i></button>` : ''}
            <button class="action-btn" title="Info"><i class='bx bx-info-circle'></i></button>
          </div>
        </td>
      </tr>`;
      
      if (inst.paid) totalPaid += parseFloat(inst.amount) || 0;
    }
    
    tbody.innerHTML = html;
    
    // Update stat cards
    const totalLoans = loansData.length;
    const paidCount = installmentsData.filter(i => i && i.paid).length;
    const overdueCount = installmentsData.filter(i => i && i.status === 'overdue').length;
    
    document.getElementById('totalLoans').textContent = totalLoans;
    document.getElementById('paidInstalls').textContent = paidCount;
    document.getElementById('overdueCount').textContent = overdueCount;
    document.getElementById('totalCollected').textContent = '₱' + formatCurrency(totalPaid || 0);
  }

  function renderRepayments() {
    const tbody = document.getElementById('repaymentTable');
    if (!repaymentsData || repaymentsData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="bx bx-receipt"></i><p>No payment records.</p></div></td></tr>';
      return;
    }

    let html = '';
    for (const rep of repaymentsData) {
      const receipt = rep.receipt || 'RCP-' + (rep.id || 'N/A');
      const client = rep.client || 'Unknown Client';
      const loanId = rep.loan_id || 'N/A';
      const amount = rep.amount || '0.00';
      const date = rep.date || 'N/A';
      const method = (rep.method || 'Unknown').replace('_', ' ');
      const status = rep.status || 'Unknown';
      
      html += `<tr>
        <td><code>${receipt}</code></td>
        <td>${client}</td>
        <td><code>${loanId}</code></td>
        <td>₱${amount}</td>
        <td>${date}</td>
        <td><span style="font-size:.78rem">${method}</span></td>
        <td><span class="badge badge-green">${status}</span></td>
      </tr>`;
    }
    tbody.innerHTML = html;
  }

  function loadLoanRepaymentData() {
    const formData = new URLSearchParams();
    formData.append('action', 'fetch_loan_repayment_data');

    return fetch('loan_repayment.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(r => {
        if (!r.ok) throw new Error('Loan repayment data fetch failed: ' + r.status);
        return r.json();
      })
      .then(data => {
        if (data.status === 200) {
          loansData.length = 0;
          installmentsData.length = 0;
          repaymentsData.length = 0;

          data.loans.forEach(l => {
            loansData.push({
              id: parseInt(l.loan_id || l.id || 0, 10),
              client: `${l.first_name || ''} ${l.last_name || ''}`.trim() || 'Unknown Client',
              amount: parseFloat(l.loan_amount || 0).toFixed(2),
              balance: parseFloat(l.current_balance || 0).toFixed(2),
              term: parseInt(l.loan_term_months || 0, 10),
              rate: parseFloat(l.interest_rate || 0)
            });
          });

          data.installments.forEach(i => {
            const dueDate = i.due_date || '';
            let status = i.status || 'pending';
            if (!i.status && dueDate) {
              const dueMs = new Date(dueDate).getTime();
              const nowMs = Date.now();
              status = dueMs && dueMs < nowMs ? 'overdue' : 'pending';
            }

            installmentsData.push({
              id: parseInt(i.installment_id || 0, 10),
              loan_id: parseInt(i.loan_id || 0, 10),
              client: `${i.first_name || ''} ${i.last_name || ''}`.trim() || 'Unknown Client',
              number: parseInt(i.installment_number || 0, 10),
              due: dueDate || 'N/A',
              amount: parseFloat(i.amount_due || i.installment_amount || 0),
              amount_formatted: Number(i.amount_due || i.installment_amount || 0).toFixed(2),
              status: status,
              paid: i.status === 'paid'
            });
          });

          data.repayments.forEach(r => {
            repaymentsData.push({
              id: parseInt(r.repayment_id || 0, 10),
              receipt: r.receipt_number || 'RCP-' + String(r.repayment_id).padStart(6, '0'),
              client: `${r.first_name || ''} ${r.last_name || ''}`.trim() || 'Unknown Client',
              loan_id: parseInt(r.loan_id || 0, 10),
              amount: Number(r.payment_amount || 0).toFixed(2),
              date: r.repayment_date || r.payment_date || '',
              method: r.payment_method || 'N/A',
              status: r.status || 'completed'
            });
          });

          renderInstallments();
          renderRepayments();
        } else {
          console.warn('Failed to fetch loan repayment data', data);
        }
      })
      .catch(err => {
        console.error('loadLoanRepaymentData error:', err);
        renderInstallments();
        renderRepayments();
      });
  }

  // Existing render calls (legacy data load)
  renderInstallments();
  renderRepayments();

  // Refresh from DB in background
  loadLoanRepaymentData();
</script>
</div>

HTML;
include 'layout.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
  --navy:        #0f246c;
  --blue-500:    #3B82F6;
  --blue-600:    #2563EB;
  --blue-700:    #1E40AF;
  --bg:          #F0F4FF;
  --surface:     #FFFFFF;
  --border:      rgba(59,130,246,0.14);
  --text-900:    #0F1E4A;
  --text-600:    #4B5E8A;
  --text-400:    #8EA0C4;
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

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text-900); }
a { text-decoration: none; color: inherit; }

.page-content { padding: 1rem; animation: fadeIn .4s ease both; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.page-header { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 2rem; }
.page-header-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, var(--blue-600), var(--blue-500)); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 18px rgba(59,130,246,0.35); }
.page-header-icon i { font-size: 26px; color: #fff; }
.page-header-text h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.35rem; font-weight: 700; color: var(--text-900); line-height: 1.2; }
.page-header-text p { font-size: .85rem; color: var(--text-600); margin-top: 4px; }
.page-header-actions { margin-left: auto; display: flex; gap: 10px; align-items: center; flex-shrink: 0; }

.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
@media (max-width: 1200px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px) { .stat-grid { grid-template-columns: 1fr; } }

.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem 1.4rem; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 14px; transition: transform .2s, box-shadow .2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(59,130,246,0.13); }

.stat-icon { width: 46px; height: 46px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon.blue { background: var(--icon-blue); } .stat-icon.blue i { color: var(--blue-500); }
.stat-icon.green { background: var(--icon-green); } .stat-icon.green i { color: var(--green); }
.stat-icon.orange { background: var(--icon-orange); } .stat-icon.orange i { color: var(--orange); }
.stat-icon.teal { background: var(--icon-teal); } .stat-icon.teal i { color: var(--teal); }
.stat-icon i { font-size: 22px; }

.stat-info { flex: 1; }
.stat-label { font-size: .75rem; font-weight: 500; color: var(--text-400); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; }
.stat-value { font-family: 'Space Grotesk', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--text-900); line-height: 1; }
.stat-sub { font-size: .75rem; color: var(--text-400); margin-top: 3px; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--card-shadow); margin-bottom: 1.5rem; overflow: hidden; }
.card-header { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); }
.card-title { font-family: 'Space Grotesk', sans-serif; font-size: .95rem; font-weight: 600; color: var(--text-900); display: flex; align-items: center; gap: 8px; }
.card-title i { font-size: 18px; color: var(--blue-500); }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; transition: all .2s; }
.btn-primary { background: var(--blue-600); color: #fff; } .btn-primary:hover { background: var(--blue-700); }
.btn-outline { background: transparent; color: var(--blue-600); border: 1px solid rgba(59,130,246,.35); }
.btn-outline:hover { background: rgba(59,130,246,.06); }
.btn-sm { padding: 5px 11px; font-size: .78rem; }
.btn i { font-size: 15px; }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: rgba(240,244,255,.8); }
th { padding: .7rem 1.1rem; text-align: left; font-size: .7rem; font-weight: 700; color: var(--text-400); text-transform: uppercase; letter-spacing: .08em; border-bottom: 1px solid var(--border); white-space: nowrap; }
td { padding: .78rem 1.1rem; font-size: .83rem; color: var(--text-600); border-bottom: 1px solid rgba(59,130,246,.06); vertical-align: middle; }
tbody tr:hover td { background: rgba(240,244,255,.5); }

.empty-state { padding: 3.5rem 1rem; text-align: center; }
.empty-state i { font-size: 38px; color: var(--text-400); margin-bottom: 10px; display: block; }
.empty-state p { font-size: .85rem; color: var(--text-400); }

.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; }
.badge-green { background: rgba(34,197,94,.1); color: #16a34a; }
.badge-orange { background: rgba(249,115,22,.1); color: #c2410c; }
.badge-red { background: rgba(239,68,68,.1); color: #dc2626; }

.action-btns { display: flex; gap: 6px; }
.action-btn { width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--border); background: var(--bg); color: var(--text-400); transition: all .15s; }
.action-btn:hover { border-color: var(--blue-500); color: var(--blue-500); background: rgba(59,130,246,.06); }
.action-btn i { font-size: 14px; }

code { background: rgba(59,130,246,.05); padding: 2px 6px; border-radius: 4px; color: var(--blue-600); }
</style>

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