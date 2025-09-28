<?php
if (isset($_POST['send_email'])) {
    $to = "mwendihillary21@gmail.com";     // your email
    $subject = "Test Email from PHP";
    $message = "Hello Hillary! This is a test email.";
    $headers = "From: sender@example.com"; // change to a valid sender email

    if (mail($to, $subject, $message, $headers)) {
        echo "<p style='color:green;'>Email sent successfully!</p>";
    } else {
        echo "<p style='color:red;'>Failed to send email.</p>";
    }
}
?>
