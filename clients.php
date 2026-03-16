<?php
/**
 * Client Management API Endpoints
 * Handles client registration, KYC verification, and profile management
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/helpers.php';

$request_method = $_SERVER['REQUEST_METHOD'];

if ($request_method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            handleGetClients();
            break;
        case 'get':
            handleGetClient();
            break;
        case 'search':
            handleSearchClients();
            break;
        case 'kyc':
            handleGetKYC();
            break;
        case 'pending_kyc':
            handleGetPendingKYC();
            break;
        default:
            send_response(400, null, 'Invalid action');
    }
} else if ($request_method === 'POST') {
    $action = $_POST['action'] ?? ($_GET['action'] ?? null);
    
    switch ($action) {
        case 'register':
            handleRegisterClient();
            break;
        case 'submit_kyc':
            handleSubmitKYC();
            break;
        case 'verify_kyc':
            handleVerifyKYC();
            break;
        case 'update':
            handleUpdateClient();
            break;
        default:
            send_response(400, null, 'Invalid action');
    }
} else if ($request_method === 'PUT') {
    $action = $_GET['action'] ?? 'update';
    
    if ($action === 'update') {
        handleUpdateClient();
    } else {
        send_response(400, null, 'Invalid action');
    }
} else {
    send_response(405, null, 'Method not allowed');
}

/**
 * Get all clients with pagination
 */
function handleGetClients() {
    apiCall(function() {
        requireRole('KYC Officer');
        
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;
        
        try {
            $clientService = service('client');
            $result = $clientService->getAllClients($page, $perPage);
            
            send_response(200, responsePaginated(
                $result['clients'],
                $result['total'],
                $result['current_page'],
                $perPage
            ), 'Clients retrieved successfully');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Get single client details
 */
function handleGetClient() {
    apiCall(function() {
        requireAuth();
        
        $clientId = $_GET['id'] ?? null;
        
        if (!$clientId) {
            send_response(400, null, 'Client ID required');
        }
        
        try {
            $clientService = service('client');
            $client = $clientService->getClient($clientId);
            
            if (!$client) {
                send_response(404, null, 'Client not found');
            }
            
            // Get additional client information
            $kyc = $clientService->getClientKYC($clientId);
            
            send_response(200, [
                'client' => $client,
                'kyc' => $kyc
            ], 'Client details retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Search clients
 */
function handleSearchClients() {
    apiCall(function() {
        requireAuth();
        
        $searchTerm = $_GET['q'] ?? null;
        
        if (!$searchTerm || strlen($searchTerm) < 3) {
            send_response(400, null, 'Search term must be at least 3 characters');
        }
        
        try {
            $clientService = service('client');
            $clients = $clientService->searchClients($searchTerm);
            
            send_response(200, [
                'results' => $clients,
                'count' => count($clients)
            ], 'Search results retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Register a new client
 */
function handleRegisterClient() {
    apiCall(function() {
        // Allow both authenticated staff and self-registration
        // For self-registration, adjust as needed
        
        $input = getJsonInput();
        
        // Validate required fields
        $missing = validateRequired($input, ['first_name', 'last_name', 'contact_number', 'email', 'address']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        // Validate email
        if (!isValidEmail($input['email'])) {
            send_response(400, null, 'Invalid email format');
        }
        
        // Validate phone
        if (!isValidPhoneNumber($input['contact_number'])) {
            send_response(400, null, 'Invalid phone number format');
        }
        
        try {
            $clientService = service('client');
            $client = $clientService->registerClient($input);
            
            send_response(201, [
                'client' => $client
            ], 'Client registered successfully');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Submit KYC documents
 */
function handleSubmitKYC() {
    apiCall(function() {
        requireAuth();
        
        $clientId = $_POST['client_id'] ?? $_GET['client_id'] ?? null;
        
        if (!$clientId) {
            send_response(400, null, 'Client ID required');
        }
        
        $input = getJsonInput();
        
        $missing = validateRequired($input, ['id_type', 'id_number']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        // Handle file upload if present
        $documentFile = null;
        if (isset($_FILES['document'])) {
            // TODO: Implement file upload handling
            $documentFile = $_FILES['document']['name'];
        }
        
        $input['document_file'] = $documentFile;
        
        try {
            $clientService = service('client');
            $kyc = $clientService->submitKYC($clientId, $input);
            
            send_response(201, [
                'kyc' => $kyc
            ], 'KYC documents submitted');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Verify KYC submission
 */
function handleVerifyKYC() {
    apiCall(function() {
        requireRole('KYC Officer');
        
        $input = getJsonInput();
        
        $missing = validateRequired($input, ['kyc_id', 'status']);
        if ($missing) {
            send_response(400, null, 'Missing fields: ' . implode(', ', $missing));
        }
        
        if (!in_array($input['status'], ['verified', 'rejected'])) {
            send_response(400, null, 'Invalid status. Must be verified or rejected');
        }
        
        try {
            $clientService = service('client');
            $result = $clientService->verifyKYC(
                $input['kyc_id'],
                $input['status'],
                $_SESSION['user_id']
            );
            
            send_response(200, [
                'kyc' => $result[0] ?? null
            ], 'KYC verification processed');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}

/**
 * Get client KYC status
 */
function handleGetKYC() {
    apiCall(function() {
        requireAuth();
        
        $clientId = $_GET['id'] ?? null;
        
        if (!$clientId) {
            send_response(400, null, 'Client ID required');
        }
        
        try {
            $clientService = service('client');
            $kyc = $clientService->getClientKYC($clientId);
            
            send_response(200, [
                'kyc' => $kyc
            ], 'KYC details retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Get pending KYC verifications
 */
function handleGetPendingKYC() {
    apiCall(function() {
        requireRole('KYC Officer');
        
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;
        
        try {
            $clientService = service('client');
            $result = $clientService->getPendingKYCVerifications($page, $perPage);
            
            send_response(200, responsePaginated(
                $result['verifications'],
                $result['total'],
                $result['current_page'],
                $perPage
            ), 'Pending KYC verifications retrieved');
        } catch (Exception $e) {
            send_response(500, null, $e->getMessage());
        }
    });
}

/**
 * Update client details
 */
function handleUpdateClient() {
    apiCall(function() {
        requireAuth();
        
        $clientId = $_GET['id'] ?? $_POST['id'] ?? null;
        
        if (!$clientId) {
            send_response(400, null, 'Client ID required');
        }
        
        $input = getJsonInput();
        
        try {
            $clientService = service('client');
            
            // Check if user can update this client
            // For now, allow KYC Officers and Admins
            if (!hasAnyRole(['Admin', 'KYC Officer'])) {
                send_response(403, null, 'Insufficient permissions');
            }
            
            $result = $clientService->updateClient($clientId, $input);
            
            send_response(200, [
                'client' => $result[0] ?? null
            ], 'Client updated successfully');
        } catch (Exception $e) {
            send_response(400, null, $e->getMessage());
        }
    });
}
