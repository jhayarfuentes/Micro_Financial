<?php
require_once __DIR__ . '/init.php';

if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (!hasRole('Admin')) {
    die('Access Denied: Admin role required');
}

// Fetch all users from database
$allUsers = service('database')->fetchAll("SELECT * FROM users ORDER BY created_at DESC") ?? [];
$totalUsers = count($allUsers);
$activeUsers = count(array_filter($allUsers, fn($u) => ($u['status'] ?? '') === 'active'));
$inactiveUsers = $totalUsers - $activeUsers;
$rolesCount = count(array_unique(array_column($allUsers, 'role')));

$page_content = <<<HTML

<!-- Page Content -->
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-cog'></i></div>
    <div class="page-header-text">
      <h2>User Management &amp; Role-Based Access</h2>
      <p>Manage system users, roles, and access control permissions.</p>
    </div>

  </div>

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-user'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Users</div>
        <div class="stat-value">$totalUsers</div>
        <div class="stat-sub">All registered</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-user-check'></i></div>
      <div class="stat-info">
        <div class="stat-label">Active Users</div>
        <div class="stat-value">$activeUsers</div>
        <div class="stat-sub">Currently active</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-lock-alt'></i></div>
      <div class="stat-info">
        <div class="stat-label">Locked / Inactive</div>
        <div class="stat-value">$inactiveUsers</div>
        <div class="stat-sub">Access restricted</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-shield'></i></div>
      <div class="stat-info">
        <div class="stat-label">Roles Defined</div>
        <div class="stat-value">$rolesCount</div>
        <div class="stat-sub">Access roles configured</div>
      </div>
    </div>
  </div>

  <!-- Tabbed Card -->
  <div class="card">

    <!-- Tab Bar -->
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab(this,'tab-users')">System Users</button>
      <button class="tab-btn" onclick="switchTab(this,'tab-roles')">Role Assignments</button>
      <button class="tab-btn" onclick="switchTab(this,'tab-permissions')">Permissions Matrix</button>
    </div>

    <!-- Tab: System Users -->
    <div id="tab-users" class="tab-pane active">

      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-user'></i> System Users</div>
        <div class="card-actions">
          <button class="btn btn-outline btn-sm"><i class='bx bx-filter-alt'></i> Filter</button>
          <button class="btn btn-primary btn-sm"><i class='bx bx-user-plus'></i> Add User</button>
        </div>
      </div>

      <!-- Status Legend -->
      <div class="legend-bar">
        <span class="legend-label">Status:</span>
        <span class="badge badge-green">Active</span>
        <span class="badge badge-orange">Inactive</span>
        <span class="badge badge-red">Locked</span>
        <span class="badge badge-gray">Pending</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Full Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th>Date Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
HTML;

if (count($allUsers) > 0) {
    foreach ($allUsers as $user) {
        $fullName = htmlspecialchars($user['full_name'] ?? $user['name'] ?? '—');
        $username = htmlspecialchars($user['username'] ?? '—');
        $email = htmlspecialchars($user['email'] ?? '—');
        $role = htmlspecialchars($user['role'] ?? 'Unassigned');
        $status = $user['status'] ?? 'active';
        $statusClass = match($status) {
            'active' => 'badge-green',
            'inactive' => 'badge-orange',
            'locked' => 'badge-red',
            'pending' => 'badge-gray',
            default => 'badge-gray'
        };
        $statusLabel = ucfirst($status);
        $lastLogin = $user['last_login'] ? date('M d, Y · g:i A', strtotime($user['last_login'])) : 'Never';
        $createdAt = date('M d, Y', strtotime($user['created_at'] ?? 'now'));

        $page_content .= "
            <tr>
              <td><strong>$fullName</strong></td>
              <td><code>$username</code></td>
              <td>$email</td>
              <td><span class=\"role-badge\">$role</span></td>
              <td><span class=\"badge $statusClass\">$statusLabel</span></td>
              <td>$lastLogin</td>
              <td>$createdAt</td>
              <td>
                <div class=\"action-btns\">
                  <button class=\"btn-icon\" title=\"Edit\"><i class='bx bx-edit'></i></button>
                  <button class=\"btn-icon\" title=\"Delete\"><i class='bx bx-trash'></i></button>
                </div>
              </td>
            </tr>
        ";
    }
} else {
    $page_content .= "
            <tr>
              <td colspan=\"8\">
                <div class=\"empty-state\">
                  <i class='bx bx-user-x'></i>
                  <p>No users found.</p>
                </div>
              </td>
            </tr>
    ";
}

