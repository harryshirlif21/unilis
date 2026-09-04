<?php
$projectRoot = dirname(__DIR__, 2);
$target = $projectRoot . '/assets/uploads/short_courses/_permtest_' . time() . '.txt';
$ok = @file_put_contents($target, 'x');
echo 'projectRoot=' . $projectRoot . "\n";
echo 'canWrite=' . ($ok !== false ? 'YES' : 'NO') . "\n";
if ($ok !== false) { @unlink($target); echo 'cleanup=OK\n' ; }