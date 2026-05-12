<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../smart-lab/models/PracticalModel.php';

Auth::guard();

$reportId = sanitize($_GET['report_id'] ?? '');

if (empty($reportId)) {
    echo 'Error: Report ID is required.';
    exit;
}

$model = new PracticalModel();
$report = $model->getReportById($reportId);

if (!$report) {
    echo 'Error: Report not found.';
    exit;
}

$practical = $model->getPracticalDetails($report['practical_id']);

if (!$practical) {
    echo 'Error: Practical not found.';
    exit;
}

// Load existing report data
$observations = json_decode($report['observations_json'] ?? '[]', true) ?: [];
$calculations = $report['calculations'] ?? '';
$result = $report['result'] ?? '';
$conclusion = $report['conclusion'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical Report</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/practical-page.js" defer></script>
    <script>
        window.reportId = '<?php echo $reportId; ?>';
        window.practicalId = '<?php echo $report['practical_id']; ?>';
    </script>
</head>
<body>
    <h1><?php echo htmlspecialchars($practical['title']); ?></h1>
    <section>
        <h2>Aim</h2>
        <p><?php echo nl2br(htmlspecialchars($practical['objective'] ?? $practical['aim'] ?? '')); ?></p>
    </section>
    <section>
        <h2>Materials</h2>
        <ul>
            <?php 
            $materials = $practical['materials'] ?? [];
            if (empty($materials) && !empty($practical['required_equipment'])) {
                $materials = array_filter(explode("\n", $practical['required_equipment']));
            }
            foreach ($materials as $material): ?>
                <li><?php echo htmlspecialchars($material); ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <section>
        <h2>Procedure</h2>
        <ol>
            <?php 
            $procedure = $practical['procedure'] ?? [];
            if (empty($procedure) && !empty($practical['procedure_json'])) {
                $procedure = json_decode($practical['procedure_json'], true) ?: [];
            }
            foreach ($procedure as $step): ?>
                <li><?php echo htmlspecialchars(is_array($step) ? ($step['description'] ?? $step) : $step); ?></li>
            <?php endforeach; ?>
        </ol>
    </section>
    <section>
        <h2>Data Entry Table</h2>
        <table id="data-table">
            <thead>
                <tr>
                    <th>Trial</th>
                    <th>Initial Reading</th>
                    <th>Final Reading</th>
                    <th>Volume Used</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < 5; $i++): 
                    $rowData = $observations[$i] ?? [];
                ?>
                    <tr>
                        <td contenteditable="true"><?php echo htmlspecialchars($rowData['trial'] ?? ''); ?></td>
                        <td contenteditable="true"><?php echo htmlspecialchars($rowData['initial_reading'] ?? ''); ?></td>
                        <td contenteditable="true"><?php echo htmlspecialchars($rowData['final_reading'] ?? ''); ?></td>
                        <td contenteditable="true"><?php echo htmlspecialchars($rowData['volume_used'] ?? ''); ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </section>
    <section>
        <h2>Calculations</h2>
        <textarea id="calculations" rows="5"><?php echo htmlspecialchars($calculations); ?></textarea>
    </section>
    <section>
        <h2>Results</h2>
        <textarea id="results" rows="5"><?php echo htmlspecialchars($result); ?></textarea>
    </section>
    <section>
        <h2>Conclusion</h2>
        <textarea id="conclusion" rows="5"><?php echo htmlspecialchars($conclusion); ?></textarea>
    </section>
    <section>
        <h2>Questions</h2>
        <ul>
            <?php foreach ($practical['questions'] as $question): ?>
                <li><?php echo htmlspecialchars($question); ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</body>
</html>