<?php
/**
 * Loan Management API Endpoints
 * Handles loan applications, approvals, disbursements, and repayments
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/helpers.php';

$request_method = $_SERVER['REQUEST_METHOD'];

if ($request_method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            handleGetLoans();
            break;
        case 'get':
            handleGetLoan();
            break;
        case 'pending':
            handleGetPendingApplications();
            break;
        case 'installments':
            handleGetInstallments();
            break;
        case 'client_loans':
            handleGetClientLoans();
            break;
        default:
            send_response(400, null, 'Invalid action');
    }
} else if ($request_method === 'POST') {
    $action = $_POST['action'] ?? ($_GET['action'] ?? null);
    
    switch ($action) {
        case 'apply':
            handleLoanApplication();
            break;
        case 'approve':
            handleApproveLoan();
            break;
        case 'reject':
            handleRejectLoan();
            break;
        case 'disburse':
            handleDisburseLoan();
            break;
        case 'payment':
            handleProcessPayment();
            break;
        default:
            send_response(400, null, 'Invalid action');
    }
} else {
    send_response(405, null, 'Method not allowed');
}

/**
 * Get all loans
 */
function handleGetLoans() {
    apiCall(function() {
        requireRole('Loan Officer');
        
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;
        
        try {
            $loanService = service('loan');
            $db = service('database');
            
            $offset = ($page - 1) * $perPage;
            $loans = $db->fetchAll(
                "SELECT l.*, c.first_name, c.last_name FROM loan l 
                 JOIN clients c ON l.client_id = c.client_id 
                 ORDER BY l.approval_date DESC 
                 LIMIT ? OFFSET ?",
                [$perPage, $offset]
            );
            
            $total = $db->count('loan');
            
            send_response(200, responsePaginated(
                $loans,
                $total,
                $page,
                $perPage
            ), 'Loans retrieved successfully');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Get loan details
 */
function handleGetLoan() {
    apiCall(function() {
        requireAuth();
        
        $loanId = $_GET['id'] ?? null;
        
        if (!$loanId) {
            send_response(400, null, 'Loan ID required');
        }
        
        try {
            $loanService = service('loan');
            $loan = $loanService->getLoan($loanId);
            $installments = $loanService->getLoanInstallments($loanId);
            
            if (!$loan) {
                send_response(404, null, 'Loan not found');
            }
            
            send_response(200, [
                'loan' => $loan,
                'installments' => $installments
            ], 'Loan details retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Get pending loan applications
 */
function handleGetPendingApplications() {
    apiCall(function() {
        requireRole('Loan Officer');
        
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;
        
        try {
            $loanService = service('loan');
            $result = $loanService->getPendingApplications($page, $perPage);
            
            send_response(200, responsePaginated(
                $result['applications'],
                $result['total'],
                $result['current_page'],
                $perPage
            ), 'Pending applications retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Get loan installments
 */
function handleGetInstallments() {
    apiCall(function() {
        requireAuth();
        
        $loanId = $_GET['loan_id'] ?? null;
        
        if (!$loanId) {
            send_response(400, null, 'Loan ID required');
        }
        
        try {
            $loanService = service('loan');
            $installments = $loanService->getLoanInstallments($loanId);
            
            send_response(200, [
                'installments' => $installments
            ], 'Installments retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Get client's loans
 */
function handleGetClientLoans() {
    apiCall(function() {
        requireAuth();
        
        $clientId = $_GET['client_id'] ?? null;
        
        if (!$clientId) {
            send_response(400, null, 'Client ID required');
        }
        
        try {
            $loanService = service('loan');
            $loans = $loanService->getClientLoans($clientId);
            
            send_response(200, [
                'loans' => $loans
            ], 'Client loans retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Submit loan application
 */
function handleLoanApplication() {
    apiCall(function() {
        requireAuth();
        
        // For clients submitting their own applications
        $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? $_SESSION['user_id'];
        
        $input = getJsonInput();
        
        $missing = validateRequired($input, ['loan_amount_requested']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        try {
            $loanService = service('loan');
            $application = $loanService->submitLoanApplication($clientId, $input);
            
            send_response(201, [
                'application' => $application
            ], 'Loan application submitted');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Approve loan application
 */
function handleApproveLoan() {
    apiCall(function() {
        requireRole('Loan Officer');
        
        $input = getJsonInput();
        
        $missing = validateRequired($input, ['application_id', 'loan_amount', 'interest_rate', 'loan_term_months']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        try {
            $loanService = service('loan');
            $loan = $loanService->approveLoan($input['application_id'], $input);
            
            send_response(201, [
                'loan' => $loan
            ], 'Loan approved successfully');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Reject loan application
 */
function handleRejectLoan() {
    apiCall(function() {
        requireRole('Loan Officer');
        
        $input = getJsonInput();
        
        if (!isset($input['application_id'])) {
            send_response(400, null, 'Application ID required');
        }
        
        try {
            $loanService = service('loan');
            $result = $loanService->rejectLoanApplication($input['application_id'], $input['reason'] ?? '');
            
            send_response(200, [
                'application' => $result[0] ?? null
            ], 'Loan application rejected');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Disburse loan
 */
function handleDisburseLoan() {
    apiCall(function() {
        requireRole('Loan Officer');
        
        $input = getJsonInput();
        
        $missing = validateRequired($input, ['loan_id', 'disbursement_amount']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        try {
            $loanService = service('loan');
            $disbursement = $loanService->disburseLoan($input['loan_id'], $input);
            
            send_response(201, [
                'disbursement' => $disbursement
            ], 'Loan disbursed successfully');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Process loan repayment
 */
function handleProcessPayment() {
    apiCall(function() {
        requireRole('Loan Collector');
        
        $input = getJsonInput();
        
        $missing = validateRequired($input, ['loan_id', 'payment_amount']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        try {
            $repaymentService = service('repayment');
            $repayment = $repaymentService->processRepayment($input['loan_id'], $input);
            
            send_response(201, [
                'repayment' => $repayment
            ], 'Repayment processed successfully');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}
