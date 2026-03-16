<?php
/**
 * Database Setup via Supabase HTTP API
 * Executes schema.sql using cURL requests
 */

require_once __DIR__ . '/config.php';

echo "════════════════════════════════════════════\n";
echo "MICRO_FINANCIAL DATABASE SETUP (via HTTP)\n";
echo "════════════════════════════════════════════\n\n";

// Supabase API endpoint
define('SUPABASE_API_URL', 'https://lvvfsgkxpulbpwrpyhuf.supabase.co/rest/v1');

// Read schema file
echo "📂 Reading schema.sql...\n";
$schemaFile = __DIR__ . '/schema.sql';

if (!file_exists($schemaFile)) {
    die("❌ schema.sql not found at: $schemaFile\n");
}

$schemaContent = file_get_contents($schemaFile);
echo "✅ Schema file loaded\n\n";

// Parse SQL statements
$statements = array_filter(
    array_map('trim', explode(';', $schemaContent)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', trim($stmt));
    }
);

echo "📋 Found " . count($statements) . " SQL statements\n\n";

echo "════════════════════════════════════════════\n";
echo "⚠️  MANUAL SETUP REQUIRED\n";
echo "════════════════════════════════════════════\n\n";

echo "PHP PostgreSQL extension is not available on this server.\n";
echo "To create your database tables, use one of these methods:\n\n";

echo "METHOD 1: Using Supabase Dashboard (Recommended)\n";
echo "───────────────────────────────────────\n";
echo "1. Go to: https://app.supabase.com\n";
echo "2. Select your project: lvvfsgkxpulbpwrpyhuf\n";
echo "3. Navigate to: SQL Editor\n";
echo "4. Click: New Query\n";
echo "5. Copy & paste the entire contents of schema.sql\n";
echo "6. Click: Run\n\n";

echo "METHOD 2: Using Terminal (psql)\n";
echo "───────────────────────────────────────\n";
echo "If you have PostgreSQL client tools installed:\n\n";
echo "set PGPASSWORD=Root@040_103\n";
echo "psql -h db.lvvfsgkxpulbpwrpyhuf.supabase.co -p 5432 -U postgres -d postgres -f schema.sql\n\n";

echo "METHOD 3: Install PostgreSQL Extension for PHP\n";
echo "───────────────────────────────────────\n";
echo "For automatic setup, install php-pgsql extension first.\n\n";

echo "════════════════════════════════════════════\n";
echo "SCHEMA SQL PREVIEW\n";
echo "════════════════════════════════════════════\n\n";

// Show first few statements
$preview = array_slice($statements, 0, 3);
foreach ($preview as $stmt) {
    echo "─ " . substr($stmt, 0, 80) . "...\n";
}

echo "\n... and " . (count($statements) - 3) . " more statements\n\n";

echo "💾 Full schema saved in: schema.sql\n";
echo "📊 Total statements: " . count($statements) . "\n\n";

echo "✨ Once tables are created, your Micro_Financial database will be ready!\n";
?>
