<?php
define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);

try {
    require_once DOCUMENT_ROOT . '/smart-lab/config/app.php';

    $db = getDB();

    echo "Creating chemistry practicals...\n";

    $practicalIds = [];
    $practicals = [
        [
            'title' => 'Chemistry Practical 1: Acid-Base Titration',
            'experiment' => 'Acid-Base Titration',
            'description' => 'Students will conduct acid-base titration to determine the concentration of an unknown acid solution using a standardized base solution.',
            'lab_number' => 'Lab 1',
            'date' => '2026-06-10',
            'time_start' => '10:00:00',
            'time_end' => '16:00:00'
        ],
        [
            'title' => 'Chemistry Practical 2: Rate of Reaction',
            'experiment' => 'Rate of Reaction',
            'description' => 'Students will investigate the effect of temperature and catalyst on the rate of reaction between hydrogen peroxide and potassium iodide.',
            'lab_number' => 'Lab 2',
            'date' => '2026-06-10',
            'time_start' => '10:00:00',
            'time_end' => '16:00:00'
        ]
    ];

    foreach ($practicals as $practical) {
        $pId = bin2hex(random_bytes(18));
        $practicalIds[] = $pId;

        $stmt = $db->prepare(
            "INSERT IGNORE INTO chemistry_practicals 
             (id, practical_id, title, scheduled_date, start_time, end_time, 
              lab_number, experiment_name, experiment_description, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->execute([
            bin2hex(random_bytes(18)),
            $pId,
            $practical['title'],
            $practical['date'],
            $practical['time_start'],
            $practical['time_end'],
            $practical['lab_number'],
            $practical['experiment'],
            $practical['description']
        ]);

        echo "✓ Created: " . $practical['title'] . "\n";
    }

    echo "\nAdding readings template for Practical 1 (Acid-Base Titration)...\n";
    $stmt = $db->prepare(
        "INSERT IGNORE INTO chemistry_practical_readings 
         (chemistry_practical_id, trial_number, measurement_label, units, observation_label, display_order)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([$practicalIds[0], 1, 'Volume of titrant used', 'ml', 'Color at endpoint', 1]);
    $stmt->execute([$practicalIds[0], 2, 'Volume of titrant used', 'ml', 'Color at endpoint', 2]);
    echo "✓ Added readings template for Practical 1\n";

    echo "\nAdding readings template for Practical 2 (Rate of Reaction)...\n";
    $stmt->execute([$practicalIds[1], 1, 'Time to reaction completion', 'seconds', 'Observations', 1]);
    $stmt->execute([$practicalIds[1], 2, 'Time to reaction completion', 'seconds', 'Observations', 2]);
    echo "✓ Added readings template for Practical 2\n";

    echo "\n✓ Chemistry practicals setup completed successfully!\n";

} catch (\Exception $e) {
    error_log('Setup Error: ' . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>
