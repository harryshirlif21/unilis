<?php
/**
 * Live Engagement Module - Database Updater
 * 
 * Handles schema migrations and data updates for the Live Engagement Module.
 * Safe to run multiple times.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('UNILIS_ACCESS') && !defined('LIVE_ENGAGEMENT_UPDATE')) {
    die('Direct access not permitted');
}

/**
 * Run all pending database updates
 * 
 * @param mysqli $conn Database connection
 * @return array{success: bool, messages: string[]}
 */
function updateLiveEngagementTables(mysqli $conn): array
{
    $messages = [];
    $success = true;

    try {
        // Track applied migrations
        if (!tableExists($conn, 'live_engagement_migrations')) {
            $sql = "CREATE TABLE `live_engagement_migrations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `description` VARCHAR(500) NULL,
                `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if ($conn->query($sql)) {
                $messages[] = "Created migrations tracking table";
            } else {
                throw new Exception("Failed to create migrations table: " . $conn->error);
            }
        }

        // Define all migrations
        $migrations = [
            '001_add_session_ratings' => [
                'description' => 'Add rating fields to live_sessions',
                'sql' => [
                    "ALTER TABLE `live_sessions` 
                     ADD COLUMN `avg_rating` DECIMAL(3,2) NULL AFTER `is_template`,
                     ADD COLUMN `rating_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `avg_rating`"
                ]
            ],
            '002_add_presentation_metadata' => [
                'description' => 'Add metadata to presentations',
                'sql' => [
                    "ALTER TABLE `live_presentations` 
                     ADD COLUMN `metadata` JSON NULL AFTER `presenter_notes`"
                ]
            ],
            '003_add_participant_notes' => [
                'description' => 'Add participant notes field',
                'sql' => [
                    "ALTER TABLE `live_participants` 
                     ADD COLUMN `notes` TEXT NULL AFTER `attendance_recorded`"
                ]
            ],
            '004_add_poll_display_config' => [
                'description' => 'Add poll display configuration',
                'sql' => [
                    "ALTER TABLE `live_polls` 
                     ADD COLUMN `display_config` JSON NULL AFTER `time_limit_seconds`"
                ]
            ],
            '005_add_quiz_display_config' => [
                'description' => 'Add quiz display configuration',
                'sql' => [
                    "ALTER TABLE `live_quizzes` 
                     ADD COLUMN `display_config` JSON NULL AFTER `max_attempts`"
                ]
            ],
            '006_add_wordcloud_stopwords' => [
                'description' => 'Add stop words configuration to wordcloud',
                'sql' => [
                    "ALTER TABLE `live_wordcloud` 
                     ADD COLUMN `stop_words` JSON NULL AFTER `allow_profanity`"
                ]
            ],
            '007_add_report_scheduling' => [
                'description' => 'Add auto-generation scheduling for reports',
                'sql' => [
                    "ALTER TABLE `live_reports` 
                     ADD COLUMN `scheduled_at` DATETIME NULL AFTER `is_auto_generated`,
                     ADD COLUMN `generated_at` DATETIME NULL AFTER `scheduled_at`"
                ]
            ],
        ];

        foreach ($migrations as $migrationName => $migration) {
            if (!migrationApplied($conn, $migrationName)) {
                $migrationSuccess = true;
                foreach ($migration['sql'] as $sql) {
                    // Check if columns already exist before attempting ALTER
                    $alterMatch = [];
                    if (preg_match('/ADD COLUMN\s+`(\w+)`/i', $sql, $alterMatch)) {
                        $columnName = $alterMatch[1];
                        // Extract table name from ALTER TABLE
                        $tableMatch = [];
                        preg_match('/ALTER TABLE\s+`(\w+)`/i', $sql, $tableMatch);
                        $tableName = $tableMatch[1] ?? '';
                        
                        if ($tableName && columnExists($conn, $tableName, $columnName)) {
                            continue; // Column already exists, skip
                        }
                    }
                    
                    if (!$conn->query($sql)) {
                        $messages[] = "Migration {$migrationName} failed: " . $conn->error;
                        $migrationSuccess = false;
                        $success = false;
                        break;
                    }
                }
                
                if ($migrationSuccess) {
                    recordMigration($conn, $migrationName, $migration['description']);
                    $messages[] = "Applied migration: {$migrationName} - {$migration['description']}";
                }
            }
        }

        // Update statistics triggers - add indexes for performance
        $indexesToAdd = [
            ['table' => 'live_poll_responses', 'columns' => 'responded_at', 'name' => 'idx_responded_at'],
            ['table' => 'quiz_attempts', 'columns' => 'completed_at', 'name' => 'idx_completed_at'],
            ['table' => 'live_reactions', 'columns' => 'created_at', 'name' => 'idx_reaction_created'],
        ];

        foreach ($indexesToAdd as $index) {
            if (!indexExists($conn, $index['table'], $index['name'])) {
                $sql = "CREATE INDEX `{$index['name']}` ON `{$index['table']}` (`{$index['columns']}`)";
                if ($conn->query($sql)) {
                    $messages[] = "Added index {$index['name']} to {$index['table']}";
                }
            }
        }

    } catch (Exception $e) {
        $success = false;
        $messages[] = 'ERROR: ' . $e->getMessage();
    }

    return [
        'success' => $success,
        'messages' => $messages
    ];
}

/**
 * Check if a migration has been applied
 * 
 * @param mysqli $conn
 * @param string $migrationName
 * @return bool
 */
function migrationApplied(mysqli $conn, string $migrationName): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM `live_engagement_migrations` WHERE `migration` = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param('s', $migrationName);
    $stmt->execute();
    $result = $stmt->get_result();
    $applied = $result->num_rows > 0;
    $stmt->close();
    return $applied;
}

/**
 * Record a migration as applied
 * 
 * @param mysqli $conn
 * @param string $migrationName
 * @param string $description
 */
function recordMigration(mysqli $conn, string $migrationName, string $description): void
{
    $stmt = $conn->prepare("INSERT INTO `live_engagement_migrations` (`migration`, `description`) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param('ss', $migrationName, $description);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Check if a table exists
 * 
 * @param mysqli $conn
 * @param string $tableName
 * @return bool
 */
function tableExists(mysqli $conn, string $tableName): bool
{
    $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    return $result && $result->num_rows > 0;
}

/**
 * Check if a column exists in a table
 * 
 * @param mysqli $conn
 * @param string $tableName
 * @param string $columnName
 * @return bool
 */
function columnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    $result = $conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE '" . $conn->real_escape_string($columnName) . "'");
    return $result && $result->num_rows > 0;
}

/**
 * Check if an index exists on a table
 * 
 * @param mysqli $conn
 * @param string $tableName
 * @param string $indexName
 * @return bool
 */
function indexExists(mysqli $conn, string $tableName, string $indexName): bool
{
    $result = $conn->query("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '" . $conn->real_escape_string($indexName) . "'");
    return $result && $result->num_rows > 0;
}

// Run updater if accessed directly
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === basename(__FILE__)) {
    define('LIVE_ENGAGEMENT_UPDATE', true);
    require_once __DIR__ . '/../../config/db.php';
    
    header('Content-Type: application/json');
    $result = updateLiveEngagementTables($conn);
    echo json_encode($result, JSON_PRETTY_PRINT);
}