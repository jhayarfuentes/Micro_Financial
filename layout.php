<?php
// CRITICAL: Set Content-Type before any output
header('Content-Type: text/html; charset=UTF-8');

// CRITICAL: Load required functions
require_once __DIR__ . '/init.php';

// CRITICAL: Start session to access $_SESSION variables
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

// ── Role-Based Access Control ──
// Define which pages each role can access
$pageAccessRules = [
    'Client' => [
        'client_portal.php',
        'portal_loans.php',
        'portal_repayments.php',
        'portal_savings.php',
        'portal_kyc.php',
        'auth.php'
    ],
    'Admin' => ['all'],
    'Administrator' => ['all'],
    'Portfolio Manager' => ['all'],
    'Compliance Officer' => ['all'],
    'Loan Officer' => ['all'],
    'KYC Officer' => ['all'],
    'Loan Collector' => ['all'],
    'Savings Officer' => ['all'],
    'Staff' => ['all'],
];

// Get current user's role - with fallback to database
$userRole = $_SESSION['role'] ?? 'Guest';
$userRole = trim($userRole);

// If session role is not set but user is logged in, reload from database
if ($userRole === 'Guest' && isset($_SESSION['user_id'])) {
    error_log("⚠️ WARNING: user_id in session but role not set. Attempting database lookup...");
    $user = getCurrentUser();
    if ($user && isset($user['role_name'])) {
        $userRole = $user['role_name'];
        $_SESSION['role'] = $userRole;  // Cache it for future requests
        error_log("✓ Role reloaded from database: " . $userRole);
    }
}

// Check if user has access to this page
if (isset($pageAccessRules[$userRole])) {
    $allowedPages = $pageAccessRules[$userRole];
    // Allow auth.php for everyone for logout
    if ($allowedPages !== ['all'] && !in_array($current_page, $allowedPages) && $current_page !== 'auth.php') {
        http_response_code(403);
        die("Access Denied: You do not have permission to access this page.");
    }
}

$portal_pages = ['client_portal.php','portal_loans.php','portal_repayments.php','portal_savings.php','portal_kyc.php'];
$portal_open  = in_array($current_page, $portal_pages);

// ============================================================
// MAIN NAVIGATION ITEMS - For all staff/admin users
// ============================================================
$all_nav_items = [
    'CT1 · CLIENT SERVICES' => [
        ['icon' => 'bx-tachometer',  'label' => 'Dashboard',                       'sub' => 'System overview',           'href' => 'dashboard.php'],
        ['icon' => 'bx-user-plus',   'label' => 'Client Registration & KYC',       'sub' => 'Onboard & verify clients',  'href' => 'client_registration.php'],
        ['icon' => 'bx-file',        'label' => 'Loan Application & Disbursement', 'sub' => 'Process & release loans',   'href' => 'loan_application.php'],
        ['icon' => 'bx-money',       'label' => 'Loan Repayment & Installments',   'sub' => 'Track repayment schedules', 'href' => 'loan_repayment.php'],
        ['icon' => 'bx-wallet',      'label' => 'Savings Account Management',      'sub' => 'Manage savings accounts',   'href' => 'savings_account.php'],
        ['icon' => 'bx-group',       'label' => 'Group Lending & Solidarity',      'sub' => 'Cooperative loan programs', 'href' => 'group_lending.php'],
        ['icon' => 'bx-devices',     'label' => 'Client Self-Service Portal',      'sub' => 'Client-facing access hub',  'href' => 'client_portal.php',
            'children' => [
                ['icon' => 'bx-money',    'label' => 'My Loans',       'href' => 'portal_loans.php'],
                ['icon' => 'bx-receipt',  'label' => 'My Repayments',  'href' => 'portal_repayments.php'],
                ['icon' => 'bx-wallet',   'label' => 'My Savings',     'href' => 'portal_savings.php'],
                ['icon' => 'bx-id-card',  'label' => 'My KYC Status',  'href' => 'portal_kyc.php'],
            ]
        ],
    ],
    'CT2 · INSTITUTIONAL OVERSIGHT' => [
        ['icon' => 'bx-bar-chart-alt-2', 'label' => 'Loan Portfolio & Risk',           'sub' => 'Exposure & risk analytics', 'href' => 'loan_portfolio.php'],
        ['icon' => 'bx-signal-5',        'label' => 'Savings & Collection Monitoring', 'sub' => 'Live collection metrics',   'href' => 'savings_monitoring.php'],
        ['icon' => 'bx-transfer-alt',    'label' => 'Disbursement & Fund Tracker',     'sub' => 'Fund flow visibility',      'href' => 'fund_allocation.php'],
        ['icon' => 'bx-shield-alt-2',    'label' => 'Compliance & Audit Trail',        'sub' => 'Regulatory & audit logs',   'href' => 'compliance.php'],
        ['icon' => 'bx-line-chart',      'label' => 'Reports & Performance',           'sub' => 'KPIs & detailed reports',   'href' => 'reports.php'],
        ['icon' => 'bx-cog',             'label' => 'User Management & RBAC',          'sub' => 'Roles, access & users',     'href' => 'user_management.php'],
    ],
    'CT3 · STAFF OPERATIONS' => [
        ['icon' => 'bx-id-card',      'label' => 'KYC Verification',         'sub' => 'Process client verification',  'href' => 'kyc_verification.php'],
        ['icon' => 'bx-check-circle', 'label' => 'Loan Approval Review',     'sub' => 'Approve & manage loans',       'href' => 'loan_approval.php'],
        ['icon' => 'bx-receipt',      'label' => 'Loan Collection',           'sub' => 'Track loan collections',       'href' => 'loan_collection.php'],
        ['icon' => 'bx-wallet',       'label' => 'Savings Management',        'sub' => 'Manage savings operations',    'href' => 'savings_management.php'],
        ['icon' => 'bx-clipboard',    'label' => 'Compliance Dashboard',      'sub' => 'Monitor compliance metrics',   'href' => 'compliance_dashboard.php'],
    ],
];

