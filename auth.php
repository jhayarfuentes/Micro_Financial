<?php
/**
 * Authentication API Endpoints
 * Handles user login and session management
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/helpers.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$endpoint = basename(__FILE__, '.php');

// Determine action based on POST parameter or HTTP method
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

if ($request_method === 'POST') {
    switch ($action) {
        case 'login':
            handleLogin();
            break;
        case 'logout':
            handleLogout();
            break;
        case 'register':
            handleRegister();
            break;
        case 'client_register':
            handleClientRegister();
            break;
        case 'change_password':
            handleChangePassword();
            break;
        default:
            send_response(400, null, 'Invalid action');
    }
} else if ($request_method === 'GET') {
    switch ($action) {
        case 'profile':
            handleGetProfile();
            break;
        case 'check_auth':
            handleCheckAuth();
            break;
        default:
            send_response(400, null, 'Invalid action');
    }
} else {
    send_response(405, null, 'Method not allowed');
}

/**
 * Handle user login
 */
function handleLogin() {
    apiCall(function() {
        $input = getJsonInput();
        
        // Validate required fields
        $missing = validateRequired($input, ['username', 'password']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        try {
            $userService = service('user');
            $user = $userService->authenticate($input['username'], $input['password']);
            
            // Store user in session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role_name'];
            
            // Force session to be written
            session_write_close();
            
            send_response(200, [
                'user' => $user,
                'session' => $_SESSION
            ], 'Login successful');
        } catch (Exception $e) {
            send_response(401, null, $e->getMessage());
        }
    });
}

/**
 * Handle user logout
 */
function handleLogout() {
    apiCall(function() {
        session_destroy();
        send_response(200, null, 'Logout successful');
    });
}

/**
 * Handle user registration
 */
function handleRegister() {
    apiCall(function() {
        requireRole('Admin');
        
        $input = getJsonInput();
        
        // Validate required fields
        $missing = validateRequired($input, ['username', 'email', 'password', 'role_id']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        // Validate email
        if (!isValidEmail($input['email'])) {
            send_response(400, null, 'Invalid email format');
        }
        
        try {
            $userService = service('user');
            $user = $userService->createUser($input);
            
            send_response(201, [
                'user' => $user
            ], 'User registered successfully');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Handle client self-registration
 * Public endpoint - no role required
 * ALWAYS creates users with Client role only
 */
function handleClientRegister() {
    apiCall(function() {
        $input = getJsonInput();
        
        // Validate required fields for client registration
        $required = ['first_name', 'last_name', 'email', 'mobile', 'password'];
        $missing = validateRequired($input, $required);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        // Validate email
        if (!isValidEmail($input['email'])) {
            send_response(400, null, 'Invalid email format');
        }
        
        try {
            $db = service('database');
            
            // CRITICAL: Always fetch Client role - this is the ONLY role clients can have
            $clientRole = $db->fetchOne(
                "SELECT role_id FROM user_roles WHERE role_name = ?",
                ['Client']
            );
            
            if (!$clientRole || !isset($clientRole['role_id']) || !$clientRole['role_id']) {
                send_response(500, null, 'Client role not found in system. Please contact administrator.');
                return;
            }
            
            $clientRoleId = $clientRole['role_id'];
            
            // Prepare user data with ONLY Client role - no exceptions
            $userData = [
                'username' => $input['email'], // Use email as username for clients
                'email' => $input['email'],
                'password' => $input['password'],
                'role_id' => $clientRoleId  // ALWAYS Client role, never Admin
            ];
            
            $userService = service('user');
            $newUser = $userService->createUser($userData);
            
            // Verify user_id was created
            if (!isset($newUser['user_id']) || !$newUser['user_id']) {
                throw new Exception('Failed to create user account - user ID not generated');
            }
            
            // Verify the role is Client (belt and suspenders check)
            if (!isset($newUser['role_id']) || $newUser['role_id'] != $clientRoleId) {
                throw new Exception('User was not created with Client role. This is a system error.');
            }
            
            // Now create the client profile with additional information
            $clientData = [
                'user_id' => $newUser['user_id'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'contact_number' => $input['mobile'],
                'gender' => $input['gender'] ?? null,
                'date_of_birth' => $input['date_of_birth'] ?? null,
                'street_address' => $input['street'] ?? null,
                'city' => $input['city'] ?? null,
                'province' => $input['province'] ?? null,
                'zip_code' => $input['zip'] ?? null,
                'client_status' => 'active',
                'kyc_status' => 'pending'
            ];
            
            $clientService = service('client');
            $newClient = $clientService->registerClient($clientData);
            
            // Verify client was created
            if (!isset($newClient['client_id']) || !$newClient['client_id']) {
                throw new Exception('Failed to create client profile');
            }
            
            // Automatically log in the new client with Client role
            if (isset($newUser['user_id'])) {
                $_SESSION['user_id'] = $newUser['user_id'];
            }
            $_SESSION['username'] = $newUser['username'] ?? $userData['username'];
            $_SESSION['role'] = 'Client';  // ALWAYS set to Client, never anything else
            
            // Force session to be written to storage before sending response
            session_write_close();
            
            send_response(201, [
                'user' => $newUser,
                'client' => $newClient,
                'session' => $_SESSION
            ], 'Client account created successfully');
            
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Handle password change
 */
function handleChangePassword() {
    apiCall(function() {
        requireAuth();
        
        $input = getJsonInput();
        
        $missing = validateRequired($input, ['old_password', 'new_password']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        try {
            $userService = service('user');
            $userService->changePassword(
                $_SESSION['user_id'],
                $input['old_password'],
                $input['new_password']
            );
            
            send_response(200, null, 'Password changed successfully');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Handle get user profile
 */
function handleGetProfile() {
    apiCall(function() {
        requireAuth();
        
        try {
            $userService = service('user');
            $user = $userService->getUser($_SESSION['user_id']);
            
            send_response(200, ['user' => $user], 'User profile retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Handle check authentication
 */
function handleCheckAuth() {
    apiCall(function() {
        if (isAuthenticated()) {
            $user = getCurrentUser();
            send_response(200, [
                'authenticated' => true,
                'user' => $user
            ], 'User is authenticated');
        } else {
            send_response(401, [
                'authenticated' => false
            ], 'User is not authenticated');
        }
    });
}
