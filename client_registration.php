<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/init.php';

// Client Registration & KYC BPMN Workflow
// Swimlanes: Client, System (QCR/Biometric), KYC Engine, Compliance Review, Office Support

if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'client') {
    die('Access Denied: Staff role required for this page');
}

// Handle AJAX workflow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $userId = $_SESSION['user_id'] ?? null;

    try {
        $db = Database::getInstance();

        switch ($action) {
            // ===== CLIENT SWIMLANE =====
            case 'start_registration':
                // Client initiates registration
                echo json_encode(['status' => 200, 'message' => 'Registration started']);
                break;

            case 'submit_registration_form':
                // ===== DIGITAL REGISTRATION (CLIENT & SYSTEM) =====
                $firstName = trim($_REQUEST['first_name'] ?? '');
                $lastName = trim($_REQUEST['last_name'] ?? '');
                $email = trim($_REQUEST['email'] ?? '');
                $contactNumber = trim($_REQUEST['contact_number'] ?? '');
                $gender = $_REQUEST['gender'] ?? null;
                $dateOfBirth = $_REQUEST['date_of_birth'] ?? null;
                $idType = $_REQUEST['id_type'] ?? null;
                $idNumber = trim($_REQUEST['id_number'] ?? '');

                if (!$firstName || !$lastName || !$email || !$contactNumber) {
                    throw new Exception('Missing required fields');
                }

                // Store registration data in session for workflow tracking
                $_SESSION['registration_data'] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'contact_number' => $contactNumber,
                    'gender' => $gender,
                    'date_of_birth' => $dateOfBirth,
                    'id_type' => $idType,
                    'id_number' => $idNumber
                ];

                echo json_encode([
                    'status' => 200,
                    'message' => 'Digital registration form submitted',
                    'step' => 'qcr_check'
                ]);
                break;

            // ===== SYSTEM SWIMLANE - QCR & BIOMETRIC CHECKS =====
            case 'perform_qcr_biometric_check':
                $qcrStatus = $_REQUEST['qcr_status'] ?? 'passed'; // passed or failed
                $biometricStatus = $_REQUEST['biometric_status'] ?? 'verified'; // verified or failed

                if ($qcrStatus === 'failed' || $biometricStatus === 'failed') {
                    echo json_encode([
                        'status' => 400,
                        'message' => 'QCR or Biometric check failed. Please re-scan documents.',
                        'data_valid' => false
                    ]);
                } else {
                    echo json_encode([
                        'status' => 200,
                        'message' => 'QCR & Biometric Liveness Check passed',
                        'data_valid' => true,
                        'step' => 'data_validation'
                    ]);
                }
                break;

            case 'validate_registration_data':
                // ===== SYSTEM SWIMLANE - DATA VALIDATION DECISION =====
                $dataValid = $_REQUEST['data_valid'] === 'true';

                if (!$dataValid) {
                    echo json_encode([
                        'status' => 400,
                        'message' => 'Data validation failed. Please correct the information and resubmit.',
                        'action' => 'error_notify'
                    ]);
                } else {
                    echo json_encode([
                        'status' => 200,
                        'message' => 'Data validation passed',
                        'action' => 'submit_to_cts',
                        'step' => 'kyc_scoring'
                    ]);
                }
                break;

            case 'submit_for_kyc':
                // ===== SYSTEM SWIMLANE - SUBMIT & KYC INITIATION =====
                $clientId = $_REQUEST['client_id'] ?? null;

                $debugInfo = [
                    'clientId' => $clientId,
                    'session_registration_data' => $_SESSION['registration_data'] ?? null,
                    'request' => [
                        'first_name' => $_REQUEST['first_name'] ?? null,
                        'last_name' => $_REQUEST['last_name'] ?? null,
                        'email' => $_REQUEST['email'] ?? null,
                        'contact_number' => $_REQUEST['contact_number'] ?? null,
                    ],
                ];
                error_log('submit_for_kyc debug: ' . json_encode($debugInfo));

                // Try stored registration data first, then fallback to request payload.
                $data = $_SESSION['registration_data'] ?? null;
                if (!$data) {
                    $maybe = [
                        'first_name' => trim($_REQUEST['first_name'] ?? ''),
                        'last_name' => trim($_REQUEST['last_name'] ?? ''),
                        'email' => trim($_REQUEST['email'] ?? ''),
                        'contact_number' => trim($_REQUEST['contact_number'] ?? ''),
                        'gender' => $_REQUEST['gender'] ?? null,
                        'date_of_birth' => $_REQUEST['date_of_birth'] ?? null,
                    ];
                    if ($maybe['first_name'] && $maybe['last_name'] && $maybe['email'] && $maybe['contact_number']) {
                        $data = $maybe;
                    }
                }

                // Create client record if not exists
                if (!$clientId && $data) {
                    try {
                        $inserted = $db->insert('clients', [
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'email' => $data['email'],
                            'contact_number' => $data['contact_number'],
                            'gender' => $data['gender'],
                            'date_of_birth' => $data['date_of_birth'],
                            'client_status' => 'pending_kyc',
                            'kyc_status' => 'pending'
                        ]);

                        if (is_array($inserted) && isset($inserted['client_id'])) {
                            $clientId = $inserted['client_id'];
                        } else {
                            $newClient = $db->fetchOne(
                                'SELECT client_id FROM clients WHERE email = ?',
                                [$data['email']]
                            );
                            $clientId = $newClient['client_id'] ?? null;
                            if (!$clientId) {
                                throw new Exception('Could not retrieve newly created client ID');
                            }
                        }
                    } catch (Exception $e) {
                        $errorMsg = $e->getMessage();
                        if (strpos($errorMsg, '409') !== false ||
                            strpos($errorMsg, 'unique') !== false ||
                            strpos($errorMsg, 'duplicate') !== false) {
                            throw new Exception('Email address is already registered. Please use a different email address.');
                        }
                        throw $e;
                    }

                    $db->auditLog($userId, 'CREATE', 'clients', $clientId,
                        "Digital registration submitted: {$data['first_name']} {$data['last_name']}", []);
                }

                if (!$clientId) {
                    throw new Exception('Client creation failed: client_id missing after submit_for_kyc. Please verify data and retry.');
                }

                echo json_encode([
                    'status' => 200,
                    'message' => 'Registration submitted to KYC Engine',
                    'client_id' => intval($clientId),
                    'step' => 'kyc_review'
                ]);
                break;

            // ===== KYC SWIMLANE =====
            case 'run_kyc_scoring':
                // ===== KYC ENGINE - RUN BUSINESS RULES ENGINE =====
                $clientId = $_REQUEST['client_id'] ?? null;
                if (!$clientId) throw new Exception('Invalid client ID');

                // Simulate KYC scoring - can be replaced with actual risk engine
                $riskScore = rand(20, 95);
                $riskLevel = $riskScore < 50 ? 'low' : ($riskScore < 75 ? 'medium' : 'high');

                // Mark KYC status in client record (and optionally keep anywhere else)
                if ($riskLevel === 'low') {
                    $db->update('clients', [
                        'client_status' => 'active',
                        'kyc_status' => 'verified'
                    ], 'client_id = ?', [$clientId]);
                    $nextStep = 'send_success';
                } else {
                    $db->update('clients', [
                        'client_status' => 'pending_compliance_review',
                        'kyc_status' => 'pending'
                    ], 'client_id = ?', [$clientId]);
                    $nextStep = 'manual_review';
                }

                // Insert/ensure a kyc_verification record exists for audit
                $existingKyc = $db->fetchOne('SELECT * FROM kyc_verification WHERE client_id = ?', [$clientId]);
                if (!$existingKyc) {
                    $sessionKyc = $_SESSION['registration_data'] ?? [];
                    $db->insert('kyc_verification', [
                        'client_id' => $clientId,
                        'id_type' => $sessionKyc['id_type'] ?? 'unknown',
                        'id_number' => $sessionKyc['id_number'] ?? 'unknown',
                        'verification_status' => $riskLevel === 'low' ? 'verified' : 'pending'
                    ]);
                } else {
                    $db->update('kyc_verification', [
                        'verification_status' => $riskLevel === 'low' ? 'verified' : 'pending'
                    ], 'client_id = ?', [$clientId]);
                }

                $db->auditLog($userId, 'UPDATE', 'clients', $clientId,
                    "KYC Scoring completed. Risk Level: $riskLevel (Score: $riskScore)", []);

                echo json_encode([
                    'status' => 200,
                    'message' => 'KYC Business Rules Engine executed',
                    'risk_level' => $riskLevel,
                    'risk_score' => $riskScore,
                    'step' => $nextStep
                ]);
                break;

            case 'auto_activate_profile':
                // ===== KYC SWIMLANE - LOW RISK AUTO-ACTIVATION =====
                $clientId = $_REQUEST['client_id'] ?? null;
                if (!$clientId) throw new Exception('Invalid client ID');

                $db->update('clients', [
                    'client_status' => 'active',
                    'kyc_status' => 'verified'
                ], 'client_id = ?', [$clientId]);

                $db->auditLog($userId, 'UPDATE', 'clients', $clientId,
                    'Auto-Activated Profile (Low Risk KYC)', []);

                echo json_encode([
                    'status' => 200,
                    'message' => 'Profile auto-activated (Low Risk)',
                    'client_id' => $clientId,
                    'step' => 'send_success'
                ]);
                break;

            case 'escalate_to_compliance':
                // ===== KYC SWIMLANE - HIGH RISK ESCALATION =====
                $clientId = $_REQUEST['client_id'] ?? null;
                $riskLevel = $_REQUEST['risk_level'] ?? 'high';
                if (!$clientId) throw new Exception('Invalid client ID');

                $db->update('clients', [
                    'client_status' => 'pending_compliance_review'
                ], 'client_id = ?', [$clientId]);

                $db->auditLog($userId, 'UPDATE', 'clients', $clientId,
                    "Escalated to Manual KYC Review (Risk Level: $riskLevel)", []);

                echo json_encode([
                    'status' => 200,
                    'message' => 'Case escalated to Compliance for manual review',
                    'client_id' => $clientId,
                    'step' => 'manual_review'
                ]);
                break;

            // ===== COMPLIANCE SWIMLANE =====
            case 'submit_manual_kyc_review':
                // ===== COMPLIANCE - MANUAL KYC REVIEW & DECISION =====
                $clientId = $_REQUEST['client_id'] ?? null;
                $reviewerNotes = trim($_REQUEST['reviewer_notes'] ?? '');
                $approval = $_REQUEST['approval'] === 'approved';

                if (!$clientId) throw new Exception('Invalid client ID');

                if ($approval) {
                    // APPROVED PATH
                    $db->update('clients', [
                        'client_status' => 'active',
                        'kyc_status' => 'verified'
                    ], 'client_id = ?', [$clientId]);

                    // Update or insert KYC verification record
                    $existingKyc = $db->fetchOne('SELECT * FROM kyc_verification WHERE client_id = ?', [$clientId]);
                    if ($existingKyc) {
                        $db->update('kyc_verification', [
                            'verification_status' => 'verified'
                        ], 'client_id = ?', [$clientId]);
                    } else {
                        $db->insert('kyc_verification', [
                            'client_id' => $clientId,
                            'id_type' => $_SESSION['registration_data']['id_type'] ?? 'unknown',
                            'id_number' => $_SESSION['registration_data']['id_number'] ?? 'unknown',
                            'verification_status' => 'verified'
                        ]);
                    }

                    $db->auditLog($userId, 'UPDATE', 'clients', $clientId,
                        "KYC Approved by Compliance. Notes: $reviewerNotes", []);

                    echo json_encode([
                        'status' => 200,
                        'message' => 'Client approved by Compliance',
                        'client_id' => $clientId,
                        'approved' => true,
                        'step' => 'send_success'
                    ]);
                } else {
                    // REJECTED PATH
                    $db->update('clients', [
                        'client_status' => 'rejected',
                        'kyc_status' => 'rejected'
                    ], 'client_id = ?', [$clientId]);

                    $existingKyc = $db->fetchOne('SELECT * FROM kyc_verification WHERE client_id = ?', [$clientId]);
                    if ($existingKyc) {
                        $db->update('kyc_verification', [
                            'verification_status' => 'rejected'
                        ], 'client_id = ?', [$clientId]);
                    } else {
                        $db->insert('kyc_verification', [
                            'client_id' => $clientId,
                            'id_type' => $_SESSION['registration_data']['id_type'] ?? 'unknown',
                            'id_number' => $_SESSION['registration_data']['id_number'] ?? 'unknown',
                            'verification_status' => 'rejected'
                        ]);
                    }

                    $db->auditLog($userId, 'UPDATE', 'clients', $clientId,
                        "KYC Rejected by Compliance. Notes: $reviewerNotes", []);

                    echo json_encode([
                        'status' => 200,
                        'message' => 'Client rejected by Compliance',
                        'client_id' => $clientId,
                        'approved' => false,
                        'step' => 'send_rejection'
                    ]);
                }
                break;

            // ===== SYSTEM SWIMLANE - NOTIFICATIONS =====
            case 'send_success_notification':
                $clientId = $_REQUEST['client_id'] ?? null;
                if (!$clientId) throw new Exception('Invalid client ID');

                // Mark notification sent (if field exists in schema)
                try {
                    $db->update('clients', [
                        'kyc_status' => 'verified'
                    ], 'client_id = ?', [$clientId]);
                } catch (Exception $e) {
                    // If update fails, field may not exist, continue anyway
                }

                $db->auditLog($userId, 'UPDATE', 'clients', $clientId,
                    'Success notification sent to client', []);

                echo json_encode([
                    'status' => 200,
                    'message' => 'Success notification sent to client',
                    'client_id' => $clientId,
                    'step' => 'complete'
                ]);
                break;

            case 'send_rejection_notification':
                $clientId = $_REQUEST['client_id'] ?? null;
                if (!$clientId) throw new Exception('Invalid client ID');

                // Mark rejection sent (if field exists in schema)
                try {
                    $db->update('clients', [
                        'kyc_status' => 'rejected'
                    ], 'client_id = ?', [$clientId]);
                } catch (Exception $e) {
                    // If update fails, field may not exist, continue anyway
                }

                $db->auditLog($userId, 'UPDATE', 'clients', $clientId,
                    'Rejection notification sent to client', []);

                echo json_encode([
                    'status' => 200,
                    'message' => 'Rejection notification sent to client',
                    'client_id' => $clientId,
                    'step' => 'complete'
                ]);
                break;

            // ===== OFFICE SUPPORT SWIMLANE =====
            case 'archive_documents':
                $clientId = $_REQUEST['client_id'] ?? null;
                if (!$clientId) throw new Exception('Invalid client ID');

                // Document archiving would update a separate documents table
                // For now, just acknowledge the action

                echo json_encode([
                    'status' => 200,
                    'message' => 'Documents archived asynchronously'
                ]);
                break;

            case 'log_audit_trail':
                $clientId = $_REQUEST['client_id'] ?? null;
                if (!$clientId) throw new Exception('Invalid client ID');

                // Audit trail is logged by auditLog() calls above
                echo json_encode([
                    'status' => 200,
                    'message' => 'Audit trail logged'
                ]);
                break;

            case 'fetch_clients':
                // Client list from Supabase clients table
                $orderQuery = 'SELECT * FROM clients ORDER BY registration_date DESC LIMIT 100';
                try {
                    $clients = $db->fetchAll($orderQuery);
                } catch (Exception $e) {
                    // fallback if registration_date absent
                    $clients = $db->fetchAll('SELECT * FROM clients ORDER BY created_at DESC LIMIT 100');
                }

                echo json_encode([
                    'status' => 200,
                    'clients' => $clients
                ]);
                break;

            case 'fetch_kyc_records':
                // KYC records from clients + latest kyc_verification row
                $kycRecords = [];
                try {
                    $kycRecords = $db->fetchAll('SELECT c.client_id AS id, c.first_name, c.last_name, c.client_status, c.kyc_status, c.registration_date, kv.id_type, kv.id_number, kv.verification_status FROM clients c LEFT JOIN kyc_verification kv ON c.client_id = kv.client_id ORDER BY c.registration_date DESC LIMIT 100');
                } catch (Exception $e) {
                    $kycRecords = [];
                }

                echo json_encode([
                    'status' => 200,
                    'kyc_records' => $kycRecords
                ]);
                break;

            default:
                throw new Exception('Unknown action');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 500, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch dashboard data
$clients = [];
$pendingKyc = [];
$activeClients = [];
$rejectedClients = [];

$db = Database::getInstance();
try {
    // Use stable ordering field; fall back to created_at or no order if registration_date is not available.
    $orderQuery = 'SELECT * FROM clients ORDER BY registration_date DESC LIMIT 100';
    try {
        $clients = $db->fetchAll($orderQuery);
    } catch (Exception $e) {
        // Table might not have registration_date; retry with created_at or without ORDER BY
        error_log('client_registration: registration_date missing, fallback: '.$e->getMessage());
        try {
            $clients = $db->fetchAll('SELECT * FROM clients ORDER BY created_at DESC LIMIT 100');
        } catch (Exception $e2) {
            error_log('client_registration: created_at missing, fallback: '.$e2->getMessage());
            $clients = $db->fetchAll('SELECT * FROM clients LIMIT 100');
        }
    }

    foreach ($clients as $client) {
        $status = strtolower($client['client_status'] ?? 'pending');
        if ($status === 'active') $activeClients[] = $client;
        elseif ($status === 'pending_kyc' || $status === 'pending_compliance_review') $pendingKyc[] = $client;
        elseif ($status === 'rejected') $rejectedClients[] = $client;
    }
} catch (Exception $e) {
    $clients = [];
}

$kyc_records = [];
try {
    $kyc_records = $db->fetchAll(
        'SELECT c.*, k.* FROM clients c LEFT JOIN kyc_verification k ON c.client_id = k.client_id ORDER BY c.registration_date DESC LIMIT 100'
    );
} catch (Exception $e) {
    $kyc_records = [];
}
?>

<?php
$page_content = <<<HTML
<style>
:root {
  --blue-500: #3B82F6;
  --blue-600: #2563EB;
  --blue-700: #1E40AF;
  --bg: #F0F4FF;
  --surface: #FFFFFF;
  --border: rgba(59,130,246,0.14);
  --text-900: #0F1E4A;
  --text-600: #4B5E8A;
  --text-400: #8EA0C4;
  --green: #22c55e;
  --orange: #f97316;
  --teal: #14b8a6;
  --red: #ef4444;
  --icon-blue: rgba(59,130,246,0.12);
  --icon-green: rgba(34,197,94,0.12);
  --icon-orange: rgba(249,115,22,0.12);
  --icon-teal: rgba(20,184,166,0.12);
  --radius: 14px;
  --card-shadow: 0 2px 16px rgba(59,130,246,0.08);
}

.page-content { padding: 1rem; }

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
.stat-icon.blue { background: var(--icon-blue); color: var(--blue-500); }
.stat-icon.green { background: var(--icon-green); color: #16a34a; }
.stat-icon.orange { background: var(--icon-orange); color: #c2410c; }
.stat-icon.teal { background: var(--icon-teal); color: #0f766e; }
.stat-icon i { font-size: 22px; }

.stat-info { flex: 1; min-width: 0; }
.stat-label { font-size: .75rem; font-weight: 500; color: var(--text-400); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; }
.stat-value { font-family: 'Space Grotesk', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--text-900); line-height: 1; }
.stat-sub { font-size: .75rem; color: var(--text-400); margin-top: 3px; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--card-shadow); margin-bottom: 1.5rem; overflow: hidden; }

.tab-bar { display: flex; border-bottom: 1px solid var(--border); background: rgba(240, 244, 255, 0.5); }
.tab-btn { flex: 1; padding: 1rem; background: none; border: none; color: var(--text-400); font-weight: 600; cursor: pointer; border-bottom: 3px solid transparent; transition: all .2s; }
.tab-btn.active { color: var(--blue-600); border-bottom-color: var(--blue-600); background: rgba(59,130,246,0.05); }

.tab-pane { display: none; }
.tab-pane.active { display: block; animation: fadeIn .3s ease; }

.card-header { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); }
.card-title { font-family: 'Space Grotesk', sans-serif; font-size: .95rem; font-weight: 600; color: var(--text-900); display: flex; align-items: center; gap: 8px; margin: 0; }
.card-title i { font-size: 18px; color: var(--blue-500); }
.card-actions { display: flex; gap: 10px; }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: rgba(240, 244, 255, .8); }
th { padding: .7rem 1.1rem; text-align: left; font-size: .7rem; font-weight: 700; color: var(--text-400); text-transform: uppercase; letter-spacing: .08em; border-bottom: 1px solid var(--border); white-space: nowrap; }
td { padding: .78rem 1.1rem; font-size: .83rem; color: var(--text-600); border-bottom: 1px solid rgba(59,130,246,.06); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: rgba(240, 244, 255, .5); }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; transition: all .2s; }
.btn-primary { background: var(--blue-600); color: #fff; }
.btn-primary:hover { background: var(--blue-700); box-shadow: 0 3px 12px rgba(37,99,235,.35); }
.btn-outline { background: transparent; color: var(--blue-600); border: 1px solid rgba(59,130,246,.35); }
.btn-outline:hover { background: rgba(59,130,246,.06); }
.btn-sm { padding: 5px 11px; font-size: .78rem; }
.btn i { font-size: 15px; }

.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; letter-spacing: .03em; }
.badge-green { background: rgba(34,197,94,.1); color: #16a34a; }
.badge-orange { background: rgba(249,115,22,.1); color: #c2410c; }
.badge-red { background: rgba(239,68,68,.1); color: #991b1b; }
.badge-blue { background: rgba(59,130,246,.1); color: var(--blue-600); }

.action-btn { width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--border); background: var(--bg); color: var(--text-400); transition: all .15s; }
.action-btn:hover { border-color: var(--blue-500); color: var(--blue-500); background: rgba(59,130,246,.06); }
.action-btn i { font-size: 14px; }

.empty-state { padding: 2rem; text-align: center; color: var(--text-400); }
.empty-state i { font-size: 48px; opacity: 0.3; margin-bottom: 1rem; display: block; }

.modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,.5); }
.modal.show { display: flex; align-items: center; justify-content: center; }
.modal-content { background-color: var(--surface); padding: 2rem; border-radius: var(--radius); width: 90%; max-width: 550px; box-shadow: 0 8px 32px rgba(0,0,0,.2); max-height: 85vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.modal-header h2 { font-size: 1.25rem; font-weight: 700; color: var(--text-900); margin: 0; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 24px; color: var(--text-400); transition: all .2s; }
.modal-close:hover { color: var(--text-900); }
.modal-body { margin-bottom: 1.5rem; }
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; font-size: .85rem; font-weight: 600; color: var(--text-900); margin-bottom: 0.5rem; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.65rem 0.9rem; border: 1px solid var(--border); border-radius: 8px; font-size: .9rem; color: var(--text-900); background: var(--surface); transition: all .2s; font-family: 'DM Sans', sans-serif; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--blue-600); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.form-group textarea { resize: vertical; min-height: 80px; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
.btn-cancel { background: transparent; border: 1px solid var(--border); color: var(--text-600); }
.btn-cancel:hover { background: rgba(59,130,246,.06); }

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 768px) {
  .page-header { flex-direction: column; }
  .page-header-actions { margin-left: 0; width: 100%; }
  .stat-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Page Content -->
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-user-plus'></i></div>
    <div class="page-header-text">
      <h2>Client Registration &amp; KYC Verification</h2>
      <p>Complete BPMN workflow: Digital Registration &rarr; QCR/Biometric &rarr; KYC Scoring &rarr; Compliance Review</p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary btn-sm" onclick="openModal('registrationModal')"><i class='bx bx-user-plus'></i> New Registration</button>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-group'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Clients</div>
        <div class="stat-value" id="totalClientsCount">0</div>
        <div class="stat-sub">All registered</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-user-check'></i></div>
      <div class="stat-info">
        <div class="stat-label">Active Clients</div>
        <div class="stat-value" id="activeClientsCount">0</div>
        <div class="stat-sub">KYC verified &amp; active</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-time'></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending KYC</div>
        <div class="stat-value" id="pendingKycCount">0</div>
        <div class="stat-sub">Under review</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-x-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Rejected</div>
        <div class="stat-value" id="rejectedCount">0</div>
        <div class="stat-sub">Failed verification</div>
      </div>
    </div>
  </div>

  <!-- Tabs Card -->
  <div class="card">
    <div class="tab-bar">
      <button class="tab-btn active" onclick="switchTab(event, 'tab-clients')">Clients</button>
      <button class="tab-btn" onclick="switchTab(event, 'tab-kyc')">KYC Reviews</button>
    </div>

    <!-- Clients Tab -->
    <div id="tab-clients" class="tab-pane active">
      <div class="card-header" style="border-top: none;">
        <div class="card-title"><i class='bx bx-user'></i> Client List</div>
        <div class="card-actions">
          <button class="btn btn-primary btn-sm" onclick="openModal('registrationModal')"><i class='bx bx-user-plus'></i> Add Client</button>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact</th>
              <th>Status</th>
              <th>KYC Status</th>
              <th>Risk Level</th>
              <th>Registration Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="clientsTableBody">
            <tr>
              <td colspan="7">
                <div class="empty-state"><i class='bx bx-user-x'></i><p>No clients registered yet.</p></div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- KYC Reviews Tab -->
    <div id="tab-kyc" class="tab-pane">
      <div class="card-header" style="border-top: none;">
        <div class="card-title"><i class='bx bx-id-card'></i> KYC Reviews</div>
        <div class="card-actions">
          <button class="btn btn-primary btn-sm" onclick="filterPendingReviews()"><i class='bx bx-filter'></i> Pending Reviews</button>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Client</th>
              <th>Risk Level</th>
              <th>Risk Score</th>
              <th>Status</th>
              <th>Reviewed By</th>
              <th>Review Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="kycTableBody">
            <tr>
              <td colspan="7">
                <div class="empty-state"><i class='bx bx-id-card'></i><p>No KYC records found.</p></div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- Digital Registration Modal -->
<div id="registrationModal" class="modal">
  <div class="modal-content" style="max-width: 520px;">
    <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--blue-600), var(--blue-500)); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
          <i class='bx bx-user-plus'></i>
        </div>
        <div>
          <h2 style="margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-900);">Register New Client</h2>
          <p style="margin: 2px 0 0 0; font-size: 0.8rem; color: var(--text-400);">Complete the registration form to onboard a new client</p>
        </div>
      </div>
      <button class="modal-close" onclick="closeModal('registrationModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem;">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
        <div class="form-group">
          <label style="font-weight: 500; color: var(--text-900); font-size: 0.85rem;">First Name *</label>
          <input type="text" id="firstName" placeholder="John" style="margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; width: 100%; transition: all 0.2s;" />
        </div>
        <div class="form-group">
          <label style="font-weight: 500; color: var(--text-900); font-size: 0.85rem;">Last Name *</label>
          <input type="text" id="lastName" placeholder="Doe" style="margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; width: 100%; transition: all 0.2s;" />
        </div>
      </div>
      
      <div class="form-group" style="margin-bottom: 1rem;">
        <label style="font-weight: 500; color: var(--text-900); font-size: 0.85rem;">Email Address *</label>
        <input type="email" id="email" placeholder="john.doe@example.com" style="margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; width: 100%; transition: all 0.2s;" />
      </div>

      <div class="form-group" style="margin-bottom: 1rem;">
        <label style="font-weight: 500; color: var(--text-900); font-size: 0.85rem;">Contact Number *</label>
        <input type="tel" id="contactNumber" placeholder="+63 9XX XXX XXXX" style="margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; width: 100%; transition: all 0.2s;" />
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
        <div class="form-group">
          <label style="font-weight: 500; color: var(--text-900); font-size: 0.85rem;">Gender</label>
          <select id="gender" style="margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; width: 100%; background: white; cursor: pointer; transition: all 0.2s;">
            <option value="">Select</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label style="font-weight: 500; color: var(--text-900); font-size: 0.85rem;">Date of Birth</label>
          <input type="date" id="dateOfBirth" style="margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; width: 100%; transition: all 0.2s;" />
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label style="font-weight: 500; color: var(--text-900); font-size: 0.85rem;">ID Type</label>
          <select id="idType" style="margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; width: 100%; background: white; cursor: pointer; transition: all 0.2s;">
            <option value="">Select Type</option>
            <option value="passport">Passport</option>
            <option value="national_id">National ID</option>
            <option value="drivers_license">Driver's License</option>
            <option value="ss">Social Security</option>
          </select>
        </div>
        <div class="form-group">
          <label style="font-weight: 500; color: var(--text-900); font-size: 0.85rem;">ID Number</label>
          <input type="text" id="idNumber" placeholder="ID-1234567890" style="margin-top: 0.4rem; padding: 0.65rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; width: 100%; transition: all 0.2s;" />
        </div>
      </div>
    </div>
    <div class="modal-footer" style="border-top: 1px solid var(--border); padding: 1.25rem 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end;">
      <button class="btn btn-cancel btn-sm" onclick="closeModal('registrationModal')" style="padding: 0.6rem 1.2rem; border: 1px solid var(--border); background: var(--surface); color: var(--text-900); border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 0.85rem; transition: all 0.2s;">Cancel</button>
      <button class="btn btn-primary btn-sm" onclick="submitRegistration()" style="padding: 0.6rem 1.2rem; background: linear-gradient(135deg, var(--blue-600), var(--blue-500)); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; font-size: 0.85rem; transition: all 0.2s; box-shadow: 0 4px 12px rgba(59,130,246,0.3);">Register Client</button>
    </div>
  </div>
