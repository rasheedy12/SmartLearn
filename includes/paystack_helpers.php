<?php

function paystack_secret_key()
{
    $key = getenv('PAYSTACK_SECRET_KEY');
    return $key === false ? '' : trim($key);
}

function paystack_checkout_configuration_error()
{
    if (paystack_secret_key() === '') {
        return 'Paystack is not configured on this PHP server. Set PAYSTACK_SECRET_KEY using a newly rotated test key.';
    }

    $callback_url = trim((string) getenv('PAYSTACK_CALLBACK_URL'));
    $host = strtolower((string) parse_url($callback_url, PHP_URL_HOST));
    if ($callback_url === '' || filter_var($callback_url, FILTER_VALIDATE_URL) === false
        || strtolower((string) parse_url($callback_url, PHP_URL_SCHEME)) !== 'https'
        || $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return 'Set PAYSTACK_CALLBACK_URL to the public HTTPS address of pages/paystack_callback.php. For local development, expose your server through an HTTPS tunnel.';
    }

    $ip = filter_var($host, FILTER_VALIDATE_IP);
    if ($ip !== false && filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) === false) {
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
