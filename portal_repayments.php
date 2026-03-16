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
    <div class="page-header-icon"><i class='bx bx-receipt'></i></div>
    <div class="page-header-text">
      <h2>My Repayments</h2>
      <p>Track your payment history and upcoming installments.</p>
    </div>
  </div>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-list-ol'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Installments</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">All scheduled</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-check-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Paid</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Settled installments</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-error-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Overdue</div>
        <div class="stat-value">0</div>
        <div class="stat-sub">Past due date</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-coin-stack'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Paid</div>
        <div class="stat-value">&#8369;0</div>
        <div class="stat-sub">Lifetime payments</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab(this,'tab-upcoming')">Upcoming</button>
      <button class="tab-btn" onclick="switchTab(this,'tab-history')">Payment History</button>
    </div>

    <div id="tab-upcoming" class="tab-pane active">
      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-calendar-check'></i> Upcoming Installments</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Loan ID</th>
              <th>Installment #</th>
              <th>Due Date</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="5">
                <div class="empty-state">
                  <i class='bx bx-calendar-x'></i>
                  <p>No upcoming installments.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab-history" class="tab-pane">
      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-receipt'></i> Payment History</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Loan ID</th>
              <th>Installment #</th>
              <th>Due Date</th>
              <th>Amount</th>
              <th>Date Paid</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="6">
                <div class="empty-state">
                  <i class='bx bx-money'></i>
                  <p>No payment history found.</p>
                </div>
              </td>
            </tr>
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
.tab-bar{display:flex;gap:4px;padding:.75rem 1.4rem .5rem;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.tab-btn{padding:6px 16px;border-radius:7px;font-size:.82rem;font-weight:500;color:var(--text-600);cursor:pointer;border:none;background:transparent;transition:all .2s;}
.tab-btn.active{background:rgba(59,130,246,.1);color:var(--blue-600);font-weight:600;}
.tab-btn:hover:not(.active){background:rgba(59,130,246,.05);}
.tab-pane{display:none;}.tab-pane.active{display:block;}
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
<script>
function switchTab(btn,id){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(id).classList.add('active');
}
</script>