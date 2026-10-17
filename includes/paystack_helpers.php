<?php

function paystack_load_environment()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $env_path = __DIR__ . '/../.env';
    if (!is_file($env_path) || !is_readable($env_path)) {
        return;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }

        $value = trim($value);
        $quote = substr($value, 0, 1);
        if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function paystack_is_local_development()
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '') {
        $app_env = strtolower((string) getenv('APP_ENV'));
        return $app_env === 'local' || $app_env === 'development';
    }

    return strpos($host, 'localhost') !== false
        || $host === '127.0.0.1'
        || $host === '::1';
}

function paystack_secret_key()
{
    paystack_load_environment();
    $key = getenv('PAYSTACK_SECRET_KEY');
    return $key === false ? '' : trim($key);
}

function paystack_checkout_configuration_error()
{
    if (paystack_secret_key() === '') {
        if (paystack_is_local_development()) {
            return '';
        }
        return 'Paystack is not configured on this PHP server. Set PAYSTACK_SECRET_KEY using a newly rotated test key.';
    }

    $callback_url = trim((string) getenv('PAYSTACK_CALLBACK_URL'));
    $host = strtolower((string) parse_url($callback_url, PHP_URL_HOST));
    if ($callback_url === '' || filter_var($callback_url, FILTER_VALIDATE_URL) === false
        || strtolower((string) parse_url($callback_url, PHP_URL_SCHEME)) !== 'https'
        || $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        if (paystack_is_local_development()) {
            return '';
        }
        return 'Set PAYSTACK_CALLBACK_URL to the public HTTPS address of pages/paystack_callback.php. For local development, expose your server through an HTTPS tunnel.';
    }

    $ip = filter_var($host, FILTER_VALIDATE_IP);
    if ($ip !== false && filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false) {
        if (paystack_is_local_development()) {
            return '';
        }
        return 'PAYSTACK_CALLBACK_URL must use a publicly reachable HTTPS address.';
    }

    return '';
}

function paystack_supported_currencies()
{
    return [
        'NGN' => 'Nigerian Naira',
        'GHS' => 'Ghanaian Cedi',
        'ZAR' => 'South African Rand',
        'KES' => 'Kenyan Shilling',
        'USD' => 'US Dollar',
        'XOF' => 'West African CFA Franc',
        'XAF' => 'Central African CFA Franc',
        'EGP' => 'Egyptian Pound',
        'RWF' => 'Rwandan Franc',
    ];
}

function paystack_currency_minor_units($currency)
{
    return in_array($currency, ['XOF', 'XAF', 'RWF'], true) ? 1 : 100;
}

function paystack_usd_exchange_rate($currency)
{
    if ($currency === 'USD') {
        return 1.0;
    }

    $curl = curl_init('https://open.er-api.com/v6/latest/USD');
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the exchange-rate request.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $status !== 200) {
        throw new RuntimeException('Exchange-rate service request failed: ' . $curl_error);
    }
    $data = json_decode($response, true);
    $rate = $data['rates'][$currency] ?? null;
    if (($data['result'] ?? '') !== 'success' || !is_numeric($rate) || (float) $rate <= 0) {
        throw new RuntimeException('Exchange-rate service did not return a valid rate for ' . $currency . '.');
    }
    return (float) $rate;
}

function paystack_api_request($method, $path, $payload = null)
{
    $secret = paystack_secret_key();
    if ($secret === '') {
        throw new RuntimeException('Paystack is not configured. Set PAYSTACK_SECRET_KEY on the server.');
    }

    $curl = curl_init('https://api.paystack.co' . $path);
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the Paystack request.');
    }
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secret,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ];
    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Paystack API request failed with HTTP ' . $status . ': ' . $curl_error);
    }
    $data = json_decode($response, true);
    if (!is_array($data) || ($data['status'] ?? false) !== true) {
        throw new RuntimeException('Paystack returned an invalid or unsuccessful response.');
    }
    return $data['data'] ?? [];
}
