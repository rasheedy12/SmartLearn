<?php

require_once __DIR__ . '/auth_session.php';

function smartlearn_oauth_load_environment()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $env_path = dirname(__DIR__) . '/.env';
    if (!is_file($env_path) || !is_readable($env_path)) {
        return;
    }
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        if ($key === '' || preg_match('/\A[A-Z][A-Z0-9_]*\z/', $key) !== 1 || getenv($key) !== false) {
            continue;
        }
        $quote = substr($value, 0, 1);
        if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function smartlearn_oauth_env($name, $default = '')
{
    smartlearn_oauth_load_environment();
    $value = getenv($name);
    return $value === false ? $default : trim((string) $value);
}

function smartlearn_oauth_config($provider)
{
    if (!in_array($provider, ['google', 'microsoft'], true)) {
        throw new RuntimeException('Unsupported sign-in provider.');
    }
    $prefix = $provider === 'google' ? 'GOOGLE' : 'MICROSOFT';
    $config = [
        'provider' => $provider,
        'client_id' => smartlearn_oauth_env('OAUTH_' . $prefix . '_CLIENT_ID'),
        'client_secret' => smartlearn_oauth_env('OAUTH_' . $prefix . '_CLIENT_SECRET'),
        'redirect_uri' => smartlearn_oauth_env('OAUTH_REDIRECT_URI'),
    ];
    if ($provider === 'microsoft') {
        $config['tenant'] = smartlearn_oauth_env('OAUTH_MICROSOFT_TENANT_ID', 'common');
        if (!in_array($config['tenant'], ['common', 'organizations', 'consumers'], true)
            && preg_match('/\A[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}\z/', $config['tenant']) !== 1) {
            throw new RuntimeException('Microsoft tenant configuration is invalid.');
        }
    }
    if ($config['client_id'] === '' || $config['client_secret'] === '' || $config['redirect_uri'] === '') {
        throw new RuntimeException('OAuth provider credentials are not configured.');
    }
    $parts = parse_url($config['redirect_uri']);
    if ($parts === false || empty($parts['host']) || empty($parts['path'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
        || filter_var($config['redirect_uri'], FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('OAuth callback URL configuration is invalid.');
    }
    $host = strtolower(trim($parts['host'], '[]'));
    $local_host = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    if (strtolower($parts['scheme'] ?? '') !== 'https' && !$local_host) {
        throw new RuntimeException('OAuth callback URL must use HTTPS outside local development.');
    }
    return $config;
}

function smartlearn_oauth_trusted_endpoint($url, $provider)
{
    $parts = parse_url((string) $url);
    if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || (isset($parts['port']) && (int) $parts['port'] !== 443)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    if ($provider === 'google') {
        return in_array($host, ['accounts.google.com', 'oauth2.googleapis.com', 'www.googleapis.com'], true);
    }
    return $host === 'login.microsoftonline.com';
}

function smartlearn_oauth_http_json($url, $provider, $form = null, $headers = [])
{
    if (!smartlearn_oauth_trusted_endpoint($url, $provider) || !function_exists('curl_init')) {
        throw new RuntimeException('OAuth service is unavailable.');
    }
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('OAuth service is unavailable.');
    }
    $request_headers = array_merge(['Accept: application/json'], $headers);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => $request_headers,
    ];
    if ($form !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($body === false || strlen($body) > 1048576 || $status < 200 || $status >= 300) {
        throw new RuntimeException('OAuth service request failed.');
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('OAuth service returned an invalid response.');
    }
    return $decoded;
}

function smartlearn_oauth_discovery(array $config)
{
    static $discovery_cache = [];
    $provider = $config['provider'];
    $cache_key = $provider . ':' . ($config['tenant'] ?? '');
    if (isset($discovery_cache[$cache_key])) {
        return $discovery_cache[$cache_key];
    }
    $url = $provider === 'google'
        ? 'https://accounts.google.com/.well-known/openid-configuration'
        : 'https://login.microsoftonline.com/' . rawurlencode($config['tenant']) . '/v2.0/.well-known/openid-configuration';
    $metadata = smartlearn_oauth_http_json($url, $provider);
    foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
        if (empty($metadata[$field]) || !is_string($metadata[$field])) {
            throw new RuntimeException('OAuth provider configuration is incomplete.');
        }
    }
    foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
        if (!smartlearn_oauth_trusted_endpoint($metadata[$field], $provider)) {
            throw new RuntimeException('OAuth provider configuration is not trusted.');
        }
    }
    if (!smartlearn_oauth_trusted_endpoint($metadata['issuer'], $provider)) {
        throw new RuntimeException('OAuth provider issuer is not trusted.');
    }
    $discovery_cache[$cache_key] = $metadata;
    return $metadata;
}

function smartlearn_oauth_base64url_decode($value)
{
    if (!is_string($value) || preg_match('/\A[A-Za-z0-9_-]+\z/', $value) !== 1 || strlen($value) % 4 === 1) {
        throw new RuntimeException('Identity token is invalid.');
    }
    $value = strtr($value, '-_', '+/');
    $value = str_pad($value, strlen($value) + (4 - strlen($value) % 4) % 4, '=');
    $decoded = base64_decode($value, true);
    if ($decoded === false) {
        throw new RuntimeException('Identity token is invalid.');
    }
    return $decoded;
}

