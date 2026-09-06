<?php
/**
 * Safaricom Daraja helpers for short-course STK Push and B2B settlement.
 * Credentials are read exclusively from environment variables.
 */

function learn_mpesa_config(string $kind = 'stk'): array
{
    $prefix = $kind === 'b2b' ? 'MPESA_B2B_' : 'MPESA_';
    $resultUrl = $kind === 'b2b'
        ? getenv('MPESA_B2B_RESULT_URL')
        : (getenv('MPESA_STK_RESULT_URL') ?: getenv('MPESA_RESULT_URL'));
    $timeoutUrl = $kind === 'b2b'
        ? getenv('MPESA_B2B_TIMEOUT_URL')
        : (getenv('MPESA_STK_TIMEOUT_URL') ?: getenv('MPESA_TIMEOUT_URL'));
    return [
        'environment' => strtolower((string)(getenv('MPESA_ENVIRONMENT') ?: 'sandbox')),
        'consumer_key' => (string)(getenv($prefix . 'CONSUMER_KEY') ?: getenv('MPESA_CONSUMER_KEY') ?: ''),
        'consumer_secret' => (string)(getenv($prefix . 'CONSUMER_SECRET') ?: getenv('MPESA_CONSUMER_SECRET') ?: ''),
        'shortcode' => (string)(getenv($prefix . 'SHORTCODE') ?: getenv('MPESA_SHORTCODE') ?: ''),
        'passkey' => (string)(getenv('MPESA_PASSKEY') ?: ''),
        'initiator_name' => (string)(getenv($prefix . 'INITIATOR_NAME') ?: ''),
        'security_credential' => (string)(getenv($prefix . 'SECURITY_CREDENTIAL') ?: ''),
        'result_url' => (string)($resultUrl ?: ''),
        'timeout_url' => (string)($timeoutUrl ?: ''),
    ];
}

function learn_mpesa_base_url(array $config): string
{
    return $config['environment'] === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}

function learn_mpesa_http(string $method, string $url, array $headers, ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => $body,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $error !== '') {
        throw new RuntimeException('M-Pesa request failed: ' . $error);
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('M-Pesa returned an invalid response.');
    }
    if ($status >= 400) {
        throw new RuntimeException((string)($decoded['errorMessage'] ?? 'M-Pesa rejected the request.'));
    }
    return $decoded;
}

function learn_mpesa_token(array $config): string
{
    if ($config['consumer_key'] === '' || $config['consumer_secret'] === '') {
        throw new RuntimeException('M-Pesa API credentials are not configured.');
    }
    $url = learn_mpesa_base_url($config) . '/oauth/v1/generate?grant_type=client_credentials';
    $basic = base64_encode($config['consumer_key'] . ':' . $config['consumer_secret']);
    $response = learn_mpesa_http('GET', $url, ['Authorization: Basic ' . $basic]);
    return (string)($response['access_token'] ?? '');
}

function learn_mpesa_normalise_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (strpos($digits, '0') === 0) {
        $digits = '254' . substr($digits, 1);
    } elseif (strpos($digits, '+') === 0) {
        $digits = ltrim($digits, '+');
    }
    if (!preg_match('/^2547\d{8}$/', $digits)) {
        throw new InvalidArgumentException('Enter a valid Safaricom number.');
    }
    return $digits;
}

function learn_mpesa_stk_push(string $phone, int $amount, string $reference, string $description): array
{
    $config = learn_mpesa_config('stk');
    foreach (['shortcode', 'passkey', 'result_url', 'timeout_url'] as $key) {
        if ($config[$key] === '') {
            throw new RuntimeException('M-Pesa STK configuration is incomplete.');
        }
    }
    $token = learn_mpesa_token($config);
    $timestamp = date('YmdHis');
    $password = base64_encode($config['shortcode'] . $config['passkey'] . $timestamp);
    $payload = [
        'BusinessShortCode' => $config['shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => learn_mpesa_normalise_phone($phone),
        'PartyB' => $config['shortcode'],
        'PhoneNumber' => learn_mpesa_normalise_phone($phone),
        'CallBackURL' => $config['result_url'],
        'AccountReference' => $reference,
        'TransactionDesc' => $description,
    ];
    return learn_mpesa_http(
        'POST',
        learn_mpesa_base_url($config) . '/mpesa/stkpush/v1/processrequest',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        json_encode($payload, JSON_THROW_ON_ERROR)
    );
}

function learn_mpesa_b2b(string $receiver_shortcode, int $amount, string $reference, string $receiver_type = 'paybill'): array
{
    $config = learn_mpesa_config('b2b');
    foreach (['shortcode', 'initiator_name', 'security_credential', 'result_url', 'timeout_url'] as $key) {
        if ($config[$key] === '') {
            throw new RuntimeException('M-Pesa B2B configuration is incomplete.');
        }
    }
    $token = learn_mpesa_token($config);
    $payload = [
        'Initiator' => $config['initiator_name'],
        'SecurityCredential' => $config['security_credential'],
        'CommandID' => 'BusinessToBusinessTransfer',
        'SenderIdentifierType' => '4',
        'RecieverIdentifierType' => $receiver_type === 'till' ? '2' : '4',
        'Amount' => $amount,
        'PartyA' => $config['shortcode'],
        'PartyB' => $receiver_shortcode,
        'Remarks' => $reference,
        'QueueTimeOutURL' => $config['timeout_url'],
        'ResultURL' => $config['result_url'],
        'AccountReference' => $reference,
    ];
    return learn_mpesa_http(
        'POST',
        learn_mpesa_base_url($config) . '/mpesa/b2b/v1/paymentrequest',
        ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        json_encode($payload, JSON_THROW_ON_ERROR)
    );
}
