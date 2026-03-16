<?php
/**
 * User Management & Authentication Service
 * Handles user registration, authentication, and role-based access control
 */

class UserService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create a new user
     */
    public function createUser($data) {
        try {
            $this->db->beginTransaction();
            
            // Validate role_id exists and is not Admin (for security)
            if (!isset($data['role_id']) || !$data['role_id']) {
                throw new Exception('Role ID is required');
            }
            
            $roleId = $data['role_id'];
            
            // Verify role exists in system
            $role = $this->db->fetchOne(
                "SELECT role_id, role_name FROM user_roles WHERE role_id = ?",
                [$roleId]
            );
            
            if (!$role) {
                throw new Exception('Role ID ' . $roleId . ' does not exist in system');
            }
            
            log_message('DEBUG', "Creating user with role: " . $role['role_name'] . " (ID: " . $roleId . ")");
            
            // Check if user already exists
            $existing = $this->db->fetchOne(
                "SELECT * FROM users WHERE username = ? OR email = ?",
                [$data['username'], $data['email']]
            );
            
            if ($existing) {
                throw new Exception('Username or email already exists');
            }
            
            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
            
            $userData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $passwordHash,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'role_id' => $roleId,
                'is_active' => true
            ];
            
            $insertResult = $this->db->insert('users', $userData);
            
            // Fetch the created user to get the auto-generated user_id (simple query for Supabase)
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE username = ?",
                [$userData['username']]
            );
            
            if (!$user) {
                throw new Exception('Failed to retrieve newly created user after insert. Username: ' . $userData['username']);
            }
            
            // Fetch role name separately
            $roleRecord = $this->db->fetchOne(
                "SELECT role_name FROM user_roles WHERE role_id = ?",
                [$user['role_id']]
            );
            
            if ($roleRecord) {
                $user['role_name'] = $roleRecord['role_name'];
            }
            
            // Verify role is correct
            if ($user['role_id'] != $roleId) {
                throw new Exception('User created with wrong role ID. Expected: ' . $roleId . ', Got: ' . $user['role_id']);
            }
            
            log_message('DEBUG', "User created successfully: " . $user['username'] . " with role: " . ($user['role_name'] ?? 'UNKNOWN'));
            
            // Log audit if user_id exists
            if (isset($user['user_id'])) {
                $this->db->auditLog(
                    $_SESSION['user_id'] ?? null,
                    'CREATE',
                    'users',
                    $user['user_id'],
                    null,
                    array_diff_key($userData, ['password_hash' => ''])
                );
            }
            
            $this->db->commit();
            return $user;
        } catch (Exception $e) {
            $this->db->rollback();
            log_message('ERROR', 'User creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        try {
// Fetch user by username or email (supports both login methods)
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE username = ? OR email = ?",
            [$username, $username]
        );
            
            if (!$user) {
                log_message('WARNING', "Login attempt failed for username/email: $username");
                throw new Exception('Invalid username or password');
            }
            
            // Check if user is active
            if ($user['is_active'] === false || $user['is_active'] === 0 || $user['is_active'] === 'false') {
                log_message('WARNING', "Login attempt for inactive user: $username");
                throw new Exception('User account is inactive');
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                log_message('WARNING', "Invalid password attempt for user: $username");
                throw new Exception('Invalid username or password');
            }
            
            // Fetch role name
            $role = $this->db->fetchOne(
                "SELECT role_name FROM user_roles WHERE role_id = ?",
                [$user['role_id']]
            );
            $roleName = $role ? $role['role_name'] : 'User';
            
            // Update last login
            $this->db->update(
                'users',
                ['last_login' => date('Y-m-d H:i:s')],
                'user_id = ?',
                [$user['user_id']]
            );
            
            // Log audit
            $this->db->auditLog(
                $user['user_id'],
                'LOGIN',
                'users',
                $user['user_id']
            );
            
            return [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'role_id' => $user['role_id'],
                'role_name' => $roleName
            ];
        } catch (Exception $e) {
            log_message('ERROR', 'Authentication failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user details
     */
    public function getUser($userId) {
        $query = "SELECT u.*, r.role_name FROM users u 
                  JOIN user_roles r ON u.role_id = r.role_id
                  WHERE u.user_id = ?";
        return $this->db->fetchOne($query, [$userId]);
    }

    /**
     * Get all users
     */
    public function getAllUsers($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT u.*, r.role_name FROM users u 
                  JOIN user_roles r ON u.role_id = r.role_id
                  ORDER BY u.user_id DESC
                  LIMIT ? OFFSET ?";
        
        $users = $this->db->fetchAll($query, [$perPage, $offset]);
        
        $total = $this->db->count('users');
        
        return [
            'users' => $users,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Update user details
     */
    public function updateUser($userId, $data) {
        try {
            $oldData = $this->getUser($userId);
            
            // Don't allow direct password update through this method
            unset($data['password_hash']);
            unset($data['password']);
            
            $result = $this->db->update(
                'users',
                $data,
                'user_id = ?',
                [$userId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'users',
                $userId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'User update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        try {
            $user = $this->getUser($userId);
            
            // Verify old password
            if (!password_verify($oldPassword, $user['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }
            
            // Hash new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $this->db->update(
                'users',
                ['password_hash' => $newPasswordHash],
                'user_id = ?',
                [$userId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'users',
                $userId,
                null,
                ['password_changed' => true]
            );
            
            return true;
        } catch (Exception $e) {
            log_message('ERROR', 'Password change failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deactivate user
     */
    public function deactivateUser($userId) {
        try {
            $oldData = $this->getUser($userId);
            
            $result = $this->db->update(
                'users',
                ['is_active' => false],
                'user_id = ?',
                [$userId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'users',
                $userId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'User deactivation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Activate user
     */
    public function activateUser($userId) {
        try {
            $oldData = $this->getUser($userId);
            
            $result = $this->db->update(
                'users',
                ['is_active' => true],
                'user_id = ?',
                [$userId]
            );
            
            // Log audit
            $this->db->auditLog(
                $_SESSION['user_id'],
                'UPDATE',
                'users',
                $userId,
                $oldData,
                $result[0] ?? null
            );
            
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'User activation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check user permission
     */
    public function hasPermission($userId, $action, $resource) {
        // This is a basic implementation
        // You can expand this based on your role-based access control requirements
        $user = $this->getUser($userId);
        
        // Admin has all permissions
        if ($user['role_name'] === 'Admin') {
            return true;
        }
        
        // Add role-specific permission logic here
        $permissions = [
            'Loan Officer' => ['create_loan', 'approve_loan', 'view_loan'],
            'KYC Officer' => ['verify_kyc', 'view_client'],
            'Loan Collector' => ['record_payment', 'view_loan'],
            'Savings Officer' => ['manage_savings', 'view_account'],
            'Client' => ['view_own_account', 'apply_loan']
        ];
        
        $rolePermissions = $permissions[$user['role_name']] ?? [];
        $actionKey = $action . '_' . $resource;
        
        return in_array($actionKey, $rolePermissions);
    }

    /**
     * Get user's role
     */
    public function getUserRole($userId) {
        $user = $this->getUser($userId);
        return $user['role_name'];
    }

    /**
     * Get all roles
     */
    public function getAllRoles() {
        return $this->db->fetchAll("SELECT * FROM user_roles ORDER BY role_id ASC");
    }

    /**
     * Get users by role
     */
    public function getUsersByRole($roleId, $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT u.*, r.role_name FROM users u 
                  JOIN user_roles r ON u.role_id = r.role_id
                  WHERE u.role_id = ? AND u.is_active = true
                  ORDER BY u.user_id DESC
                  LIMIT ? OFFSET ?";
        
        $users = $this->db->fetchAll($query, [$roleId, $perPage, $offset]);
        
        $total = $this->db->count('users', 'role_id = ? AND is_active = true', [$roleId]);
        
        return [
            'users' => $users,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }
}
