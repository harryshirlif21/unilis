<?php
// Direct test of QR controller
require_once __DIR__.'/controllers/QrAuthController.php';

$qrController = new QrAuthController();
$qrController->generate();
?>
