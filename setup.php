<?php
/**
 * Database Setup Script
 * Executes schema.sql against Supabase PostgreSQL database
 */

require_once __DIR__ . '/config.php';

echo "════════════════════════════════════════════\n";
echo "MICRO_FINANCIAL DATABASE SETUP\n";
echo "════════════════════════════════════════════\n\n";

try {
    // Create PDO connection
    $dsn = "pgsql:host=" . SUPABASE_DB_HOST . ";port=" . SUPABASE_DB_PORT . ";dbname=" . SUPABASE_DB_NAME;
    
    echo "📡 Connecting to Supabase PostgreSQL...\n";
    echo "   Host: " . SUPABASE_DB_HOST . "\n";
    echo "   Database: " . SUPABASE_DB_NAME . "\n\n";
    
    $pdo = new PDO($dsn, SUPABASE_DB_USER, SUPABASE_DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Connected successfully!\n\n";
    
    // Read schema file
    echo "📂 Reading schema.sql...\n";
    $schemaFile = __DIR__ . '/schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at: $schemaFile");
    }
    
    $schemaContent = file_get_contents($schemaFile);
    echo "✅ Schema file loaded\n\n";
    
    // Split statements by semicolon
    $statements = array_filter(
        array_map('trim', explode(';', $schemaContent)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', trim($stmt));
        }
    );
    
    echo "📋 Found " . count($statements) . " SQL statements\n\n";
    echo "════════════════════════════════════════════\n";
    echo "EXECUTING SCHEMA\n";
    echo "════════════════════════════════════════════\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        // Extract table name if it's a CREATE TABLE statement
        preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(\w+)/i', $statement, $matches);
        $tableName = $matches[1] ?? 'Statement #' . ($index + 1);
        
        try {
            $pdo->exec($statement);
            echo "✅ $tableName\n";
            $successCount++;
        } catch (PDOException $e) {
            echo "❌ $tableName - Error: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    echo "\n════════════════════════════════════════════\n";
    echo "SETUP COMPLETE\n";
    echo "════════════════════════════════════════════\n";
    echo "✅ Successfully created: $successCount tables/indexes\n";
    
    if ($errorCount > 0) {
        echo "⚠️  Errors encountered: $errorCount\n";
    }
    
    echo "\n📊 Database Tables Created:\n";
    
    // List all tables
    $tables = $pdo->query("
        SELECT tablename FROM pg_tables 
        WHERE schemaname = 'public' 
        ORDER BY tablename
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "   • $table\n";
    }
    
    echo "\n✨ Your Micro_Financial database is ready to use!\n";
    
} catch (PDOException $e) {
    echo "\n❌ CONNECTION ERROR\n";
    echo "════════════════════════════════════════════\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERROR\n";
    echo "════════════════════════════════════════════\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