$page_content .= <<<HTML
          </tbody>
        </table>
      </div>

    </div><!-- /tab-users -->

    <!-- Tab: Role Assignments -->
    <div id="tab-roles" class="tab-pane">

      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-shield'></i> Role Assignments</div>
        <div class="card-actions">
          <button class="btn btn-primary btn-sm"><i class='bx bx-plus'></i> Assign Role</button>
        </div>
      </div>

      <!-- Role Legend -->
      <div class="legend-bar">
        <span class="legend-label">Role Level:</span>
        <span class="badge badge-red">Administrator</span>
        <span class="badge badge-orange">Portfolio Manager</span>
        <span class="badge badge-blue">Compliance Officer</span>
        <span class="badge badge-teal">Staff</span>
        <span class="badge badge-gray">Client</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Module Access</th>
              <th>Assigned By</th>
              <th>Assigned Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
HTML;

if (count($allUsers) > 0) {
    foreach ($allUsers as $user) {
        $userName = htmlspecialchars($user['full_name'] ?? $user['name'] ?? '—');
        $role = htmlspecialchars($user['role'] ?? 'Unassigned');
        $modules = match($user['role'] ?? 'Client') {
            'Admin' => 'All Modules',
            'Portfolio Manager' => 'CT1, CT2',
            'Compliance Officer' => 'CT1, CT2, CT3',
            'Client' => 'Portal',
            default => 'CT1, CT3'
        };

        $page_content .= "
            <tr>
              <td><strong>$userName</strong></td>
              <td><span class=\"role-badge\">$role</span></td>
              <td>$modules</td>
              <td>System</td>
              <td>" . date('M d, Y', strtotime($user['created_at'] ?? 'now')) . "</td>
              <td>
                <div class=\"action-btns\">
                  <button class=\"btn-icon\" title=\"Edit\"><i class='bx bx-edit'></i></button>
                </div>
              </td>
            </tr>
        ";
    }
} else {
    $page_content .= "
            <tr>
              <td colspan=\"6\">
                <div class=\"empty-state\">
                  <i class='bx bx-shield'></i>
                  <p>No role assignments found.</p>
                </div>
              </td>
            </tr>
    ";
}

$page_content .= <<<HTML
          </tbody>
        </table>
      </div>

    </div><!-- /tab-roles -->

    <!-- Tab: Permissions Matrix -->
    <div id="tab-permissions" class="tab-pane">

      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-key'></i> Permissions Matrix</div>
        <div class="card-actions">
          <button class="btn btn-outline btn-sm"><i class='bx bx-export'></i> Export Matrix</button>
          <button class="btn btn-primary btn-sm"><i class='bx bx-edit'></i> Edit Permissions</button>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Module</th>
              <th>Admin</th>
              <th>Portfolio Manager</th>
              <th>Compliance Officer</th>
              <th>Staff</th>
              <th>Client</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>CT1 · Client Services</strong></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
            </tr>
            <tr>
              <td><strong>CT2 · Institutional Oversight</strong></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
            </tr>
            <tr>
              <td><strong>CT3 · Staff Operations</strong></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
            </tr>
            <tr>
              <td><strong>CT4 · System Administration</strong></td>
              <td><i class='bx bx-check' style="color:green;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
              <td><i class='bx bx-x' style="color:red;"></i></td>
            </tr>
          </tbody>
        </table>
      </div>

    </div><!-- /tab-permissions -->

  </div><!-- /card -->

