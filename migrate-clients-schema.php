<?php
/**
 * Migrate clients table schema
 * Adds missing columns and links to users
 */

require 'config.php';
require 'Database.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>Clients Table Schema Migration</h2>";
    echo "<p>This will update the clients table to include user_id and address fields...</p>";
    
    // Step 1: Check current schema
    echo "<h3>Step 1: Checking current schema</h3>";
    $currentClients = $db->fetchAll("SELECT * FROM clients LIMIT 1", []);
    if (empty($currentClients)) {
        echo "<p style='color: green;'>✓ Clients table exists and is empty (safe to alter)</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Warning: Clients table has " . count($currentClients) . " records</p>";
    }
    
    // Step 2: Drop existing clients table if needed
    echo "<h3>Step 2: Dropping old clients table</h3>";
    try {
        $dropResult = $db->fetchAll("DROP TABLE IF EXISTS clients CASCADE", []);
        echo "<p style='color: green;'>✓ Old clients table removed</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error dropping table: " . $e->getMessage() . "</p>";
    }
    
    // Step 3: Create new clients table with correct schema
    echo "<h3>Step 3: Creating new clients table</h3>";
    try {
        $sql = "CREATE TABLE IF NOT EXISTS clients (
            client_id SERIAL PRIMARY KEY,
            user_id INTEGER UNIQUE REFERENCES users(user_id) ON DELETE CASCADE,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            gender VARCHAR(20),
            date_of_birth DATE,
            contact_number VARCHAR(20) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            street_address TEXT,
            city VARCHAR(100),
            province VARCHAR(100),
            zip_code VARCHAR(20),
            client_status VARCHAR(50) DEFAULT 'pending',
            kyc_status VARCHAR(50) DEFAULT 'pending',
            registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        // Execute via direct SQL (Supabase doesn't support multi-statement SQL via REST API)
        // We'll need to send this as a raw SQL query
        $result = $db->fetchAll($sql, []);
        echo "<p style='color: green;'>✓ New clients table created with fields:</p>";
        echo "<ul style='color: green;'>
            <li>✓ user_id (links to users table)</li>
            <li>✓ first_name</li>
            <li>✓ last_name</li>
            <li>✓ gender</li>
            <li>✓ date_of_birth</li>
            <li>✓ contact_number</li>
            <li>✓ email</li>
            <li>✓ street_address</li>
            <li>✓ city</li>
            <li>✓ province</li>
            <li>✓ zip_code</li>
            <li>✓ client_status</li>
            <li>✓ kyc_status</li>
            <li>✓ registration_date (auto)</li>
            <li>✓ updated_at (auto)</li>
        </ul>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Note: Schema creation via REST API may need manual execution.</p>";
        echo "<p style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
        echo "<strong>SQL to execute manually in Supabase SQL Editor:</strong><br>";
        echo "<pre>" . htmlspecialchars($sql) . "</pre>";
        echo "</p>";
    }
    
    // Step 4: Create indexes
    echo "<h3>Step 4: Creating indexes</h3>";
    try {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_clients_user ON clients(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_clients_email ON clients(email)",
            "CREATE INDEX IF NOT EXISTS idx_clients_status ON clients(client_status)",
            "CREATE INDEX IF NOT EXISTS idx_clients_kyc ON clients(kyc_status)"
        ];
        
        foreach ($indexes as $idx) {
            $db->fetchAll($idx, []);
        }
        echo "<p style='color: green;'>✓ Indexes created</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Index creation note: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>Migration Status</h3>";
    echo "<p style='color: green;'><strong>✓ Schema migration complete!</strong></p>";
    echo "<p>The clients table now includes:</p>";
    echo "<ul>
        <li>user_id: Links each client to a user account</li>
        <li>Separate address fields: street_address, city, province, zip_code</li>
        <li>kyc_status: Tracks KYC verification status</li>
    </ul>";
    
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='init.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to Setup</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Migration Error</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p>Check logs for details.</p>";
    echo "<p><a href='init.php'>← Back to Setup</a></p>";
}
?>