// ============================================================
// INITIALIZE nav_items as EMPTY - will be filled based on role
// ============================================================
$nav_items = [];

// ── Role-Based Navigation Filtering ──
// Get current user's role from session
$userRole = $_SESSION['role'] ?? 'Guest';
// Normalize role name (trim whitespace, handle case)
$userRole = trim($userRole);

// DETAILED DEBUG LOGGING
error_log("========== NAVIGATION FILTERING DEBUG ==========");
error_log("Raw session role: '" . ($_SESSION['role'] ?? 'NOT SET') . "'");
error_log("Trimmed userRole: '" . $userRole . "'");
error_log("Role length: " . strlen($userRole));
error_log("Role bytes: " . bin2hex($userRole));
error_log("all_nav_items keys: " . implode(', ', array_keys($all_nav_items)));

// Test each condition explicitly
$is_client = strcasecmp($userRole, 'Client') === 0;
$is_admin = strcasecmp($userRole, 'Admin') === 0;
$is_admin_alt = strcasecmp($userRole, 'Administrator') === 0;
$is_portfolio = strcasecmp($userRole, 'Portfolio Manager') === 0;
$is_compliance = strcasecmp($userRole, 'Compliance Officer') === 0;
$is_loan_officer = strcasecmp($userRole, 'Loan Officer') === 0;
$is_kyc_officer = strcasecmp($userRole, 'KYC Officer') === 0;
$is_loan_collector = strcasecmp($userRole, 'Loan Collector') === 0;
$is_savings_officer = strcasecmp($userRole, 'Savings Officer') === 0;
$is_staff = strcasecmp($userRole, 'Staff') === 0;

error_log("Condition tests:");
error_log("  - is_client: " . ($is_client ? 'TRUE' : 'FALSE'));
error_log("  - is_admin: " . ($is_admin ? 'TRUE' : 'FALSE'));
error_log("  - is_admin_alt: " . ($is_admin_alt ? 'TRUE' : 'FALSE'));
error_log("  - is_portfolio: " . ($is_portfolio ? 'TRUE' : 'FALSE'));
error_log("  - is_compliance: " . ($is_compliance ? 'TRUE' : 'FALSE'));
error_log("  - is_loan_officer: " . ($is_loan_officer ? 'TRUE' : 'FALSE'));
error_log("  - is_kyc_officer: " . ($is_kyc_officer ? 'TRUE' : 'FALSE'));
error_log("  - is_loan_collector: " . ($is_loan_collector ? 'TRUE' : 'FALSE'));
error_log("  - is_savings_officer: " . ($is_savings_officer ? 'TRUE' : 'FALSE'));

// ============================================================
// ROLE-BASED NAVIGATION FILTERING - EXPLICIT PER ROLE
// ============================================================

