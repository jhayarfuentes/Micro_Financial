<?php
/**
 * Generate Clean SQL for Supabase Web Editor
 */

echo "════════════════════════════════════════════\n";
echo "SUPABASE SCHEMA SETUP GUIDE\n";
echo "════════════════════════════════════════════\n\n";

echo "✅ Your schema.sql file is ready!\n\n";

echo "To create your database tables:\n\n";

echo "STEP 1: Open Supabase Dashboard\n";
echo "  └─ Visit: https://app.supabase.com\n\n";

echo "STEP 2: Select Your Project\n";
echo "  └─ Click on project: lvvfsgkxpulbpwrpyhuf\n\n";

echo "STEP 3: Open SQL Editor\n";
echo "  └─ Left sidebar → SQL Editor\n\n";

echo "STEP 4: Create New Query\n";
echo "  └─ Click: New Query\n\n";

echo "STEP 5: Copy Schema SQL\n";
echo "  └─ Open the file: schema.sql in this directory\n";
echo "  └─ Copy ALL the contents\n";
echo "  └─ Paste into the SQL editor\n\n";

echo "STEP 6: Execute\n";
echo "  └─ Click: Run or press Ctrl+Enter\n\n";

echo "════════════════════════════════════════════\n";
echo "WHAT WILL BE CREATED:\n";
echo "════════════════════════════════════════════\n\n";

// Parse schema to show what will be created
$schema = file_get_contents(__DIR__ . '/schema.sql');
preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+(\w+)/i', $schema, $tables);
preg_match_all('/CREATE\s+INDEX\s+\w+\s+ON\s+(\w+)/i', $schema, $indexes);

$tableList = array_unique($tables[1] ?? []);
$indexList = $indexes[1] ?? [];

echo "📊 TABLES (" . count($tableList) . "):\n";
foreach ($tableList as $table) {
    echo "  ✓ $table\n";
}

echo "\n📑 INDEXES (" . count($indexList) . "):\n";
foreach ($indexList as $index) {
    echo "  ✓ $index\n";
}

echo "\n════════════════════════════════════════════\n";
echo "DATABASE CONNECTION INFO\n";
echo "════════════════════════════════════════════\n";
echo "Host:     db.lvvfsgkxpulbpwrpyhuf.supabase.co\n";
echo "Port:     5432\n";
echo "Database: postgres\n";
echo "User:     postgres\n";
echo "Password: [configured in config.php]\n\n";

echo "✨ After completing these steps, your database will be ready!\n\n";
?>
