<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Path is relative to this PHP file's location
// Place this file in the same folder as co2_log.json
$jsonFilePath = 'C:/xampp/htdocs/smart-lab/co2_log.json';
$logEntries = [];

if (file_exists($jsonFilePath) && filesize($jsonFilePath) > 0) {
    $jsonContent = file_get_contents($jsonFilePath);
    $logEntries = json_decode($jsonContent, true);

    if (is_array($logEntries) && !empty($logEntries)) {
        $logEntries = array_reverse($logEntries); // newest first
    }
}

foreach ($logEntries as $key => $entry) {
    $ppm = $entry['co2_ppm'];
    if ($ppm <= 700) {
        $logEntries[$key]['status'] = 'Excellent';
        $logEntries[$key]['color']  = '#2E8B57';
        $logEntries[$key]['bg']     = '#EAF4EF';
    } elseif ($ppm <= 1000) {
        $logEntries[$key]['status'] = 'Good / Normal';
        $logEntries[$key]['color']  = '#1E6FBA';
        $logEntries[$key]['bg']     = '#E8F1F8';
    } elseif ($ppm <= 1500) {
        $logEntries[$key]['status'] = 'Fair (Ventilate)';
        $logEntries[$key]['color']  = '#D4AF37';
        $logEntries[$key]['bg']     = '#FAF6E8';
    } else {
        $logEntries[$key]['status'] = 'Poor / High';
        $logEntries[$key]['color']  = '#C0392B';
        $logEntries[$key]['bg']     = '#FADBD8';
    }
}

echo json_encode($logEntries);
