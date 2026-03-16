<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/init.php';

// Loan Application & Disbursement BPMN Workflow
// Swimlanes: Client, Core Transaction System (CTS), Field Staff, Loan Officer, Finance & Compliance, Credit Authority

if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
}

// Handle AJAX workflow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['action'];
    $userId = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;

    try {
        $db = Database::getInstance();

        // Helper: attach client names to applications (DB parser does not support JOIN syntax in this wrapper)
        $attachClientNames = function(array $applications) use ($db) {
            foreach ($applications as &$app) {
                if (!empty($app['client_id'])) {
                    $client = $db->fetchOne('SELECT first_name, last_name FROM clients WHERE client_id = ?', [$app['client_id']]);
                    if ($client) {
                        $app['first_name'] = $client['first_name'];
                        $app['last_name'] = $client['last_name'];
                    }
                }
            }
            return $applications;
        };

        // Audit helper for loan application actions
        $logLoanAction = function($actionType, $applicationId, $details = '', $fields = []) use ($db, $userId) {
            if (!$applicationId) return null;
            try {
                $auditEntry = $db->auditLog($userId, $actionType, 'loan_applications', $applicationId, null, array_merge(['details' => $details], $fields));
                if ($auditEntry && is_array($auditEntry) && !empty($auditEntry['audit_id'])) {
                    $db->insert('compliance_audit', [
                        'audit_id' => $auditEntry['audit_id'],
                        'compliance_check_type' => 'Loan_Transaction',
                        'compliance_status' => 'pending',
                        'notes' => 'Auto-captured loan action: ' . $actionType . ' - ' . $details,
                        'check_date' => date('Y-m-d H:i:s')
                    ]);
                }
                return $auditEntry;
            } catch (Exception $e) {
                error_log('Loan audit attempt failed: ' . $e->getMessage());
                return null;
            }
        };

        switch ($action) {
            // ===== CLIENT SWIMLANE =====
            case 'file_online_application':
                $clientId = $_REQUEST['client_id'] ?? null;
                $loanAmount = floatval($_REQUEST['loan_amount'] ?? 0);
                $loanPurpose = $_REQUEST['loan_purpose'] ?? null;
                $loanTerm = intval($_REQUEST['loan_term'] ?? 12);
                if (!$clientId || !$loanAmount) throw new Exception('Missing required fields');

                $insertData = [
                    'client_id' => $clientId,
                    'loan_amount_requested' => $loanAmount,
                    'loan_purpose' => $loanPurpose,
                    'loan_term_months' => $loanTerm,
                    'loan_status' => 'application_submitted',
                    'application_date' => date('Y-m-d H:i:s')
                ];

                try {
                    $insertedApp = $db->insert('loan_applications', $insertData);
                } catch (Exception $e) {
                    // fallback for schemas without loan_term_months column
                    if (stripos($e->getMessage(), 'loan_term_months') !== false) {
                        unset($insertData['loan_term_months']);
                        $insertData['loan_term'] = $loanTerm;
                        $insertedApp = $db->insert('loan_applications', $insertData);
                    } else {
                        throw $e;
                    }
                }

                $insertedId = is_array($insertedApp) ? ($insertedApp['application_id'] ?? $insertedApp['id'] ?? null) : $insertedApp;

                $auditEntry = $db->auditLog($userId, 'CREATE', 'loan_applications', $insertedId,
                    "Client filed online loan application: ₱{$loanAmount}", []);

                if ($auditEntry && is_array($auditEntry) && isset($auditEntry['audit_id'])) {
                    $db->insert('compliance_audit', [
                        'audit_id' => $auditEntry['audit_id'],
                        'compliance_check_type' => 'Loan_Application',
                        'compliance_status' => 'pending',
                        'notes' => 'New loan application has been filed, awaiting compliance review',
                        'check_date' => date('Y-m-d H:i:s')
                    ]);
                }

                // Enrich response with client name if available
                $clientRecord = $db->fetchOne('SELECT first_name, last_name FROM clients WHERE client_id = ?', [$clientId]);
                if ($clientRecord) {
                    $insertedApp['first_name'] = $clientRecord['first_name'];
                    $insertedApp['last_name'] = $clientRecord['last_name'];
                }

                echo json_encode([
                    'status' => 200,
                    'message' => 'Application filed successfully',
                    'application' => $insertedApp
                ]);
                break;

            case 'upload_documents':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'documents_uploaded' => true,
                    'documents_uploaded_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Documents uploaded', ['documents_uploaded' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Documents uploaded successfully']);
                break;

            // ===== CTS (CORE TRANSACTION SYSTEM) SWIMLANE =====
            case 'log_application':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'los_logged' => true,
                    'los_logged_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Application logged in LOS', ['los_logged' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Application logged in LOS']);
                break;

            case 'ai_eligibility_check':
                $appId = $_REQUEST['app_id'] ?? null;
                $eligible = $_REQUEST['eligible'] === 'true';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $newStatus = $eligible ? 'eligibility_passed' : 'eligibility_failed';
                $db->update('loan_applications', [
                    'eligibility_checked' => true,
                    'eligibility_status' => $eligible ? 'eligible' : 'ineligible',
                    'loan_status' => $newStatus
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'AI eligibility check completed', ['eligibility_status' => $eligible ? 'eligible' : 'ineligible', 'loan_status' => $newStatus]);
                
                echo json_encode(['status' => 200, 'message' => 'AI Eligibility Check completed']);
                break;

            case 'request_missing_docs':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'docs_requested' => true,
                    'docs_requested_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Missing documents requested', ['docs_requested' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Missing documents request sent']);
                break;

            case 'profile_check':
                $appId = $_REQUEST['app_id'] ?? null;
                $profileExists = $_REQUEST['profile_exists'] === 'true';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'profile_checked' => true,
                    'profile_exists' => $profileExists
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Profile check completed', ['profile_exists' => $profileExists]);
                
                echo json_encode(['status' => 200, 'message' => 'Profile check completed']);
                break;

            case 'auto_create_profile':
                $appId = $_REQUEST['app_id'] ?? null;
                $clientId = $_REQUEST['client_id'] ?? null;
                if (!$appId || !$clientId) throw new Exception('Invalid data');
                
                $db->update('loan_applications', [
                    'profile_created' => true,
                    'profile_created_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Auto-created profile', ['profile_created' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Client profile created automatically']);
                break;

            case 'fetch_profile_api':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'profile_fetched' => true,
                    'profile_fetched_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Profile fetched via API', ['profile_fetched' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Profile fetched via API']);
                break;

            case 'auto_extract_encode':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'data_extracted' => true,
                    'data_extracted_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Data extracted and encoded automatically', ['data_extracted' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Data extracted & encoded automatically']);
                break;

            case 'kyc_risk_check':
                $appId = $_REQUEST['app_id'] ?? null;
                $kycStatus = $_REQUEST['kyc_status'] ?? 'verified';
                $riskScore = intval($_REQUEST['risk_score'] ?? 50);
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'kyc_checked' => true,
                    'kyc_status' => $kycStatus,
                    'risk_score' => $riskScore
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'KYC & Risk API check completed', ['kyc_status' => $kycStatus, 'risk_score' => $riskScore]);
                
                echo json_encode(['status' => 200, 'message' => 'KYC & Risk API Check completed']);
                break;

            case 'capacity_model':
                $appId = $_REQUEST['app_id'] ?? null;
                $capacity = floatval($_REQUEST['capacity'] ?? 0);
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'capacity_analyzed' => true,
                    'repayment_capacity' => $capacity,
                    'capacity_analyzed_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Repayment capacity model executed', ['repayment_capacity' => $capacity]);
                
                echo json_encode(['status' => 200, 'message' => 'Repayment Capacity Model executed']);
                break;

            case 'risk_engine_report':
                $appId = $_REQUEST['app_id'] ?? null;
                $riskLevel = $_REQUEST['risk_level'] ?? 'medium';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'risk_report_generated' => true,
                    'risk_level' => $riskLevel
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Risk engine report generated', ['risk_level' => $riskLevel]);
                
                echo json_encode(['status' => 200, 'message' => 'Risk Engine Report generated']);
                break;

            case 'generate_contract':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'contract_generated' => true,
                    'contract_generated_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Contract and schedule generated', ['contract_generated' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Contract & Schedule generated automatically']);
                break;

            case 'esignature_validation':
                $appId = $_REQUEST['app_id'] ?? null;
                $validated = $_REQUEST['validated'] === 'true';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $newStatus = $validated ? 'contract_signed' : 'contract_pending';
                $db->update('loan_applications', [
                    'contract_signed' => $validated,
                    'contract_signed_at' => date('Y-m-d H:i:s'),
                    'loan_status' => $newStatus
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'E-signature validation ' . ($validated ? 'passed' : 'failed'), ['contract_signed' => $validated, 'loan_status' => $newStatus]);
                
                echo json_encode(['status' => 200, 'message' => 'E-Signature validation ' . ($validated ? 'passed' : 'failed')]);
                break;

            case 'create_loan_account':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'loan_account_created' => true,
                    'loan_account_created_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Loan account created', ['loan_account_created' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Loan account created automatically']);
                break;

            case 'generate_approval_record':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'approval_record_generated' => true,
                    'approval_record_generated_at' => date('Y-m-d H:i:s')
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Approval record generated', ['approval_record_generated' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Approval record generated automatically']);
                break;

            // ===== FIELD STAFF SWIMLANE =====
            case 'conduct_interview':
                $appId = $_REQUEST['app_id'] ?? null;
                $interviewNotes = $_REQUEST['interview_notes'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'interview_conducted' => true,
                    'interview_notes' => $interviewNotes,
                    'interview_conducted_at' => date('Y-m-d H:i:s'),
                    'interview_conducted_by' => $userId
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Virtual client interview conducted', ['interview_conducted' => true]);
                
                echo json_encode(['status' => 200, 'message' => 'Virtual Client Interview conducted']);
                break;

            // ===== LOAN OFFICER SWIMLANE =====
            case 'review_application_summary':
                $appId = $_REQUEST['app_id'] ?? null;
                $recommendation = $_REQUEST['recommendation'] ?? 'review';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $newStatus = $recommendation === 'approve' ? 'officer_approved' : 'officer_flagged';
                $db->update('loan_applications', [
                    'officer_reviewed' => true,
                    'officer_recommendation' => $recommendation,
                    'officer_reviewed_at' => date('Y-m-d H:i:s'),
                    'officer_reviewed_by' => $userId,
                    'loan_status' => $newStatus
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Loan officer review: ' . $recommendation, ['loan_status' => $newStatus]);
                
                echo json_encode(['status' => 200, 'message' => "Loan Officer Review: {$recommendation}"]);
                break;

            // ===== FINANCE & COMPLIANCE REVIEW =====
            case 'aml_rule_check':
                $appId = $_REQUEST['app_id'] ?? null;
                $issuesDetected = $_REQUEST['issues_detected'] === 'true';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'aml_checked' => true,
                    'aml_checked_at' => date('Y-m-d H:i:s'),
                    'compliance_issues' => $issuesDetected ? 'yes' : 'no'
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'AML rule check completed', ['compliance_issues' => $issuesDetected ? 'yes' : 'no']);
                
                echo json_encode(['status' => 200, 'message' => 'AML Rule Engine Check completed']);
                break;

            case 'auto_escalate_exceptions':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'escalated' => true,
                    'escalated_at' => date('Y-m-d H:i:s'),
                    'loan_status' => 'escalated'
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Auto escalated exceptions', ['loan_status' => 'escalated']);
                
                echo json_encode(['status' => 200, 'message' => 'Exceptions auto-escalated']);
                break;

            // ===== CREDIT APPROVAL AUTHORITY =====
            case 'automated_approval_decision':
                $appId = $_REQUEST['app_id'] ?? null;
                $decision = $_REQUEST['decision'] ?? 'pending';
                if (!$appId) throw new Exception('Invalid application ID');
                
                $newStatus = $decision === 'approve' ? 'loan_approved' : ($decision === 'reject' ? 'loan_rejected' : 'pending_review');
                
                $db->update('loan_applications', [
                    'approval_decision' => $decision,
                    'approval_decision_at' => date('Y-m-d H:i:s'),
                    'loan_status' => $newStatus
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Automated approval decision: ' . $decision, ['approval_decision' => $decision, 'loan_status' => $newStatus]);
                
                echo json_encode(['status' => 200, 'message' => "Automated Approval Decision: {$decision}"]);
                break;

            case 'send_rejection_notice':
                $appId = $_REQUEST['app_id'] ?? null;
                if (!$appId) throw new Exception('Invalid application ID');
                
                $db->update('loan_applications', [
                    'rejection_notice_sent' => true,
                    'rejection_notice_sent_at' => date('Y-m-d H:i:s'),
                    'loan_status' => 'rejected'
                ], ['application_id' => $appId]);

                $logLoanAction('UPDATE', $appId, 'Rejection notice sent to client', ['loan_status' => 'rejected']);
                
                echo json_encode(['status' => 200, 'message' => 'Rejection notice sent to client']);
                break;

            case 'fetch_applications':
                $applications = $db->fetchAll(
                    'SELECT * FROM loan_applications ORDER BY application_date DESC LIMIT 100'
                );
                $applications = $attachClientNames($applications);
                echo json_encode(['status' => 200, 'applications' => $applications]);
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
$applications = [];
$dbError = null;
$db = Database::getInstance();

try {
    $applications = $db->fetchAll(
        'SELECT * FROM loan_applications ORDER BY application_date DESC LIMIT 100'
    );
    // attach client names for display
    foreach ($applications as &$app) {
        if (!empty($app['client_id'])) {
            $client = $db->fetchOne('SELECT first_name, last_name FROM clients WHERE client_id = ?', [$app['client_id']]);
            if ($client) {
                $app['first_name'] = $client['first_name'];
                $app['last_name'] = $client['last_name'];
            }
        }
    }
    unset($app);
} catch (Exception $e) {
    $applications = [];
}

if (empty($applications)) {
    $dbError = 'No applications found. Start by filing a new loan application.';
}

// Count applications by status
$statusCounts = [];
if (!empty($applications)) {
    foreach ($applications as $app) {
        $status = $app['loan_status'] ?? 'unknown';
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }
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
@media (max-width: 600px)  { .stat-grid { grid-template-columns: 1fr; } }

.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem 1.4rem; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 14px; transition: transform .2s, box-shadow .2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(59,130,246,0.13); }

.stat-icon { width: 46px; height: 46px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.stat-icon.blue    { background: var(--icon-blue); }
.stat-icon.blue i  { color: var(--blue-500); }
.stat-icon.green   { background: var(--icon-green); }
.stat-icon.green i { color: #16a34a; }
.stat-icon.orange   { background: var(--icon-orange); }
.stat-icon.orange i { color: #c2410c; }
.stat-icon.teal    { background: var(--icon-teal); }
.stat-icon.teal i  { color: #0f766e; }
.stat-icon i { font-size: 22px; }

.stat-info { flex: 1; min-width: 0; }
.stat-label { font-size: .75rem; font-weight: 500; color: var(--text-400); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 2px; }
.stat-value { font-family: 'Space Grotesk', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--text-900); line-height: 1; }
.stat-sub { font-size: .75rem; color: var(--text-400); margin-top: 3px; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--card-shadow); margin-bottom: 1.5rem; overflow: hidden; }
.card-header { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); }
.card-header h3 { font-family: 'Space Grotesk', sans-serif; font-size: .95rem; font-weight: 600; color: var(--text-900); display: flex; align-items: center; gap: 8px; margin: 0; }
.card-header h3 i { font-size: 18px; color: var(--blue-500); }

.card-body { padding: 0; }

table { width: 100%; border-collapse: collapse; }
thead tr { background: rgba(240,244,255,.8); }
th { padding: .7rem 1.1rem; text-align: left; font-size: .7rem; font-weight: 700; color: var(--text-400); text-transform: uppercase; letter-spacing: .08em; border-bottom: 1px solid var(--border); white-space: nowrap; }
td { padding: .78rem 1.1rem; font-size: .83rem; color: var(--text-600); border-bottom: 1px solid rgba(59,130,246,.06); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: rgba(240,244,255,.5); }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-size: .82rem; font-weight: 600; cursor: pointer; border: none; transition: all .2s; }
.btn-primary { background: var(--blue-600); color: #fff; }
.btn-primary:hover { background: var(--blue-700); box-shadow: 0 3px 12px rgba(37,99,235,.35); }
.btn-outline { background: transparent; color: var(--blue-600); border: 1px solid rgba(59,130,246,.35); }
.btn-outline:hover { background: rgba(59,130,246,.06); }
.btn-sm { padding: 5px 11px; font-size: .78rem; }
.btn i { font-size: 15px; }

.badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 600; letter-spacing: .03em; }
.badge-green  { background: rgba(34,197,94,.1);   color: #16a34a; }
.badge-orange { background: rgba(249,115,22,.1);  color: #c2410c; }
.badge-blue   { background: rgba(59,130,246,.1);  color: var(--blue-600); }
.badge-teal   { background: rgba(20,184,166,.1);  color: #0f766e; }

.action-btn { width: 30px; height: 30px; border-radius: 7px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid var(--border); background: var(--bg); color: var(--text-400); transition: all .15s; }
.action-btn:hover { border-color: var(--blue-500); color: var(--blue-500); background: rgba(59,130,246,.06); }
.action-btn i { font-size: 14px; }

.alert { padding: 15px; border-radius: var(--radius); border-left: 4px solid; }
.alert-warning { background: rgba(251,191,36,.1); border-color: #fbbf24; color: #92400e; }

.modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,.5); }
.modal.show { display: flex; align-items: center; justify-content: center; }
.modal-content { background-color: var(--surface); padding: 2rem; border-radius: var(--radius); width: 90%; max-width: 500px; box-shadow: 0 8px 32px rgba(0,0,0,.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
.modal-header h2 { font-size: 1.25rem; font-weight: 700; color: var(--text-900); margin: 0; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 24px; color: var(--text-400); transition: all .2s; }
.modal-close:hover { color: var(--text-900); }
.modal-body { margin-bottom: 1.5rem; }
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; font-size: .85rem; font-weight: 600; color: var(--text-900); margin-bottom: 0.5rem; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.65rem 0.9rem; border: 1px solid var(--border); border-radius: 8px; font-size: .9rem; color: var(--text-900); background: var(--surface); transition: all .2s; font-family: 'DM Sans', sans-serif; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--blue-600); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
.btn-cancel { background: transparent; border: 1px solid var(--border); color: var(--text-600); }
.btn-cancel:hover { background: rgba(59,130,246,.06); }

@media (max-width: 768px) {
  .page-header { flex-direction: column; }
  .page-header-actions { margin-left: 0; width: 100%; }
}
</style>

<!-- Page Content -->
<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-icon"><i class='bx bx-file-blank'></i></div>
    <div class="page-header-text">
      <h2>Loan Application &amp; Approval Workflow</h2>
      <p>Complete BPMN process from application to disbursement with automated decision engine</p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary btn-sm" onclick="openModal('fileAppModal')"><i class='bx bx-plus'></i> New Application</button>
    </div>
  </div>

HTML;

if ($dbError) {
    $page_content .= <<<'HTML'
  <div class="alert alert-warning">
    <div style="display: flex; align-items: center; gap: 10px;">
      <i class='bx bx-exclamation-circle'></i>
      <div><strong>Info:</strong> No applications found yet. Create the first application to begin the workflow.</div>
    </div>
  </div>
HTML;
}

$page_content .= <<<'HTML'

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class='bx bx-receipt'></i></div>
      <div class="stat-info">
        <div class="stat-label">Total Applications</div>
        <div class="stat-value" id="totalAppsCount">0</div>
        <div class="stat-sub">Filed applications</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class='bx bx-check-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Approved Loans</div>
        <div class="stat-value" id="approvedCount">0</div>
        <div class="stat-sub">Ready for disbursement</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon orange"><i class='bx bx-time'></i></div>
      <div class="stat-info">
        <div class="stat-label">In Progress</div>
        <div class="stat-value" id="inProgressCount">0</div>
        <div class="stat-sub">Under review</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class='bx bx-x-circle'></i></div>
      <div class="stat-info">
        <div class="stat-label">Rejected</div>
        <div class="stat-value" id="rejectedCount">0</div>
        <div class="stat-sub">Not approved</div>
      </div>
    </div>
  </div>

  <!-- Loan Applications Table -->
  <div class="card">
    <div class="card-header">
      <h3><i class='bx bx-receipt'></i> Loan Applications & Workflow Status</h3>
    </div>
    <div class="card-body">
      <table>
        <thead>
          <tr>
            <th>Application ID</th>
            <th>Client</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Progress</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="applicationsTableBody">
          <tr><td colspan="6" style="text-align: center; padding: 20px; color: #8EA0C4;">No applications found.</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- File Application Modal -->
<div id="fileAppModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>File New Loan Application</h2>
      <button class="modal-close" onclick="closeModal('fileAppModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label>Client ID</label>
        <input type="number" id="clientId" placeholder="Enter client ID" />
      </div>
      <div class="form-group">
        <label>Loan Amount (₱)</label>
        <input type="number" id="loanAmount" placeholder="Enter loan amount" min="1000" step="100" />
      </div>
      <div class="form-group">
        <label>Loan Purpose</label>
        <input type="text" id="loanPurpose" placeholder="e.g., Business expansion" />
      </div>
      <div class="form-group">
        <label>Loan Term (months)</label>
        <input type="number" id="loanTerm" value="12" min="1" max="60" />
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-cancel btn-sm" onclick="closeModal('fileAppModal')">Cancel</button>
      <button class="btn btn-primary btn-sm" onclick="submitFileApplication()">File Application</button>
    </div>
  </div>
</div>

<!-- Application Details Modal -->
<div id="appDetailsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="appDetailsTitle">Application Details</h2>
      <button class="modal-close" onclick="closeModal('appDetailsModal')">&times;</button>
    </div>
    <div class="modal-body" id="appDetailsBody">
      <!-- Populated by JavaScript -->
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary btn-sm" onclick="closeModal('appDetailsModal')">Close</button>
    </div>
  </div>
</div>

<script>
  const applicationsData = [];

  const statusBadgeMap = {
    'application_submitted': 'badge-blue',
    'eligibility_passed': 'badge-blue',
    'kyc_verified': 'badge-blue',
    'officer_approved': 'badge-green',
    'loan_approved': 'badge-green',
    'contract_signed': 'badge-green',
    'eligibility_failed': 'badge-orange',
    'loan_rejected': 'badge-orange',
    'rejected': 'badge-orange'
  };

  const progressMap = {
    'application_submitted': 10,
    'eligibility_passed': 25,
    'kyc_verified': 40,
    'officer_approved': 60,
    'contract_signed': 85,
    'loan_approved': 100,
    'loan_rejected': 0,
    'rejected': 0
  };

  function renderApplications() {
    const tbody = document.getElementById('applicationsTableBody');
    if (!applicationsData || applicationsData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #8EA0C4;">No applications found.</td></tr>';
      updateStatCards(0, 0, 0, 0);
      return;
    }

    let html = '', approved = 0, inProgress = 0, rejected = 0;
    
    for (const app of applicationsData) {
      const badgeClass = statusBadgeMap[app.status] || 'badge-blue';
      const progress = progressMap[app.status] || 25;
      
      if (app.status.includes('approved') && !app.status.includes('rejected')) approved++;
      else if (app.status.includes('rejected')) rejected++;
      else inProgress++;
      
      const amountFormatted = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0
      }).format(app.amount);
      
      html += `<tr>
        <td><strong>#${app.id}</strong></td>
        <td>${app.client}</td>
        <td>${amountFormatted}</td>
        <td><span class="badge ${badgeClass}">${app.status.replace(/_/g, ' ')}</span></td>
        <td><div style="background: rgba(59,130,246,0.1); border-radius: 20px; height: 24px; overflow: hidden;">
          <div style="background: var(--blue-600); height: 100%; width: ${progress}%; transition: width 0.3s;"></div>
        </div></td>
        <td><button class="action-btn" title="Details" onclick="viewApplication(${app.id})"><i class='bx bx-show'></i></button></td>
      </tr>`;
    }
    
    tbody.innerHTML = html;
    updateStatCards(applicationsData.length, approved, inProgress, rejected);
  }

  function updateStatCards(total, approved, inProgress, rejected) {
    document.getElementById('totalAppsCount').textContent = total;
    document.getElementById('approvedCount').textContent = approved;
    document.getElementById('inProgressCount').textContent = inProgress;
    document.getElementById('rejectedCount').textContent = rejected;
  }

  function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
  }

  function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
  }

  function loadApplications() {
    fetch('loan_application.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=fetch_applications'
    })
      .then(r => r.json())
      .then(data => {
        if (data.status !== 200 || !Array.isArray(data.applications)) {
          console.warn('Failed to load applications', data);
          return;
        }

        applicationsData.length = 0;
        data.applications.forEach(app => {
          const clientName = `${(app.first_name || '').trim()} ${(app.last_name || '').trim()}`.trim();
          const clientFallback = app.client || app.client_id ? `Client #${app.client_id || app.client}` : 'Unknown Client';

          applicationsData.push({
            id: parseInt(app.application_id || app.id || 0, 10),
            client: clientName || clientFallback,
            amount: parseFloat(app.loan_amount_requested || 0),
            status: app.loan_status || 'submitted',
            purpose: app.loan_purpose || 'N/A',
            term: parseInt(app.loan_term_months || app.loan_term || 12, 10),
            createdAt: app.application_date || app.application_date || new Date().toISOString().slice(0, 10)
          });
        });

        renderApplications();
      })
      .catch(err => console.error('Error loading applications:', err));
  }

  function signalComplianceUpdate() {
    try {
      localStorage.setItem('compliance_event', Date.now().toString());
    } catch (e) {
      // Browser may disallow storage in private mode
    }
  }

  function submitFileApplication() {
    const clientId = document.getElementById('clientId').value;
    const loanAmount = document.getElementById('loanAmount').value;
    const loanPurpose = document.getElementById('loanPurpose').value;
    const loanTerm = document.getElementById('loanTerm').value;
    
    if (!clientId || !loanAmount) {
      alert('Please fill in all required fields');
      return;
    }

    fetch('loan_application.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=file_online_application&client_id=' + clientId + '&loan_amount=' + loanAmount + 
            '&loan_purpose=' + encodeURIComponent(loanPurpose) + '&loan_term=' + loanTerm
    })
    .then(r => r.json())
    .then(data => {
      if (data.status === 200) {
        alert('Application filed successfully!');
        closeModal('fileAppModal');
        proactiveComplianceSignal();

        if (data.application) {
          applicationsData.unshift({
            id: parseInt(data.application.application_id || data.application.id || 0, 10),
            client: `${data.application.first_name || ''} ${data.application.last_name || ''}`.trim() || 'Unknown Client',
            amount: parseFloat(data.application.loan_amount_requested || 0),
            status: data.application.loan_status || 'application_submitted',
            purpose: data.application.loan_purpose || 'N/A',
            term: parseInt(data.application.loan_term_months || data.application.loan_term || loanTerm || 12, 10),
            createdAt: data.application.application_date || new Date().toISOString().slice(0, 10)
          });
          renderApplications();
        } else {
          loadApplications();
        }
      } else {
        alert('Error: ' + data.message);
      }
    })
    .catch(err => {
      console.error('Submit application error:', err);
      alert('Error filing application. Please check the server logs.');
    });
  }

  function viewApplication(id) {
    const app = applicationsData.find(a => a.id === id);
    if (app) {
      document.getElementById('appDetailsTitle').textContent = 'Application #' + id;
      const amountFormatted = new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0
      }).format(app.amount);
      
      document.getElementById('appDetailsBody').innerHTML = `
        <div class="form-group">
          <label>Client</label>
          <input type="text" readonly value="${app.client}" />
        </div>
        <div class="form-group">
          <label>Loan Amount</label>
          <input type="text" readonly value="${amountFormatted}" />
        </div>
        <div class="form-group">
          <label>Purpose</label>
          <input type="text" readonly value="${app.purpose}" />
        </div>
        <div class="form-group">
          <label>Term</label>
          <input type="text" readonly value="${app.term} months" />
        </div>
        <div class="form-group">
          <label>Current Status</label>
          <input type="text" readonly value="${app.status.replace(/_/g, ' ')}" />
        </div>
        <div class="form-group">
          <label>Filed Date</label>
          <input type="text" readonly value="${app.createdAt}" />
        </div>
      `;
      openModal('appDetailsModal');
    }
  }

  renderApplications();
  loadApplications();
</script>

HTML;
include 'layout.php';
?>
