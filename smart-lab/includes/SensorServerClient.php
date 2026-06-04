<?php
/**
 * Smart Lab Sensor API Wrapper
 * 
 * Communicates with smart-lab-server-production.py and provides
 * endpoints for RFID scanning and CO2 monitoring in the application.
 * 
 * CO2 is stored in daily JSON files. Database tracks file metadata.
 */

class SensorServerClient {
    private $serverUrl;
    private $timeout = 20;
    private $db;
    private $jsonPath;
    
    // CO2 Threshold Levels (PPM)
    const CO2_EXCELLENT = 600;
    const CO2_GOOD = 1000;
    const CO2_FAIR = 1500;
    const CO2_POOR = 1501;  // Above FAIR is poor
    
    public function __construct($host = 'localhost', $port = 8765, $jsonPath = './co2_data') {
        if (defined('SENSOR_SERVER_URL') && SENSOR_SERVER_URL) {
            $this->serverUrl = rtrim(SENSOR_SERVER_URL, '/');
        } elseif (is_string($host) && preg_match('#^https?://#i', $host)) {
            $this->serverUrl = rtrim($host, '/');
        } else {
            $this->serverUrl = "http://{$host}:{$port}";
        }
        $this->jsonPath = $jsonPath;
        $this->db = $this->getDbConnection();
    }
    
