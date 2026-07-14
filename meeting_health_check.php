<?php
/**
 * Meeting-UI Health Check
 * -----------------------
 * Diagnoses connectivity between the PHP app container and the Python
 * meeting-server container over the Docker network, and checks Apache's
 * proxy config for /meeting-ui/.
 *
 * USAGE:
 *   Deploy this file to your web root (e.g. same folder as index.php),
 *   commit + push so GitHub Actions deploys it, then visit:
 *     https://unilis.jhubafrica.com/meeting_health_check.php?key=CHANGE_ME
 *
 * SECURITY:
 *   Change the SECRET_KEY below before deploying, since this reveals
 *   internal hostnames/ports. Delete this file once you're done debugging.
 */

$SECRET_KEY = 'Attack_2086';

if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
    http_response_code(403);
    die('Forbidden. Add ?key=YOUR_SECRET_KEY to the URL.');
}

header('Content-Type: text/plain');

echo "=== Meeting-UI Health Check ===\n";
echo "Server time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP version: " . phpversion() . "\n";
echo "Hostname (this container): " . gethostname() . "\n";
echo "curl extension loaded: " . (extension_loaded('curl') ? 'yes' : 'NO - install php-curl') . "\n\n";

echo "--- Checking environment variables ---\n";
$envCandidates = [
    'MEETING_SERVER_URL', 'MEETING_SERVICE_URL', 'MEETING_API_URL',
    'MEETING_SERVER_HOST', 'MEETING_SERVER_PORT', 'PYTHON_SERVICE_URL',
];
$foundEnv = false;
foreach ($envCandidates as $var) {
    $val = getenv($var);
    if ($val !== false && $val !== '') {
        echo "  $var = $val\n";
        $foundEnv = true;
    }
}
if (!$foundEnv) {
    echo "  (none of the common env var names were set - not necessarily a problem)\n";
}
echo "\n";

echo "--- Testing internal Docker network connectivity ---\n";

$hostCandidates = [
    'meeting-server', 'meeting-media', 'unilis-meeting-media',
    'meeting_server', 'meeting-service', 'python-service', 'media-server',
];
$portCandidates = [5000, 8000, 8080, 3000, 8001, 5001, 8888];
$pathCandidates = ['/', '/health', '/healthz', '/status'];

$anySuccess = false;

foreach ($hostCandidates as $host) {
    $ip = @gethostbyname($host);
    $resolved = ($ip !== $host);
    if (!$resolved) {
        continue;
    }
    echo "Host '$host' resolves to $ip\n";

    foreach ($portCandidates as $port) {
        foreach ($pathCandidates as $path) {
            $url = "http://$host:$port$path";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body !== false) {
                $anySuccess = true;
                $snippet = substr(trim(strip_tags($body)), 0, 150);
                echo "  OK $url -> HTTP $code | body: " . ($snippet ?: '(empty)') . "\n";
            } elseif ($err && strpos($err, 'Connection refused') === false && strpos($err, 'Failed to connect') === false) {
                echo "  WARN $url -> $err\n";
            }
        }
    }
}

if (!$anySuccess) {
    echo "\n  FAIL: No successful connection found on any host/port combination tried.\n";
    echo "  This means either:\n";
    echo "    - the meeting-server container isn't running, or\n";
    echo "    - it's on a different Docker network than this PHP container, or\n";
    echo "    - it uses a hostname/port not in the guess-list above.\n";
}
echo "\n";

echo "--- Checking Apache proxy config ---\n";
$confPaths = [
    '/etc/apache2/sites-enabled/000-default.conf',
    '/etc/apache2/sites-available/000-default.conf',
];
$foundConf = false;
foreach ($confPaths as $path) {
    if (is_readable($path)) {
        $foundConf = true;
        $contents = file_get_contents($path);
        echo "Readable: $path\n";
        foreach (explode("\n", $contents) as $line) {
            if (stripos($line, 'meeting') !== false || stripos($line, 'proxypass') !== false) {
                echo "  " . trim($line) . "\n";
            }
        }
    }
}
if (!$foundConf) {
    echo "  Could not read Apache config from this PHP process.\n";
}
echo "\n";

echo "--- Testing /meeting-ui/host via localhost (through Apache) ---\n";
$ch = curl_init('http://localhost/meeting-ui/host');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "HTTP status: $code\n";
if ($err) echo "curl error: $err\n";
if ($body) echo "Response snippet: " . substr(trim(strip_tags($body)), 0, 200) . "\n";

echo "\n=== End of report ===\n";