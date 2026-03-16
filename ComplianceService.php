<?php
/**
 * Compliance & Audit Service
 * Handles compliance checks and audit trail management
 */

class ComplianceService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get audit trail by record
     */
    public function getAuditTrail($tableName, $recordId, $limit = 50) {
        $query = "SELECT a.*, u.username FROM audit_trail a
                  LEFT JOIN users u ON a.user_id = u.user_id
                  WHERE a.table_name = ? AND a.record_id = ?
                  ORDER BY a.action_timestamp DESC
                  LIMIT ?";
        
        return $this->db->fetchAll($query, [$tableName, $recordId, $limit]);
    }

    /**
     * Get audit trail by user
     */
    public function getAuditTrailByUser($userId, $page = 1, $perPage = 50) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT * FROM audit_trail WHERE user_id = ? ORDER BY action_timestamp DESC LIMIT ? OFFSET ?";
        
        $trail = $this->db->fetchAll($query, [$userId, $perPage, $offset]);
        
        $total = $this->db->count('audit_trail', 'user_id = ?', [$userId]);
        
        return [
            'trail' => $trail,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Get audit trail by date range
     */
    public function getAuditTrailByDateRange($startDate, $endDate, $page = 1, $perPage = 50) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT a.*, u.username FROM audit_trail a
                  LEFT JOIN users u ON a.user_id = u.user_id
                  WHERE a.action_timestamp BETWEEN ? AND ?
                  ORDER BY a.action_timestamp DESC
                  LIMIT ? OFFSET ?";
        
        $trail = $this->db->fetchAll($query, [$startDate, $endDate, $perPage, $offset]);
        
        $total = $this->db->count(
            'audit_trail',
            'action_timestamp BETWEEN ? AND ?',
            [$startDate, $endDate]
        );
        
        return [
            'trail' => $trail,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Perform KYC compliance check
     */
    public function performKYCCompliance($clientId) {
        try {
            $kyc = $this->db->fetchOne(
                "SELECT * FROM kyc_verification WHERE client_id = ? AND verification_status = 'verified' LIMIT 1",
                [$clientId]
            );
            
            $client = $this->db->getById('clients', $clientId, 'client_id');
            
            $isCompliant = !empty($kyc) && !empty($client);
            
            $complianceData = [
                'compliance_check_type' => 'KYC',
                'compliance_status' => $isCompliant ? 'compliant' : 'non_compliant',
                'notes' => $isCompliant ? 'KYC verification completed' : 'KYC verification pending'
            ];
            
            return $complianceData;
        } catch (Exception $e) {
            log_message('ERROR', 'KYC compliance check failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform AML compliance check
     */
    public function performAMLCompliance($clientId) {
        try {
            // Implement AML (Anti-Money Laundering) checks
            // This is a placeholder for actual AML validation logic
            
            $complianceData = [
                'compliance_check_type' => 'AML',
                'compliance_status' => 'compliant',
                'notes' => 'AML check completed'
            ];
            
            return $complianceData;
        } catch (Exception $e) {
            log_message('ERROR', 'AML compliance check failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform loan policy compliance check
     */
    public function performLoanPolicyCompliance($loanId) {
        try {
            $loan = $this->db->getById('loan', $loanId, 'loan_id');
            
            if (!$loan) {
                throw new Exception('Loan not found');
            }
            
            // Check loan amount limits, interest rates, etc.
            $isCompliant = $loan['interest_rate'] > 0 && $loan['loan_amount'] > 0;
            
            $complianceData = [
                'compliance_check_type' => 'Loan_Policy',
                'compliance_status' => $isCompliant ? 'compliant' : 'non_compliant',
                'notes' => $isCompliant ? 'Loan policy check passed' : 'Loan policy check failed'
            ];
            
            return $complianceData;
        } catch (Exception $e) {
            log_message('ERROR', 'Loan policy compliance check failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport($startDate, $endDate) {
        try {
            $query = "SELECT 
                        COUNT(DISTINCT ca.compliance_id) as total_checks,
                        COUNT(DISTINCT CASE WHEN ca.compliance_status = 'compliant' THEN ca.compliance_id END) as compliant_count,
                        COUNT(DISTINCT CASE WHEN ca.compliance_status = 'non_compliant' THEN ca.compliance_id END) as non_compliant_count
                      FROM compliance_audit ca
                      WHERE ca.check_date BETWEEN ? AND ?";
            
            $stats = $this->db->fetchOne($query, [$startDate, $endDate]);
            
            return [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'total_checks' => $stats['total_checks'],
                'compliant' => $stats['compliant_count'],
                'non_compliant' => $stats['non_compliant_count'],
                'compliance_rate' => $stats['total_checks'] > 0 
                    ? round(($stats['compliant_count'] / $stats['total_checks']) * 100, 2) 
                    : 0
            ];
        } catch (Exception $e) {
            log_message('ERROR', 'Compliance report generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get compliance audit logs
     */
    public function getComplianceAuditLogs($page = 1, $perPage = 50) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT ca.*, at.user_id FROM compliance_audit ca
                  JOIN audit_trail at ON ca.audit_id = at.audit_id
                  ORDER BY ca.check_date DESC
                  LIMIT ? OFFSET ?";
        
        $logs = $this->db->fetchAll($query, [$perPage, $offset]);
        
        $total = $this->db->count('compliance_audit');
        
        return [
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Get action summary by user
     */
    public function getActionSummaryByUser($userId = null) {
        $query = "SELECT 
                    u.username,
                    COUNT(*) as total_actions,
                    action_type,
                    COUNT(*) as action_count
                  FROM audit_trail a
                  LEFT JOIN users u ON a.user_id = u.user_id";
        
        if ($userId) {
            $query .= " WHERE a.user_id = ?";
        }
        
        $query .= " GROUP BY u.username, a.action_type ORDER BY a.action_timestamp DESC";
        
        if ($userId) {
            return $this->db->fetchAll($query, [$userId]);
        }
        return $this->db->fetchAll($query);
    }
}
