$p = 'c:\xampp\htdocs\unilis\learn\course.php'
$L = Get-Content $p
$head = $L[0..412]
$orig = Get-Content 'c:\xampp\htdocs\unilis\tmp_course.php.original.txt'
$sched = $orig[717..826]
$modjs  = $orig[827..861]
$out = New-Object System.Collections.Generic.List[string]
foreach ($head in $h) { $out.Add($h) }
$out.Add('')
$out.Add('<?php require __DIR__ . "/includes/course_ui.php"; ?>')
$out.Add('')
foreach ($sched in $s) { $out.Add($s) }
$out.Add('')
foreach ($modjs in $m) { $out.Add($m) }
$out.Add('')
$out.Add('<?php')
$out.Add('learn_foot();')
Set-Content $p -Value $out
Write-Output ('wrote ' + $out.Count + ' lines')