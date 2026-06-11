<?php
/**
 * Script to create test practicals for testing action buttons on student side
 * 
 * Usage: php smart-lab/create_test_practical.php [type]
 * 
 * types:
 *   upcoming  - Practical starting in 2 hours (shows "View Practical")
 *   now       - Practical starting now, within access window (shows "Take Practical")
 *   closed    - Practical that already ended (shows "Closed / Request Entry")
 *   all       - Creates all three types (default)
 * 
 * Uses config/database.php (local) - change to database_production.php for production
 */

require_once __DIR__ . '/config/database.php';

$type = $argv[1] ?? 'all';

try {
    $pdo = getDB();

    // Find an available lecturer
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'lecturer' LIMIT 1");
    $stmt->execute();
    $lecturer = $stmt->fetch();

    if (!$lecturer) {
        // Try admin
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $lecturer = $stmt->fetch();
    }

    if (!$lecturer) {
        echo "ERROR: No lecturer or admin found in users table.\n";
        echo "Run setup scripts first to create users.\n";
        exit(1);
    }

    // Find available lab
    $stmt = $pdo->prepare("SELECT id, name FROM labs LIMIT 1");
    $stmt->execute();
    $lab = $stmt->fetch();

    if (!$lab) {
        echo "ERROR: No labs found. Create a lab first.\n";
        exit(1);
    }

    $lecturerId = $lecturer['id'];
    $labId = $lab['id'];
    $now = new DateTime();
    $today = $now->format('Y-m-d');

    $created = 0;

    // Helper to generate UUID
    function genUuid(): string {
        $rawId = random_bytes(16);
        $rawId[6] = chr((ord($rawId[6]) & 0x0f) | 0x40);
        $rawId[8] = chr((ord($rawId[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($rawId), 4));
    }

    // Helper to check conflict
    function hasConflict(PDO $pdo, string $labId, string $date, string $start, string $end): bool {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as c FROM practicals 
             WHERE lab_id = ? AND scheduled_date = ? 
             AND start_time < ? AND end_time > ? 
             AND status IN ('published', 'ongoing')"
        );
        $stmt->execute([$labId, $date, $end, $start]);
        return $stmt->fetch()['c'] > 0;
    }

    // Helper to insert practical
    function createPractical(PDO $pdo, array $data): bool {
        $stmt = $pdo->prepare(
            "INSERT INTO practicals 
             (id, title, objective, theory, description, lab_id, lecturer_id, course_code,
              scheduled_date, start_time, end_time, duration_hours, max_students,
              required_equipment, required_chemicals, safety_notes,
              procedure_json, observations_table_structure,
              results_template, calculations_template, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $data['id'], $data['title'], $data['objective'], $data['theory'],
            $data['description'], $data['lab_id'], $data['lecturer_id'], $data['course_code'],
            $data['scheduled_date'], $data['start_time'], $data['end_time'],
            $data['duration_hours'], $data['max_students'],
            $data['required_equipment'], $data['required_chemicals'], $data['safety_notes'],
            $data['procedure_json'], $data['observations_table_structure'],
            $data['results_template'], $data['calculations_template'],
            $data['status']
        ]);
    }

    // Template data for practicals
    $objective = '<p>By the end of this practical session, students will be able to:</p><ul><li>Understand basic laboratory safety procedures</li><li>Identify and use common lab tools and equipment</li><li>Demonstrate proper measurement techniques</li><li>Apply scientific principles in a hands-on environment</li></ul>';
    $theory = '<p>This practical involves the application of fundamental scientific principles in a controlled laboratory environment. Students will learn proper handling of equipment, measurement techniques, and safety protocols.</p>';
    $description = '<p>A hands-on practical session introducing students to laboratory techniques. Students will rotate through different workstations covering measurement, observation, and analysis.</p>';
    $equipment = "Safety goggles (1 per student)\nLab coats (1 per student)\nMeasuring cylinders (100ml, 250ml)\nBeakers (250ml, 500ml)\nThermometers\nBunsen burners\nTripod stand and wire gauze\nTest tubes and test tube holders";
    $chemicals = "Distilled water\nSodium chloride solution (0.1M)\nHydrochloric acid (0.1M)\nUniversal indicator";
    $safety = "Wear safety goggles and lab coat at all times.\nTie back loose hair and remove dangling jewelry.\nKeep work area clean and free of obstructions.\nReport any accidents or spills immediately.\nDo not handle chemicals without gloves.\nEnsure proper ventilation.";
    $procedure = json_encode([
        ['step_number' => 1, 'step_description' => 'Safety briefing and introduction to lab rules'],
        ['step_number' => 2, 'step_description' => 'Set up apparatus as shown by instructor'],
        ['step_number' => 3, 'step_description' => 'Measure 100ml of distilled water using a measuring cylinder'],
        ['step_number' => 4, 'step_description' => 'Add 5 drops of universal indicator to the water'],
        ['step_number' => 5, 'step_description' => 'Slowly add 0.1M HCl and record color changes'],
        ['step_number' => 6, 'step_description' => 'Record observations in the data table'],
        ['step_number' => 7, 'step_description' => 'Clean all apparatus and return to storage'],
        ['step_number' => 8, 'step_description' => 'Complete lab report with observations and conclusions'],
    ]);
    $observations = json_encode([
        ['name' => 'Trial No.', 'type' => 'text', 'formula' => ''],
        ['name' => 'Volume of HCl added (ml)', 'type' => 'number', 'formula' => ''],
        ['name' => 'Color Observed', 'type' => 'text', 'formula' => ''],
        ['name' => 'pH Value', 'type' => 'number', 'formula' => ''],
    ]);
    $results = '<table class="table-builder"><thead><tr><th>Trial</th><th>Volume (ml)</th><th>Color</th><th>pH</th></tr></thead><tbody><tr><td contenteditable="true">1</td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td></tr><tr><td contenteditable="true">2</td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td></tr><tr><td contenteditable="true">3</td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td></tr></tbody></table>';
    $calculations = '<p><strong>Calculations:</strong></p><ol><li>Calculate the average volume of HCl used</li><li>Determine the pH at each stage</li><li>Plot a graph of pH vs Volume of HCl added</li></ol>';

    // === TYPE: UPCOMING (starts in 2 hours) ===
    if ($type === 'all' || $type === 'upcoming') {
        $startTime = (clone $now)->modify('+2 hours');
        $endTime = (clone $startTime)->modify('+2 hours');
        $startStr = $startTime->format('H:i:s');
        $endStr = $endTime->format('H:i:s');

        if (!hasConflict($pdo, $labId, $today, $startStr, $endStr)) {
            $data = [
                'id' => genUuid(),
                'title' => '[TEST] Chemistry Practical - UPCOMING',
                'objective' => $objective,
                'theory' => $theory,
                'description' => $description,
                'lab_id' => $labId,
                'lecturer_id' => $lecturerId,
                'course_code' => 'SCT101',
                'scheduled_date' => $today,
                'start_time' => $startStr,
                'end_time' => $endStr,
                'duration_hours' => 2,
                'max_students' => 30,
                'required_equipment' => $equipment,
                'required_chemicals' => $chemicals,
                'safety_notes' => $safety,
                'procedure_json' => $procedure,
                'observations_table_structure' => $observations,
                'results_template' => $results,
                'calculations_template' => $calculations,
                'status' => 'published',
            ];

            if (createPractical($pdo, $data)) {
                echo "✅ UPCOMING Practical created!\n";
                echo "   Title: {$data['title']}\n";
                echo "   Date:  $today\n";
                echo "   Time:  {$startStr} - {$endStr}\n";
                echo "   ID:    {$data['id']}\n\n";
                $created++;
            }
        } else {
            echo "⚠ UPCOMING: Time conflict at $startStr-$endStr, skipping.\n\n";
        }
    }

    // === TYPE: NOW (starts 15 minutes ago - within access window) ===
    if ($type === 'all' || $type === 'now') {
        $startTime = (clone $now)->modify('-15 minutes');
        $endTime = (clone $now)->modify('+2 hours');
        $startStr = $startTime->format('H:i:s');
        $endStr = $endTime->format('H:i:s');

        if (!hasConflict($pdo, $labId, $today, $startStr, $endStr)) {
            $data = [
                'id' => genUuid(),
                'title' => '[TEST] Chemistry Practical - AVAILABLE NOW',
                'objective' => $objective,
                'theory' => $theory,
                'description' => $description,
                'lab_id' => $labId,
                'lecturer_id' => $lecturerId,
                'course_code' => 'SCT101',
                'scheduled_date' => $today,
                'start_time' => $startStr,
                'end_time' => $endStr,
                'duration_hours' => 2,
                'max_students' => 30,
                'required_equipment' => $equipment,
                'required_chemicals' => $chemicals,
                'safety_notes' => $safety,
                'procedure_json' => $procedure,
                'observations_table_structure' => $observations,
                'results_template' => $results,
                'calculations_template' => $calculations,
                'status' => 'published',
            ];

            if (createPractical($pdo, $data)) {
                echo "✅ NOW (available) Practical created!\n";
                echo "   Title: {$data['title']}\n";
                echo "   Date:  $today\n";
                echo "   Time:  {$startStr} - {$endStr}\n";
                echo "   ID:    {$data['id']}\n\n";
                $created++;
            }
        } else {
            echo "⚠ NOW: Time conflict at $startStr-$endStr, skipping.\n\n";
        }
    }

    // === TYPE: CLOSED (ended 1 hour ago) ===
    if ($type === 'all' || $type === 'closed') {
        $startTime = (clone $now)->modify('-4 hours');
        $endTime = (clone $now)->modify('-1 hour');
        $startStr = $startTime->format('H:i:s');
        $endStr = $endTime->format('H:i:s');

        if (!hasConflict($pdo, $labId, $today, $startStr, $endStr)) {
            $data = [
                'id' => genUuid(),
                'title' => '[TEST] Chemistry Practical - CLOSED',
                'objective' => $objective,
                'theory' => $theory,
                'description' => $description,
                'lab_id' => $labId,
                'lecturer_id' => $lecturerId,
                'course_code' => 'SCT101',
                'scheduled_date' => $today,
                'start_time' => $startStr,
                'end_time' => $endStr,
                'duration_hours' => 2,
                'max_students' => 30,
                'required_equipment' => $equipment,
                'required_chemicals' => $chemicals,
                'safety_notes' => $safety,
                'procedure_json' => $procedure,
                'observations_table_structure' => $observations,
                'results_template' => $results,
                'calculations_template' => $calculations,
                'status' => 'published',
            ];

            if (createPractical($pdo, $data)) {
                echo "✅ CLOSED Practical created!\n";
                echo "   Title: {$data['title']}\n";
                echo "   Date:  $today\n";
                echo "   Time:  {$startStr} - {$endStr}\n";
                echo "   ID:    {$data['id']}\n\n";
                $created++;
            }
        } else {
            echo "⚠ CLOSED: Time conflict at $startStr-$endStr, skipping.\n\n";
        }
    }

    if ($created === 0) {
        echo "No practicals were created (time conflicts or all types skipped).\n";
        echo "Try again later or manually free up some time slots.\n";
    } else {
        echo "=== Created $created practical(s) successfully! ===\n";
        echo "View them at: http://localhost/smart-lab/practicals\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}