<?php
/**
 * API Helper Functions
 * Common functions used across all API endpoints
 */

/**
 * Get JSON request body
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Validate required fields
 */
function validateRequired($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Check if user is authenticated
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        send_response(401, null, 'Unauthorized: Please login first');
    }
}

/**
 * Check user role
 */
function requireRole($requiredRoles) {
    requireAuth();
    
    if (!is_array($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }
    
    $userRole = hasRole($requiredRoles[0]);
    if (!$userRole) {
        send_response(403, null, 'Forbidden: Insufficient permissions');
    }
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function isValidPhoneNumber($phone) {
    return preg_match('/^[0-9]{10,15}$/', preg_replace('/[^0-9]/', '', $phone));
}

/**
 * Generate response with pagination
 */
function responsePaginated($data, $total, $page, $perPage) {
    return [
        'items' => $data,
        'pagination' => [
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page,
            'per_page' => $perPage
        ]
    ];
}

/**
 * Try-catch wrapper for API endpoints
 */
function apiCall($callback) {
    try {
        return $callback();
    } catch (Exception $e) {
        log_message('ERROR', $e->getMessage());
        $debugMsg = defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Unknown error';
        send_response(500, null, 'An error occurred: ' . $debugMsg);
    }
}

/**
 * Note: send_response() is defined in config.php to avoid duplicate declarations
 **/
