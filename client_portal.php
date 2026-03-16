<?php
require_once __DIR__ . '/init.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasRole('Client')) {
    die('Access Denied: Client role required');
}

$db = Database::getInstance();
$connectionStatus = 'OK';
$connectionMessage = 'Connected to database';
$clientName = 'Client';
$clientAccount = 'CT-Unknown';
$activeLoansCount = 0;
$totalSavings = 0.00;
$pendingInstallments = 0;
$totalRepaid = 0.00;
$ct2Count = 0;
$ct3Count = 0;
$syncLink = 'fund_allocation.php';

try {
    $userId = $_SESSION['user_id'];
    $clientRow = $db->fetchOne('SELECT * FROM clients WHERE user_id = ?', [$userId]);

    if ($clientRow) {
        $clientName = trim(($clientRow['first_name'] ?? '') . ' ' . ($clientRow['last_name'] ?? '')) ?: 'Client';
        $clientAccount = $clientRow['client_code'] ?? ('CT-' . str_pad($clientRow['client_id'], 5, '0', STR_PAD_LEFT));
        $clientId = $clientRow['client_id'];

        $loanApps = $db->fetchAll('SELECT * FROM loan_applications WHERE client_id = ?', [$clientId]) ?? [];
        $activeLoansCount = count(array_filter($loanApps, function($app) {
            return in_array($app['loan_status'], ['group_pending','group_approved','loan_approved','disbursed','active']);
        }));

        $repayments = $db->fetchAll('SELECT * FROM repayments WHERE client_id = ?', [$clientId]) ?? [];
        foreach ($repayments as $r) {
            $totalRepaid += floatval($r['amount_paid'] ?? 0);
            if (in_array($r['status'] ?? 'pending', ['pending','due'])) {
                $pendingInstallments++;
            }
        }

        $savingsAccounts = $db->fetchAll('SELECT * FROM savings_accounts WHERE client_id = ?', [$clientId]) ?? [];
        foreach ($savingsAccounts as $s) {
            $totalSavings += floatval($s['current_balance'] ?? 0);
        }

        // Connect to CT2 and CT3 nodes: KYC and Loan Tracking
        $ct2 = $db->fetchAll('SELECT * FROM compliance_audit WHERE client_id = ?', [$clientId]) ?? [];
        $ct3 = $db->fetchAll('SELECT * FROM loan_applications WHERE client_id = ?', [$clientId]) ?? [];
        $ct2Count = count($ct2);
        $ct3Count = count($ct3);

        // Optionally, sync with Fund Allocation dashboard action buttons
        $syncLink = 'fund_allocation.php';
    }
} catch (Exception $e) {
    $connectionStatus = 'DOWN';
    $connectionMessage = 'Database request failed: ' . $e->getMessage();
    $ct2Count = 0;
    $ct3Count = 0;
    $syncLink = 'fund_allocation.php';
}

$page_content = <<<HTML

