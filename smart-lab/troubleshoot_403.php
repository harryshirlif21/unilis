<?php
/**
 * 403 Forbidden Troubleshooting Script for UNILIS SmartLab
 * Run this script to diagnose the cause of 403 errors
 */

echo "<!DOCTYPE html><html><head><title>SmartLab 403 Troubleshooting</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>";
echo "</head><body><h1>SmartLab 403 Forbidden Troubleshooting</h1>";

// Check 1: File existence
echo "<h2>1. File Existence Check</h2>";
if (file_exists('index.php')) {
    echo "<p class='ok'>✓ index.php exists</p>";
} else {
    echo "<p class='error'>✗ index.php NOT FOUND - This is the most likely cause!</p>";
}

if (file_exists('.htaccess')) {
    echo "<p class='ok'>✓ .htaccess exists</p>";
} else {
    echo "<p class='warning'>⚠ .htaccess not found</p>";
}

// Check 2: File permissions
echo "<h2>2. File Permissions Check</h2>";
$indexPerms = substr(sprintf('%o', fileperms('index.php')), -4);
echo "<p>index.php permissions: $indexPerms ";
if ($indexPerms === '0644') {
    echo "<span class='ok'>✓ Correct</span>";
} else {
    echo "<span class='error'>✗ Should be 644</span>";
}

if (file_exists('.htaccess')) {
    $htaccessPerms = substr(sprintf('%o', fileperms('.htaccess')), -4);
    echo "<p>.htaccess permissions: $htaccessPerms ";
    if ($htaccessPerms === '0644') {
        echo "<span class='ok'>✓ Correct</span>";
    } else {
        echo "<span class='error'>✗ Should be 644</span>";
    }
}

// Check 3: Directory permissions
echo "<h2>3. Directory Permissions Check</h2>";
$dirPerms = substr(sprintf('%o', fileperms('.')), -4);
echo "<p>SmartLab directory permissions: $dirPerms ";
if ($dirPerms === '0755') {
    echo "<span class='ok'>✓ Correct</span>";
} else {
    echo "<span class='error'>✗ Should be 755</span>";
}

// Check 4: Required files
echo "<h2>4. Required Files Check</h2>";
$requiredFiles = [
    'config/app.php',
    'config/database.php',
    'config/roles.php',
    'utils/helpers.php',
    'routes/web.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<p class='ok'>✓ $file exists</p>";
    } else {
        echo "<p class='error'>✗ $file NOT FOUND</p>";
    }
}

// Check 5: PHP syntax
echo "<h2>5. PHP Syntax Check</h2>";
$output = [];
$return_var = 0;
exec('php -l index.php 2>&1', $output, $return_var);
if ($return_var === 0) {
    echo "<p class='ok'>✓ index.php syntax is valid</p>";
} else {
    echo "<p class='error'>✗ index.php has syntax errors:</p>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
}

// Check 6: Server info
echo "<h2>6. Server Information</h2>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Name: " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>HTTP Host: " . $_SERVER['HTTP_HOST'] . "</p>";

// Check 7: Apache modules
echo "<h2>7. Apache Modules Check</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "<p>mod_rewrite: " . (in_array('mod_rewrite', $modules) ? "<span class='ok'>✓ Enabled</span>" : "<span class='error'>✗ Disabled</span>") . "</p>";
} else {
    echo "<p class='warning'>⚠ Cannot check Apache modules</p>";
}

// Check 8: .htaccess content
echo "<h2>8. .htaccess Content Analysis</h2>";
if (file_exists('.htaccess')) {
    $htaccess = file_get_contents('.htaccess');
    if (strpos($htaccess, 'Options -Indexes') !== false) {
        echo "<p class='warning'>⚠ 'Options -Indexes' found - prevents directory listing</p>";
    }
    if (strpos($htaccess, 'Require all denied') !== false) {
        echo "<p class='error'>✗ 'Require all denied' found - blocks access!</p>";
    }
    if (strpos($htaccess, 'Deny from') !== false) {
        echo "<p class='error'>✗ 'Deny from' directive found - blocks access!</p>";
    }
}

echo "<h2>Recommendations</h2>";
echo "<ol>";
echo "<li>If index.php is missing, upload it immediately</li>";
echo "<li>If permissions are wrong, run: chmod 644 index.php and chmod 755 .</li>";
echo "<li>If .htaccess contains restrictive rules, modify or rename it</li>";
echo "<li>If PHP syntax errors exist, fix them before uploading</li>";
echo "<li>Ensure the smart-lab directory is in the web root</li>";
echo "</ol>";

echo "</body></html>";
?>
