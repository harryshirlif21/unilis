<?php
// send_email.php

$message_sent = ""; // variable to store status message

if (isset($_POST['send_email'])) {
    $to = "mwendihillary21@gmail.com";     // your email
    $subject = "Test Email from PHP";
    $message = "Hello Hillary! This is a test email.";
    $headers = "From: sender@example.com"; // change to a valid sender email

    if (mail($to, $subject, $message, $headers)) {
        $message_sent = "<p style='color:green;'>Email sent successfully!</p>";
    } else {
        $message_sent = "<p style='color:red;'>Failed to send email.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Email</title>
</head>
<body>
    <?php
        if (!empty($message_sent)) {
            echo $message_sent;
        }
    ?>
    <form method="post" action="">
        <button type="submit" name="send_email">Send Email</button>
    </form>
</body>
</html>
