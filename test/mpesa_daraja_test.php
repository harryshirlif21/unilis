<?php
/**
 * Pure-logic tests for the M-Pesa (Daraja) payment layer.
 *
 * These do NOT touch the network or the database: they exercise the functions in
 * learn/includes/mpesa.php that make decisions (phone validation, token caching,
 * callback result-code mapping, amount verification), plus a couple of shared
 * callback helpers. Run with:  php test/mpesa_daraja_test.php
 */
require_once __DIR__ . '/../learn/includes/mpesa.php';

$pass = 0;
$fail = 0;

function check(bool $cond, string $name): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo 'PASS  ' . $name . "\n";
    } else {
        $fail++;
        echo 'FAIL  ' . $name . "\n";
    }
}

function checkThrows($fn, string $name): void
{
    try {
        $fn();
        check(false, $name . ' (expected an exception)');
    } catch (Throwable $e) {
        check(true, $name);
    }
}

echo "== Phone number validation ==\n";
check(learn_mpesa_normalise_phone('0712345678') === '254712345678', '0-prefix normalised to 2547...');
check(learn_mpesa_normalise_phone('254712345678') === '254712345678', 'already 2547... left alone');
check(learn_mpesa_normalise_phone('+254712345678') === '254712345678', 'leading + stripped');
checkThrows(fn() => learn_mpesa_normalise_phone('12345'), 'too-short number rejected');
checkThrows(fn() => learn_mpesa_normalise_phone('071234567'), '9-digit 07 number rejected');
checkThrows(fn() => learn_mpesa_normalise_phone(''), 'empty number rejected');

echo "\n== Access-token cache ==\n";
learn_mpesa_token_clear_cache();
check(learn_mpesa_token_cache_get('sandbox:key') === null, 'empty cache returns null');
learn_mpesa_token_cache_set('sandbox:key', 'tok123', 3600);
check(learn_mpesa_token_cache_get('sandbox:key') === 'tok123', 'cached token returned');
learn_mpesa_token_cache_set('sandbox:key', 'other', 1);
check(learn_mpesa_token_cache_get('sandbox:key') === 'tok123', 'sub-2s TTL ignored (no clobber)');
learn_mpesa_token_cache_set('prod:key', 'tokProd', 3600);
check(
    learn_mpesa_token_cache_get('sandbox:key') === 'tok123'
    && learn_mpesa_token_cache_get('prod:key') === 'tokProd',
    'keys isolated between environment/consumer'
);
$GLOBALS['learn_mpesa_token_cache'] = ['stale' => ['access_token' => 'x', 'expires_at' => time() - 5]];
check(learn_mpesa_token_cache_get('stale') === null, 'expired token dropped on read');
learn_mpesa_token_clear_cache();
check(learn_mpesa_token_cache_get('sandbox:key') === null, 'clear-all empties the cache');

echo "\n== Callback result-code mapping ==\n";
check(learn_mpesa_failure_status(1032)[0] === 'cancelled', '1032 -> cancelled');
check(learn_mpesa_failure_status(1037)[0] === 'timeout', '1037 -> timeout');
check(learn_mpesa_failure_status(1)[0] === 'failed', 'other code -> failed');

echo "\n== Amount verification (never trust the callback) ==\n";
check(learn_mpesa_amount_matches(12750.0, 12750.0) === true, 'matching amount accepted');
check(learn_mpesa_amount_matches(250.0, 250.0) === true, 'exact small amount accepted');
check(learn_mpesa_amount_matches(12750.5, 12750.0) === false, 'mismatch rejected');
check(learn_mpesa_amount_matches(12750.0, 12749.0) === false, 'one-shilling mismatch rejected');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);