</div><!-- /page-content -->

<style>
.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--card-shadow); }
.stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon.blue { background: rgba(59,130,246,0.12); }
.stat-icon.blue i { color: var(--blue-500); font-size: 24px; }
.stat-icon.green { background: rgba(34,197,94,0.12); }
.stat-icon.green i { color: var(--green); font-size: 24px; }
.stat-icon.orange { background: rgba(249,115,22,0.12); }
.stat-icon.orange i { color: var(--orange); font-size: 24px; }
.stat-icon.teal { background: rgba(20,184,166,0.12); }
.stat-icon.teal i { color: var(--teal); font-size: 24px; }
.stat-info { flex: 1; }
.stat-label { font-size: 11px; font-weight: 600; color: var(--text-400); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
.stat-value { font-size: 28px; font-weight: 700; color: var(--text-900); }
.stat-sub { font-size: 12px; color: var(--text-600); margin-top: 4px; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--card-shadow); margin-bottom: 2rem; }
.tab-bar { display: flex; border-bottom: 1px solid var(--border); }
.tab-btn { flex: 1; padding: 12px 16px; border: none; background: transparent; color: var(--text-600); font-weight: 500; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s; }
.tab-btn:hover { background: var(--bg); }
.tab-btn.active { color: var(--blue-600); border-bottom-color: var(--blue-600); }
.tab-pane { display: none; padding: 0; }
.tab-pane.active { display: block; }
.card-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border); }
.card-title { font-size: 16px; font-weight: 600; color: var(--text-900); display: flex; align-items: center; gap: 8px; }
.card-title i { font-size: 20px; color: var(--blue-600); }
.card-actions { display: flex; gap: 8px; }
.btn { padding: 8px 14px; border-radius: 8px; border: none; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
.btn-primary { background: linear-gradient(135deg, var(--blue-600), var(--blue-500)); color: #fff; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-600); }
.btn-outline:hover { border-color: var(--blue-600); color: var(--blue-600); }
.btn-sm { padding: 6px 12px; font-size: 11px; }
.legend-bar { display: flex; align-items: center; gap: 12px; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.legend-label { font-size: 11px; font-weight: 600; color: var(--text-400); text-transform: uppercase; }
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-green { background: rgba(34,197,94,0.1); color: #16a34a; }
.badge-orange { background: rgba(249,115,22,0.1); color: #c2410c; }
.badge-red { background: rgba(239,68,68,0.1); color: #dc2626; }
.badge-gray { background: rgba(100,116,139,0.1); color: #475569; }
.badge-blue { background: rgba(59,130,246,0.1); color: var(--blue-600); }
.badge-teal { background: rgba(20,184,166,0.1); color: #0d9488; }
.role-badge { background: rgba(59,130,246,0.1); color: var(--blue-600); padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead { background: var(--bg); }
th { padding: 12px 1.5rem; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-600); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); }
td { padding: 12px 1.5rem; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text-600); }
tr:hover { background: var(--bg); }
code { background: rgba(59,130,246,0.1); color: var(--blue-600); padding: 2px 6px; border-radius: 4px; font-size: 12px; font-family: 'Courier New', monospace; }
.empty-state { text-align: center; padding: 2rem; color: var(--text-400); }
.empty-state i { font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5; }
.empty-state p { font-size: 13px; }
.action-btns { display: flex; gap: 6px; }
.btn-icon { width: 32px; height: 32px; border: 1px solid var(--border); background: transparent; border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; color: var(--text-600); }
.btn-icon:hover { background: var(--bg); color: var(--blue-600); border-color: var(--blue-600); }
.btn-icon i { font-size: 16px; }
</style>

<script>
function switchTab(btn, tabId) {
  const tabs = btn.parentElement.nextElementSibling.parentElement;
  Array.from(tabs.querySelectorAll('.tab-btn')).forEach(b => b.classList.remove('active'));
  Array.from(tabs.querySelectorAll('.tab-pane')).forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(tabId).classList.add('active');
}
</script>

HTML;

include 'layout.php';
?>
                  <i class='bx bx-user-x'></i>
                  <p>No users found.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div><!-- /tab-users -->

    <!-- Tab: Role Assignments -->
    <div id="tab-roles" class="tab-pane">

      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-shield'></i> Role Assignments</div>
        <div class="card-actions">
          <button class="btn btn-primary btn-sm"><i class='bx bx-plus'></i> Assign Role</button>
        </div>
      </div>

      <!-- Role Legend -->
      <div class="legend-bar">
        <span class="legend-label">Role Level:</span>
        <span class="badge badge-red">Super Admin</span>
        <span class="badge badge-orange">Admin</span>
        <span class="badge badge-blue">Manager</span>
        <span class="badge badge-teal">Staff</span>
        <span class="badge badge-gray">Read-Only</span>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Module Access</th>
              <th>Assigned By</th>
              <th>Assigned Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="6">
                <div class="empty-state">
                  <i class='bx bx-shield'></i>
                  <p>No role assignments found.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div><!-- /tab-roles -->

    <!-- Tab: Permissions Matrix -->
    <div id="tab-permissions" class="tab-pane">

      <div class="card-header" style="border-top:none;border-bottom:1px solid var(--border);">
        <div class="card-title"><i class='bx bx-key'></i> Permissions Matrix</div>
        <div class="card-actions">
          <button class="btn btn-outline btn-sm"><i class='bx bx-export'></i> Export Matrix</button>
          <button class="btn btn-primary btn-sm"><i class='bx bx-edit'></i> Edit Permissions</button>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Module</th>
              <th>Super Admin</th>
              <th>Admin</th>
              <th>Manager</th>
              <th>Staff</th>
              <th>Read-Only</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="6">
                <div class="empty-state">
                  <i class='bx bx-key'></i>
                  <p>No permission rules configured.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div><!-- /tab-permissions -->

  </div><!-- /card -->

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
.page-header-actions{margin-left:auto;display:flex;gap:10px;align-items:center;flex-shrink:0;}

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
.card-actions{display:flex;gap:8px;align-items:center;}

.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;}
.btn-primary{background:var(--blue-600);color:#fff;}
.btn-primary:hover{background:var(--blue-700);box-shadow:0 3px 12px rgba(37,99,235,.35);}
.btn-outline{background:transparent;color:var(--blue-600);border:1px solid rgba(59,130,246,.35);}
.btn-outline:hover{background:rgba(59,130,246,.06);}
.btn-sm{padding:5px 11px;font-size:.78rem;}
.btn i{font-size:15px;}

.tab-bar{display:flex;gap:4px;padding:.75rem 1.4rem .5rem;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.tab-btn{padding:6px 16px;border-radius:7px;font-size:.82rem;font-weight:500;color:var(--text-600);cursor:pointer;border:none;background:transparent;transition:all .2s;}
.tab-btn.active{background:rgba(59,130,246,.1);color:var(--blue-600);font-weight:600;}
.tab-btn:hover:not(.active){background:rgba(59,130,246,.05);}
.tab-pane{display:none;}.tab-pane.active{display:block;}

.legend-bar{display:flex;align-items:center;gap:8px;padding:.75rem 1.4rem;background:rgba(240,244,255,.5);border-bottom:1px solid var(--border);flex-wrap:wrap;}
.legend-label{font-size:.75rem;font-weight:500;color:var(--text-400);}

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

.action-btns{display:flex;gap:6px;}
.action-btn{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;cursor:pointer;border:1px solid var(--border);background:var(--bg);color:var(--text-400);transition:all .15s;}
.action-btn:hover{border-color:var(--blue-500);color:var(--blue-500);background:rgba(59,130,246,.06);}
.action-btn.danger:hover{border-color:var(--red);color:var(--red);background:rgba(239,68,68,.06);}
.action-btn i{font-size:14px;}
</style>

<script>
function switchTab(btn,id){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(id).classList.add('active');
}
</script>