<?php
/**
 * Test Unified Login Field
 * Verifies that single field works for both reg_number and email authentication
 */

require_once __DIR__.'/config/database_production.php';

echo "<h2>Unified Login Field Test</h2>";

try {
    $pdo = getDB();
    echo "<p style='color: green;'>Database connected: " . DB_NAME . "</p>";
    
    // Test cases for unified login
    $testCases = [
        [
            'input' => 'admin@unilis.jhubafrica.com',
            'password' => 'Admin@2024',
            'expected_type' => 'email',
            'expected_role' => 'admin',
            'description' => 'Admin login with email'
        ],
        [
            'input' => 'ADMIN001',
            'password' => 'Admin@2024',
            'expected_type' => 'reg_number',
            'expected_role' => 'admin',
            'description' => 'Admin login with reg_number (if exists)'
        ],
        [
            'input' => 'STU001',
            'password' => 'Student@2024',
            'expected_type' => 'reg_number',
            'expected_role' => 'student',
            'description' => 'Student login with reg_number'
        ]
    ];
    
    echo "<div style='display: grid; gap: 20px;'>";
    
    foreach ($testCases as $index => $testCase) {
        echo "<div style='border: 1px solid #ddd; padding: 15px; border-radius: 8px;'>";
        echo "<h4 style='margin-top: 0; color: #2c3e50;'>Test " . ($index + 1) . ": " . $testCase['description'] . "</h4>";
        echo "<p><strong>Input:</strong> " . htmlspecialchars($testCase['input']) . "</p>";
        echo "<p><strong>Password:</strong> " . htmlspecialchars($testCase['password']) . "</p>";
        echo "<p><strong>Expected Type:</strong> " . htmlspecialchars($testCase['expected_type']) . "</p>";
        
        // Test input type detection
        $isEmail = filter_var($testCase['input'], FILTER_VALIDATE_EMAIL);
        $detectedType = $isEmail ? 'email' : 'reg_number';
        
        echo "<p><strong>Detected Type:</strong> " . htmlspecialchars($detectedType) . "</p>";
        
        if ($detectedType === $testCase['expected_type']) {
            echo "<p style='color: green;'>Input type detection: <strong>Correct</strong></p>";
        } else {
            echo "<p style='color: red;'>Input type detection: <strong>Incorrect</strong></p>";
        }
        
        // Test authentication
        $loginSuccess = false;
        if ($isEmail) {
            $loginSuccess = Auth::loginByEmail($testCase['input'], $testCase['password']);
        } else {
            $loginSuccess = Auth::login($testCase['input'], $testCase['password']);
        }
        
        if ($loginSuccess) {
            echo "<p style='color: green;'>Authentication: <strong>Success</strong></p>";
            echo "<p><strong>User ID:</strong> " . htmlspecialchars(Auth::id()) . "</p>";
            echo "<p><strong>User Role:</strong> " . htmlspecialchars(Auth::role()) . "</p>";
            echo "<p><strong>User Name:</strong> " . htmlspecialchars(Auth::name()) . "</p>";
            
            // Check if role matches expected
            if (Auth::role() === $testCase['expected_role']) {
                echo "<p style='color: green;'>Role verification: <strong>Correct</strong></p>";
            } else {
                echo "<p style='color: orange;'>Role verification: <strong>Expected " . htmlspecialchars($testCase['expected_role']) . ", got " . htmlspecialchars(Auth::role()) . "</strong></p>";
            }
            
            // Logout for next test
            Auth::logout();
        } else {
            echo "<p style='color: red;'>Authentication: <strong>Failed</strong></p>";
        }
        
        echo "</div>";
    }
    
    echo "</div>";
    
    echo "<div style='background: #f8f9fa; border: 1px solid #e9ecef; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>Unified Login System Summary</h3>";
    echo "<p>The login system now works with a single field that accepts:</p>";
    echo "<ul>";
    echo "<li>Registration numbers (for students)</li>";
    echo "<li>Email addresses (for admin, technician, lecturer)</li>";
    echo "<li>Automatic detection of input type</li>";
    echo "<li>Appropriate authentication method based on input</li>";
    echo "</ul>";
    echo "<p><strong>Test Credentials:</strong></p>";
    echo "<ul>";
    echo "<li>Admin: admin@unilis.jhubafrica.com / Admin@2024</li>";
    echo "<li>Student: [reg_number] / [password]</li>";
    echo "</ul>";
    echo "<p><a href='".APP_URL."/index.php?url=auth/login' style='background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Try Unified Login</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