// CLIENT ROLE - ONLY client portal pages
if ($is_client) {
    error_log("✓ MATCHED: Client role - setting nav_items to MY ACCOUNT only");
    $nav_items = [
        'MY ACCOUNT' => [
            ['icon' => 'bx-devices', 'label' => 'My Portal', 'sub' => 'Access your account hub', 'href' => 'client_portal.php',
                'children' => [
                    ['icon' => 'bx-money', 'label' => 'My Loans', 'href' => 'portal_loans.php'],
                    ['icon' => 'bx-receipt', 'label' => 'My Repayments', 'href' => 'portal_repayments.php'],
                    ['icon' => 'bx-wallet', 'label' => 'My Savings', 'href' => 'portal_savings.php'],
                    ['icon' => 'bx-id-card', 'label' => 'My KYC Status', 'href' => 'portal_kyc.php'],
                ]
            ],
        ]
    ];
}
// ADMIN ROLE - User Management + all operational pages (CT1 + CT2 + CT3)
elseif ($is_admin || $is_admin_alt) {
    error_log("✓ MATCHED: Admin role - showing all pages: Management + CT1 + CT2 + CT3");
    $nav_items = [
        'CT4 · SYSTEM ADMINISTRATION' => [
            ['icon' => 'bx-cog',            'label' => 'User Management',        'sub' => 'Manage users & roles',      'href' => 'user_management.php'],
            ['icon' => 'bx-lock-alt',       'label' => 'Security & Permissions', 'sub' => 'Access control & audit',   'href' => 'user_management.php'],
        ],
        'CT1 · CLIENT SERVICES' => $all_nav_items['CT1 · CLIENT SERVICES'],
        'CT2 · INSTITUTIONAL OVERSIGHT' => $all_nav_items['CT2 · INSTITUTIONAL OVERSIGHT'],
        'CT3 · STAFF OPERATIONS' => $all_nav_items['CT3 · STAFF OPERATIONS'],
    ];
}
// PORTFOLIO MANAGER - CT1 (without Client Portal) + CT2
elseif ($is_portfolio) {
    error_log("✓ MATCHED: Portfolio Manager role - showing CT1 (without Client Portal) + CT2");
    $nav_items = [
        'CT1 · CLIENT SERVICES' => [
            ['icon' => 'bx-tachometer',  'label' => 'Dashboard',                       'sub' => 'System overview',           'href' => 'dashboard.php'],
            ['icon' => 'bx-user-plus',   'label' => 'Client Registration & KYC',       'sub' => 'Onboard & verify clients',  'href' => 'client_registration.php'],
            ['icon' => 'bx-file',        'label' => 'Loan Application & Disbursement', 'sub' => 'Process & release loans',   'href' => 'loan_application.php'],
            ['icon' => 'bx-money',       'label' => 'Loan Repayment & Installments',   'sub' => 'Track repayment schedules', 'href' => 'loan_repayment.php'],
            ['icon' => 'bx-wallet',      'label' => 'Savings Account Management',      'sub' => 'Manage savings accounts',   'href' => 'savings_account.php'],
            ['icon' => 'bx-group',       'label' => 'Group Lending & Solidarity',      'sub' => 'Cooperative loan programs', 'href' => 'group_lending.php'],
        ],
        'CT2 · INSTITUTIONAL OVERSIGHT' => $all_nav_items['CT2 · INSTITUTIONAL OVERSIGHT'],
    ];
}
// COMPLIANCE OFFICER - CT2 + CT3 (with explicit page definitions, NO CT1 or CT4)
elseif ($is_compliance) {
    error_log("✓ MATCHED: Compliance Officer role - showing CT2 + CT3 only");
    $nav_items = [
        'CT2 · INSTITUTIONAL OVERSIGHT' => [
            ['icon' => 'bx-bar-chart-alt-2', 'label' => 'Loan Portfolio & Risk',           'sub' => 'Exposure & risk analytics', 'href' => 'loan_portfolio.php'],
            ['icon' => 'bx-signal-5',        'label' => 'Savings & Collection Monitoring', 'sub' => 'Live collection metrics',   'href' => 'savings_monitoring.php'],
            ['icon' => 'bx-transfer-alt',    'label' => 'Disbursement & Fund Tracker',     'sub' => 'Fund flow visibility',      'href' => 'fund_allocation.php'],
            ['icon' => 'bx-shield-alt-2',    'label' => 'Compliance & Audit Trail',        'sub' => 'Regulatory & audit logs',   'href' => 'compliance.php'],
            ['icon' => 'bx-line-chart',      'label' => 'Reports & Performance',           'sub' => 'KPIs & detailed reports',   'href' => 'reports.php'],
        ],
        'CT3 · STAFF OPERATIONS' => [
            ['icon' => 'bx-id-card',      'label' => 'KYC Verification',         'sub' => 'Process client verification',  'href' => 'kyc_verification.php'],
            ['icon' => 'bx-check-circle', 'label' => 'Loan Approval Review',     'sub' => 'Approve & manage loans',       'href' => 'loan_approval.php'],
            ['icon' => 'bx-receipt',      'label' => 'Loan Collection',           'sub' => 'Track loan collections',       'href' => 'loan_collection.php'],
            ['icon' => 'bx-wallet',       'label' => 'Savings Management',        'sub' => 'Manage savings operations',    'href' => 'savings_management.php'],
            ['icon' => 'bx-clipboard',    'label' => 'Compliance Dashboard',      'sub' => 'Monitor compliance metrics',   'href' => 'compliance_dashboard.php'],
        ],
    ];
}
// LOAN OFFICER - Only Loan Approval
elseif ($is_loan_officer) {
    error_log("✓ MATCHED: Loan Officer role - showing Loan Approval only");
    $nav_items = [
        'LOAN OPERATIONS' => [
            ['icon' => 'bx-check-circle', 'label' => 'Loan Approval Review', 'sub' => 'Approve & manage loans', 'href' => 'loan_approval.php'],
        ]
    ];
}
// KYC OFFICER - Only KYC Verification
elseif ($is_kyc_officer) {
    error_log("✓ MATCHED: KYC Officer role - showing KYC Verification only");
    $nav_items = [
        'KYC OPERATIONS' => [
            ['icon' => 'bx-id-card', 'label' => 'KYC Verification', 'sub' => 'Process client verification', 'href' => 'kyc_verification.php'],
        ]
    ];
}
// LOAN COLLECTOR - Only Loan Collection
elseif ($is_loan_collector) {
    error_log("✓ MATCHED: Loan Collector role - showing Loan Collection only");
    $nav_items = [
        'COLLECTION OPERATIONS' => [
            ['icon' => 'bx-receipt', 'label' => 'Loan Collection', 'sub' => 'Track loan collections', 'href' => 'loan_collection.php'],
        ]
    ];
}
// SAVINGS OFFICER - Only Savings Management
elseif ($is_savings_officer) {
    error_log("✓ MATCHED: Savings Officer role - showing Savings Management only");
    $nav_items = [
        'SAVINGS OPERATIONS' => [
            ['icon' => 'bx-wallet', 'label' => 'Savings Management', 'sub' => 'Manage savings operations', 'href' => 'savings_management.php'],
        ]
    ];
}
// STAFF - CT1 only (without Client Self-Service Portal)
elseif ($is_staff) {
    error_log("✓ MATCHED: Staff role - setting nav_items to CT1 WITHOUT Client Portal");
    $nav_items = [
        'CT1 · CLIENT SERVICES' => [
            ['icon' => 'bx-tachometer',  'label' => 'Dashboard',                       'sub' => 'System overview',           'href' => 'dashboard.php'],
            ['icon' => 'bx-user-plus',   'label' => 'Client Registration & KYC',       'sub' => 'Onboard & verify clients',  'href' => 'client_registration.php'],
            ['icon' => 'bx-file',        'label' => 'Loan Application & Disbursement', 'sub' => 'Process & release loans',   'href' => 'loan_application.php'],
            ['icon' => 'bx-money',       'label' => 'Loan Repayment & Installments',   'sub' => 'Track repayment schedules', 'href' => 'loan_repayment.php'],
            ['icon' => 'bx-wallet',      'label' => 'Savings Account Management',      'sub' => 'Manage savings accounts',   'href' => 'savings_account.php'],
            ['icon' => 'bx-group',       'label' => 'Group Lending & Solidarity',      'sub' => 'Cooperative loan programs', 'href' => 'group_lending.php'],
        ],
    ];
}
// UNKNOWN/GUEST ROLE - No sections
else {
    error_log("✓ DEFAULT: Unknown role - setting nav_items to empty");
    $nav_items = [];
}

