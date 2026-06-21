<?php
$hosts = ['127.0.0.1:3306', '127.0.0.1:3308', '127.0.0.1:3309', 'localhost:3306'];
$creds = [
    ['root', ''],
    ['root', 'rootpass'],
    ['lab_admin', 'lab_password'],
    ['unilisuser', 'unilispass'],
];

foreach ($hosts as $hostport) {
    list($host, $port) = explode(':', $hostport);
    foreach ($creds as $cred) {
        list($user, $pass) = $cred;
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=unilis_smartlab;charset=utf8mb4", $user, $pass);
            echo "CONNECTED: $host:$port as $user\n";
            $r = $pdo->query("SELECT VERSION()")->fetch();
            echo "MySQL: " . $r[0] . "\n\n";
            goto done;
        } catch (Exception $e) {
            // try next
        }
    }
}
echo "No connection found\n";
done:
echo "Check complete\n";