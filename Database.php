<?php
/**
 * Supabase HTTP API Database Wrapper
 * Uses REST API instead of direct PostgreSQL connection
 */

class Database {
    private static $instance = null;
    private $apiUrl = '';
    private $apiKey = '';
    private $headers = [];

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->apiUrl = 'https://lvvfsgkxpulbpwrpyhuf.supabase.co/rest/v1';
        $this->apiKey = SUPABASE_SECRET_KEY;
        
        $this->headers = [
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        log_message('INFO', 'Supabase HTTP API connection initialized');
    }

    /**
     * Execute a query using Supabase REST API
     */
    private function apiRequest($method, $endpoint, $data = null, $query = '', $returnRepresentation = false) {
        $url = $this->apiUrl . '/' . $endpoint;
        if ($query) {
            $url .= '?' . $query;
        }

        log_message('DEBUG', "API Request: $method $url");
        
        $headers = $this->headers;
        
        // For POST requests, request the inserted data back
        if ($returnRepresentation) {
            $headers[] = 'Prefer: return=representation';
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            // For local development environment, allow self-signed CA chains if cert store is missing
            CURLOPT_SSL_VERIFYPEER => (defined('APP_ENV') && APP_ENV === 'development') ? false : true,
            CURLOPT_SSL_VERIFYHOST => (defined('APP_ENV') && APP_ENV === 'development') ? 0 : 2
        ]);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log detailed response info
        log_message('DEBUG', "API Response [HTTP $httpCode]: " . substr($response ?? '', 0, 200));

        if ($error) {
            log_message('ERROR', 'Supabase curl error: ' . $error);
            throw new Exception('Database request failed: ' . $error);
        }

        if ($httpCode >= 400) {
            log_message('ERROR', "Supabase API HTTP $httpCode: " . $response);
            
            // Extract error details from response
            $errorDetails = '';
            if (!empty($response)) {
                $decoded = @json_decode($response, true);
                if (is_array($decoded)) {
                    // Supabase returns error details in specific formats
                    if (isset($decoded['message'])) {
                        $errorDetails = $decoded['message'];
                    } elseif (isset($decoded['error'])) {
                        $errorDetails = $decoded['error'];
                    } elseif (isset($decoded['details'])) {
                        $errorDetails = $decoded['details'];
                    } elseif (isset($decoded[0]['message'])) {
                        $errorDetails = $decoded[0]['message'];
                    }
                }
            }
            
            // Provide more helpful error messages
            if ($httpCode === 401) {
                throw new Exception('Authentication failed: Invalid or expired Supabase API key. Please verify SUPABASE_SECRET_KEY in config.php');
            } elseif ($httpCode === 403) {
                throw new Exception('Access denied: Insufficient permissions for this API key');
            } elseif ($httpCode === 404) {
                throw new Exception('Not found: The requested table or resource does not exist');
            } elseif ($httpCode === 400) {
                $msg = 'Database request failed with status 400';
                if (!empty($errorDetails)) {
                    $msg .= ': ' . $errorDetails;
                }
                throw new Exception($msg);
            }
            
            throw new Exception('Database request failed with status ' . $httpCode);
        }