error_log("Final nav_items section count: " . count($nav_items));
error_log("Final nav_items sections: " . implode(', ', array_keys($nav_items)));
error_log("========== END FILTERING ==========");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CORE TRANSACTION — Microfinancial Management System</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --navy:        #0f246c;
  --navy-dark:   #0e4627;
  --navy-mid:    #137f40;
  --navy-light:  #22c55e;
  --green-400:   #4ade80;
  --green-500:   #22c55e;
  --green-600:   #16a34a;
  --green-700:   #15803d;
  --blue-500:    #3B82F6;
  --blue-600:    #2563EB;
  --blue-700:    #1E40AF;
  --blue-light:  #93C5FD;
  --bg:          #EBFFEF;
  --surface:     #FFFFFF;
  --border:      rgba(59,130,246,0.14);
  --text-900:    #0F1E4A;
  --text-600:    #4B5E8A;
  --text-400:    #8EA0C4;
  --sidebar-w:   300px;
  --topbar-h:    64px;
  --transition:  cubic-bezier(0.4,0,0.2,1) 0.3s;
  --sidebar-text:      rgba(255,255,255,0.88);
  --sidebar-muted:     rgba(255,255,255,0.45);
  --sidebar-hover-bg:  rgba(255,255,255,0.07);
  --sidebar-active-bg: rgba(59,130,246,0.22);
  --sidebar-border:    rgba(255,255,255,0.08);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text-900); overflow-x: hidden; }
a { text-decoration: none; color: inherit; }

