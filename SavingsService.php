<?php
/**
 * Savings Account Service
 * Handles savings account creation, deposits, withdrawals, and interest calculations
 */

class SavingsService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a savings account for a client
     */
    public function createSavingsAccount($clientId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Generate unique account number
            $accountNumber = $this->generateAccountNumber();
            
            $accountData = [
                'client_id' => $clientId,
                'account_number' => $accountNumber,
                'account_type' => $data['account_type'] ?? 'savings',
                'balance' => floatval($data['balance'] ?? 0),
                'account_status' => 'active'
            ];
            
            $account = $this->db->insert('savings_accounts', $accountData);
            
            // Log audit
            $auditEntry = $this->db->auditLog(
                $_SESSION['user_id'] ?? $clientId,
                'CREATE',
                'savings_accounts',
                $account['savings_id'],
                null,
                $accountData
            );

            if ($auditEntry && is_array($auditEntry) && !empty($auditEntry['audit_id'])) {
                $this->db->insert('compliance_audit', [
                    'audit_id' => $auditEntry['audit_id'],
                    'compliance_check_type' => 'Savings_Account',
                    'compliance_status' => 'pending',
                    'notes' => 'Savings account created',
                    'check_date' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->db->commit();
            return $account;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Savings account creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deposit into a savings account
     */
    public function deposit($savingsId, $amount, $description = '') {
        try {
            $this->db->beginTransaction();
            
            $account = $this->db->getById('savings_accounts', $savingsId, 'savings_id');
            
            if (!$account) {
                throw new Exception('Savings account not found');
            }
            
            if ($account['account_status'] !== 'active') {
                throw new Exception('Account is not active');
            }
            
            // Create transaction record
            $transactionData = [
                'savings_id' => $savingsId,
                'transaction_type' => 'deposit',
                'transaction_amount' => $amount,
                'processed_by' => $_SESSION['user_id'] ?? null,
                'description' => $description ?: 'Deposit'
            ];
            
            $transaction = $this->db->insert('savings_transactions', $transactionData);
            
            // Update account balance
            $newBalance = $account['balance'] + $amount;
            $this->db->update(
                'savings_accounts',
                ['balance' => $newBalance],
                'savings_id = ?',
                [$savingsId]
            );
            
            // Update savings collection monitoring
            $this->updateSavingsMonitoring($account['client_id'], $newBalance);
            
            // Log audit
            $auditEntry = $this->db->auditLog(
                $_SESSION['user_id'],
                'CREATE',
                'savings_transactions',
                $transaction['transaction_id'],
                null,
                $transactionData
            );

            if ($auditEntry && is_array($auditEntry) && !empty($auditEntry['audit_id'])) {
                $this->db->insert('compliance_audit', [
                    'audit_id' => $auditEntry['audit_id'],
                    'compliance_check_type' => 'Savings_Deposit',
                    'compliance_status' => 'pending',
                    'notes' => 'Deposit transaction created',
                    'check_date' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->db->commit();
            return $transaction;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Deposit failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Withdraw from a savings account
     */
    public function withdraw($savingsId, $amount, $description = '') {
        try {
            $this->db->beginTransaction();
            
            $account = $this->db->getById('savings_accounts', $savingsId, 'savings_id');
            
            if (!$account) {
                throw new Exception('Savings account not found');
            }
            
            if ($account['account_status'] !== 'active') {
                throw new Exception('Account is not active');
            }
            
            if ($account['balance'] < $amount) {
                throw new Exception('Insufficient balance');
            }
            
            // Create transaction record
            $transactionData = [
                'savings_id' => $savingsId,
                'transaction_type' => 'withdrawal',
                'transaction_amount' => $amount,
                'processed_by' => $_SESSION['user_id'],
                'description' => $description ?: 'Withdrawal'
            ];
            
            $transaction = $this->db->insert('savings_transactions', $transactionData);
            
            // Update account balance
            $newBalance = $account['balance'] - $amount;
            $this->db->update(
                'savings_accounts',
                ['balance' => $newBalance],
                'savings_id = ?',
                [$savingsId]
            );
            
            // Update savings collection monitoring
            $this->updateSavingsMonitoring($account['client_id'], $newBalance);
            
            // Log audit
            $auditEntry = $this->db->auditLog(
                $_SESSION['user_id'],
                'CREATE',
                'savings_transactions',
                $transaction['transaction_id'],
                null,
                $transactionData
            );

            if ($auditEntry && is_array($auditEntry) && !empty($auditEntry['audit_id'])) {
                $this->db->insert('compliance_audit', [
                    'audit_id' => $auditEntry['audit_id'],
                    'compliance_check_type' => 'Savings_Withdrawal',
                    'compliance_status' => 'pending',
                    'notes' => 'Withdrawal transaction created',
                    'check_date' => date('Y-m-d H:i:s')
                ]);
            }
            
            $this->db->commit();
            return $transaction;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Withdrawal failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Credit interest to savings account
     */
    public function creditInterest($savingsId, $interestAmount, $description = 'Monthly Interest') {
        try {
            return $this->deposit($savingsId, $interestAmount, $description);
        } catch (Exception $e) {
            log_message('ERROR', 'Interest credit failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get savings account details
     */
    public function getSavingsAccount($savingsId) {
        return $this->db->getById('savings_accounts', $savingsId, 'savings_id');
    }

    /**
     * Get client's savings accounts
     */
    public function getClientSavingsAccounts($clientId) {
        $query = "SELECT * FROM savings_accounts WHERE client_id = ? ORDER BY opening_date DESC";
        return $this->db->fetchAll($query, [$clientId]);
    }

    /**
     * Get savings account transaction history
     */
    public function getTransactionHistory($savingsId, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT * FROM savings_transactions WHERE savings_id = ? ORDER BY transaction_date DESC LIMIT ? OFFSET ?";
        
        $transactions = $this->db->fetchAll($query, [$savingsId, $perPage, $offset]);
        
        $total = $this->db->count('savings_transactions', 'savings_id = ?', [$savingsId]);
        
        return [
            'transactions' => $transactions,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Get account statement
     */
    public function getAccountStatement($savingsId, $startDate = null, $endDate = null) {
        $query = "SELECT * FROM savings_transactions WHERE savings_id = ?";
        $params = [$savingsId];
        
        if ($startDate) {
            $query .= " AND transaction_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $query .= " AND transaction_date <= ?";
            $params[] = $endDate;
        }
        
        $query .= " ORDER BY transaction_date ASC";
        
        $transactions = $this->db->fetchAll($query, $params);
        
        // Calculate running balance
        $account = $this->getSavingsAccount($savingsId);
        $runningBalance = 0;
        
        foreach ($transactions as &$transaction) {
            if ($transaction['transaction_type'] === 'deposit' || $transaction['transaction_type'] === 'interest') {
                $runningBalance += $transaction['transaction_amount'];
            } else {
                $runningBalance -= $transaction['transaction_amount'];
            }
            $transaction['running_balance'] = $runningBalance;
        }
        
        return $transactions;
    }

    /**
     * Close a savings account
     */
    public function closeSavingsAccount($savingsId, $reason = '') {
        try {
            $oldData = $this->db->getById('savings_accounts', $savingsId, 'savings_id');
            
            $result = $this->db->update(
                'savings_accounts',
                ['account_status' => 'closed'],
                'savings_id = ?',
                [$savingsId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'savings_accounts',
                $savingsId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'Account closure failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update savings collection monitoring
     */
    private function updateSavingsMonitoring($clientId, $currentBalance) {
        try {
            $existing = $this->db->fetchOne(
                "SELECT * FROM savings_collection_monitoring WHERE client_id = ?",
                [$clientId]
            );
            
            $collectionStatus = $currentBalance > 0 ? 'active' : 'at_risk';
            
            $monitoringData = [
                'current_balance' => $currentBalance,
                'collection_status' => $collectionStatus,
                'last_collection_date' => date('Y-m-d H:i:s')
            ];
            
            if ($existing) {
                $this->db->update(
                    'savings_collection_monitoring',
                    $monitoringData,
                    'client_id = ?',
                    [$clientId]
                );
            } else {
                $monitoringData['client_id'] = $clientId;
                $this->db->insert('savings_collection_monitoring', $monitoringData);
            }
        } catch (Exception $e) {
            log_message('ERROR', 'Savings monitoring update failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate unique account number
     */
    private function generateAccountNumber() {
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        return 'SAV' . $timestamp . $random;
    }

    /**
     * Get total savings balance for a client
     */
    public function getClientTotalBalance($clientId) {
        $query = "SELECT COALESCE(SUM(balance), 0) as total_balance FROM savings_accounts WHERE client_id = ? AND account_status = 'active'";
        $result = $this->db->fetchOne($query, [$clientId]);
        return $result['total_balance'] ?? 0;
    }
}