    /**
     * Get database connection
     */
    private function getDbConnection() {
        try {
            global $mysqli;
            if (isset($mysqli)) {
                return $mysqli;
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Set custom server URL (for online deployment)
     */
    public function setServerUrl($url) {
        $this->serverUrl = rtrim($url, '/');
    }
    
    /**
     * Check if sensor server is online
     */
    public function isHealthy() {
        try {
            $response = $this->makeRequest('/health');
            return $response && isset($response['status']) && $response['status'] === 'healthy';
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Trigger RFID scan (12 second timeout)
     */
    public function triggerRfidScan() {
        try {
            $response = $this->makeRequest('/scan', 15);
            if ($response && $response['success']) {
                return [
                    'success' => true,
                    'uid' => $response['uid'],
                    'timestamp' => $response['timestamp']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Unknown error'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get latest CO2 reading from today's JSON file
     */
    public function getLatestCo2Reading() {
        try {
            // Get today's CO2 file path from database
            $filePath = $this->getTodaysCo2FilePath();
            if ($filePath) {
                // Load and return latest entry
                $readings = $this->loadCo2JsonFile($filePath);
                if (!empty($readings)) {
                    return array_pop($readings);  // Get last entry
                }
            }

            // Fallback to live sensor server response if file storage is unavailable
            return $this->fetchLatestCo2FromServer();
        } catch (Exception $e) {
            error_log("CO2 fetch error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all CO2 readings for today
     */
    public function getTodaysCo2Readings() {
        try {
            $filePath = $this->getTodaysCo2FilePath();
            if (!$filePath) {
                return $this->fetchCo2ReadingsFromServer();
            }
            return $this->loadCo2JsonFile($filePath);
        } catch (Exception $e) {
            error_log("CO2 readings error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get CO2 readings for a specific date (YYYY-MM-DD)
     */
    public function getCo2ReadingsByDate($date) {
        try {
            $filePath = $this->getCo2FilePath($date);
            if (!$filePath || !file_exists($filePath)) {
                return $this->fetchCo2ReadingsFromServer();
            }
            return $this->loadCo2JsonFile($filePath);
        } catch (Exception $e) {
            error_log("CO2 date fetch error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get CO2 readings for date range
     */
    public function getCo2ReadingsByDateRange($startDate, $endDate) {
        $allReadings = [];
        $current = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $readings = $this->getCo2ReadingsByDate($dateStr);
            foreach ($readings as $reading) {
                $allReadings[] = array_merge($reading, ['date' => $dateStr]);
            }
            $current->modify('+1 day');
        }
        
        return $allReadings;
    }
    
    /**
     * Check if latest CO2 exceeds recommended level
     * Returns: null (no reading), false (ok), true (warning)
     */
    public function isCo2Warning() {
        $latest = $this->getLatestCo2Reading();
        if (!$latest || !isset($latest['ppm'])) {
            return null;
        }
        return $latest['ppm'] > self::CO2_FAIR;
    }
    
    /**
     * Get CO2 status for display
     * Returns array with status, color, and warning flag
     */
    public function getCo2Status() {
        $latest = $this->getLatestCo2Reading();
        if (!$latest) {
            return [
                'has_reading' => false,
                'ppm' => null,
                'status' => 'No data',
                'color' => '#94a3b8',
                'is_warning' => false
            ];
        }
        
        $ppm = (int)($latest['ppm'] ?? $latest['co2_ppm'] ?? 0);
        $status = $latest['status'] ?? $this->deriveCo2Status($ppm);
        $color = $latest['color'] ?? $this->deriveCo2Color($ppm);
        $isWarning = $ppm > self::CO2_FAIR;
        
        return [
            'has_reading' => true,
            'ppm' => $ppm,
            'timestamp' => $latest['timestamp'] ?? '',
            'status' => $status,
            'color' => $color,
            'is_warning' => $isWarning
        ];
    }
    
    /**
     * Get latest RFID scans
     */
    public function getLatestRfidScans($limit = 100) {
        try {
            $response = $this->makeRequest("/cards?limit={$limit}");
            if (isset($response['success']) && isset($response['data']) && is_array($response['data'])) {
                return $response['data'];
            }

            return is_array($response) ? $response : [];
        } catch (Exception $e) {
            error_log("RFID fetch error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get CO2 file path for today
     */
    private function getTodaysCo2FilePath() {
        $today = date('Y-m-d');
        $filePath = $this->jsonPath . '/co2_' . $today . '.json';
        return file_exists($filePath) ? $filePath : null;
    }
    
    /**
     * Get CO2 file path for specific date
     */
    private function getCo2FilePath($date) {
        $filePath = $this->jsonPath . '/co2_' . $date . '.json';
        return file_exists($filePath) ? $filePath : null;
    }
    
    /**
     * Load CO2 JSON file
     */
    private function loadCo2JsonFile($filePath) {
        try {
            if (!file_exists($filePath)) {
                return [];
            }
            $content = file_get_contents($filePath);
            $data = json_decode($content, true);
            if (!is_array($data)) {
                return [];
            }

            $normalized = [];
            foreach ($data as $reading) {
                if (is_array($reading)) {
                    $normalized[] = $this->normalizeCo2Reading($reading);
                }
            }

            return $normalized;
        } catch (Exception $e) {
            throw new Exception("Failed to load CO2 file: {$filePath}");
        }
    }

    private function normalizeCo2Reading(array $reading) {
        $ppm = (int)($reading['ppm'] ?? $reading['co2_ppm'] ?? $reading['value'] ?? 0);

        return [
            'date' => $reading['date'] ?? null,
            'timestamp' => $reading['timestamp'] ?? $reading['reading_time'] ?? $reading['time'] ?? date('H:i:s'),
            'ppm' => $ppm,
            'status' => $reading['status'] ?? $this->deriveCo2Status($ppm),
            'color' => $reading['color'] ?? $this->deriveCo2Color($ppm),
            'bg' => $reading['bg'] ?? $this->deriveCo2Background($ppm),
        ];
    }

    private function deriveCo2Status($ppm) {
        if ($ppm <= self::CO2_EXCELLENT) return 'Excellent';
        if ($ppm <= self::CO2_GOOD) return 'Good';
        if ($ppm <= self::CO2_FAIR) return 'Fair (Stale)';
        return 'Poor / Ventilation Required';
    }

    private function deriveCo2Color($ppm) {
        if ($ppm <= self::CO2_EXCELLENT) return '#2E8B57';
        if ($ppm <= self::CO2_GOOD) return '#1E6FBA';
        if ($ppm <= self::CO2_FAIR) return '#D4AF37';
        return '#DC3545';
    }

    private function deriveCo2Background($ppm) {
        if ($ppm <= self::CO2_EXCELLENT) return '#EAF4EF';
        if ($ppm <= self::CO2_GOOD) return '#E8F0F8';
        if ($ppm <= self::CO2_FAIR) return '#FAF6E8';
        return '#FDF2F3';
    }

    private function fetchLatestCo2FromServer() {
        $response = $this->makeRequest('/co2');
        if (!$response) {
            return null;
        }

        if (isset($response['success']) && $response['success'] && isset($response['data']) && is_array($response['data'])) {
            return $this->normalizeCo2Reading($response['data']);
        }

        if (isset($response['ppm']) || isset($response['co2_ppm'])) {
            return $this->normalizeCo2Reading($response);
        }

        return null;
    }

    private function fetchCo2ReadingsFromServer() {
        try {
            $response = $this->makeRequest('/co2');
            if (isset($response['success']) && $response['success'] && isset($response['data'])) {
                if (is_array($response['data']) && array_keys($response['data']) === range(0, count($response['data']) - 1)) {
                    $normalized = [];
                    foreach ($response['data'] as $reading) {
                        if (is_array($reading)) {
                            $normalized[] = $this->normalizeCo2Reading($reading);
                        }
                    }
                    return $normalized;
                }

                if (is_array($response['data'])) {
                    return [$this->normalizeCo2Reading($response['data'])];
                }
            }

            if (is_array($response) && array_keys($response) === range(0, count($response) - 1)) {
                $normalized = [];
                foreach ($response as $reading) {
                    if (is_array($reading)) {
                        $normalized[] = $this->normalizeCo2Reading($reading);
                    }
                }
                return $normalized;
            }

            if (isset($response['ppm']) || isset($response['co2_ppm'])) {
                return [$this->normalizeCo2Reading($response)];
            }
        } catch (Exception $e) {
            error_log("CO2 live fallback error: " . $e->getMessage());
        }

        return [];
    }
    
    /**
     * Make HTTP request to sensor server
     */
    private function makeRequest($endpoint, $timeout = null) {
        $url = $this->serverUrl . $endpoint;
        $timeout = $timeout ?? $this->timeout;
        
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET'
            ]
        ]);
        
        try {
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                throw new Exception("Server unreachable: {$url}");
            }
            return json_decode($response, true);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}

// Example usage and API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Determine server host (localhost for dev, sensor.example.com for production)
    $serverHost = defined('SENSOR_SERVER_HOST') ? SENSOR_SERVER_HOST : 'localhost';
    $serverPort = defined('SENSOR_SERVER_PORT') ? SENSOR_SERVER_PORT : 8765;
    $jsonPath = defined('SENSOR_JSON_PATH') ? SENSOR_JSON_PATH : './co2_data';
    
    $client = new SensorServerClient($serverHost, $serverPort, $jsonPath);
    
    switch ($_GET['action']) {
        case 'health':
            echo json_encode(['healthy' => $client->isHealthy()]);
            break;
        
        case 'scan':
            echo json_encode($client->triggerRfidScan());
            break;
        
        case 'co2_latest':
            echo json_encode($client->getLatestCo2Reading());
            break;
        
        case 'co2_status':
            echo json_encode($client->getCo2Status());
            break;
        
        case 'co2_today':
            echo json_encode($client->getTodaysCo2Readings());
            break;
        
        case 'co2_date':
            if (isset($_GET['date'])) {
                echo json_encode($client->getCo2ReadingsByDate($_GET['date']));
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Missing date parameter']);
            }
            break;
        
        case 'co2_range':
            if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                echo json_encode($client->getCo2ReadingsByDateRange($_GET['start_date'], $_GET['end_date']));
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Missing date parameters']);
            }
            break;
        
        case 'cards':
            echo json_encode($client->getLatestRfidScans($_GET['limit'] ?? 100));
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}
?>