</div>

<!-- KYC Review Modal -->
<div id="kycReviewModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Manual KYC Review</h2>
      <button class="modal-close" onclick="closeModal('kycReviewModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background: rgba(59,130,246,0.05); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <p style="margin: 0; font-size: 0.9rem;">
          <strong>Client:</strong> <span id="reviewClientName"></span><br>
          <strong>Risk Level:</strong> <span id="reviewRiskLevel" style="padding: 2px 8px; border-radius: 4px;"></span><br>
          <strong>Risk Score:</strong> <span id="reviewRiskScore"></span>
        </p>
      </div>
      <div class="form-group">
        <label>Review Notes</label>
        <textarea id="reviewNotes" placeholder="Enter your review findings and recommendations..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-cancel btn-sm" onclick="closeModal('kycReviewModal')">Cancel</button>
      <button class="btn btn-primary btn-sm" style="background: #22c55e;" onclick="submitKycReview('approved')"><i class='bx bx-check'></i> Approve</button>
      <button class="btn btn-primary btn-sm" style="background: #ef4444;" onclick="submitKycReview('rejected')"><i class='bx bx-x'></i> Reject</button>
    </div>
  </div>
</div>

<script>
  const clientsData = [
HTML;

if (!empty($clients)) {
    $clientsJSON = [];
    foreach ($clients as $client) {
        $clientsJSON[] = [
            'id' => intval($client['client_id'] ?? 0),
            'firstName' => htmlspecialchars($client['first_name'] ?? ''),
            'lastName' => htmlspecialchars($client['last_name'] ?? ''),
            'email' => htmlspecialchars($client['email'] ?? ''),
            'phone' => htmlspecialchars($client['contact_number'] ?? ''),
            'status' => htmlspecialchars($client['client_status'] ?? 'pending_kyc'),
            'kycStatus' => htmlspecialchars($client['kyc_status'] ?? 'pending'),
            'riskLevel' => htmlspecialchars($client['kyc_risk_level'] ?? 'N/A'),
            'riskScore' => intval($client['kyc_risk_score'] ?? 0),
            'registrationDate' => htmlspecialchars($client['registration_date'] ?? date('Y-m-d'))
        ];
    }
    $page_content .= json_encode($clientsJSON);
}

$page_content .= <<<'HTML'
  ];

  console.log('clientsData loaded:', clientsData);

  function loadClients() {
    const formData = new URLSearchParams();
    formData.append('action', 'fetch_clients');

    return fetch('client_registration.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.status === 200 && Array.isArray(data.clients)) {
          clientsData.length = 0; // preserve reference for render
          data.clients.forEach(client => {
            clientsData.push({
              id: parseInt(client.client_id || client.id || 0, 10),
              firstName: client.first_name || client.firstName || '',
              lastName: client.last_name || client.lastName || '',
              email: client.email || client.email_address || '',
              phone: client.contact_number || client.phone || client.phone_number || '',
              status: client.client_status || client.status || 'pending_kyc',
              kycStatus: client.kyc_status || client.kycStatus || 'pending',
              riskLevel: client.kyc_risk_level || client.riskLevel || 'N/A',
              riskScore: parseInt(client.kyc_risk_score || client.riskScore || 0, 10),
              registrationDate: client.registration_date || client.registrationDate || ''
            });
          });
          renderClients();
        } else {
          console.warn('fetch_clients returned no clients or invalid format', data);
        }
      })
      .catch(err => console.error('Error Loading Clients From Supabase:', err));
  }

  function loadKycRecords() {
    const formData = new URLSearchParams();
    formData.append('action', 'fetch_kyc_records');

    return fetch('client_registration.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.status === 200 && Array.isArray(data.kyc_records) && data.kyc_records.length > 0) {
          kycData = data.kyc_records.map(record => ({
            id: parseInt(record.id || record.client_id || 0, 10),
            name: (record.first_name || record.last_name) ? `${record.first_name || ''} ${record.last_name || ''}`.trim() : (record.name || 'Unknown Client'),
            riskLevel: record.kyc_risk_level || record.risk_level || record.riskLevel || 'pending',
            riskScore: parseInt(record.kyc_risk_score || record.risk_score || record.riskScore || 0, 10),
            status: record.client_status || record.kyc_status || record.status || 'pending_kyc',
            reviewedBy: record.kyc_verified_by || record.reviewedBy || 'N/A',
            reviewDate: record.kyc_approval_date || record.reviewDate || record.registration_date || ''
          }));
          console.log('KyC data loaded from Supabase:', kycData);
          renderKycRecords();
        } else {
          console.warn('fetch_kyc_records returned no records or invalid format', data);

          // fallback: build KYC records from clientsData if none exist
          if (Array.isArray(clientsData) && clientsData.length > 0) {
            kycData = clientsData.map(client => ({
              id: client.id || 0,
              name: `${client.firstName || client.first_name || 'Unknown'} ${client.lastName || client.last_name || ''}`.trim(),
              riskLevel: client.kycStatus === 'verified' ? 'low' : 'medium',
              riskScore: client.kycStatus === 'verified' ? 25 : 60,
              status: client.status || client.client_status || 'pending_kyc',
              reviewedBy: 'Auto',
              reviewDate: client.registrationDate || client.registration_date || ''
            }));
            console.log('Falling back to clientsData for KYC list:', kycData);
            renderKycRecords();
          }
        }
      })
      .catch(err => {
        console.error('Error loading KYC records from Supabase:', err);

        // fallback to local client data if fetch fails
        if (Array.isArray(clientsData) && clientsData.length > 0) {
          kycData = clientsData.map(client => ({
            id: client.id || 0,
            name: `${client.firstName || client.first_name || 'Unknown'} ${client.lastName || client.last_name || ''}`.trim(),
            riskLevel: client.kycStatus === 'verified' ? 'low' : 'medium',
            riskScore: client.kycStatus === 'verified' ? 25 : 60,
            status: client.status || client.client_status || 'pending_kyc',
            reviewedBy: 'Auto',
            reviewDate: client.registrationDate || client.registration_date || ''
          }));
          renderKycRecords();
        }
      });
  }

  let kycData = [
HTML;

if (!empty($kyc_records)) {
    $kycJSON = [];
    foreach ($kyc_records as $record) {
        $kycJSON[] = [
            'id' => intval($record['client_id'] ?? 0),
            'name' => htmlspecialchars(($record['first_name'] ?? 'Unknown') . ' ' . ($record['last_name'] ?? 'Client')),
            'riskLevel' => htmlspecialchars($record['kyc_risk_level'] ?? 'pending'),
            'riskScore' => intval($record['kyc_risk_score'] ?? 0),
            'status' => htmlspecialchars($record['client_status'] ?? 'pending_kyc'),
            'reviewedBy' => htmlspecialchars($record['kyc_verified_by'] ?? 'N/A'),
            'reviewDate' => htmlspecialchars($record['kyc_approval_date'] ?? '')
        ];
    }
    $page_content .= json_encode($kycJSON);
}

$page_content .= <<<'HTML'
  ];

  function renderClients() {
    const tbody = document.getElementById('clientsTableBody');
    if (!clientsData || clientsData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="bx bx-user-x"></i><p>No clients registered yet.</p></div></td></tr>';
      updateStatCards(0, 0, 0, 0);
      return;
    }

    let html = '', totalClients = clientsData.length, active = 0, pending = 0, rejected = 0;

    for (const client of clientsData) {
      const firstName = client.firstName || client.first_name || '';
      const lastName = client.lastName || client.last_name || '';
      const email = client.email || client.email_address || '';
      const phone = client.phone || client.contact_number || client.phone_number || '';
      const status = (client.status || client.client_status || 'pending_kyc').toString();
      const kycStatus = client.kycStatus || client.kyc_status || 'pending';
      const riskLevel = client.riskLevel || client.kyc_risk_level || 'N/A';
      const riskScore = (client.riskScore != null ? client.riskScore : client.kyc_risk_score) || 0;
      const registrationDate = client.registrationDate || client.registration_date || '';

      const statusBadge = getStatusBadge(status);
      const kycBadge = kycStatus === 'verified' || kycStatus === 'active' ? 'badge-green' : (kycStatus === 'rejected' ? 'badge-red' : 'badge-orange');
      const riskBadge = riskLevel === 'low' ? 'badge-green' : (riskLevel === 'high' ? 'badge-red' : 'badge-orange');

      if (status === 'active') active++;
      else if (status.includes('pending')) pending++;
      else if (status === 'rejected') rejected++;

      const normalizedStatus = status.replace(/_/g, ' ');
      const fullName = (firstName || lastName) ? `${firstName} ${lastName}`.trim() : 'Unknown Client';
      const contactInfo = (phone || email)
        ? `${phone || 'N/A'}${phone && email ? '<br>' : ''}<span style="font-size: 0.75rem; color: var(--text-400);">${email || 'N/A'}</span>`
        : '<span style="font-size: 0.75rem; color: var(--text-400);">N/A</span>';

      console.log('renderClients row:', { id: client.id, firstName, lastName, fullName, email, phone, status, kycStatus, riskLevel, registrationDate });

      html += `<tr>
        <td><strong>${fullName}</strong></td>
        <td>${contactInfo}</td>
        <td><span class="badge ${statusBadge}">${normalizedStatus || 'pending kyc'}</span></td>
        <td><span class="badge ${kycBadge}">${kycStatus}</span></td>
        <td><span class="badge ${riskBadge}">${riskLevel}</span></td>
        <td>${registrationDate}</td>
        <td><button class="action-btn" title="Details" onclick="viewClient(${client.id})"><i class='bx bx-show'></i></button></td>
      </tr>`;
    }

    tbody.innerHTML = html;
    updateStatCards(totalClients, active, pending, rejected);
  }

  function renderKycRecords() {
    const tbody = document.getElementById('kycTableBody');
    if (!kycData || kycData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="bx bx-id-card"></i><p>No KYC records found.</p></div></td></tr>';
      return;
    }

    let html = '';
    for (const record of kycData) {
      const riskBadge = record.riskLevel === 'low' ? 'badge-green' : (record.riskLevel === 'high' ? 'badge-red' : 'badge-orange');
      const statusBadge = record.status === 'active' ? 'badge-green' : (record.status === 'rejected' ? 'badge-red' : 'badge-orange');

      html += `<tr>
        <td><strong>${record.name}</strong></td>
        <td><span class="badge ${riskBadge}">${record.riskLevel}</span></td>
        <td>${record.riskScore}</td>
        <td><span class="badge ${statusBadge}">${record.status.replace(/_/g, ' ')}</span></td>
        <td>${record.reviewedBy}</td>
        <td>${record.reviewDate || 'Pending'}</td>
        <td>${record.status === 'pending_kyc' || record.status === 'pending_compliance_review' ? 
          `<button class="action-btn" title="Review" onclick="openKycReview(${record.id}, '${record.name}', '${record.riskLevel}', ${record.riskScore})"><i class='bx bx-pencil'></i></button>` : 
          `<button class="action-btn" title="View" onclick="viewClient(${record.id})"><i class='bx bx-show'></i></button>`}</td>
      </tr>`;
    }
    tbody.innerHTML = html;
  }

  function updateStatCards(total, active, pending, rejected) {
    document.getElementById('totalClientsCount').textContent = total;
    document.getElementById('activeClientsCount').textContent = active;
    document.getElementById('pendingKycCount').textContent = pending;
    document.getElementById('rejectedCount').textContent = rejected;
  }

  function getStatusBadge(status) {
    const map = {
      'active': 'badge-green',
      'pending_kyc': 'badge-blue',
      'pending_compliance_review': 'badge-orange',
      'rejected': 'badge-red'
    };
    return map[status] || 'badge-blue';
  }

  function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
  }

  function switchTab(event, tabName) {
    const tabs = document.querySelectorAll('.tab-pane');
    const btns = document.querySelectorAll('.tab-btn');
    tabs.forEach(t => t.classList.remove('active'));
    btns.forEach(b => b.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
  }

  function submitRegistration() {
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const email = document.getElementById('email').value.trim();
    const contactNumber = document.getElementById('contactNumber').value.trim();
    const gender = document.getElementById('gender').value;
    const dateOfBirth = document.getElementById('dateOfBirth').value;
    const idType = document.getElementById('idType').value;
    const idNumber = document.getElementById('idNumber').value.trim();

    // Keep registration data for submit_for_kyc fallback when sessions are not shared in fetch
    window.registrationPayload = {
      first_name: firstName,
      last_name: lastName,
      email: email,
      contact_number: contactNumber,
      gender: gender,
      date_of_birth: dateOfBirth,
      id_type: idType,
      id_number: idNumber,
    };

    if (!firstName || !lastName || !email || !contactNumber) {
      alert('Please fill in all required fields (marked with *)');
      return;
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      alert('Please enter a valid email address');
      return;
    }

    const formData = new FormData();
    formData.append('action', 'submit_registration_form');
    formData.append('first_name', firstName);
    formData.append('last_name', lastName);
    formData.append('email', email);
    formData.append('contact_number', contactNumber);
    formData.append('gender', gender);
    formData.append('date_of_birth', dateOfBirth);
    formData.append('id_type', idType);
    formData.append('id_number', idNumber);

    // Step 1: Submit registration form
    fetch('client_registration.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
    .then(r => {
      if (!r.ok) throw new Error('Network error: ' + r.status);
      return r.json();
    })
    .then(data => {
      console.log('Step 1 - Submit Form:', data);
      if (data.status !== 200) throw new Error(data.message);
      return performQcrCheck();
    })
    // Step 2: QCR Check
    .then((result) => {
      console.log('Step 2 - QCR Check:', result);
      return result;
    })
    // Step 3: Completion
    .then(() => {
      alert('✓ Client registered successfully!');
      closeModal('registrationModal');
      // Clear form fields
      document.getElementById('firstName').value = '';
      document.getElementById('lastName').value = '';
      document.getElementById('email').value = '';
      document.getElementById('contactNumber').value = '';
      document.getElementById('gender').value = '';
      document.getElementById('dateOfBirth').value = '';
      document.getElementById('idType').value = '';
      document.getElementById('idNumber').value = '';
      // Wait 1 second then hard reload
      setTimeout(() => {
        window.location.reload(true);
      }, 1000);
    })
    .catch(err => {
      console.error('Registration Error:', err);
      alert('❌ Error: ' + err.message);
    });
  }

  function performQcrCheck() {
    const formData = new FormData();
    formData.append('action', 'perform_qcr_biometric_check');
    formData.append('qcr_status', 'passed');
    formData.append('biometric_status', 'verified');

    return fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(r => {
        if (!r.ok) throw new Error('QCR failed: ' + r.status);
        return r.json();
      })
      .then(data => {
        console.log('QCR Response:', data);
        if (!data.data_valid) throw new Error(data.message || 'QCR validation failed');
        return validateRegistrationData();
      });
  }

  function validateRegistrationData() {
    const formData = new FormData();
    formData.append('action', 'validate_registration_data');
    formData.append('data_valid', 'true');

    return fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(r => {
        if (!r.ok) throw new Error('Validation failed: ' + r.status);
        return r.json();
      })
      .then(data => {
        console.log('Validation Response:', data);
        if (data.status !== 200) throw new Error(data.message);
        return submitForKyc();
      });
  }

  function submitForKyc() {
    const formData = new FormData();
    formData.append('action', 'submit_for_kyc');

    // Pass registration payload for non-session authoring
    if (window.registrationPayload) {
      for (const [key, value] of Object.entries(window.registrationPayload)) {
        formData.append(key, value);
      }
    }

    return fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(async r => {
        const data = await r.json().catch(()=>({}));
        if (!r.ok) {
          const msg = data.message || data.error || data.statusText || 'Unknown error';
          throw new Error('Submit for KYC failed: ' + r.status + ' - ' + msg);
        }
        return data;
      })
      .then(data => {
        console.log('Submit for KYC Response:', data);
        if (data.status !== 200) throw new Error(data.message || 'Submit for KYC returned non-200 status');
        const clientId = parseInt(data.client_id, 10);
        if (!clientId || clientId <= 0) {
          throw new Error('Submit for KYC failed: missing client_id');
        }
        return runKycScoring(clientId);
      });
  }

  function runKycScoring(clientId) {
    const formData = new FormData();
    formData.append('action', 'run_kyc_scoring');
    formData.append('client_id', clientId);

    return fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(r => {
        if (!r.ok) throw new Error('KYC scoring failed: ' + r.status);
        return r.json();
      })
      .then(data => {
        console.log('KYC Scoring Response:', data);
        if (data.status !== 200) throw new Error(data.message);
        
        // Decision: KYC Risk Level
        if (data.risk_level === 'low') {
          return autoActivateProfile(clientId);
        } else {
          return escalateToCompliance(clientId, data.risk_level);
        }
      });
  }

  function autoActivateProfile(clientId) {
    const formData = new FormData();
    formData.append('action', 'auto_activate_profile');
    formData.append('client_id', clientId);

    return fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(r => {
        if (!r.ok) throw new Error('Auto-activation failed: ' + r.status);
        return r.json();
      })
      .then(data => {
        console.log('Auto-activation Response:', data);
        if (data.status !== 200) throw new Error(data.message);
        return sendSuccessNotification(clientId);
      });
  }

  function escalateToCompliance(clientId, riskLevel) {
    const formData = new FormData();
    formData.append('action', 'escalate_to_compliance');
    formData.append('client_id', clientId);
    formData.append('risk_level', riskLevel);

    return fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(r => {
        if (!r.ok) throw new Error('Escalation failed: ' + r.status);
        return r.json();
      })
      .then(data => {
        console.log('Escalation Response:', data);
        console.log('Client escalated to compliance review');
        return Promise.resolve(data);
      });
  }

  function sendSuccessNotification(clientId) {
    const formData = new FormData();
    formData.append('action', 'send_success_notification');
    formData.append('client_id', clientId);

    return fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(r => {
        if (!r.ok) throw new Error('Notification failed: ' + r.status);
        return r.json();
      })
      .then(data => {
        console.log('Success Notification Response:', data);
        return Promise.resolve(data);
      });
  }

  function openKycReview(clientId, clientName, riskLevel, riskScore) {
    document.getElementById('reviewClientName').textContent = clientName;
    document.getElementById('reviewRiskScore').textContent = riskScore;
    const riskBadge = document.getElementById('reviewRiskLevel');
    riskBadge.textContent = riskLevel.toUpperCase();
    riskBadge.style.padding = '2px 8px';
    riskBadge.style.borderRadius = '4px';
    riskBadge.style.background = riskLevel === 'low' ? 'rgba(34,197,94,.1)' : (riskLevel === 'high' ? 'rgba(239,68,68,.1)' : 'rgba(249,115,22,.1)');
    riskBadge.style.color = riskLevel === 'low' ? '#16a34a' : (riskLevel === 'high' ? '#991b1b' : '#c2410c');
    
    document.getElementById('currentReviewClientId').value = clientId;
    openModal('kycReviewModal');
  }

  function submitKycReview(approval) {
    const clientId = document.getElementById('currentReviewClientId').value;
    const notes = document.getElementById('reviewNotes').value.trim();

    const formData = new FormData();
    formData.append('action', 'submit_manual_kyc_review');
    formData.append('client_id', clientId);
    formData.append('reviewer_notes', notes);
    formData.append('approval', approval === 'approved' ? 'approved' : 'rejected');

    fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.status === 200) {
          if (data.approved) {
            return sendSuccessNotification(clientId);
          } else {
            return sendRejectionNotification(clientId);
          }
        } else {
          throw new Error(data.message);
        }
      })
      .then(() => {
        alert('KYC Review submitted successfully!');
        closeModal('kycReviewModal');
        location.reload();
      })
      .catch(err => alert('Error: ' + err.message));
  }

  function sendRejectionNotification(clientId) {
    const formData = new FormData();
    formData.append('action', 'send_rejection_notification');
    formData.append('client_id', clientId);

    return fetch('client_registration.php', { method: 'POST', credentials: 'same-origin', body: formData })
      .then(r => r.json());
  }

  function viewClient(clientId) {
    const client = clientsData.find(c => c.id === clientId);
    if (!client) {
      alert('Client not found');
      return;
    }

    const firstName = client.firstName || client.first_name || 'N/A';
    const lastName = client.lastName || client.last_name || 'N/A';
    const email = client.email || client.email_address || 'N/A';
    const phone = client.phone || client.contact_number || client.phone_number || 'N/A';
    const status = client.status || client.client_status || 'pending_kyc';
    const kycStatus = client.kycStatus || client.kyc_status || 'pending';

    alert(`Client: ${firstName} ${lastName}\nEmail: ${email}\nContact: ${phone}\nStatus: ${status}\nKYC: ${kycStatus}`);
  }

  function filterPendingReviews() {
    const tbody = document.getElementById('kycTableBody');
    const pending = kycData.filter(r => r.status === 'pending_kyc' || r.status === 'pending_compliance_review');
    
    if (pending.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">No pending reviews.</div></td></tr>';
      return;
    }

    let html = '';
    for (const record of pending) {
      const riskBadge = record.riskLevel === 'low' ? 'badge-green' : (record.riskLevel === 'high' ? 'badge-red' : 'badge-orange');
      const statusBadge = record.status === 'active' ? 'badge-green' : 'badge-orange';

      html += `<tr>
        <td><strong>${record.name}</strong></td>
        <td><span class="badge ${riskBadge}">${record.riskLevel}</span></td>
        <td>${record.riskScore}</td>
        <td><span class="badge ${statusBadge}">${record.status.replace(/_/g, ' ')}</span></td>
        <td>${record.reviewedBy}</td>
        <td>${record.reviewDate || 'Pending'}</td>
        <td><button class="action-btn" onclick="openKycReview(${record.id}, '${record.name}', '${record.riskLevel}', ${record.riskScore})"><i class='bx bx-pencil'></i></button></td>
      </tr>`;
    }
    tbody.innerHTML = html;
  }

  // Hidden input for current review client
  document.body.insertAdjacentHTML('beforeend', '<input type="hidden" id="currentReviewClientId" />');

  // Initial render from preloaded data
  renderClients();
  renderKycRecords();

  // Refresh from Supabase database to ensure latest data
  loadClients();
  loadKycRecords();
</script>

HTML;
include 'layout.php';
?>
