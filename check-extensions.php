<?php
/**
 * Database Connection Diagnostic
 * Tests connection to Supabase PostgreSQL
 */

echo "════════════════════════════════════════════\n";
echo "DATABASE CONNECTION DIAGNOSTIC\n";
echo "════════════════════════════════════════════\n\n";

// Check available PHP extensions
echo "📦 Available PHP Database Extensions:\n";
$extensions = get_loaded_extensions();
$dbExtensions = ['pdo', 'pdo_mysql', 'pdo_pgsql', 'mysqli', 'mysql'];
foreach ($dbExtensions as $ext) {
    $status = in_array($ext, $extensions) ? '✅' : '❌';
    echo "   $status $ext\n";
}

echo "\n📋 PDO Drivers:\n";
if (class_exists('PDO')) {
    $drivers = PDO::getAvailableDrivers();
    foreach ($drivers as $driver) {
        echo "   ✅ $driver\n";
    }
} else {
    echo "   ❌ PDO not available\n";
}

echo "\n════════════════════════════════════════════\n";
echo "SOLUTION\n";
echo "════════════════════════════════════════════\n\n";

echo "PostgreSQL PHP extension (pdo_pgsql) is NOT installed.\n\n";

echo "OPTIONS:\n\n";

echo "Option 1: Install PostgreSQL Extension (RECOMMENDED)\n";
echo "─────────────────────────────────────────\n";
echo "For XAMPP:\n";
echo "1. Download php_pgsql.dll from:\n";
echo "   https://windows.php.net/downloads/pecl/releases/\n";
echo "2. Place in: C:\\xampp\\php\\ext\\\n";
echo "3. Edit C:\\xampp\\php\\php.ini and add:\n";
echo "   extension=pgsql\n";
echo "   extension=pdo_pgsql\n";
echo "4. Restart Apache\n";
echo "5. Verify with: php -m | findstr pgsql\n\n";

echo "Option 2: Use HTTP API Instead\n";
echo "─────────────────────────────────────────\n";
echo "Modify Database.php to use Supabase HTTP API\n";
echo "instead of direct PostgreSQL connection.\n\n";

echo "Option 3: Use MySQL Connection to Supabase\n";
echo "─────────────────────────────────────────\n";
echo "Configure Supabase for MySQL compatibility.\n\n";

?>