<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-devices'></i></div>
    <div class="page-header-text">
      <h2>Client Self-Service Portal</h2>
      <p>Client-facing view for loan status, savings, and repayment history.</p>
    </div>
  </div>

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-banner-left">
      <div class="welcome-avatar">JD</div>
      <div class="welcome-text">
        <div class="welcome-name">Welcome back, Juan Dela Cruz</div>
        <div class="welcome-meta">Client ID: CT-00001 &nbsp;&middot;&nbsp; Member since Jan 2024</div>
      </div>
    </div>
    <div class="welcome-badge">
      <span class="welcome-dot"></span>
      <span>Account Active</span>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-money'></i></div>
      <div class="stat-info">
        <div class="stat-label">Active Loans</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Ongoing loan accounts</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-wallet'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Savings</div>
        <div class="stat-value">&#8369;0</div>
        <div class="stat-sub">Current balance</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-calendar-check'></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Installments</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Due this period</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-coin-stack'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Repaid</div>
        <div class="stat-value">&#8369;0</div>
        <div class="stat-sub">Lifetime payments</div>
      </div>
    </div>
  </div>

  <!-- Quick Links to Portal Sections - BPMN Based Organization -->
  
  <!-- LOAN MANAGEMENT SERVICE -->
  <div class="service-section">
    <div class="service-header">
      <div class="service-icon"><i class='bx bx-money'></i></div>
      <div>
        <div class="service-title">Loan Management</div>
        <div class="service-sub">View and apply for loans</div>
      </div>
    </div>
    <div class="portal-grid">
      <a href="portal_loans.php" class="portal-card">
        <div class="portal-card-icon blue"><i class='bx bx-money'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">View Loan Accounts</div>
          <div class="portal-card-sub">Check active and past loans</div>
        </div>
        <i class='bx bx-chevron-right portal-card-arrow'></i>
      </a>
      <a href="loan_application.php" class="portal-card">
        <div class="portal-card-icon orange"><i class='bx bx-file-plus'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">Apply for Loan</div>
          <div class="portal-card-sub">Submit a new loan application</div>
        </div>
        <i class='bx bx-chevron-right portal-card-arrow'></i>
      </a>
    </div>
  </div>

  <!-- PAYMENT MANAGEMENT SERVICE -->
  <div class="service-section">
    <div class="service-header">
      <div class="service-icon"><i class='bx bx-receipt'></i></div>
      <div>
        <div class="service-title">Payment Management</div>
        <div class="service-sub">Track and make payments</div>
      </div>
    </div>
    <div class="portal-grid">
      <a href="portal_repayments.php" class="portal-card">
        <div class="portal-card-icon green"><i class='bx bx-receipt'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">My Repayments</div>
          <div class="portal-card-sub">View schedule & payment history</div>
        </div>
        <i class='bx bx-chevron-right portal-card-arrow'></i>
      </a>
      <a href="#" class="portal-card" onclick="alert('Payment processing coming soon'); return false;">
        <div class="portal-card-icon teal"><i class='bx bx-credit-card'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">Make Payment</div>
          <div class="portal-card-sub">Submit installment payment</div>
        </div>
        <i class='bx bx-chevron-right portal-card-arrow'></i>
      </a>
    </div>
  </div>

  <!-- ACCOUNT OPERATIONS --> 
  <div class="service-section">
    <div class="service-header">
      <div class="service-icon"><i class='bx bx-shield-quarter'></i></div>
      <div>
        <div class="service-title">CT2/CT3 Connectors</div>
        <div class="service-sub">KYC compliance and loan tracking status</div>
      </div>
    </div>
    <div class="portal-grid">
      <div class="portal-card" style="cursor:default;">
        <div class="portal-card-icon teal"><i class='bx bx-shield-alt-2'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">CT2 KYC Checks</div>
          <div class="portal-card-sub">$ct2Count records</div>
        </div>
      </div>
      <div class="portal-card" style="cursor:default;">
        <div class="portal-card-icon blue"><i class='bx bx-trending-up'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">CT3 Loan Tracking</div>
          <div class="portal-card-sub">$ct3Count records</div>
        </div>
      </div>
      <a href="$syncLink" class="portal-card">
        <div class="portal-card-icon orange"><i class='bx bx-sync'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">Go to Fund Allocation</div>
          <div class="portal-card-sub">Review staff workflow & disbursement tracker</div>
        </div>
        <i class='bx bx-chevron-right portal-card-arrow'></i>
      </a>
    </div>
  </div>
    <div class="portal-grid">
      <a href="portal_savings.php" class="portal-card">
        <div class="portal-card-icon orange"><i class='bx bx-wallet'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">My Savings</div>
          <div class="portal-card-sub">View balances and statements</div>
        </div>
        <i class='bx bx-chevron-right portal-card-arrow'></i>
      </a>
      <a href="portal_kyc.php" class="portal-card">
        <div class="portal-card-icon purple"><i class='bx bx-id-card'></i></div>
        <div class="portal-card-body">
          <div class="portal-card-title">KYC Status</div>
          <div class="portal-card-sub">Check verification progress</div>
        </div>
        <i class='bx bx-chevron-right portal-card-arrow'></i>
      </a>
    </div>
  </div>

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
  --blue-light:  #93C5FD;
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

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text-900);}
a{text-decoration:none;color:inherit;}

