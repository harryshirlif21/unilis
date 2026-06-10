<?php
namespace SmartLab;

class DigitalSignature {
    private string $algorithm = 'sha256';

    public function generateSignature(
        string $studentId,
        string $practicalId,
        string $timestamp = null,
        string $secret = ''
    ): string {
        if ($timestamp === null) {
            $timestamp = date('Y-m-d H:i:s');
        }

        $data = sprintf(
            '%s|%s|%s|%s',
            $studentId,
            $practicalId,
            $timestamp,
            $secret ?: $_ENV['APP_KEY'] ?? 'default-secret-key'
        );

        return hash($this->algorithm, $data);
    }

    public function verifySignature(
        string $signature,
        string $studentId,
        string $practicalId,
        string $timestamp,
        string $secret = ''
    ): bool {
        $computed = $this->generateSignature($studentId, $practicalId, $timestamp, $secret);
        return hash_equals($computed, $signature);
    }

    public function generateAuthenticationHash(
        array $authenticationData,
        string $method = 'biometric'
    ): string {
        $data = json_encode([
            'method' => $method,
            'data' => $authenticationData,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        return hash($this->algorithm, $data);
    }

    public function hashCredentials(string $studentId, string $practicalId): string {
        return hash(
            $this->algorithm,
            $studentId . '|' . $practicalId . '|' . time()
        );
    }

    public function getAlgorithm(): string {
        return $this->algorithm;
    }

    public function setAlgorithm(string $algorithm): self {
        $supported = hash_algos();
        if (!in_array($algorithm, $supported)) {
            throw new \InvalidArgumentException("Algorithm '$algorithm' not supported");
        }
        $this->algorithm = $algorithm;
        return $this;
    }
}
