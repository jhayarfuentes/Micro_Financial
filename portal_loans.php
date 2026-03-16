<?php
require_once __DIR__ . '/init.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasRole('Client')) {
    die('Access Denied: Client role required');
}

$page_content = <<<HTML

<div class="page-content">

  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-money'></i></div>
    <div class="page-header-text">
      <h2>My Loans</h2>
      <p>View your active and past loan accounts.</p>
    </div>
  </div>

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
      <div class="stat-icon green"><i class='bx bx-check-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Fully Paid</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Completed loans</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-time-five'></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Approval</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Awaiting review</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-coin-stack'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Balance</div>
        <div class="stat-value">&#8369;0</div>
        <div class="stat-sub">Outstanding amount</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class='bx bx-money'></i> My Loan Accounts</div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Loan ID</th>
            <th>Amount</th>
            <th>Purpose</th>
            <th>Term</th>
            <th>Balance</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <i class='bx bx-file-blank'></i>
                <p>No loan records found.</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

HTML;
include 'layout.php';
?>

<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#0f246c;--blue-500:#3B82F6;--blue-600:#2563EB;--blue-700:#1E40AF;--blue-light:#93C5FD;--bg:#F0F4FF;--surface:#FFFFFF;--border:rgba(59,130,246,0.14);--text-900:#0F1E4A;--text-600:#4B5E8A;--text-400:#8EA0C4;--green:#22c55e;--orange:#f97316;--teal:#14b8a6;--red:#ef4444;--icon-blue:rgba(59,130,246,0.12);--icon-green:rgba(34,197,94,0.12);--icon-orange:rgba(249,115,22,0.12);--icon-teal:rgba(20,184,166,0.12);--radius:14px;--card-shadow:0 2px 16px rgba(59,130,246,0.08);}
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
.stat-icon.blue{background:var(--icon-blue);}.stat-icon.blue i{color:var(--blue-500);}
.stat-icon.green{background:var(--icon-green);}.stat-icon.green i{color:var(--green);}
.stat-icon.orange{background:var(--icon-orange);}.stat-icon.orange i{color:var(--orange);}
.stat-icon.teal{background:var(--icon-teal);}.stat-icon.teal i{color:var(--teal);}
.stat-icon i{font-size:22px;}
.stat-info{flex:1;min-width:0;}
.stat-label{font-size:.75rem;font-weight:500;color:var(--text-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;}
.stat-value{font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:700;color:var(--text-900);line-height:1;}
.stat-sub{font-size:.75rem;color:var(--text-400);margin-top:3px;}
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
.badge-red{background:rgba(239,68,68,.1);color:#dc2626;}
.badge-gray{background:rgba(100,116,139,.1);color:#475569;}
.badge-teal{background:rgba(20,184,166,.1);color:#0f766e;}
</style>