<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require '../vendor/autoload.php';

$APP_ID = "your-app-id";
$APP_SECRET = "your-app-secret"; // must match Jitsi prosody config
$meeting_id = $_GET['meeting_id'] ?? null;
$userName = $_SESSION['user_name'];
$userRole = $_SESSION['user_role']; // "lecturer" or "student"

$roomName = "unilis_meeting_" . $meeting_id;

// Create JWT payload
$payload = [
    "aud" => "jitsi",
    "iss" => $APP_ID,
    "sub" => "meet.jit.si", // or your self-hosted domain
    "room" => $roomName,
    "exp" => time() + 3600,
    "context" => [
        "user" => [
            "name" => $userName,
            "email" => $_SESSION['email'] ?? '',
            "moderator" => ($userRole === 'lecturer') ? true : false
        ]
    ]
];

// Generate token
$jwt = JWT::encode($payload, $APP_SECRET, 'HS256');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Meeting</title>
    <script src="https://meet.jit.si/external_api.js"></script>
</head>
<body>
    <div id="jitsi-container" style="height:100vh;width:100%"></div>

    <script>
        const domain = "your.jitsi.domain"; // not meet.jit.si if JWT needed
        const options = {
            roomName: "<?= $roomName ?>",
            jwt: "<?= $jwt ?>",  // attach lecturer/student token
            width: "100%",
            height: "100%",
            parentNode: document.querySelector('#jitsi-container'),
            userInfo: {
                displayName: "<?= htmlspecialchars($userName) ?>"
            }# Install system deps
            RUN apt-get update && apt-get install -y \
                libzip-dev unzip git && \
                docker-php-ext-install pdo pdo_mysql zip
            
            # Enable Apache mod_rewrite
            RUN a2enmod rewrite
            
            # Copy app files
            COPY . /var/www/html/
            
            # Install Composer
            COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
            
            # Install PHP dependencies
            WORKDIR /var/www/html
            RUN composer install --no-dev --optimize-autoloader
        };
        const api = new JitsiMeetExternalAPI(domain, options);
    </script>
</body>
</html>
