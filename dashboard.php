<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/init.php';
// Dashboard - accessible to all authenticated users except Client
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'client') {
    die('Access Denied: Dashboard not available for clients');
}
?>
<?php
$page_content = <<<HTML
<div class="page-content">

  <!-- Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-tachometer'></i></div>
    <div class="page-header-text">
      <h2>Dashboard</h2>
      <p>Overview of Core Transaction modules and key financial metrics.</p>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-user'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Clients</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Registered clients</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-money'></i></div>
      <div class="stat-info">
        <div class="stat-label">Active Loans</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Currently active</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-wallet'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Savings</div>
        <div class="stat-value">₱0</div>
        <div class="stat-sub">Across all accounts</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-id-card'></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending KYC</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Awaiting verification</div>
      </div>
    </div>
  </div>

  <!-- Quick Links -->
  <div class="section-label">Quick Access</div>
  <div class="quick-grid">
    <a href="client_registration.php" class="quick-card">
      <div class="quick-card-icon"><i class='bx bx-user-plus'></i></div>
      <div><div class="quick-card-label">Client Registration</div><div class="quick-card-sub">Onboard new clients</div></div>
    </a>
    <a href="loan_application.php" class="quick-card">
      <div class="quick-card-icon"><i class='bx bx-file'></i></div>
      <div><div class="quick-card-label">Loan Applications</div><div class="quick-card-sub">Process new loans</div></div>
    </a>
    <a href="loan_repayment.php" class="quick-card">
      <div class="quick-card-icon"><i class='bx bx-money'></i></div>
      <div><div class="quick-card-label">Repayments</div><div class="quick-card-sub">Track installments</div></div>
    </a>
    <a href="savings_account.php" class="quick-card">
      <div class="quick-card-icon"><i class='bx bx-wallet'></i></div>
      <div><div class="quick-card-label">Savings Accounts</div><div class="quick-card-sub">Manage savings</div></div>
    </a>
    <a href="reports.php" class="quick-card">
      <div class="quick-card-icon"><i class='bx bx-line-chart'></i></div>
      <div><div class="quick-card-label">Reports</div><div class="quick-card-sub">View performance</div></div>
    </a>
    <a href="compliance.php" class="quick-card">
      <div class="quick-card-icon"><i class='bx bx-shield-alt-2'></i></div>
      <div><div class="quick-card-label">Compliance</div><div class="quick-card-sub">Audit trail</div></div>
    </a>
  </div>

  <!-- Two column tables -->
  <div class="two-col">

    <!-- Recent Clients -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class='bx bx-user'></i> Recent Clients</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <tr><td colspan="3"><div class="empty-state"><i class='bx bx-user-x'></i><p>No records found.</p></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Loan Applications -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class='bx bx-file'></i> Recent Loan Applications</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Client</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td colspan="3"><div class="empty-state"><i class='bx bx-file-blank'></i><p>No records found.</p></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
HTML;
include 'layout.php';
?>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#0f246c;--blue-500:#3B82F6;--blue-600:#2563EB;--blue-700:#1E40AF;--blue-light:#93C5FD;--bg:#F0F4FF;--surface:#FFFFFF;--border:rgba(59,130,246,0.14);--text-900:#0F1E4A;--text-600:#4B5E8A;--text-400:#8EA0C4;--sidebar-w:300px;--topbar-h:64px;--green:#22c55e;--orange:#f97316;--teal:#14b8a6;--red:#ef4444;--icon-blue:rgba(59,130,246,0.12);--icon-green:rgba(34,197,94,0.12);--icon-orange:rgba(249,115,22,0.12);--icon-teal:rgba(20,184,166,0.12);--radius:14px;--card-shadow:0 2px 16px rgba(59,130,246,0.08);}
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
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
@media(max-width:1200px){.stat-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:600px){.stat-grid{grid-template-columns:1fr;}}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem 1.4rem;box-shadow:var(--card-shadow);display:flex;align-items:center;gap:14px;transition:transform .2s,box-shadow .2s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(59,130,246,0.13);}
.stat-icon{width:46px;height:46px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon.blue{background:var(--icon-blue);} .stat-icon.blue i{color:var(--blue-500);}
.stat-icon.green{background:var(--icon-green);} .stat-icon.green i{color:var(--green);}
.stat-icon.orange{background:var(--icon-orange);} .stat-icon.orange i{color:var(--orange);}
.stat-icon.teal{background:var(--icon-teal);} .stat-icon.teal i{color:var(--teal);}
.stat-icon i{font-size:22px;}
.stat-info{flex:1;min-width:0;}
.stat-label{font-size:.75rem;font-weight:500;color:var(--text-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;}
.stat-value{font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:700;color:var(--text-900);line-height:1;}
.stat-sub{font-size:.75rem;color:var(--text-400);margin-top:3px;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
@media(max-width:900px){.two-col{grid-template-columns:1fr;}}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--card-shadow);margin-bottom:1.5rem;overflow:hidden;}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);}
.card-title{font-family:'Space Grotesk',sans-serif;font-size:.95rem;font-weight:600;color:var(--text-900);display:flex;align-items:center;gap:8px;}
.card-title i{font-size:18px;color:var(--blue-500);}
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
.badge-gray{background:rgba(100,116,139,.1);color:#475569;}
/* Quick links */
.quick-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;}
@media(max-width:900px){.quick-grid{grid-template-columns:repeat(2,1fr);}}
.quick-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.1rem 1.2rem;display:flex;align-items:center;gap:12px;cursor:pointer;transition:all .2s;box-shadow:var(--card-shadow);}
.quick-card:hover{border-color:rgba(59,130,246,.35);transform:translateY(-2px);box-shadow:0 6px 22px rgba(59,130,246,.13);}
.quick-card-icon{width:38px;height:38px;border-radius:9px;background:var(--icon-blue);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.quick-card-icon i{font-size:19px;color:var(--blue-500);}
.quick-card-label{font-size:.83rem;font-weight:600;color:var(--text-900);}
.quick-card-sub{font-size:.72rem;color:var(--text-400);}
.section-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--text-400);margin-bottom:.75rem;margin-top:.25rem;display:flex;align-items:center;gap:8px;}
.section-label::after{content:'';flex:1;height:1px;background:var(--border);}
</style>