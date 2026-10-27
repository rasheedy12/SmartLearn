<?php
require_once __DIR__ . '/../includes/oauth_helpers.php';
smartlearn_start_secure_session();

$request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$provider = strtolower(trim((string) ($request_data['provider'] ?? '')));
$intent = strtolower(trim((string) ($request_data['intent'] ?? 'login')));
if (!in_array($provider, ['google', 'microsoft'], true)
    || !in_array($intent, ['login', 'register', 'link'], true)) {
    header('Location: ../pages/login.php?error=' . urlencode('That sign-in option is not available.'));
    exit;
}
if (($intent === 'link' && ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || !isset($_POST['csrf_token'], $_SESSION['profile_csrf_token'])
        || !hash_equals((string) $_SESSION['profile_csrf_token'], (string) $_POST['csrf_token'])))
    || ($intent !== 'link' && $_SERVER['REQUEST_METHOD'] !== 'GET')) {
    if ($intent === 'link') {
        $destination = '../admin/profile.php?oauth_error=';
    } elseif ($intent === 'register') {
        $destination = '../pages/register.php?error=';
    } else {
        $destination = '../pages/login.php?error=';
    }
    header('Location: ' . $destination . urlencode('That sign-in request could not be verified. Please try again.'));
    exit;
}
if ($intent === 'link' && (empty($_SESSION['loggedin']) || empty($_SESSION['user_id']))) {
    header('Location: ../pages/login.php?error=' . urlencode('Sign in before connecting an account.'));
    exit;
}
if ($intent !== 'link' && !empty($_SESSION['loggedin'])) {
    header('Location: ../admin/profile.php?oauth_error=' . urlencode('You are already signed in. Connect another provider from your profile.'));
    exit;
}

try {
    if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
        throw new RuntimeException('OAuth verification dependency is missing.');
    }
    $config = smartlearn_oauth_config($provider);
    $metadata = smartlearn_oauth_discovery($config);
    $state = bin2hex(random_bytes(32));
    $nonce = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    $_SESSION['smartlearn_oauth_pending'] = [
        'state' => $state,
        'nonce' => $nonce,
        'code_verifier' => $verifier,
        'provider' => $provider,
        'intent' => $intent,
        'link_user_id' => $intent === 'link' ? (int) $_SESSION['user_id'] : null,
        'started_at' => time(),
    ];
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $query = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'response_mode' => 'query',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ];
    header('Location: ' . $metadata['authorization_endpoint'] . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    exit;
} catch (Throwable $exception) {
    error_log('OAuth start failed for ' . $provider . ': ' . $exception->getMessage());
    $destination = $intent === 'link' ? '../admin/profile.php?oauth_error=' : '../pages/login.php?error=';
    $message = $exception->getMessage() === 'OAuth provider credentials are not configured.'
        ? ucfirst($provider) . ' sign-in is not configured yet.'
        : ($exception->getMessage() === 'OAuth verification dependency is missing.'
            ? 'OAuth sign-in is unavailable until Composer dependencies are installed.'
            : 'Could not start sign-in. Check the provider configuration and try again.');
    header('Location: ' . $destination . urlencode($message));
    exit;
}
