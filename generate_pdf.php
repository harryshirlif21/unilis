<?php
/**
 * UNILIS Project Features - PDF Documentation Generator
 * Generates a professional PDF documentation from PROJECT_FEATURES.json
 */

// Prevent timeout for large document
set_time_limit(120);

// Load the JSON data
$jsonPath = __DIR__ . '/PROJECT_FEATURES.json';
if (!file_exists($jsonPath)) {
    die("ERROR: PROJECT_FEATURES.json not found at $jsonPath\n");
}

$data = json_decode(file_get_contents($jsonPath), true);
if ($data === null) {
    die("ERROR: Invalid JSON: " . json_last_error_msg() . "\n");
}

// Use Dompdf for PDF generation
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// ─── Helper: status badge ──────────────────────────────────────────────
function statusBadge(string $status): string {
    return match ($status) {
        'implemented' => '✓ IMPLEMENTED',
        'partial'     => '◐ PARTIAL',
        'not-started' => '✗ NOT STARTED',
        default       => strtoupper($status),
    };
}

function statusColor(string $status): string {
    return match ($status) {
        'implemented' => '009900',
        'partial'     => 'CC8800',
        'not-started' => 'CC0000',
        default       => '666666',
    };
}

// ─── Helper: escape for HTML ──────────────────────────────────────────
function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ─── Generate HTML content first ──────────────────────────────────────
$html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>' . h($data['project_name']) . ' - Software Documentation</title>
<style>
    @page { margin: 20mm 15mm; }
    body { font-family: "Helvetica", "Arial", sans-serif; font-size: 10pt; line-height: 1.5; color: #222; }
    h1 { font-size: 22pt; color: #1a3a5c; border-bottom: 3px solid #1a3a5c; padding-bottom: 8px; margin-top: 0; }
    h2 { font-size: 16pt; color: #1a3a5c; border-bottom: 2px solid #ccc; padding-bottom: 5px; margin-top: 30px; }
    h3 { font-size: 13pt; color: #2a5a8c; margin-top: 20px; margin-bottom: 8px; }
    h4 { font-size: 11pt; color: #333; margin-top: 15px; margin-bottom: 5px; }
    .cover { text-align: center; padding-top: 120px; }
    .cover h1 { font-size: 28pt; border: none; margin-bottom: 10px; }
    .cover .subtitle { font-size: 14pt; color: #555; margin-bottom: 30px; }
    .cover .meta { font-size: 11pt; color: #777; }
    .cover .line { width: 120px; height: 3px; background: #1a3a5c; margin: 20px auto; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9pt; }
    th { background: #1a3a5c; color: white; padding: 6px 8px; text-align: left; }
    td { padding: 5px 8px; border-bottom: 1px solid #ddd; }
    tr:nth-child(even) { background: #f5f8fc; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; color: white; font-size: 8pt; font-weight: bold; }
    .badge-green { background: #009900; }
    .badge-orange { background: #CC8800; }
    .badge-red { background: #CC0000; }
    .summary-box { background: #f0f4f8; border: 1px solid #d0d8e0; border-radius: 6px; padding: 15px; margin: 15px 0; }
    .summary-box table { margin: 0; }
    .summary-box th { background: #2a5a8c; }
    .sub-features { margin-left: 15px; }
    .sub-features li { margin-bottom: 3px; font-size: 9pt; }
    .file-list { font-family: "Courier New", monospace; font-size: 8pt; color: #555; background: #f8f8f8; padding: 8px; border-radius: 4px; border: 1px solid #eee; margin: 5px 0; }
    .known-issues li, .next-steps li { margin-bottom: 4px; }
    .page-break { page-break-before: always; }
    .toc a { color: #1a3a5c; text-decoration: none; }
    .toc a:hover { text-decoration: underline; }
    .toc li { margin-bottom: 6px; }
    .footer-note { text-align: center; color: #999; font-size: 8pt; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; }
</style>
</head>
<body>

<!-- ═══════════════════ COVER PAGE ═══════════════════ -->
<div class="cover">
    <h1>' . h($data['project_name']) . '</h1>
    <div class="line"></div>
    <div class="subtitle">Comprehensive Software Documentation & Feature Inventory</div>
    <div class="meta">
        <p>Version: <strong>' . h($data['version']) . '</strong></p>
        <p>Last Updated: <strong>' . h($data['last_updated']) . '</strong></p>
        <p>Total Features Documented: <strong>' . $data['summary']['total_features'] . '</strong></p>
        <p style="margin-top:40px; font-size:9pt; color:#999;">' . h($data['description']) . '</p>
    </div>
</div>

<div class="page-break"></div>

<!-- ═══════════════════ TABLE OF CONTENTS ═══════════════════ -->
<h2>Table of Contents</h2>
<ol class="toc">
    <li><a href="#exec-summary">Executive Summary</a></li>
    <li><a href="#summary-stats">Feature Summary & Statistics</a></li>
    ';

$moduleNum = 3;
foreach ($data['modules'] as $module) {
    $html .= '    <li><a href="#mod-' . h($module['name']) . '">' . $moduleNum . '. ' . h($module['name']) . '</a></li>';
    $moduleNum++;
}
$html .= '
    <li><a href="#known-issues">' . $moduleNum . '. Known Issues & Limitations</a></li>
    <li><a href="#next-steps">' . ($moduleNum + 1) . '. Recommended Next Steps</a></li>
</ol>

<div class="page-break"></div>

<!-- ═══════════════════ EXECUTIVE SUMMARY ═══════════════════ -->
<h2 id="exec-summary">1. Executive Summary</h2>
<p>' . h($data['description']) . '</p>
<p>This document provides a comprehensive inventory of all features implemented across the UNILIS system, organized by module. It serves as both a technical reference and a project planning tool, documenting the current implementation status of <strong>' . $data['summary']['total_features'] . '</strong> features across <strong>' . count($data['modules']) . '</strong> modules.</p>

<h3>System Overview</h3>
<table>
    <tr><th>Metric</th><th>Value</th></tr>
    <tr><td>Total Features</td><td>' . $data['summary']['total_features'] . '</td></tr>
    <tr><td>Fully Implemented</td><td>' . $data['summary']['implemented'] . ' (' . round($data['summary']['implemented'] / $data['summary']['total_features'] * 100) . '%)</td></tr>
    <tr><td>Partially Implemented</td><td>' . $data['summary']['partial'] . '</td></tr>
    <tr><td>Not Started</td><td>' . $data['summary']['not_started'] . '</td></tr>
    <tr><td>Modules</td><td>' . count($data['modules']) . '</td></tr>
    <tr><td>Version</td><td>' . h($data['version']) . '</td></tr>
    <tr><td>Last Updated</td><td>' . h($data['last_updated']) . '</td></tr>
</table>

<div class="page-break"></div>

<!-- ═══════════════════ SUMMARY STATISTICS ═══════════════════ -->
<h2 id="summary-stats">2. Feature Summary & Statistics</h2>

<div class="summary-box">
    <h3>Implementation Status by Module</h3>
    <table>
        <tr><th>Module</th><th>Total</th><th>Implemented</th><th>Partial</th><th>Not Started</th><th>Completion</th></tr>';

foreach ($data['summary']['by_module'] as $moduleName => $stats) {
    $pct = $stats['total'] > 0 ? round(($stats['implemented'] + $stats['partial'] * 0.5) / $stats['total'] * 100) : 0;
    $bar = str_repeat('█', round($pct / 10)) . str_repeat('░', 10 - round($pct / 10));
    $html .= '        <tr><td><strong>' . h($moduleName) . '</strong></td><td>' . $stats['total'] . '</td><td>' . $stats['implemented'] . '</td><td>' . $stats['partial'] . '</td><td>' . $stats['not_started'] . '</td><td>' . $pct . '% ' . $bar . '</td></tr>';
}

$html .= '        <tr style="font-weight:bold; background:#e8edf4;">
            <td>TOTAL</td>
            <td>' . $data['summary']['total_features'] . '</td>
            <td>' . $data['summary']['implemented'] . '</td>
            <td>' . $data['summary']['partial'] . '</td>
            <td>' . $data['summary']['not_started'] . '</td>
            <td>' . round(($data['summary']['implemented'] + $data['summary']['partial'] * 0.5) / $data['summary']['total_features'] * 100) . '%</td>
        </tr>
    </table>
</div>

<div class="page-break"></div>

<!-- ═══════════════════ MODULES ═══════════════════ -->
';

$moduleNum = 3;
foreach ($data['modules'] as $module) {
    $modLabel = $moduleNum . '. ' . h($module['name']);
    $html .= '<h2 id="mod-' . h($module['name']) . '">' . $modLabel . '</h2>';
    $html .= '<p><em>' . h($module['description']) . '</em></p>';

    $modStats = $data['summary']['by_module'][$module['name']] ?? ['total' => 0, 'implemented' => 0, 'partial' => 0, 'not_started' => 0];
    $html .= '<div class="summary-box" style="margin-bottom:15px;">
        <table>
            <tr><th>Total Features</th><th>Implemented</th><th>Partial</th><th>Not Started</th></tr>
            <tr>
                <td>' . $modStats['total'] . '</td>
                <td><span class="badge badge-green">' . $modStats['implemented'] . '</span></td>
                <td><span class="badge badge-orange">' . $modStats['partial'] . '</span></td>
                <td><span class="badge badge-red">' . $modStats['not_started'] . '</span></td>
            </tr>
        </table>
    </div>';

    $featureNum = 1;
    foreach ($module['features'] as $feature) {
        $sc = statusColor($feature['status']);
        $sb = statusBadge($feature['status']);
        $html .= '<h3>' . $moduleNum . '.' . $featureNum . ' ' . h($feature['name']) . ' <span class="badge" style="background:#' . $sc . ';">' . $sb . '</span></h3>';
        $html .= '<p>' . h($feature['description']) . '</p>';

        // Files
        if (!empty($feature['files'])) {
            $html .= '<div class="file-list">📁 ' . implode(', ', array_map('h', $feature['files'])) . '</div>';
        }

        // Sub-features
        if (!empty($feature['sub_features'])) {
            $html .= '<h4>Sub-Features:</h4><ul class="sub-features">';
            foreach ($feature['sub_features'] as $sf) {
                $sfc = statusColor($sf['status']);
                $sfb = statusBadge($sf['status']);
                $extra = !empty($sf['files']) ? ' <span style="font-family:Courier;font-size:8pt;color:#888;">[' . implode(', ', array_map('h', $sf['files'])) . ']</span>' : '';
                $html .= '<li><span class="badge" style="background:#' . $sfc . ';">' . $sfb . '</span> ' . h($sf['name']) . $extra . '</li>';
            }
            $html .= '</ul>';
        }
        $featureNum++;
    }

    $moduleNum++;
    $html .= '<div class="page-break"></div>';
}

// ── Known Issues ─────────────────────────────────────────────────────
$html .= '<h2 id="known-issues">' . $moduleNum . '. Known Issues & Limitations</h2>
<ul class="known-issues">';
foreach ($data['known_issues'] as $issue) {
    $html .= '    <li>' . h($issue) . '</li>';
}
$html .= '</ul>

<div class="page-break"></div>';

// ── Recommended Next Steps ───────────────────────────────────────────
$moduleNum++;
$html .= '<h2 id="next-steps">' . $moduleNum . '. Recommended Next Steps</h2>
<ol class="next-steps">';
foreach ($data['recommended_next_steps'] as $step) {
    $html .= '    <li>' . h($step) . '</li>';
}
$html .= '</ol>

<div class="footer-note">
    <p>UNILIS - Unified Learning and Innovation Lab Information System v' . h($data['version']) . '</p>
    <p>Document generated on ' . date('F j, Y') . ' | Total Features: ' . $data['summary']['total_features'] . '</p>
</div>

</body>
</html>';

// ─── Output PDF using Dompdf ─────────────────────────────────────────
$outputFile = __DIR__ . '/UNILIS_Features_Documentation.pdf';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('dpi', 96);

$pdf = new Dompdf($options);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4', 'portrait');
$pdf->render();

file_put_contents($outputFile, $pdf->output());

echo "✅ PDF Documentation generated successfully!\n";
echo "Output: $outputFile\n";
echo "File size: " . number_format(filesize($outputFile)) . " bytes\n";