function smartlearn_oauth_verify_id_token($token, array $config, array $metadata, $expected_nonce)
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('OAuth verification dependency is missing.');
    }
    require_once $autoload;
    if (!class_exists('Firebase\\JWT\\JWT') || !class_exists('Firebase\\JWT\\JWK')) {
        throw new RuntimeException('OAuth verification dependency is missing.');
    }
    if (!is_string($token) || strlen($token) > 16384) {
        throw new RuntimeException('Identity token is invalid.');
    }
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new RuntimeException('Identity token is invalid.');
    }
    $header = json_decode(smartlearn_oauth_base64url_decode($parts[0]), true);
    if (!is_array($header) || ($header['alg'] ?? '') !== 'RS256'
        || isset($header['crit'])
        || (isset($header['b64']) && $header['b64'] !== true)
        || !isset($header['kid']) || !is_string($header['kid']) || $header['kid'] === '' || strlen($header['kid']) > 256) {
        throw new RuntimeException('Identity token uses an unsupported signing key.');
    }
    $jwks = smartlearn_oauth_http_json($metadata['jwks_uri'], $config['provider']);
    if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
        throw new RuntimeException('OAuth signing keys are unavailable.');
    }
    $keys = Firebase\JWT\JWK::parseKeySet($jwks);
    if (!isset($keys[$header['kid']])) {
        throw new RuntimeException('OAuth signing key was not found.');
    }
    $previous_leeway = Firebase\JWT\JWT::$leeway;
    Firebase\JWT\JWT::$leeway = 60;
    try {
        $claims = (array) Firebase\JWT\JWT::decode($token, [$header['kid'] => $keys[$header['kid']]]);
    } finally {
        Firebase\JWT\JWT::$leeway = $previous_leeway;
    }
    $now = time();
    if (!isset($claims['iss'], $claims['sub'], $claims['exp'], $claims['iat'], $claims['aud'], $claims['nonce'])
        || !is_string($claims['iss']) || !is_string($claims['nonce'])
        || !is_string($claims['sub']) || $claims['sub'] === '' || strlen($claims['sub']) > 255
        || (!is_string($claims['aud']) && !is_array($claims['aud']))
        || !is_numeric($claims['exp']) || (float) $claims['exp'] < $now - 60
        || !is_numeric($claims['iat']) || (float) $claims['iat'] > $now + 60) {
        throw new RuntimeException('Identity token claims are invalid.');
    }
    $audiences = is_array($claims['aud']) ? $claims['aud'] : [$claims['aud']];
    if (!in_array($config['client_id'], $audiences, true)
        || (count($audiences) > 1 && ($claims['azp'] ?? '') !== $config['client_id'])
        || (isset($claims['azp']) && $claims['azp'] !== $config['client_id'])) {
        throw new RuntimeException('Identity token audience is invalid.');
    }
    if (!hash_equals((string) $expected_nonce, (string) $claims['nonce'])) {
        throw new RuntimeException('Identity token nonce is invalid.');
    }
    if ($config['provider'] === 'google') {
        if (!in_array($claims['iss'], ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new RuntimeException('Identity token issuer is invalid.');
        }
    } else {
        $tenant_id = (string) ($claims['tid'] ?? '');
        if (preg_match('/\A[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}\z/', $tenant_id) !== 1) {
            throw new RuntimeException('Identity token tenant is invalid.');
        }
        if (preg_match('/\A[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}\z/', $config['tenant']) === 1
            && strtolower($tenant_id) !== strtolower($config['tenant'])) {
            throw new RuntimeException('Identity token tenant is invalid.');
        }
        $expected_issuer = str_replace('{tenantid}', $tenant_id, $metadata['issuer']);
        if (!hash_equals($expected_issuer, (string) $claims['iss'])) {
            throw new RuntimeException('Identity token issuer is invalid.');
        }
    }
    if (isset($claims['nbf']) && (!is_numeric($claims['nbf']) || (float) $claims['nbf'] > $now + 60)) {
        throw new RuntimeException('Identity token is not yet valid.');
    }
    return $claims;
}

function smartlearn_oauth_find_identity($mysqli, $provider, $issuer, $subject)
{
    $sql = 'SELECT u.user_id, u.first_name, u.last_name, u.email, u.role, u.avatar_url, u.status '
        . 'FROM oauth_identities oi JOIN users u ON u.user_id = oi.user_id '
        . 'WHERE oi.provider = ? AND oi.issuer = ? AND oi.subject = ? LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('OAuth identity storage is unavailable. Apply the OAuth database migration.');
    }
    $stmt->bind_param('sss', $provider, $issuer, $subject);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('OAuth identity lookup failed.');
    }
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $user ?: null;
}

function smartlearn_oauth_find_user_by_email($mysqli, $email)
{
    $stmt = $mysqli->prepare('SELECT user_id, first_name, last_name, email, role, avatar_url, status FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Account lookup failed.');
    }
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Account lookup failed.');
    }
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $user ?: null;
}

function smartlearn_oauth_insert_identity($mysqli, $user_id, $provider, $issuer, $subject)
{
    $stmt = $mysqli->prepare('INSERT INTO oauth_identities (user_id, provider, issuer, subject) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('OAuth identity storage is unavailable. Apply the OAuth database migration.');
    }
    $user_id = (int) $user_id;
    $stmt->bind_param('isss', $user_id, $provider, $issuer, $subject);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        throw new RuntimeException('This provider identity is already connected to an account.');
    }
}

function smartlearn_oauth_user_can_sign_in(array $user)
{
    return !in_array($user['status'], ['suspended', 'deleted'], true)
        && !($user['status'] === 'pending_approval' && $user['role'] !== 'student');
}

function smartlearn_oauth_login($mysqli, array $user, $provider, $issuer, $subject, $base_url = '../')
{
    $stmt = $mysqli->prepare('UPDATE oauth_identities SET last_login_at = CURRENT_TIMESTAMP WHERE provider = ? AND issuer = ? AND subject = ?');
    if ($stmt) {
        $stmt->bind_param('sss', $provider, $issuer, $subject);
        $stmt->execute();
        $stmt->close();
    }
    smartlearn_complete_login($mysqli, $user, $base_url, 'Signed in successfully.');
}