.page-content{padding:1rem;animation:fadeIn .4s ease both;}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* Page Header */
.page-header{display:flex;align-items:flex-start;gap:16px;margin-bottom:2rem;}
.page-header-icon{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,var(--blue-600),var(--blue-500));display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 18px rgba(59,130,246,0.35);}
.page-header-icon i{font-size:26px;color:#fff;}
.page-header-text h2{font-family:'Space Grotesk',sans-serif;font-size:1.35rem;font-weight:700;color:var(--text-900);line-height:1.2;}
.page-header-text p{font-size:.85rem;color:var(--text-600);margin-top:4px;line-height:1.5;}

/* Welcome Banner */
.welcome-banner{background:linear-gradient(135deg,var(--navy) 0%,#1e3a9a 100%);border-radius:var(--radius);padding:1.4rem 1.6rem;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:2rem;box-shadow:0 4px 24px rgba(9,26,82,0.22);position:relative;overflow:hidden;}
.welcome-banner::before{content:'';position:absolute;top:-60px;right:-60px;width:220px;height:220px;background:radial-gradient(circle,rgba(59,130,246,0.2) 0%,transparent 70%);pointer-events:none;}
.welcome-banner-left{display:flex;align-items:center;gap:14px;}
.welcome-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--blue-500),var(--blue-light));display:flex;align-items:center;justify-content:center;font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;color:#fff;flex-shrink:0;border:2px solid rgba(255,255,255,0.2);box-shadow:0 2px 10px rgba(59,130,246,0.4);}
.welcome-name{font-family:'Space Grotesk',sans-serif;font-size:1rem;font-weight:700;color:#fff;line-height:1.3;}
.welcome-meta{font-size:.78rem;color:rgba(255,255,255,0.5);margin-top:3px;}
.welcome-badge{display:flex;align-items:center;gap:7px;background:rgba(74,222,128,0.12);border:1px solid rgba(74,222,128,0.28);border-radius:20px;padding:5px 13px 5px 9px;flex-shrink:0;}
.welcome-dot{width:7px;height:7px;background:var(--green);border-radius:50%;animation:pulse-green 2s ease-in-out infinite;}
@keyframes pulse-green{0%,100%{box-shadow:0 0 0 0 rgba(74,222,128,0.7);}50%{box-shadow:0 0 0 5px rgba(74,222,128,0);}}
.welcome-badge span:last-child{font-size:11px;font-weight:600;color:var(--green);letter-spacing:0.04em;text-transform:uppercase;}

/* Stat Grid */
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

/* Section Label */
.section-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--text-400);margin-bottom:.75rem;display:flex;align-items:center;gap:8px;}
.section-label::after{content:'';flex:1;height:1px;background:var(--border);}

/* Portal Grid */
.portal-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;}
@media(max-width:900px){.portal-grid{grid-template-columns:1fr;}}

/* Portal Card */
.portal-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem 1.4rem;box-shadow:var(--card-shadow);display:flex;align-items:center;gap:14px;transition:transform .2s,box-shadow .2s,border-color .2s;cursor:pointer;}
.portal-card:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(59,130,246,0.13);border-color:rgba(59,130,246,0.3);}
.portal-card-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.portal-card-icon.blue{background:var(--icon-blue);}.portal-card-icon.blue i{color:var(--blue-500);}
.portal-card-icon.green{background:var(--icon-green);}.portal-card-icon.green i{color:var(--green);}
.portal-card-icon.orange{background:var(--icon-orange);}.portal-card-icon.orange i{color:var(--orange);}
.portal-card-icon.teal{background:var(--icon-teal);}.portal-card-icon.teal i{color:var(--teal);}
.portal-card-icon i{font-size:23px;}
.portal-card-body{flex:1;min-width:0;}
.portal-card-title{font-family:'Space Grotesk',sans-serif;font-size:.95rem;font-weight:600;color:var(--text-900);}
.portal-card-sub{font-size:.78rem;color:var(--text-400);margin-top:3px;}
.portal-card-arrow{font-size:20px;color:var(--text-400);flex-shrink:0;transition:transform .2s,color .2s;}
.portal-card:hover .portal-card-arrow{transform:translateX(3px);color:var(--blue-500);}

/* Service Section - BPMN Based Organization */
.service-section{margin-bottom:2rem;}
.service-header{display:flex;align-items:center;gap:12px;padding-bottom:1rem;margin-bottom:1.25rem;border-bottom:2px solid var(--border);}
.service-icon{width:36px;height:36px;border-radius:10px;background:var(--bg);display:flex;align-items:center;justify-content:center;color:var(--blue-500);font-size:20px;}
.service-title{font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:600;color:var(--text-900);}
.service-sub{font-size:.85rem;color:var(--text-400);margin-top:2px;}

/* Color variants for service icons */
.portal-card-icon.purple{background:rgba(168,85,247,0.1);}.portal-card-icon.purple i{color:#a855f7;}
</style>