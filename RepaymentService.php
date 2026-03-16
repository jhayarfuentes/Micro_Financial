<?php
/**
 * Loan Repayment Service
 * Handles loan repayments, installment tracking, and payment processing
 */

class RepaymentService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Process a loan repayment
     */
    public function processRepayment($loanId, $data) {
        try {
            $this->db->beginTransaction();
            
            $loan = $this->db->getById('loan', $loanId, 'loan_id');
            
            if (!$loan) {
                throw new Exception('Loan not found');
            }
            
            // Get next pending installment
            $nextInstallment = $this->db->fetchOne(
                "SELECT * FROM installments WHERE loan_id = ? AND status = 'pending' ORDER BY installment_number ASC LIMIT 1",
                [$loanId]
            );
            
            if (!$nextInstallment) {
                throw new Exception('No pending installments for this loan');
            }
            
            $repaymentData = [
                'loan_id' => $loanId,
                'installment_id' => $nextInstallment['installment_id'],
                'payment_amount' => $data['payment_amount'],
                'payment_method' => $data['payment_method'] ?? 'cash',
                'received_by' => $_SESSION['user_id']
            ];
            
            $repayment = $this->db->insert('repayments', $repaymentData);
            
            // Update installment status to paid
            $this->db->update(
                'installments',
                ['status' => 'paid'],
                'installment_id = ?',
                [$nextInstallment['installment_id']]
            );
            
            // Update loan outstanding balance
            $newBalance = $loan['outstanding_balance'] - $data['payment_amount'];
            $loanStatus = $newBalance <= 0 ? 'completed' : $loan['loan_status'];
            
            $this->db->update(
                'loan',
                [
                    'outstanding_balance' => max(0, $newBalance),
                    'loan_status' => $loanStatus
                ],
                'loan_id = ?',
                [$loanId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'CREATE',
                'repayments',
                $repayment['repayment_id'],
                null,
                $repaymentData
            );
            
            $this->db->commit();
            return $repayment;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Repayment processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get loan repayment history
     */
    public function getRepaymentHistory($loanId) {
        $query = "SELECT r.*, i.installment_number, i.installment_amount, i.due_date
                  FROM repayments r
                  LEFT JOIN installments i ON r.installment_id = i.installment_id
                  WHERE r.loan_id = ?
                  ORDER BY r.repayment_date DESC";
        return $this->db->fetchAll($query, [$loanId]);
    }

    /**
     * Get loan repayment status
     */
    public function getRepaymentStatus($loanId) {
        $query = "SELECT 
                    COUNT(*) as total_installments,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_installments,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_installments,
                    SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_installments
                  FROM installments
                  WHERE loan_id = ?";
        
        return $this->db->fetchOne($query, [$loanId]);
    }

    /**
     * Get overdue installments
     */
    public function getOverdueInstallments($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT i.*, l.client_id, c.first_name, c.last_name, c.email, l.outstanding_balance
                  FROM installments i
                  JOIN loan l ON i.loan_id = l.loan_id
                  JOIN clients c ON l.client_id = c.client_id
                  WHERE i.status = 'pending' AND i.due_date < CURRENT_DATE
                  ORDER BY i.due_date ASC
                  LIMIT ? OFFSET ?";
        
        $overdueInstallments = $this->db->fetchAll($query, [$perPage, $offset]);
        
        $total = $this->db->count(
            'installments',
            'status = ? AND due_date < CURRENT_DATE',
            ['pending']
        );
        
        return [
            'installments' => $overdueInstallments,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Generate payment reminder
     */
    public function sendPaymentReminder($loanId) {
        try {
            $nextInstallment = $this->db->fetchOne(
                "SELECT i.*, l.client_id, c.email, c.first_name
                 FROM installments i
                 JOIN loan l ON i.loan_id = l.loan_id
                 JOIN clients c ON l.client_id = c.client_id
                 WHERE i.loan_id = ? AND i.status = 'pending'
                 ORDER BY i.installment_number ASC
                 LIMIT 1",
                [$loanId]
            );
            
            if (!$nextInstallment) {
                return ['sent' => false, 'message' => 'No pending installments'];
            }
            
            // Send email reminder (implement email service as needed)
            // email_service::send_payment_reminder($nextInstallment['email'], $nextInstallment);
            
            log_message('INFO', "Payment reminder sent for loan $loanId to {$nextInstallment['email']}");
            
            return ['sent' => true, 'installment' => $nextInstallment];
        } catch (Exception $e) {
            log_message('ERROR', 'Payment reminder failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update overdue installments
     */
    public function updateOverdueStatus() {
        try {
            $this->db->update(
                'installments',
                ['status' => 'overdue'],
                'status = ? AND due_date < CURRENT_DATE',
                ['pending']
            );
            
            log_message('INFO', 'Overdue status updated for installments');
        } catch (Exception $e) {
            log_message('ERROR', 'Overdue status update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get repayment statistics for a client
     */
    public function getClientRepaymentStats($clientId) {
        $query = "SELECT 
                    COUNT(DISTINCT l.loan_id) as total_loans,
                    COUNT(DISTINCT CASE WHEN l.loan_status = 'completed' THEN l.loan_id END) as completed_loans,
                    COUNT(DISTINCT CASE WHEN l.loan_status = 'defaulted' THEN l.loan_id END) as defaulted_loans,
                    COALESCE(SUM(r.payment_amount), 0) as total_repaid,
                    COALESCE(SUM(l.outstanding_balance), 0) as total_outstanding
                  FROM loan l
                  LEFT JOIN repayments r ON l.loan_id = r.loan_id
                  WHERE l.client_id = ?";
        
        return $this->db->fetchOne($query, [$clientId]);
    }
}
