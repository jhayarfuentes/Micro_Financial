<?php
/**
 * Client Registration & KYC Service
 * Handles client registration and KYC verification
 */

class ClientService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Register a new client
     */
    public function registerClient($data) {
        try {
            $this->db->beginTransaction();
            
            // Insert client
            $clientData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
                'street_address' => $data['street_address'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
                'client_status' => $data['client_status'] ?? 'pending',
                'kyc_status' => $data['kyc_status'] ?? 'pending',
                'user_id' => $data['user_id'] ?? null
            ];
            
            $insertResult = $this->db->insert('clients', $clientData);
            
            // Fetch the created client to get the auto-generated client_id
            $client = $this->db->fetchOne(
                "SELECT * FROM clients WHERE user_id = ? OR email = ? LIMIT 1",
                [$data['user_id'] ?? null, $data['email']]
            );
            
            if (!$client) {
                throw new Exception('Failed to retrieve newly created client profile');
            }
            
            // Log audit if client_id exists
            if (isset($client['client_id'])) {
                $this->db->auditLog(
                    $_SESSION['user_id'] ?? null,
                    'CREATE',
                    'clients',
                    $client['client_id'],
                    null,
                    $clientData
                );
            }
            
            $this->db->commit();
            return $client;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'Client registration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Submit KYC verification for a client
     */
    public function submitKYC($clientId, $data) {
        try {
            $kycData = [
                'client_id' => $clientId,
                'id_type' => $data['id_type'],
                'id_number' => $data['id_number'],
                'document_file' => $data['document_file'] ?? null,
                'verification_status' => 'pending'
            ];
            
            $insertResult = $this->db->insert('kyc_verification', $kycData);
            
            // Fetch the created KYC record to get the auto-generated kyc_id
            $kyc = $this->db->fetchOne(
                "SELECT * FROM kyc_verification WHERE client_id = ? AND id_number = ? LIMIT 1",
                [$clientId, $data['id_number']]
            );
            
            if (!$kyc) {
                throw new Exception('Failed to retrieve KYC verification record');
            }
            
            // Log audit if kyc_id exists
            if (isset($kyc['kyc_id'])) {
                $this->db->auditLog(
                    $_SESSION['user_id'] ?? null,
                    'CREATE',
                    'kyc_verification',
                    $kyc['kyc_id'],
                    null,
                    $kycData
                );
            }
            
            return $kyc;
        } catch (Exception $e) {
            log_message('ERROR', 'KYC submission failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify KYC submission
     */
    public function verifyKYC($kycId, $status, $verifiedBy = null) {
        try {
            $verifiedBy = $verifiedBy ?? $_SESSION['user_id'];
            
            $oldData = $this->db->getById('kyc_verification', $kycId, 'kyc_id');
            
            $updateData = [
                'verification_status' => $status,
                'verified_by' => $verifiedBy,
                'verification_date' => date('Y-m-d H:i:s')
            ];
            
            $result = $this->db->update('kyc_verification', $updateData, 'kyc_id = ?', [$kycId]);
            
            // Update client status if verified
            if ($status === 'verified') {
                $this->db->update('clients', ['client_status' => 'active'], 'client_id = ?', [$oldData['client_id']]);
            }
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'kyc_verification',
                $kycId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'KYC verification failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get client details
     */
    public function getClient($clientId) {
        return $this->db->getById('clients', $clientId, 'client_id');
    }

    /**
     * Get client KYC details
     */
    public function getClientKYC($clientId) {
        $query = "SELECT * FROM kyc_verification WHERE client_id = ? ORDER BY kyc_id DESC LIMIT 1";
        return $this->db->fetchOne($query, [$clientId]);
    }

    /**
     * Get all clients with pagination
     */
    public function getAllClients($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT * FROM clients ORDER BY registration_date DESC LIMIT ? OFFSET ?";
        $clients = $this->db->fetchAll($query, [$perPage, $offset]);
        
        $total = $this->db->count('clients');
        
        return [
            'clients' => $clients,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Search clients
     */
    public function searchClients($searchTerm) {
        $query = "SELECT * FROM clients WHERE 
                  first_name ILIKE ? OR 
                  last_name ILIKE ? OR 
                  email ILIKE ? OR 
                  contact_number ILIKE ?
                  ORDER BY registration_date DESC";
        
        $term = "%$searchTerm%";
        return $this->db->fetchAll($query, [$term, $term, $term, $term]);
    }

    /**
     * Update client details
     */
    public function updateClient($clientId, $data) {
        try {
            $oldData = $this->db->getById('clients', $clientId, 'client_id');
            
            $result = $this->db->update('clients', $data, 'client_id = ?', [$clientId]);
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'clients',
                $clientId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'Client update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get pending KYC verifications
     */
    public function getPendingKYCVerifications($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT k.*, c.first_name, c.last_name, c.email 
                  FROM kyc_verification k
                  JOIN clients c ON k.client_id = c.client_id
                  WHERE k.verification_status = 'pending'
                  ORDER BY k.kyc_id ASC
                  LIMIT ? OFFSET ?";
        
        $verifications = $this->db->fetchAll($query, [$perPage, $offset]);
        
        $total = $this->db->count('kyc_verification', 'verification_status = ?', ['pending']);
        
        return [
            'verifications' => $verifications,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }
}