        if (empty($response)) {
            log_message('WARNING', "Empty response from Supabase API for endpoint: $endpoint");
            return [];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('ERROR', 'JSON decode error: ' . json_last_error_msg() . ' Response: ' . $response);
            throw new Exception('Invalid JSON response from database: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Get database connection (not needed for REST API)
     */
    public function getConnection() {
        return $this;
    }

    /**
     * Fetch one record
     */
    public function fetchOne($query, $params = []) {
        try {
            // Parse COUNT queries first
            if (preg_match('/SELECT\s+COUNT\s*\(\s*\*\s*\)\s+AS\s+(\w+)\s+FROM\s+(\w+)/i', $query, $countMatches)) {
                $table = $countMatches[2];
                if (empty($whereClause = trim(preg_replace('/SELECT\s+COUNT\s*\(\s*\*\s*\)\s+AS\s+\w+\s+FROM\s+\w+/i', '', $query)))) {
                    $rows = $this->fetchAll("SELECT * FROM {$table}");
                    return ['ct' => is_array($rows) ? count($rows) : 0];
                }
            }

            // Parse SELECT queries for REST API
            // Handle: SELECT * FROM table WHERE column = ?
            if (preg_match('/SELECT\s+.*\s+FROM\s+(\w+)(?:\s+(?:as\s+)?(\w+))?\s+WHERE\s+(\w+(?:\.\w+)?)\s*=\s*\?/i', $query, $matches)) {
                $table = $matches[1];
                $column = $matches[3];
                
                // Handle prefixed columns like u.username -> username
                if (strpos($column, '.') !== false) {
                    $column = explode('.', $column)[1];
                }
                
                $value = $params[0] ?? null;
                
                // Properly encode the filter value for Supabase (URL-encode special characters)
                $encodedValue = urlencode($value);
                $filter = "{$column}=eq.{$encodedValue}";
                
                $result = $this->apiRequest('GET', $table, null, 'select=*&' . $filter . '&limit=1');
                return isset($result[0]) ? $result[0] : null;
            }
            
            // Fallback for simple queries
            log_message('WARNING', 'Query not recognized by parser: ' . $query);
            return null;
        } catch (Exception $e) {
            log_message('ERROR', 'fetchOne failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch record by primary key id column
     */
    public function getById($table, $id, $idColumn = 'id') {
        try {
            if (!$table || !$idColumn) {
                throw new Exception('Table and idColumn are required for getById');
            }
            $idValue = $id;
            if ($idValue === null || $idValue === '') {
                return null;
            }
            // URL encode in Supabase GET filter, no SQL injection risk in REST approach.
            $result = $this->apiRequest('GET', $table, null, 'select=*&' . urlencode($idColumn) . '=eq.' . urlencode($idValue) . '&limit=1');
            return isset($result[0]) ? $result[0] : null;
        } catch (Exception $e) {
            log_message('ERROR', 'getById failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch all records
     */
    public function fetchAll($query, $params = []) {
        try {
            // Parse SELECT queries with optional ORDER BY and LIMIT
            if (preg_match('/SELECT.*FROM\s+(\w+)(?:\s+(?:as\s+)?(\w+))?(?:\s+WHERE\s+(.+?))?(?:\s+ORDER\s+BY\s+(.+?))?(?:\s+LIMIT\s+(\d+))?$/i', $query, $matches)) {
                $table = $matches[1];
                $whereClause = $matches[3] ?? null;
                $orderBy = $matches[4] ?? null;
                $limit = $matches[5] ?? null;
                
                $queryStr = 'select=*';
                
                // Add ordering if specified (e.g., "registration_date DESC" -> "registration_date.desc")
                if ($orderBy) {
                    $orderBy = trim($orderBy);
                    // Parse multiple order clauses and convert to Supabase format
                    $orderClauses = preg_split('/\s*,\s*/', $orderBy);
                    $supabaseOrder = [];
                    foreach ($orderClauses as $clause) {
                        if (preg_match('/(\w+(?:\.\w+)?)\s+(asc|desc)?/i', $clause, $orderMatch)) {
                            $column = $orderMatch[1];
                            $direction = strtolower($orderMatch[2] ?? 'asc');
                            $supabaseOrder[] = "{$column}.{$direction}";
                        }
                    }
                    if (!empty($supabaseOrder)) {
                        $queryStr .= '&order=' . urlencode(implode(',', $supabaseOrder));
                    }
                }
                
                // Add WHERE filter (supports = ? and IN (?))
                if (!empty($whereClause)) {
                    $whereClause = trim($whereClause);
                    // IN clause support
                    if (preg_match('/^(\w+(?:\.\w+)?)\s+IN\s*\((.+)\)$/i', $whereClause, $inMatches)) {
                        $column = $inMatches[1];
                        if (strpos($column, '.') !== false) {
                            $column = explode('.', $column)[1];
                        }
                        $valuesPart = trim($inMatches[2]);
                        $values = [];
                        if (strpos($valuesPart, '?') !== false) {
                            $placeholderCount = substr_count($valuesPart, '?');
                            for ($i = 0; $i < $placeholderCount; $i++) {
                                if (isset($params[$i])) {
                                    $values[] = $params[$i];
                                }
                            }
                        } else {
                            $literalValues = array_map('trim', explode(',', $valuesPart));
                            foreach ($literalValues as $lit) {
                                $lit = trim($lit, " '\"");
                                $values[] = $lit;
                            }
                        }
                        if (!empty($values)) {
                            $queryStr .= '&' . urlencode($column) . '=in.(' . implode(',', array_map('urlencode', $values)) . ')';
                        }
                    } elseif (preg_match('/^(\w+(?:\.\w+)?)\s*=\s*\?$/i', $whereClause, $eqMatches)) {
                        $column = $eqMatches[1];
                        if (strpos($column, '.') !== false) {
                            $column = explode('.', $column)[1];
                        }
                        $value = $params[0] ?? '';
                        $queryStr .= '&' . urlencode($column) . '=eq.' . urlencode($value);
                    }
                }

                // Add ordering if specified (e.g., "registration_date DESC" -> "registration_date.desc")
                if ($orderBy) {
                    $orderBy = trim($orderBy);
                    // Parse multiple order clauses and convert to Supabase format
                    $orderClauses = preg_split('/\s*,\s*/', $orderBy);
                    $supabaseOrder = [];
                    foreach ($orderClauses as $clause) {
                        if (preg_match('/(\w+(?:\.\w+)?)\s+(asc|desc)?/i', $clause, $orderMatch)) {
                            $column = $orderMatch[1];
                            $direction = strtolower($orderMatch[2] ?? 'asc');
                            $supabaseOrder[] = "{$column}.{$direction}";
                        }
                    }
                    if (!empty($supabaseOrder)) {
                        $queryStr .= '&order=' . urlencode(implode(',', $supabaseOrder));
                    }
                }

                // Add limit if specified
                if ($limit) {
                    $queryStr .= '&limit=' . intval($limit);
                }

                return $this->apiRequest('GET', $table, null, $queryStr);
            }
            
            return [];
        } catch (Exception $e) {
            log_message('ERROR', 'fetchAll failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Insert record
     */
    public function insert($table, $data) {
        try {
            log_message('DEBUG', "Inserting into table '$table' with fields: " . implode(', ', array_keys($data)));
            log_message('DEBUG', "Insert data: " . json_encode($data));
            
            // Request "Prefer: return=representation" to get the inserted record back
            $result = $this->apiRequest('POST', $table, [$data], '', true);
            return isset($result[0]) ? $result[0] : $data;
        } catch (Exception $e) {
            log_message('ERROR', 'Insert into ' . $table . ' failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update record
     */
    public function update($table, $data, $where = '', $params = []) {
        try {
            if (preg_match('/(\w+)\s*=\s*\?/', $where, $matches)) {
                $column = $matches[1];
                $value = $params[0] ?? null;
                $encodedValue = urlencode($value);
                $filter = "{$column}=eq.{$encodedValue}";
                
                return $this->apiRequest('PATCH', $table, $data, $filter);
            }
            
            return null;
        } catch (Exception $e) {
            log_message('ERROR', 'Update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Count records
     */
    public function count($table, $where = '', $params = []) {
        try {
            // Try direct REST count for basic equality WHERE clauses
            if (empty($where)) {
                $rows = $this->fetchAll("SELECT * FROM {$table}");
                return is_array($rows) ? count($rows) : 0;
            }

            if (preg_match('/^(\w+(?:\.\w+)?)\s*=\s*\?$/', trim($where), $matches)) {
                $column = $matches[1];
                $value = $params[0] ?? null;
                $encodedValue = urlencode($value);
                $result = $this->apiRequest('GET', $table, null, 'select=*&' . urlencode($column) . '=eq.' . $encodedValue);
                return is_array($result) ? count($result) : 0;
            }

            // Fallback: fetch matching rows and count
            $rows = $this->fetchAll("SELECT * FROM {$table} WHERE {$where}");
            return is_array($rows) ? count($rows) : 0;
        } catch (Exception $e) {
            log_message('ERROR', 'Count failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Execute raw query (limited support)
     */
    public function execute($query, $params = []) {
        throw new Exception('Raw SQL queries not supported via REST API. Use fetchOne, fetchAll, insert, update, or delete methods.');
    }

    /**
     * Begin transaction (not supported via REST API)
     */
    public function beginTransaction() {
        return true;
    }

    /**
     * Commit transaction (not supported via REST API)
     */
    public function commit() {
        return true;
    }

    /**
     * Rollback transaction (not supported via REST API)
     */
    public function rollback() {
        return true;
    }

    /**
     * Audit log (writes to audit_trail)
     */
    public function auditLog($userId, $action, $table, $recordId, $oldValues = null, $newValues = null) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $payload = [
                'user_id' => $userId,
                'action_type' => $action,
                'table_name' => $table,
                'record_id' => $recordId,
                'old_values' => (is_array($oldValues) || is_object($oldValues)) ? $oldValues : null,
                'new_values' => (is_array($newValues) || is_object($newValues)) ? $newValues : null,
                'action_timestamp' => date('Y-m-d H:i:s'),
                'ip_address' => $ipAddress
            ];

            $result = $this->insert('audit_trail', $payload);
            return $result;
        } catch (Exception $e) {
            log_message('ERROR', 'auditLog failed: ' . $e->getMessage());
            return false;
        }
    }
}
?>
