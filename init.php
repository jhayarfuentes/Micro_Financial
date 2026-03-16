<?php
/**
 * Auto-loader and Service Initializer
 * Loads all required classes and initializes services
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Load Database class
require_once __DIR__ . '/Database.php';

// Load all Service classes
require_once __DIR__ . '/ClientService.php';
require_once __DIR__ . '/LoanService.php';
require_once __DIR__ . '/RepaymentService.php';
require_once __DIR__ . '/SavingsService.php';
require_once __DIR__ . '/GroupLendingService.php';
require_once __DIR__ . '/UserService.php';
require_once __DIR__ . '/ComplianceService.php';
require_once __DIR__ . '/ReportsService.php';

/**
 * Service Container - Provides access to all services
 */
class ServiceContainer {
    private static $services = [];

    /**
     * Get service instance
     */
    public static function get($serviceName) {
        if (!isset(self::$services[$serviceName])) {
            self::$services[$serviceName] = self::createService($serviceName);
        }
        return self::$services[$serviceName];
    }

    /**
     * Create service instance
     */
    private static function createService($serviceName) {
        switch ($serviceName) {
            case 'client':
                return new ClientService();
            case 'loan':
                return new LoanService();
            case 'repayment':
                return new RepaymentService();
            case 'savings':
                return new SavingsService();
            case 'group':
                return new GroupLendingService();
            case 'user':
                return new UserService();
            case 'compliance':
                return new ComplianceService();
            case 'reports':
                return new ReportsService();
            case 'database':
                return Database::getInstance();
            default:
                throw new Exception("Service '$serviceName' not found");
        }
    }

    /**
     * Get all available services
     */
    public static function getAvailableServices() {
        return [
            'client' => 'ClientService',
            'loan' => 'LoanService',
            'repayment' => 'RepaymentService',
            'savings' => 'SavingsService',
            'group' => 'GroupLendingService',
            'user' => 'UserService',
            'compliance' => 'ComplianceService',
            'reports' => 'ReportsService',
            'database' => 'Database'
        ];
    }
}

// Utility function for easier service access
function service($name) {
    return ServiceContainer::get($name);
}

// Helper function to check if user is authenticated
function isAuthenticated() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

// Helper function to get current user
function getCurrentUser() {
    if (isAuthenticated()) {
        return service('user')->getUser($_SESSION['user_id']);
    }
    return null;
}

// Helper function to check user roles
function hasRole($roleName) {
    // First try session (faster, always available after login)
    if (isset($_SESSION['role']) && $_SESSION['role'] === $roleName) {
        return true;
    }
    
    // Fallback to database query
    $user = getCurrentUser();
    return $user && isset($user['role_name']) && $user['role_name'] === $roleName;
}

// Helper function to check any role from array
function hasAnyRole($roleArray) {
    // First try session (faster, always available after login)
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], $roleArray)) {
        return true;
    }
    
    // Fallback to database query
    $user = getCurrentUser();
    return $user && isset($user['role_name']) && in_array($user['role_name'], $roleArray);
}

// Helper for CT2/CT3 transaction summary counts
function getCt2Ct3Status() {
    $status = [
        'ct2count' => 0,
        'ct3count' => 0,
        'ctConnectionStatus' => 'UNKNOWN',
        'ctConnectionMessage' => 'Not checked'
    ];

    try {
        $db = Database::getInstance();
        $status['ct2count'] = $db->count('compliance_audit');
        $status['ct3count'] = $db->count('loan_applications');
        $status['ctConnectionStatus'] = 'OK';
        $status['ctConnectionMessage'] = 'CT2/CT3 metrics loaded';
    } catch (Exception $e) {
        $status['ctConnectionStatus'] = 'DOWN';
        $status['ctConnectionMessage'] = 'CT2/CT3 load failed: ' . $e->getMessage();
    }

    return $status;
}
