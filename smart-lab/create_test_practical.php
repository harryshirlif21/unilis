<?php
/**
 * Script to create a test practical for today (2026-06-10)
 * Time: 11:20 AM to 4:00 PM
 * Location: Engineering Workshop (lab-eng-001)
 * Lecturer: kamau john (71bf048cda937785152023a19f9e2ef2)
 * 
 * Run: php smart-lab/create_test_practical.php
 */

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'unilis_smartlab';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Generate UUID-format ID (same method as PracticalController)
    $rawId = random_bytes(16);
    $rawId[6] = chr((ord($rawId[6]) & 0x0f) | 0x40);
    $rawId[8] = chr((ord($rawId[8]) & 0x3f) | 0x80);
    $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($rawId), 4));

    $lecturerId = '71bf048cda937785152023a19f9e2ef2'; // kamau john
    $labId = 'lab-eng-001'; // Engineering Workshop

    // Check for time conflicts
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM practicals 
        WHERE lab_id = ? AND scheduled_date = '2026-06-10' 
        AND start_time < '16:00:00' AND end_time > '11:20:00' 
        AND status IN ('published', 'ongoing')");
    $stmt->execute([$labId]);
    $conflicts = $stmt->fetch()['c'];

    // Insert the practical
    $stmt = $pdo->prepare("INSERT INTO practicals 
        (id, title, objective, theory, description, lab_id, lecturer_id, course_code, 
         scheduled_date, start_time, end_time, duration_hours, max_students, 
         required_equipment, required_chemicals, safety_notes, 
         procedure_json, observations_table_structure, 
         results_template, calculations_template, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $data = [
        'id' => $uuid,
        'title' => 'Engineering Workshop - Practical Session',
        'objective' => '<p>By the end of this practical session, students will be able to:</p><ul><li>Understand basic engineering workshop safety procedures</li><li>Identify and use common workshop tools and equipment</li><li>Demonstrate proper measurement techniques</li><li>Apply engineering principles in a hands-on environment</li></ul>',
        'theory' => '<p>Engineering workshop practice involves the application of fundamental engineering principles in a controlled laboratory environment. Students will learn proper handling of tools, measurement techniques, and safety protocols essential for all engineering disciplines.</p>',
        'description' => '<p>This hands-on practical session introduces students to the Engineering Workshop. Students will rotate through different workstations covering measurement, cutting, joining, and assembly operations under the supervision of qualified technicians.</p>',
        'lab_id' => $labId,
        'lecturer_id' => $lecturerId,
        'course_code' => 'ENG101',
        'scheduled_date' => '2026-06-10',
        'start_time' => '11:20:00',
        'end_time' => '16:00:00',
        'duration_hours' => 5,
        'max_students' => 20,
        'required_equipment' => "Safety goggles (1 per student)\nLab coats (1 per student)\nMeasuring tapes (1 per workstation)\nVernier calipers (1 per workstation)\nHack saws (2)\nSteel rules (1 per workstation)\nCenter punches (1 per workstation)\nBall-peen hammers (2)\nFiles (assorted set per workstation)",
        'required_chemicals' => "Cutting oil\nLubricating oil\nCleaning solvent (isopropyl alcohol)",
        'safety_notes' => "Wear safety goggles and lab coat at all times. \nTie back loose hair and remove dangling jewelry. \nKeep work area clean and free of obstructions. \nReport any accidents or equipment damage immediately. \nDo not operate any equipment without prior instruction. \nEnsure machines are properly guarded before use.",
        'procedure_json' => json_encode([
            ['step_number' => 1, 'step_description' => 'Safety briefing and introduction to workshop rules'],
            ['step_number' => 2, 'step_description' => 'Instructor demonstration of proper tool handling techniques'],
            ['step_number' => 3, 'step_description' => 'Station 1: Measurement - Use vernier calipers and micrometers to measure provided specimens and record dimensions'],
            ['step_number' => 4, 'step_description' => 'Station 2: Marking - Practice marking out workpieces using measuring tape, steel rule, and center punch'],
            ['step_number' => 5, 'step_description' => 'Station 3: Cutting - Demonstrate proper hacksaw technique and cut a marked workpiece'],
            ['step_number' => 6, 'step_description' => 'Station 4: Filing - Use files to deburr and finish edges of cut workpiece'],
            ['step_number' => 7, 'step_description' => 'Quality check - Measure finished workpiece dimensions and compare with specifications'],
            ['step_number' => 8, 'step_description' => 'Clean up - Return all tools, clean workstations, and dispose of waste properly'],
        ]),
        'observations_table_structure' => json_encode([
            ['name' => 'Specimen No.', 'type' => 'text', 'formula' => ''],
            ['name' => 'Measured Length (mm)', 'type' => 'number', 'formula' => ''],
            ['name' => 'Measured Width (mm)', 'type' => 'number', 'formula' => ''],
            ['name' => 'Measured Depth (mm)', 'type' => 'number', 'formula' => ''],
            ['name' => 'Calculated Volume (mm³)', 'type' => 'calculation', 'formula' => 'col1 * col2 * col3'],
            ['name' => 'Tolerance (±mm)', 'type' => 'number', 'formula' => ''],
        ]),
        'results_template' => '<table class="table-builder"><thead><tr><th>Specimen</th><th>Length (mm)</th><th>Width (mm)</th><th>Depth (mm)</th><th>Volume (mm³)</th></tr></thead><tbody><tr><td contenteditable="true">1</td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td></tr><tr><td contenteditable="true">2</td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td></tr><tr><td contenteditable="true">3</td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td></tr></tbody></table>',
        'calculations_template' => '<p><strong>Calculations:</strong></p><ol><li>Calculate the volume of each specimen: V = L × W × D</li><li>Calculate the percentage error from the standard dimension</li><li>Plot a bar chart comparing the volumes of all specimens</li></ol>',
        'status' => 'published',
    ];

    $stmt->execute([
        $data['id'],
        $data['title'],
        $data['objective'],
        $data['theory'],
        $data['description'],
        $data['lab_id'],
        $data['lecturer_id'],
        $data['course_code'],
        $data['scheduled_date'],
        $data['start_time'],
        $data['end_time'],
        $data['duration_hours'],
        $data['max_students'],
        $data['required_equipment'],
        $data['required_chemicals'],
        $data['safety_notes'],
        $data['procedure_json'],
        $data['observations_table_structure'],
        $data['results_template'],
        $data['calculations_template'],
        $data['status'],
    ]);

    echo "✅ Practical created successfully!\n\n";
    echo "ID:          {$uuid}\n";
    echo "Title:       {$data['title']}\n";
    echo "Date:        2026-06-10\n";
    echo "Time:        11:20 AM - 4:00 PM\n";
    echo "Lab:         Engineering Workshop (lab-eng-001)\n";
    echo "Lecturer:    kamau john\n";
    echo "Status:      published\n\n";
    echo "You can view it at: http://localhost/smart-lab/practicals/view/{$uuid}\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}