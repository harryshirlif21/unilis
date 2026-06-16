<?php
/**
 * UNILIS SmartLabs - Dynamic QR Code Service
 * 
 * Generates dynamic QR codes for attendance verification.
 * QR codes are displayed on laboratory screens and:
 * - Refresh every 15 seconds
 * - Contain lab_id, session_id, practical_id, timestamp, expiration, digital signature
 * - Expire after a single use
 * - Cannot be reused from screenshots
 * - Ensure student is physically inside the laboratory
 */

class DynamicQRService {
    private PDO $db;
    private int $refreshInterval = 15; // seconds
    
    public function __construct(?PDO $db = null) {
        $this->db = $db ?? getDB();
    }
    
    /**
     * Generate a dynamic QR code payload for attendance verification
     * This is displayed on the lab projector/TV screen
     */
    public function generateQRPayload(string $labId, string $practicalId, string $sessionId = null): array {
        try {
            $timestamp = time();
            $expirationTimestamp = $timestamp + $this->refreshInterval;
            
            // Generate a unique nonce to prevent replay attacks
            $nonce = bin2hex(random_bytes(16));
            
            // Build payload
            $payload = [
                'lab_id' => $labId,
                'session_id' => $sessionId ?? $practicalId,
                'practical_id' => $practicalId,
                'timestamp' => $timestamp,
                'expiration_timestamp' => $expirationTimestamp,
                'nonce' => $nonce,
                'type' => 'attendance_verification'
            ];
            
            // Generate digital signature
            $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $digitalSignature = $this->generateSignature($payloadJson, $labId);
            $payload['digital_signature'] = $digitalSignature;
            
            // Store the nonce to prevent reuse
            $this->storeNonce($nonce, $labId, $practicalId, $expirationTimestamp);
            
            // Encode for QR display
            $encodedPayload = base64_encode(json_encode($payload));
            
            return [
                'success' => true,
                'qr_data' => $encodedPayload,
                'payload' => $payload,
                'expires_at' => $expirationTimestamp,
                'refresh_interval' => $this->refreshInterval,
                'generated_at' => date('Y-m-d H:i:s', $timestamp)
            ];
            
        } catch (Exception $e) {
            error_log("DynamicQRService::generateQRPayload Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Verify a scanned QR code payload
     * This runs when a student scans the QR code from their phone
     */
    public function verifyQRPayload(string $encodedPayload, string $studentId): array {
        try {
            // Decode the payload
            $payload = json_decode(base64_decode($encodedPayload), true);
            if (!$payload) {
                return ['valid' => false, 'message' => 'Invalid QR code format'];
            }
            
            // Verify required fields exist
            $required = ['lab_id', 'session_id', 'practical_id', 'timestamp', 'expiration_timestamp', 'nonce', 'digital_signature'];
            foreach ($required as $field) {
                if (!isset($payload[$field])) {
                    return ['valid' => false, 'message' => "Missing required field: {$field}"];
                }
            }
            
            // Check expiration
            $now = time();
            if ($now > $payload['expiration_timestamp']) {
                return ['valid' => false, 'message' => 'QR code has expired. Please scan a new one from the lab display.'];
            }
            
            // Verify digital signature
            $payloadWithoutSig = $payload;
            unset($payloadWithoutSig['digital_signature']);
            $payloadJson = json_encode($payloadWithoutSig, JSON_UNESCAPED_SLASHES);
            
            if (!$this->verifySignature($payloadJson, $payload['digital_signature'], $payload['lab_id'])) {
                return ['valid' => false, 'message' => 'Invalid QR code signature'];
            }
            
            // Check nonce (prevent replay/screenshot reuse)
            $nonceCheck = $this->verifyNonce($payload['nonce']);
            if (!$nonceCheck['valid']) {
                return [
                    'valid' => false, 
                    'message' => $nonceCheck['message'] ?? 'This QR code has already been used or is invalid.'
                ];
            }
            
            // Mark nonce as used
            $this->useNonce($payload['nonce']);
            
            // Log successful verification
            logActivity($studentId, 'dynamic_qr_verified', 'practical', [
                'lab_id' => $payload['lab_id'],
                'practical_id' => $payload['practical_id']
            ]);
            
            return [
                'valid' => true,
                'message' => 'Attendance verified via Dynamic QR',
                'lab_id' => $payload['lab_id'],
                'practical_id' => $payload['practical_id'],
                'session_id' => $payload['session_id'],
                'verification_method' => 'DYNAMIC_QR',
                'verified_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("DynamicQRService::verifyQRPayload Error: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Verification error'];
        }
    }
    
    /**
     * Generate a digital signature for the QR payload
     */
    private function generateSignature(string $data, string $labId): string {
        $secretKey = $this->getLabSecretKey($labId);
        return hash_hmac('sha256', $data, $secretKey);
    }
    
    /**
     * Verify the digital signature
     */
    private function verifySignature(string $data, string $signature, string $labId): bool {
        $expectedSignature = $this->generateSignature($data, $labId);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Store a nonce to prevent QR code reuse
     */
    private function storeNonce(string $nonce, string $labId, string $practicalId, int $expirationTimestamp): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, module, created_at)
                VALUES ('system', ?, 'qr_nonce', NOW())
            ");
            $stmt->execute(["qr_nonce_generated:{$nonce}:lab={$labId}:prac={$practicalId}"]);
            
            // Also store in session/APCu if available
            $cacheKey = "qr_nonce_{$nonce}";
            $ttl = max($this->refreshInterval * 2, $expirationTimestamp - time());
            
            // Use file-based cache as fallback
            $cacheDir = __DIR__ . '/../cache/qr_nonces/';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents(
                $cacheDir . $nonce,
                json_encode([
                    'nonce' => $nonce,
                    'lab_id' => $labId,
                    'practical_id' => $practicalId,
                    'expires_at' => $expirationTimestamp,
                    'used' => false
                ])
            );
            
        } catch (Exception $e) {
            error_log("DynamicQRService::storeNonce Error: " . $e->getMessage());
        }
    }
    
    /**
     * Verify a nonce hasn't been used before
     */
    private function verifyNonce(string $nonce): array {
        try {
            $cacheDir = __DIR__ . '/../cache/qr_nonces/';
            $nonceFile = $cacheDir . $nonce;
            
            if (!file_exists($nonceFile)) {
                // The nonce may be stored in DB logs
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as cnt FROM audit_logs 
                    WHERE action LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                ");
                $stmt->execute(["qr_nonce_generated:{$nonce}%"]);
                $result = $stmt->fetch();
                
                if ($result['cnt'] === 0) {
                    return ['valid' => false, 'message' => 'QR code not recognized by system'];
                }
                
                // Check if already used
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as cnt FROM audit_logs 
                    WHERE action LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                ");
                $stmt->execute(["qr_nonce_used:{$nonce}%"]);
                
                if ($result['cnt'] > 0) {
                    return ['valid' => false, 'message' => 'QR code has already been used'];
                }
                
                return ['valid' => true];
            }
            
            $data = json_decode(file_get_contents($nonceFile), true);
            if (!$data) {
                return ['valid' => false, 'message' => 'Invalid nonce data'];
            }
            
            if ($data['used']) {
                return ['valid' => false, 'message' => 'QR code has already been scanned'];
            }
            
            if ($data['expires_at'] < time()) {
                return ['valid' => false, 'message' => 'QR code has expired'];
            }
            
            return ['valid' => true];
            
        } catch (Exception $e) {
            error_log("DynamicQRService::verifyNonce Error: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Nonce verification error'];
        }
    }
    
    /**
     * Mark a nonce as used (prevent screenshot reuse)
     */
    private function useNonce(string $nonce): void {
        try {
            $cacheDir = __DIR__ . '/../cache/qr_nonces/';
            $nonceFile = $cacheDir . $nonce;
            
            if (file_exists($nonceFile)) {
                $data = json_decode(file_get_contents($nonceFile), true);
                $data['used'] = true;
                file_put_contents($nonceFile, json_encode($data));
            }
            
            // Log usage
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, module, created_at)
                VALUES ('system', ?, 'qr_nonce', NOW())
            ");
            $stmt->execute(["qr_nonce_used:{$nonce}"]);
            
        } catch (Exception $e) {
            error_log("DynamicQRService::useNonce Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get the lab secret key for HMAC signing
     */
    private function getLabSecretKey(string $labId): string {
        // Use a system-wide secret combined with lab-specific data
        $systemSecret = defined('APP_SECRET') ? APP_SECRET : 'unilis_smartlab_default_secret_key_2026';
        return hash('sha256', $systemSecret . $labId . 'dynamic_qr_v1');
    }
    
    /**
     * Get the current active QR code for a lab's display screen
     * (Called every 15 seconds by the frontend rotating display)
     */
    public function getCurrentLabQR(string $labId, string $practicalId): array {
        return $this->generateQRPayload($labId, $practicalId);
    }
    
    /**
     * Clean up expired nonces
     */
    public function cleanupExpiredNonces(): int {
        $count = 0;
        $cacheDir = __DIR__ . '/../cache/qr_nonces/';
        if (!is_dir($cacheDir)) {
            return 0;
        }
        
        $files = glob($cacheDir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && $data['expires_at'] < $now) {
                    unlink($file);
                    $count++;
                }
            }
        }
        
        return $count;
    }
}