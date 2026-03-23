<?php
$php = file_get_contents('/tmp/grade_answer.txt');
file_put_contents('/var/www/html/lecturer/ajax/grade_answer.php', $php);
echo "done\n";
