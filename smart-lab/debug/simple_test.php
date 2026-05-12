<?php
/**
 * Simple test to check if basic PHP functionality works
 */

echo "PHP is working<br>";

// Test config loading
try {
    require_once __DIR__ . '/../config/app.php';
    echo "Config loaded successfully<br>";
} catch (Exception $e) {
    echo "Config load failed: " . $e->getMessage() . "<br>";
}

// Test database
try {
    require_once __DIR__ . '/../config/database_production.php';
    $db = getDB();
    echo "Database connection successful<br>";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "<br>";
}

// Test model
try {
    require_once __DIR__ . '/../models/PracticalModel.php';
    $model = new PracticalModel();
    echo "Model instantiation successful<br>";
} catch (Exception $e) {
    echo "Model instantiation failed: " . $e->getMessage() . "<br>";
}

// Test helpers
try {
    require_once __DIR__ . '/../utils/helpers.php';
    echo "Helpers loaded successfully<br>";
} catch (Exception $e) {
    echo "Helpers load failed: " . $e->getMessage() . "<br>";
}

echo "<br>Basic functionality test completed.";
?>
