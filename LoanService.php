<?php
/**
 * Loan Management Service
 * Handles loan applications, approvals, and disbursements
 */

class LoanService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Submit a loan application
     */
    public function submitLoanApplication($clientId, $data) {
        try {
            $this->db->beginTransaction();
            
            $appData = [
                'client_id' => $clientId,
                'loan_amount_requested' => $data['loan_amount_requested'],
                'loan_purpose' => $data['loan_purpose'] ?? null,
                'loan_status' => 'pending'
            ];
            
            $application = $this->db->insert('loan_applications', $appData);
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'] ?? $clientId,
                'CREATE',
                'loan_applications',
                $application['application_id'],
                null,
                $appData
            );
            
            $this->db->commit();
            return $application;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Loan application submission failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Approve a loan application and create loan record
     */
    public function approveLoan($applicationId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Get application details
            $application = $this->db->getById('loan_applications', $applicationId, 'application_id');
            
            if (!$application) {
                throw new Exception('Application not found');
            }
            
            // Create loan record
            $loanData = [
                'application_id' => $applicationId,
                'client_id' => $application['client_id'],
                'loan_amount' => $data['loan_amount'],
                'interest_rate' => $data['interest_rate'],
                'loan_term_months' => $data['loan_term_months'],
                'loan_status' => 'active'
            ];
            
            $loan = $this->db->insert('loan', $loanData);
            $loanId = $loan['loan_id'];
            
            // Update application status
            $this->db->update(
                'loan_applications',
                ['loan_status' => 'approved'],
                'application_id = ?',
                [$applicationId]
            );
            
            // Create installment schedule
            $this->createInstallmentSchedule($loanId, $loanData['loan_amount'], $loanData['loan_term_months']);
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'CREATE',
                'loan',
                $loanId,
                null,
                $loanData
            );
            
            // Update loan portfolio
            $this->updateLoanPortfolio();
            
            $this->db->commit();
            return $loan;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Loan approval failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create installment schedule for a loan
     */
    private function createInstallmentSchedule($loanId, $loanAmount, $termMonths) {
        try {
            $installmentAmount = $loanAmount / $termMonths;
            $startDate = new DateTime();
            
            for ($i = 1; $i <= $termMonths; $i++) {
                $dueDate = clone $startDate;
                $dueDate->modify("+$i month");
                
                $installmentData = [
                    'loan_id' => $loanId,
                    'installment_number' => $i,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'installment_amount' => round($installmentAmount, 2),
                    'status' => 'pending'
                ];
                
                $this->db->insert('installments', $installmentData);
            }
        } catch (Exception $e) {
            log_message('ERROR', 'Installment schedule creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Disburse a loan
     */
    public function disburseLoan($loanId, $data) {
        try {
            $this->db->beginTransaction();
            
            $loan = $this->db->getById('loan', $loanId, 'loan_id');
            
            if (!$loan) {
                throw new Exception('Loan not found');
            }
            
            $disbursementData = [
                'loan_id' => $loanId,
                'disbursement_amount' => $data['disbursement_amount'],
                'disbursement_method' => $data['disbursement_method'] ?? 'bank_transfer',
                'processed_by' => $_SESSION['user_id']
            ];
            
            $disbursement = $this->db->insert('disbursement', $disbursementData);
            
            // Create fund allocation record
            $allocationData = [
                'loan_id' => $loanId,
                'allocated_amount' => $data['disbursement_amount'],
                'allocation_status' => 'disbursed',
                'fund_source' => $data['fund_source'] ?? 'loan_portfolio'
            ];
            
            $this->db->insert('fund_allocation', $allocationData);
            
            // Create disbursement tracker record
            $trackerData = [
                'disbursement_id' => $disbursement['disbursement_id'],
                'tracking_status' => 'completed',
                'notes' => $data['notes'] ?? null
            ];
            
            $this->db->insert('disbursement_tracker', $trackerData);
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'CREATE',
                'disbursement',
                $disbursement['disbursement_id'],
                null,
                $disbursementData
            );
            
            $this->db->commit();
            return $disbursement;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Loan disbursement failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get loan details
     */
    public function getLoan($loanId) {
        $query = "SELECT l.*, la.loan_purpose, c.first_name, c.last_name, c.email
                  FROM loan l
                  JOIN loan_applications la ON l.application_id = la.application_id
                  JOIN clients c ON l.client_id = c.client_id
                  WHERE l.loan_id = ?";
        return $this->db->fetchOne($query, [$loanId]);
    }

    /**
     * Get client's loans
     */
    public function getClientLoans($clientId) {
        $query = "SELECT * FROM loan WHERE client_id = ? ORDER BY approval_date DESC";
        return $this->db->fetchAll($query, [$clientId]);
    }

    /**
     * Get loan installments
     */
    public function getLoanInstallments($loanId) {
        $query = "SELECT * FROM installments WHERE loan_id = ? ORDER BY installment_number ASC";
        return $this->db->fetchAll($query, [$loanId]);
    }

    /**
     * Get pending loan applications
     */
    public function getPendingApplications($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT la.*, c.first_name, c.last_name, c.email
                  FROM loan_applications la
                  JOIN clients c ON la.client_id = c.client_id
                  WHERE la.loan_status = 'pending'
                  ORDER BY la.application_date ASC
                  LIMIT ? OFFSET ?";
        
        $applications = $this->db->fetchAll($query, [$perPage, $offset]);
        
        $total = $this->db->count('loan_applications', 'loan_status = ?', ['pending']);
        
        return [
            'applications' => $applications,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Reject a loan application
     */
    public function rejectLoanApplication($applicationId, $reason = '') {
        try {
            $oldData = $this->db->getById('loan_applications', $applicationId, 'application_id');
            
            $result = $this->db->update(
                'loan_applications',
                ['loan_status' => 'rejected'],
                'application_id = ?',
                [$applicationId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'loan_applications',
                $applicationId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'Loan rejection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update loan portfolio with current statistics
     */
    private function updateLoanPortfolio() {
        try {
            $stats = $this->db->fetchOne("
                SELECT 
                    COALESCE(SUM(l.loan_amount), 0) as total_loans_issued,
                    COALESCE(SUM(l.outstanding_balance), 0) as total_outstanding_balance,
                    COALESCE(SUM(r.payment_amount), 0) as total_repayments_received,
                    COUNT(CASE WHEN l.loan_status = 'defaulted' THEN 1 END) as default_loans_count
                FROM loan l
                LEFT JOIN repayments r ON l.loan_id = r.loan_id
            ");
            
            if ($stats) {
                $portolioData = [
                    'total_loans_issued' => $stats['total_loans_issued'],
                    'total_outstanding_balance' => $stats['total_outstanding_balance'],
                    'total_repayments_received' => $stats['total_repayments_received'],
                    'default_loans_count' => $stats['default_loans_count'],
                    'last_updated' => date('Y-m-d H:i:s')
                ];
                
                $existing = $this->db->getAll('loan_portfolio', 1);
                if ($existing) {
                    $this->db->update('loan_portfolio', $portolioData, 'portfolio_id = ?', [$existing[0]['portfolio_id']]);
                } else {
                    $this->db->insert('loan_portfolio', $portolioData);
                }
            }
        } catch (Exception $e) {
            log_message('ERROR', 'Loan portfolio update failed: ' . $e->getMessage());
        }
    }
}