::-webkit-scrollbar            { width: 4px; }
::-webkit-scrollbar-track      { background: transparent; }
::-webkit-scrollbar-thumb      { background: rgba(255,255,255,0.15); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover{ background: rgba(255,255,255,0.28); }

/* ── Sidebar ── */
.sidebar {
  position: fixed; top: 0; left: 0;
  width: var(--sidebar-w); height: 100vh;
  background: linear-gradient(165deg, var(--green-700) 0%, var(--green-500) 100%);
  display: flex; flex-direction: column;
  z-index: 1000;
  transition: transform var(--transition);
  overflow: hidden;
  box-shadow: 4px 0 32px rgba(9,26,82,0.45);
}

.sidebar-header {
  padding: 28px 24px 20px;
  flex-shrink: 0;
  position: relative; z-index: 1;
  border-bottom: 1px solid var(--sidebar-border);
}

.brand-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.brand-icon { width: 40px; height: 40px; background: linear-gradient(135deg,var(--green-700),var(--green-500)); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 14px rgba(34,197,94,0.55); flex-shrink: 0; }
.brand-icon i { font-size: 22px; color: #fff; }
.brand-title { font-family: 'Space Grotesk', sans-serif; font-size: 13.5px; font-weight: 700; letter-spacing: 0.12em; color: #fff; text-transform: uppercase; }
.brand-subtitle { font-size: 10px; font-weight: 400; color: var(--sidebar-muted); letter-spacing: 0.04em; margin-top: 2px; }

.status-badge { display: inline-flex; align-items: center; gap: 7px; background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.25); border-radius: 20px; padding: 4px 10px 4px 8px; }
.pulse-dot { width: 7px; height: 7px; background: var(--green-400); border-radius: 50%; flex-shrink: 0; animation: pulse-glow 2s ease-in-out infinite; }
@keyframes pulse-glow { 0%,100%{ box-shadow:0 0 0 0 rgba(74,222,128,0.7); opacity:1; } 50%{ box-shadow:0 0 0 5px rgba(74,222,128,0); opacity:0.8; } }
.status-label { font-size: 10px; font-weight: 600; color: var(--green-400); letter-spacing: 0.05em; text-transform: uppercase; }

/* ── Nav ── */
.sidebar-nav { flex: 1; overflow-y: auto; padding: 16px 0 8px; position: relative; z-index: 1; }
.nav-section { margin-bottom: 4px; }
.nav-section-label { display: flex; align-items: center; gap: 8px; padding: 10px 24px 6px; font-size: 9.5px; font-weight: 700; letter-spacing: 0.13em; color: var(--sidebar-muted); text-transform: uppercase; user-select: none; }
.nav-section-label::after { content: ''; flex: 1; height: 1px; background: var(--sidebar-border); }

.nav-item { padding: 0 14px; margin: 1px 0; }
.nav-link { display: flex; align-items: center; gap: 13px; width: 100%; padding: 9px 12px; border-radius: 10px; transition: background var(--transition), transform var(--transition); cursor: pointer; }
.nav-link:hover { background: rgba(34,197,94,0.18); transform: translateX(8px); }
.nav-link.active { background: rgba(22,163,74,0.35); }

.icon-wrapper { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: rgba(255,255,255,0.06); transition: background var(--transition); }
.nav-link:hover .icon-wrapper, .nav-link.active .icon-wrapper { background: rgba(34,197,94,0.25); }
.icon-wrapper i { font-size: 18px; color: rgba(255,255,255,0.9); transition: color var(--transition); }
.nav-link:hover .icon-wrapper i, .nav-link.active .icon-wrapper i { color: var(--blue-light); }

.nav-text { flex: 1; min-width: 0; line-height: 1.25; }
.main-text { font-size: 12.5px; font-weight: 500; color: var(--sidebar-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nav-link.active .main-text { color: #fff; font-weight: 600; }
.sub-text { font-size: 10px; color: var(--sidebar-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }

.nav-chevron { opacity: 0; transition: opacity var(--transition); color: var(--blue-light); font-size: 14px; flex-shrink: 0; }
.nav-link:hover .nav-chevron, .nav-link.active .nav-chevron { opacity: 1; }

/* ── Expandable parent nav item ── */
.nav-item-parent .nav-link-parent { display: flex; align-items: center; gap: 13px; width: 100%; padding: 9px 12px; border-radius: 10px; transition: background var(--transition); cursor: pointer; }
.nav-item-parent .nav-link-parent:hover { background: var(--sidebar-hover-bg); }
.nav-item-parent .nav-link-parent.open { background: rgba(59,130,246,0.12); }

/* Toggle arrow */
.nav-toggle-arrow {
  font-size: 15px;
  color: var(--sidebar-muted);
  flex-shrink: 0;
  transition: transform 0.25s cubic-bezier(0.4,0,0.2,1), color 0.2s;
}
.nav-link-parent.open .nav-toggle-arrow {
  transform: rotate(90deg);
  color: var(--blue-light);
}

/* Sub-nav list */
.nav-subnav {
  display: none;
  flex-direction: column;
  gap: 1px;
  margin: 3px 0 4px 14px;
  padding-left: 20px;
  border-left: 2px solid rgba(59,130,246,0.2);
}
.nav-subnav.open { display: flex; }
/* Collapsed sidebar for desktop */
.sidebar.collapsed { transform: translateX(-100%); }
.nav-sublink {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 10px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 500;
  color: var(--sidebar-muted);
  transition: background var(--transition), color var(--transition), transform var(--transition);
  cursor: pointer;
}
.nav-sublink:hover {
  background: var(--sidebar-hover-bg);
  color: var(--sidebar-text);
  transform: translateX(4px);
}
.nav-sublink.active {
  background: rgba(59,130,246,0.18);
  color: #fff;
  font-weight: 600;
}
.nav-sublink i { font-size: 15px; color: var(--sidebar-muted); flex-shrink: 0; transition: color var(--transition); }
.nav-sublink:hover i, .nav-sublink.active i { color: var(--blue-light); }

/* ── Sidebar footer ── */
.sidebar-footer { flex-shrink: 0; padding: 16px; border-top: 1px solid var(--sidebar-border); position: relative; z-index: 1; }
.footer-card { background: rgba(255,255,255,0.05); border: 1px solid var(--sidebar-border); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 12px; }
.footer-icon { width: 34px; height: 34px; background: linear-gradient(135deg,rgba(59,130,246,0.3),rgba(30,64,175,0.3)); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.footer-icon i { font-size: 17px; color: var(--blue-light); }
.footer-info { flex: 1; min-width: 0; }
.footer-title { font-size: 11.5px; font-weight: 600; color: var(--sidebar-text); line-height: 1.3; }
.footer-status { font-size: 10px; color: var(--sidebar-muted); margin-top: 1px; }
.footer-version { font-size: 9px; font-weight: 700; letter-spacing: 0.08em; color: var(--blue-light); background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.25); border-radius: 5px; padding: 2px 6px; flex-shrink: 0; }

/* ── Overlay ── */
.sidebar-overlay { position: fixed; inset: 0; background: rgba(9,26,82,0.55); backdrop-filter: blur(2px); z-index: 999; opacity: 0; transition: opacity var(--transition); pointer-events: none; }
.sidebar-overlay.visible { opacity: 1; pointer-events: auto; }

/* ── Top Navbar ── */
.top-navbar { position: sticky; top: 0; width: 100%; height: var(--topbar-h); background: var(--surface); border-bottom: 1px solid var(--border); box-shadow: 0 1px 16px rgba(59,130,246,0.08); z-index: 800; }
.navbar-container { max-width: 100vw; height: 100%; display: flex; align-items: center; padding: 0 18px; }
.navbar-content { width: 100%; display: flex; align-items: center; justify-content: space-between; }
.navbar-left { display: flex; align-items: center; }
.toggle-btn { width: 38px; height: 38px; border: 1px solid var(--border); border-radius: 9px; background: var(--bg); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
.toggle-btn:hover { background: #e4ecff; border-color: rgba(59,130,246,0.3); transform: scale(1.05); }
.toggle-btn i { font-size: 22px; color: var(--text-600); }
.navbar-right { display: flex; align-items: center; gap: 14px; }
.time-display { display: flex; align-items: center; background: var(--bg); border: 1px solid var(--border); border-radius: 20px; padding: 5px 14px; min-width: 130px; font-family: 'Space Grotesk', sans-serif; font-size: 13px; font-weight: 600; color: var(--text-900); letter-spacing: 0.03em; }
.date-separator { margin: 0 7px; color: var(--text-400); font-size: 13px; }
.icon-btn { width: 38px; height: 38px; border: 1px solid var(--border); border-radius: 9px; background: var(--bg); display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative; transition: background var(--transition); }
.icon-btn:hover { background: #e4ecff; border-color: rgba(59,130,246,0.3); }
.icon-btn i { font-size: 20px; color: var(--text-600); }
.badge-dot { position: absolute; top: 7px; right: 7px; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 1.5px solid var(--surface); animation: pulse-red 2s ease-in-out infinite; }
@keyframes pulse-red { 0%,100%{ box-shadow:0 0 0 0 rgba(239,68,68,0.7); } 50%{ box-shadow:0 0 0 4px rgba(239,68,68,0); } }
.profile-wrapper { position: relative; }
.profile-avatar, .dropdown-avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg,var(--blue-600),var(--blue-500)); display: flex; align-items: center; justify-content: center; font-family: 'Space Grotesk', sans-serif; font-size: 13px; font-weight: 700; color: #fff; cursor: pointer; border: 2px solid rgba(59,130,246,0.35); transition: box-shadow 0.2s, transform 0.15s; user-select: none; }
.profile-avatar:hover { box-shadow: 0 0 0 4px rgba(59,130,246,0.18); transform: scale(1.06); }
.profile-dropdown { position: absolute; top: calc(100% + 10px); right: 0; width: 192px; background: var(--surface); border: 1px solid var(--border); border-radius: 13px; box-shadow: 0 8px 40px rgba(59,130,246,0.14),0 2px 8px rgba(9,26,82,0.1); padding: 6px; opacity: 0; transform: translateY(-8px) scale(0.97); pointer-events: none; transition: opacity 0.2s ease, transform 0.2s ease; z-index: 900; }
.profile-dropdown.open { opacity: 1; transform: translateY(0) scale(1); pointer-events: all; }
.dropdown-header { padding: 10px 12px 8px; border-bottom: 1px solid var(--border); margin-bottom: 4px; display: flex; align-items: center; gap: 10px; }
.dropdown-name { font-size: 12.5px; font-weight: 600; color: var(--text-900); }
.dropdown-role { font-size: 10.5px; color: var(--text-400); margin-top: 1px; }
.dropdown-item { display: flex; align-items: center; gap: 9px; padding: 8px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 500; color: var(--text-600); cursor: pointer; transition: background 0.15s, color 0.15s; }
.dropdown-item:hover { background: var(--bg); color: var(--text-900); }
.dropdown-item i { font-size: 16px; color: var(--text-400); transition: color 0.15s; }
.dropdown-item:hover i { color: var(--blue-500); }
.dropdown-item.dropdown-logout { color: #dc2626; }
.dropdown-item.dropdown-logout i { color: #dc2626; }
.dropdown-item.dropdown-logout:hover { background: #fff0f0; color: #b91c1c; }
.dropdown-divider { height: 1px; background: var(--border); margin: 4px 0; }

/* ── Page Wrapper ── */
.page-wrapper { margin-left: var(--sidebar-w); min-height: 100vh; transition: margin-left var(--transition); display: flex; flex-direction: column; }
.page-wrapper.shifted { margin-left: 0; }
.page-content { padding: 32px; flex: 1; }

.demo-notice { background: var(--surface); border: 1px dashed var(--border); border-radius: 16px; padding: 48px 32px; text-align: center; color: var(--text-400); }
.demo-notice i { font-size: 40px; color: var(--blue-light); margin-bottom: 12px; display: block; }
.demo-notice h2 { font-size: 16px; font-weight: 600; color: var(--text-600); margin-bottom: 6px; }
.demo-notice p { font-size: 13px; line-height: 1.6; }

@media (max-width: 768px) {
  .page-wrapper { margin-left: 0; }
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .time-display { display: none; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="brand-logo">
      <div class="brand-icon"><i class='bx bx-building-house'></i></div>
      <div class="brand-text">
        <div class="brand-title">Core Transaction</div>
        <div class="brand-subtitle">Financial Services & Institutional Control</div>
      </div>
    </div>
    <div class="status-badge">
      <span class="pulse-dot"></span>
      <span class="status-label">Online &amp; Operational</span>
    </div>
    <!-- Role Badge Based on User's Current Role -->
    <div class="role-badge" style="margin-top: 12px; padding: 8px 12px; background: rgba(59,130,246,0.1); border-radius: 8px; border-left: 3px solid var(--blue-600);">
      <div style="font-size: 10px; color: var(--text-400); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Current Role</div>
      <div style="font-size: 13px; font-weight: 600; color: var(--blue-600); margin-top: 2px;"><?= htmlspecialchars($userRole) ?></div>
      <div style="font-size: 10px; color: var(--text-400); margin-top: 4px; padding-top: 4px; border-top: 1px solid rgba(59,130,246,0.2);">
        Sections: <?= count($nav_items) > 0 ? implode(', ', array_slice(array_keys($nav_items), 0, 2)) : 'NONE' ?>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <!-- Role-Based Navigation - Filtered Above -->
    <?php if (empty($nav_items)): ?>
    <!-- Empty State if No Sections Available -->
    <div class="nav-section" style="padding: 20px 15px; text-align: center; color: var(--text-400);">
      <p style="font-size: 12px;"><i class='bx bx-info-circle'></i></p>
      <p style="font-size: 12px; font-weight: 500;">No accessible sections</p>
      <p style="font-size: 11px; margin-top: 4px;">Contact administrator for access</p>
    </div>
    <?php else: ?>
    
    <?php foreach ($nav_items as $section => $items): ?>
    <div class="nav-section">
      <div class="nav-section-label" title="<?= htmlspecialchars($section) ?>">
        <?= htmlspecialchars($section) ?>
        <?php if (strcasecmp($userRole, 'Client') === 0): ?>
        <span style="font-size: 10px; color: var(--blue-500); margin-left: 4px; text-transform: uppercase; font-weight: 600;">Client</span>
        <?php endif; ?>
      </div>

      <?php foreach ($items as $item):
        $is_active = ($current_page === $item['href']);
        $has_children = !empty($item['children']);
        $parent_open = $has_children && $portal_open;
      ?>

        <?php if ($has_children): ?>
        <!-- Expandable nav item -->
        <div class="nav-item nav-item-parent">
          <div class="nav-link-parent <?= $parent_open ? 'open' : '' ?>"
               onclick="toggleNavGroup(this)"
               title="<?= htmlspecialchars($item['label']) ?>">
            <div class="icon-wrapper">
              <i class='bx <?= htmlspecialchars($item['icon']) ?>'></i>
            </div>
            <div class="nav-text">
              <div class="main-text"><?= htmlspecialchars($item['label']) ?></div>
              <div class="sub-text"><?= htmlspecialchars($item['sub']) ?></div>
            </div>
            <i class='bx bx-chevron-right nav-toggle-arrow'></i>
          </div>

          <!-- Sub-nav -->
          <div class="nav-subnav <?= $parent_open ? 'open' : '' ?>">
            <a href="<?= htmlspecialchars($item['href']) ?>"
               class="nav-sublink <?= $is_active ? 'active' : '' ?>"
               title="<?= htmlspecialchars($item['label']) ?> Overview">
              <i class='bx bx-tachometer'></i>
              Overview
            </a>
            <?php foreach ($item['children'] as $child):
              $child_active = ($current_page === $child['href']);
            ?>
            <a href="<?= htmlspecialchars($child['href']) ?>"
               class="nav-sublink <?= $child_active ? 'active' : '' ?>"
               title="<?= htmlspecialchars($child['label']) ?>">
              <i class='bx <?= htmlspecialchars($child['icon']) ?>'></i>
              <?= htmlspecialchars($child['label']) ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>

        <?php else: ?>
        <!-- Regular nav item -->
        <div class="nav-item">
          <a href="<?= htmlspecialchars($item['href']) ?>"
             class="nav-link <?= $is_active ? 'active' : '' ?>"
             title="<?= htmlspecialchars($item['label']) ?>">
            <div class="icon-wrapper">
              <i class='bx <?= htmlspecialchars($item['icon']) ?>'></i>
            </div>
            <div class="nav-text">
              <div class="main-text"><?= htmlspecialchars($item['label']) ?></div>
              <div class="sub-text"><?= htmlspecialchars($item['sub']) ?></div>
            </div>
            <i class='bx bx-chevron-right nav-chevron'></i>
          </a>
        </div>
        <?php endif; ?>

      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="footer-card">
      <div class="footer-icon"><i class='bx bx-shield-alt-2'></i></div>
      <div class="footer-info">
        <div class="footer-title">Secure Platform</div>
        <div class="footer-status">All systems operational</div>
      </div>
      <div class="footer-version">MFS v1.0</div>
    </div>
  </div>
</aside>

<div class="page-wrapper" id="pageWrapper">
  <nav class="top-navbar" id="topNavbar">
    <div class="navbar-container">
      <div class="navbar-content">
        <div class="navbar-left">
          <button class="toggle-btn" id="toggleBtn" aria-label="Toggle sidebar"><i class='bx bx-menu'></i></button>
        </div>
        <div class="navbar-right">
          <div class="time-display" id="timeDisplay">
            <span id="currentTime"></span>
            <span class="date-separator">•</span>
            <span id="currentDate"></span>
          </div>
          <button class="icon-btn" id="searchBtn" title="Search"><i class='bx bx-search'></i></button>
          <button class="icon-btn" id="notificationBtn" title="Notifications"><i class='bx bx-bell'></i><span class="badge-dot"></span></button>
          <div class="profile-wrapper" id="profileWrapper">
            <div class="profile-avatar" id="profileBtn"><?php 
              $currentUser = getCurrentUser();
              $userName = $currentUser['first_name'] ?? 'User';
              $userInitials = substr($userName, 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1);
              echo strtoupper($userInitials ?: 'US'); 
            ?></div>
            <div class="profile-dropdown" id="profileDropdown">
              <div class="dropdown-header">
                <div class="dropdown-avatar"><?php echo strtoupper($userInitials ?: 'US'); ?></div>
                <div>
                  <div class="dropdown-name"><?php echo htmlspecialchars($currentUser['first_name'] ?? 'User') . ' ' . htmlspecialchars($currentUser['last_name'] ?? ''); ?></div>
                  <div class="dropdown-role"><?php echo htmlspecialchars($userRole); ?></div>
                </div>
              </div>
              <div class="dropdown-divider"></div>
              <a href="#" class="dropdown-item"><i class='bx bx-user'></i><span>My Profile</span></a>
              <a href="#" class="dropdown-item"><i class='bx bx-cog'></i><span>Settings</span></a>
              <div class="dropdown-divider"></div>
              <a href="#" id="logoutBtn" class="dropdown-item dropdown-logout"><i class='bx bx-log-out'></i><span>Log Out</span></a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <main class="page-content">
    <?php if (isset($page_content)) { echo $page_content; } else { ?>
      <div class="demo-notice">
        <i class='bx bx-layout'></i>
        <h2>Layout Shell — layout.php</h2>
        <p>This file provides the sidebar navigation and top navbar only.<br>
           Include or extend it in your page files to add content here.</p>
      </div>
    <?php } ?>
  </main>
</div>

<script>
(function () {
  'use strict';

  const sidebar     = document.getElementById('sidebar');
  const overlay     = document.getElementById('sidebarOverlay');
  const pageWrapper = document.getElementById('pageWrapper');
  const toggleBtn   = document.getElementById('toggleBtn');
  const profileBtn  = document.getElementById('profileBtn');
  const profileDropdown = document.getElementById('profileDropdown');
  const currentTime = document.getElementById('currentTime');
  const currentDate = document.getElementById('currentDate');

  let sidebarOpen = true;

  function isMobile() { return window.innerWidth <= 768; }

  function openSidebar() {
    sidebarOpen = true;
    sidebar.classList.remove('collapsed');
    sidebar.classList.add('open');
    if (isMobile()) {
      overlay.classList.add('visible');
      document.body.style.overflow = 'hidden';
    } else {
      pageWrapper.classList.remove('shifted');
    }
  }

  function closeSidebar() {
    sidebarOpen = false;
    sidebar.classList.add('collapsed');
    sidebar.classList.remove('open');
    overlay.classList.remove('visible');
    document.body.style.overflow = '';
    pageWrapper.classList.add('shifted');
  }

  toggleBtn.addEventListener('click', () => sidebarOpen ? closeSidebar() : openSidebar());
  overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && isMobile() && sidebarOpen) closeSidebar(); });
  window.addEventListener('resize', () => {
    if (isMobile()) {
      if (sidebarOpen) {
        overlay.classList.add('visible');
        pageWrapper.classList.remove('shifted');
        document.body.style.overflow = 'hidden';
      }
    } else {
      overlay.classList.remove('visible');
      document.body.style.overflow = '';
      if (sidebarOpen) pageWrapper.classList.remove('shifted');
      else pageWrapper.classList.add('shifted');
    }
  });
  if (isMobile()) { sidebarOpen = false; sidebar.classList.add('collapsed'); pageWrapper.classList.add('shifted'); }

  /* Profile dropdown */
  profileBtn.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('open'); });
  document.addEventListener('click', e => { if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) profileDropdown.classList.remove('open'); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') profileDropdown.classList.remove('open'); });

  /* Logout handler */
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async e => {
      e.preventDefault();
      try {
        const response = await fetch('auth.php?action=logout', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({})
        });
        const result = await response.json();
        if (response.ok) {
          window.location.href = 'client_login.php';
        } else {
          alert('Logout failed: ' + (result.message || 'Unknown error'));
        }
      } catch (error) {
        console.error('Logout error:', error);
        alert('Error during logout: ' + error.message);
      }
    });
  }

  /* Clock */
  var DAYS   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  function padZ(n) { return String(n).padStart(2,'0'); }
  function updateClock() {
    var now = new Date();
    var h12 = now.getHours() % 12 || 12;
    currentTime.textContent = padZ(h12)+':'+padZ(now.getMinutes())+':'+padZ(now.getSeconds())+' '+(now.getHours()>=12?'PM':'AM');
    currentDate.textContent = DAYS[now.getDay()]+', '+MONTHS[now.getMonth()]+' '+now.getDate()+' '+now.getFullYear();
  }
  updateClock();
  setInterval(updateClock, 1000);
})();

/* ── Nav group toggle ── */
function toggleNavGroup(trigger) {
  const isOpen = trigger.classList.contains('open');
  // Close all other open groups first
  document.querySelectorAll('.nav-link-parent.open').forEach(el => {
    if (el !== trigger) {
      el.classList.remove('open');
      el.nextElementSibling.classList.remove('open');
    }
  });
  // Toggle this one
  trigger.classList.toggle('open', !isOpen);
  trigger.nextElementSibling.classList.toggle('open', !isOpen);
}

/**
 * Global helper to signal compliance dashboard that a transaction event happened.
 * This writes to localStorage and optionally calls runProactiveComplianceCheck()
 * if available (in the currently open compliance page).
 */
function proactiveComplianceSignal() {
  try {
    localStorage.setItem('compliance_event', Date.now().toString());
  } catch (e) {
    console.warn('proactiveComplianceSignal: storage unavailable', e);
  }

  if (typeof runProactiveComplianceCheck === 'function') {
    runProactiveComplianceCheck();
  } else {
    // fire a light background fetch so compliance can refresh on its own timeline
    fetch('compliance.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'action=run_proactive_compliance_check'
    }).catch(() => {});
  }
}

/**
 * Fallback audit log event firing from client-side actions across pages.
 * Ensures page events mark activities in audit_trail even before explicit page-specific logging.
 */
function logAuditAction(actionType, tableName, recordId, oldValues, newValues) {
  try {
    const params = new URLSearchParams();
    params.append('action', 'log_action');
    params.append('action_type', (actionType || 'UNKNOWN').toUpperCase());
    params.append('table_name', tableName || 'unknown');

    if (typeof recordId !== 'undefined' && recordId !== null) {
      params.append('record_id', recordId);
    }
    if (oldValues) {
      params.append('old_values', JSON.stringify(oldValues));
    }
    if (newValues) {
      params.append('new_values', JSON.stringify(newValues));
    }

    fetch('compliance.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: params.toString()
    }).catch(err => console.warn('logAuditAction failed', err));
  } catch (e) {
    console.warn('logAuditAction exception', e);
  }
}

(function() {
  const originalFetch = window.fetch.bind(window);

  function isReadAction(action) {
    if (!action) return false;
    const readActions = ['fetch_dashboard','fetch_clients','fetch_applications','fetch_audit_trail','fetch_compliance_stats','fetch_compliance_records','run_proactive_compliance_check','log_action'];
    return readActions.includes(action.toLowerCase());
  }

  function extractAction(body) {
    if (!body) return null;
    if (typeof body === 'string') {
      const match = body.match(/action=([\w_]+)/i);
      return match ? match[1] : null;
    }
    try {
      const bodyObj = (typeof body === 'object' && body !== null) ? body : JSON.parse(body);
      return bodyObj.action || null;
    } catch (e) {
      return null;
    }
  }

  window.fetch = async function(input, init = {}) {
    const requestInfo = typeof input === 'string' ? {url: input, method: (init.method || 'GET').toUpperCase(), body: init.body} : {url: input.url, method: (input.method || (init.method || 'GET')).toUpperCase(), body: init.body || input.body};

    const response = await originalFetch(input, init);

    try {
      if (response.ok && requestInfo.method === 'POST' && requestInfo.url && requestInfo.url.endsWith('.php')) {
        const action = extractAction(requestInfo.body);
        const shouldLog = !isReadAction(action);

        if (shouldLog) {
          logAuditAction(action || 'unknown_action', new URL(requestInfo.url, window.location.href).pathname.replace(/^\//, ''), null, null, null);
          proactiveComplianceSignal();
        }
      }
    } catch (err) {
      console.warn('fetch audit wrapper failed', err);
    }

    return response;
  };

  document.addEventListener('submit', function(event) {
    const form = event.target;
    if (!form || !form.action || form.method.toLowerCase() !== 'post') return;

    // If a form posts to a server endpoint, trigger compliance refresh and a fallback audit event.
    setTimeout(function() {
      const targetUrl = form.action;
      if (targetUrl.endsWith('.php')) {
        proactiveComplianceSignal();
        logAuditAction('FORM_SUBMIT', new URL(targetUrl, window.location.href).pathname.replace(/^\//, ''), null, null, null);
      }
    }, 100);
  }, true);
})();
</script>

</body>
</html>