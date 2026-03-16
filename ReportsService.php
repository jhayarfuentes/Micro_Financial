<?php
/**
 * Reports & Performance Dashboard Service
 * Generates reports on loan performance, financial metrics, and client statuses
 */

class ReportsService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Generate loan performance report
     */
    public function generateLoanPerformanceReport($startDate = null, $endDate = null) {
        try {
            $dateFilter = '';
            $params = [];
            
            if ($startDate && $endDate) {
                $dateFilter = ' WHERE l.approval_date BETWEEN ? AND ?';
                $params = [$startDate, $endDate];
            }
            
            $query = "SELECT 
                        COUNT(DISTINCT l.loan_id) as total_loans,
                        COALESCE(SUM(l.loan_amount), 0) as total_loaned,
                        COALESCE(SUM(l.outstanding_balance), 0) as outstanding_balance,
                        COALESCE(SUM(r.payment_amount), 0) as total_repaid,
                        COUNT(DISTINCT CASE WHEN l.loan_status = 'completed' THEN l.loan_id END) as completed_loans,
                        COUNT(DISTINCT CASE WHEN l.loan_status = 'defaulted' THEN l.loan_id END) as defaulted_loans,
                        ROUND(COALESCE(SUM(r.payment_amount) / SUM(l.loan_amount) * 100, 0), 2) as repayment_rate
                      FROM loan l
                      LEFT JOIN repayments r ON l.loan_id = r.loan_id
                      $dateFilter";
            
            $report = $this->db->fetchOne($query, $params);
            
            return [
                'report_type' => 'loan_performance',
                'period' => ['start' => $startDate, 'end' => $endDate],
                'data' => $report,
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            log_message('ERROR', 'Loan performance report generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate financial summary report
     */
    public function generateFinancialSummaryReport() {
        try {
            $loanPortfolio = $this->db->fetchOne("SELECT * FROM loan_portfolio ORDER BY portfolio_id DESC LIMIT 1");
            
            $savingsQuery = "SELECT 
                            COUNT(*) as total_accounts,
                            COALESCE(SUM(balance), 0) as total_savings
                           FROM savings_accounts
                           WHERE account_status = 'active'";
            $savingsData = $this->db->fetchOne($savingsQuery);
            
            return [
                'report_type' => 'financial_summary',
                'loans' => $loanPortfolio ?? [],
                'savings' => $savingsData ?? [],
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            log_message('ERROR', 'Financial summary report generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate client status report
     */
    public function generateClientStatusReport() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_clients,
                        SUM(CASE WHEN client_status = 'active' THEN 1 ELSE 0 END) as active_clients,
                        SUM(CASE WHEN client_status = 'pending' THEN 1 ELSE 0 END) as pending_clients,
                        SUM(CASE WHEN client_status = 'inactive' THEN 1 ELSE 0 END) as inactive_clients,
                        SUM(CASE WHEN client_status = 'suspended' THEN 1 ELSE 0 END) as suspended_clients
                      FROM clients";
            
            $clientStats = $this->db->fetchOne($query);
            
            return [
                'report_type' => 'client_status',
                'data' => $clientStats,
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            log_message('ERROR', 'Client status report generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate portfolio health report
     */
    public function generatePortfolioHealthReport() {
        try {
            $portfolio = $this->db->fetchOne("SELECT * FROM loan_portfolio ORDER BY portfolio_id DESC LIMIT 1");
            
            $riskAnalysis = $this->db->fetchOne(
                "SELECT 
                    COUNT(DISTINCT CASE WHEN l.loan_status = 'defaulted' THEN l.loan_id END) as high_risk_loans,
                    COUNT(DISTINCT CASE WHEN i.status = 'overdue' THEN i.installment_id END) as overdue_installments,
                    COALESCE(SUM(CASE WHEN l.loan_status = 'defaulted' THEN l.outstanding_balance ELSE 0 END), 0) as defaulted_amount
                 FROM loan l
                 LEFT JOIN installments i ON l.loan_id = i.loan_id"
            );
            
            return [
                'report_type' => 'portfolio_health',
                'portfolio' => $portfolio ?? [],
                'risk_analysis' => $riskAnalysis ?? [],
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            log_message('ERROR', 'Portfolio health report generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Save generated report
     */
    public function saveReport($reportType, $reportName, $reportData) {
        try {
            $reportInfo = [
                'report_type' => $reportType,
                'report_name' => $reportName,
                'generated_by' => $_SESSION['user_id'],
                'report_data' => json_encode($reportData),
                'report_period' => date('Y-m')
            ];
            
            $report = $this->db->insert('reports', $reportInfo);
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'CREATE',
                'reports',
                $report['report_id'],
                null,
                array_diff_key($reportInfo, ['report_data' => ''])
            );
            
            return $report;
        } catch (Exception $e) {
            log_message('ERROR', 'Report saving failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get saved reports
     */
    public function getSavedReports($reportType = null, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT r.*, u.username FROM reports r
                  LEFT JOIN users u ON r.generated_by = u.user_id";
        $params = [];
        
        if ($reportType) {
            $query .= " WHERE r.report_type = ?";
            $params[] = $reportType;
        }
        
        $query .= " ORDER BY r.generated_date DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $reports = $this->db->fetchAll($query, $params);
        
        $countQuery = "SELECT COUNT(*) as count FROM reports";
        if ($reportType) {
            $countQuery .= " WHERE report_type = ?";
        }
        
        $totalResult = $reportType 
            ? $this->db->fetchOne($countQuery, [$reportType])
            : $this->db->fetchOne($countQuery);
        
        $total = $totalResult['count'];
        
        return [
            'reports' => $reports,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Get report by ID
     */
    public function getReport($reportId) {
        return $this->db->getById('reports', $reportId, 'report_id');
    }

    /**
     * Create/Update dashboard configuration
     */
    public function saveDashboardConfig($dashboardName, $userId, $config, $isDefault = false) {
        try {
            $existing = $this->db->fetchOne(
                "SELECT * FROM performance_dashboards WHERE dashboard_name = ? AND user_id = ?",
                [$dashboardName, $userId]
            );
            
            $dashboardData = [
                'dashboard_name' => $dashboardName,
                'user_id' => $userId,
                'dashboard_config' => json_encode($config),
                'is_default' => $isDefault,
                'updated_date' => date('Y-m-d H:i:s')
            ];
            
            if ($existing) {
                $result = $this->db->update(
                    'performance_dashboards',
                    $dashboardData,
                    'dashboard_id = ?',
                    [$existing['dashboard_id']]
                );
                return $result;
            } else {
                $dashboard = $this->db->insert('performance_dashboards', $dashboardData);
                return [$dashboard];
            }
        } catch (Exception $e) {
            log_message('ERROR', 'Dashboard config save failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's dashboards
     */
    public function getUserDashboards($userId) {
        $query = "SELECT * FROM performance_dashboards WHERE user_id = ? ORDER BY updated_date DESC";
        return $this->db->fetchAll($query, [$userId]);
    }

    /**
     * Get default dashboard
     */
    public function getDefaultDashboard($userId) {
        $query = "SELECT * FROM performance_dashboards WHERE user_id = ? AND is_default = true LIMIT 1";
        return $this->db->fetchOne($query, [$userId]);
    }
}
