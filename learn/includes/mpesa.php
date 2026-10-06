<?php
/**
 * Safaricom Daraja helpers for short-course STK Push and B2B settlement.
 * Credentials are read exclusively from environment variables.
 */

function learn_mpesa_config(string $kind = 'stk', ?string $environmentOverride = null): array
{
    $environment = $environmentOverride ?? getenv('MPESA_ENVIRONMENT');
    $prefix = $kind === 'b2b' ? 'MPESA_B2B_' : 'MPESA_';
    $resultUrl = $kind === 'b2b'
        ? getenv('MPESA_B2B_RESULT_URL')
        : (getenv('MPESA_STK_RESULT_URL') ?: getenv('MPESA_RESULT_URL') ?: getenv('MPESA_CALLBACK_URL'));
    $timeoutUrl = $kind === 'b2b'
        ? getenv('MPESA_B2B_TIMEOUT_URL')
        : (getenv('MPESA_STK_TIMEOUT_URL') ?: getenv('MPESA_TIMEOUT_URL') ?: getenv('MPESA_CALLBACK_URL'));
    return [
        'environment' => strtolower((string)($environment ?: 'sandbox')),
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

/*
 * Access-token cache.
 *
 * Daraja short-lived bearer tokens are expensive to fetch (one Basic-auth round
 * trip each time) and are valid for a while after issue, so we cache them for
 * the process lifetime and only re-request shortly before they expire. The
 * cache is keyed by environment + consumer key so sandbox and production, and
 * the STK app versus the B2B app, never share a token. Tokens live in a process
 * variable only - never in a database, log, or the browser.
 */

function learn_mpesa_token_cache_key(string $environment, string $consumerKey): string
{
    return strtolower(trim($environment)) . ':' . preg_replace('/[^A-Za-z0-9]/', '', (string)$consumerKey);
}

function learn_mpesa_token_cache_get(string $key): ?string
{
    $cache = $GLOBALS['learn_mpesa_token_cache'] ?? [];
    $entry = $cache[$key] ?? null;
    if (!is_array($entry)) {
        return null;
    }
    if ((int)$entry['expires_at'] <= time()) {
        // Expired while this request was in flight: drop it so the next call
        // fetches a fresh token instead of reusing a dead one.
        unset($cache[$key]);
        $GLOBALS['learn_mpesa_token_cache'] = $cache;
        return null;
    }
    return (string)$entry['access_token'];
}

function learn_mpesa_token_cache_set(string $key, string $token, int $ttlSeconds): void
{
    if ($token === '' || $ttlSeconds < 2) {
        return;
    }
    $cache = $GLOBALS['learn_mpesa_token_cache'] ?? [];
    // Refresh a little before Safaricom actually expires the token so a call
    // right at the boundary never sends an expired bearer token.
    $safetyMargin = 30;
    $cache[$key] = [
        'access_token' => $token,
        'expires_at' => time() + max(1, $ttlSeconds - $safetyMargin),
    ];
    $GLOBALS['learn_mpesa_token_cache'] = $cache;
}

/**
 * Drop cached tokens. With no arguments the whole cache is cleared (used by the
 * test-suite); with an environment + consumer key only that entry is removed.
 */
function learn_mpesa_token_clear_cache(?string $environment = null, ?string $consumerKey = null): void
{
    $cache = $GLOBALS['learn_mpesa_token_cache'] ?? [];
    if ($environment === null || $consumerKey === null) {
        $GLOBALS['learn_mpesa_token_cache'] = [];
        return;
    }
    unset($cache[learn_mpesa_token_cache_key($environment, $consumerKey)]);
    $GLOBALS['learn_mpesa_token_cache'] = $cache;
}

/**
 * Map a non-zero Daraja STK result code onto the richer failure states stored on
 * a short-course payment row.
 */
function learn_mpesa_failure_status(int $code): array
{
    // 1032 = the payer declined/cancelled the prompt; 1037 = the prompt timed out.
    if ($code === 1032) return ['cancelled', 'Transaction cancelled by the customer.'];
    if ($code === 1037) return ['timeout', 'Transaction timed out before completion.'];
    return ['failed', 'M-Pesa reported an unsuccessful transaction.'];
}

/**
 * Whether the amount the wallet actually charged (from a callback) matches the
 * authoritative amount we stored, within a cent to allow for formatting.
 */
function learn_mpesa_amount_matches(float $claimed, float $expected): bool
{
    return ($claimed - $expected < 0.005) && ($expected - $claimed < 0.005);
}

/**
 * Fetch (or return a cached) Daraja bearer token for the given config.
 *
 * The plaintext consumer secret never leaves this function: it is used only to
 * build the HTTP Basic header and is not stored, logged, or returned.
 */
function learn_mpesa_token(array $config): string
{
    if ($config['consumer_key'] === '' || $config['consumer_secret'] === '') {
        throw new RuntimeException('M-Pesa API credentials are not configured.');
    }
    $key = learn_mpesa_token_cache_key(
        (string)$config['environment'],
        (string)$config['consumer_key']
    );
    $cached = learn_mpesa_token_cache_get($key);
    if ($cached !== null && $cached !== '') {
        return $cached;
    }

    $url = learn_mpesa_base_url($config) . '/oauth/v1/generate?grant_type=client_credentials';
    $basic = base64_encode($config['consumer_key'] . ':' . $config['consumer_secret']);
    $response = learn_mpesa_http('GET', $url, ['Authorization: Basic ' . $basic]);
    $token = (string)($response['access_token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('M-Pesa did not return an access token.');
    }
    $expiresIn = (int)($response['expires_in'] ?? 3600);
    learn_mpesa_token_cache_set($key, $token, $expiresIn > 0 ? $expiresIn : 3600);
    return $token;
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

function learn_mpesa_stk_push(string $phone, int $amount, string $reference, string $description, ?string $environmentOverride = null): array
{
    $config = learn_mpesa_config('stk', $environmentOverride);
    foreach (['consumer_key', 'consumer_secret', 'shortcode', 'passkey', 'result_url'] as $key) {
        if ($config[$key] === '') {
            $environmentNames = [
                'consumer_key' => 'MPESA_CONSUMER_KEY',
                'consumer_secret' => 'MPESA_CONSUMER_SECRET',
                'shortcode' => 'MPESA_SHORTCODE',
                'passkey' => 'MPESA_PASSKEY',
                'result_url' => 'MPESA_STK_RESULT_URL or MPESA_RESULT_URL',
            ];
            throw new RuntimeException('M-Pesa STK configuration is missing ' . $environmentNames[$key] . '.');
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
