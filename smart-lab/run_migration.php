<?php
/**
 * Lab Datasheet Workflow - Database Migration Runner
 * 
 * Usage: php run_migration.php
 * 
 * This script executes the datasheet workflow database migration.
 * It reads the SQL file and applies all schema changes.
 */

define('DOCUMENT_ROOT', __DIR__);

try {
    echo "=== Lab Datasheet Workflow Migration Runner ===\n\n";

    require_once dirname(__DIR__) . '/config/db.php';

    $migrationFile = __DIR__ . '/migrations/datasheet_workflow_migration.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }

    echo "📁 Migration file: $migrationFile\n";
    echo "📊 Database: unilis_smartlab\n\n";

    $mysqli = new mysqli('localhost', 'root', '', 'unilis_smartlab');

    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }

    echo "✓ Database connection established\n\n";

    $sqlContent = file_get_contents($migrationFile);

    $queries = explode(';', $sqlContent);
    $executed = 0;
    $skipped = 0;
    $errors = [];

    foreach ($queries as $query) {
        $query = trim($query);

        if (empty($query) || strpos($query, '--') === 0) {
            continue;
        }

        if (strpos($query, '/*') === 0 || strpos($query, '*') === 0) {
            continue;
        }

        echo "Executing: " . substr($query, 0, 80) . "...\n";

        if ($mysqli->multi_query($query)) {
            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());

            echo "  ✓ Success\n";
            $executed++;
        } else {
            $error = $mysqli->error;

            if (strpos($error, 'already exists') !== false || 
                strpos($error, 'Duplicate') !== false ||
                strpos($error, 'already') !== false) {
                echo "  ⓘ Skipped (already exists)\n";
                $skipped++;
            } else {
                echo "  ✗ Error: $error\n";
                $errors[] = [
                    'query' => substr($query, 0, 100),
                    'error' => $error
                ];
            }
        }
    }

    $mysqli->close();

    echo "\n=== Migration Summary ===\n";
    echo "✓ Executed: $executed\n";
    echo "ⓘ Skipped: $skipped\n";
    if (!empty($errors)) {
        echo "✗ Errors: " . count($errors) . "\n\n";
        foreach ($errors as $err) {
            echo "  Query: " . $err['query'] . "...\n";
            echo "  Error: " . $err['error'] . "\n\n";
        }
    } else {
        echo "✗ Errors: 0\n";
    }

    echo "\n✅ Migration completed successfully!\n\n";

    echo "📋 Next steps:\n";
    echo "1. Verify tables: SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='unilis_smartlab';\n";
    echo "2. Setup practicals: php setup_chemistry_practicals.php\n";
    echo "3. Test dashboard: http://localhost/smart-lab/views/datasheets.php\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
?>
