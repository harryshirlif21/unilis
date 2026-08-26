<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'C:/xampp/htdocs/unilis/config/db.php';

$conn->query("INSERT INTO public_courses (slug,title,code,department_id,level,is_published,certificate_enabled,pass_mark,is_paid,created_by_lecturer_id,created_at,updated_at)
 VALUES ('tmp-topics','Topics Test','TT',NULL,'beginner',0,0,50,0,1,NOW(),NOW())");
$cid=$conn->insert_id;
$conn->query("INSERT INTO public_course_modules (course_id,title,position) VALUES ($cid,'M1',0)");
$mid=$conn->insert_id;
$conn->query("INSERT INTO public_course_lessons (module_id,title,content_html,position) VALUES ($mid,'Lesson T',NULL,0)");
$lid=$conn->insert_id;
$conn->query("INSERT INTO public_course_lesson_topics (lesson_id,parent_id,title,content_html,position) VALUES ($lid,NULL,'Top A','Body A',0)");
$topId=$conn->insert_id;
$conn->query("INSERT INTO public_course_lesson_topics (lesson_id,parent_id,title,content_html,position) VALUES ($lid,$topId,'Sub A1','Body A1',0)");

session_start();
$_SESSION['user_id']=1; $_SESSION['user_role']='admin';
$_GET['lesson_id']=$lid;
ob_start();
require 'C:/xampp/htdocs/unilis/lecturer/lesson_topics.php';
$html=(string)ob_get_clean();

$checks=[
  'page title'      => strpos($html,'Topics &amp; subtopics')!==false,
  'top topic shown' => strpos($html,'Top A')!==false,
  'subtopic shown'  => strpos($html,'Sub A1')!==false,
  'reader count'    => strpos($html,'have read this')!==false,
  'add form'        => strpos($html,'id="add-form"')!==false,
  'sub add button'  => strpos($html,'Sub-topic')!==false,
];
foreach($checks as $n=>$ok){ echo ($ok?'PASS ':'FAIL ').$n."\n"; }

$conn->query("DELETE FROM public_course_lesson_topics WHERE lesson_id=$lid");
$conn->query("DELETE FROM public_course_lessons WHERE id=$lid");
$conn->query("DELETE FROM public_course_modules WHERE id=$mid");
$conn->query("DELETE FROM public_courses WHERE id=$cid");
echo "cleanup done\n";