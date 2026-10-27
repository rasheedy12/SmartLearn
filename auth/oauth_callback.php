<?php
require_once __DIR__ . '/../includes/oauth_helpers.php';
smartlearn_start_secure_session();
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');

$pending = $_SESSION['smartlearn_oauth_pending'] ?? null;
unset($_SESSION['smartlearn_oauth_pending']);

function smartlearn_oauth_callback_error($pending, $message)
{
    if ((is_array($pending) && ($pending['intent'] ?? '') === 'link')
        || (!is_array($pending) && !empty($_SESSION['loggedin']))) {
        header('Location: ../admin/profile.php?oauth_error=' . urlencode($message));
    } else {
        $page = is_array($pending) && ($pending['intent'] ?? '') === 'register' ? 'register.php' : 'login.php';
        header('Location: ../pages/' . $page . '?error=' . urlencode($message));
    }
    exit;
}

$state = (string) ($_GET['state'] ?? '');
if (!is_array($pending) || empty($pending['state']) || empty($pending['provider'])
    || !isset($pending['started_at']) || time() - (int) $pending['started_at'] > 600
    || $state === '' || !hash_equals((string) $pending['state'], $state)) {
    smartlearn_oauth_callback_error(null, 'Sign-in expired or could not be verified. Please try again.');
}
if (isset($_GET['error']) || empty($_GET['code']) || !is_string($_GET['code']) || strlen($_GET['code']) > 4096) {
    smartlearn_oauth_callback_error($pending, 'Sign-in was cancelled or could not be completed. Please try again.');
}

try {
    $provider = $pending['provider'];
    $config = smartlearn_oauth_config($provider);
    $metadata = smartlearn_oauth_discovery($config);
    $token_response = smartlearn_oauth_http_json($metadata['token_endpoint'], $provider, [
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'code' => (string) $_GET['code'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
        'code_verifier' => $pending['code_verifier'],
    ]);
    if (empty($token_response['id_token']) || !is_string($token_response['id_token'])) {
        throw new RuntimeException('Identity token was not returned.');
    }
    $claims = smartlearn_oauth_verify_id_token($token_response['id_token'], $config, $metadata, $pending['nonce']);
    $issuer = (string) $claims['iss'];
    $subject = (string) $claims['sub'];
    require __DIR__ . '/../includes/db_config.php';

    $identity_user = smartlearn_oauth_find_identity($mysqli, $provider, $issuer, $subject);
    if ($pending['intent'] === 'link') {
        if (empty($_SESSION['loggedin']) || (int) ($_SESSION['user_id'] ?? 0) !== (int) $pending['link_user_id']) {
            smartlearn_oauth_callback_error($pending, 'Your session changed. Sign in again before connecting this provider.');
        }
        if ($identity_user) {
            if ((int) $identity_user['user_id'] === (int) $pending['link_user_id']) {
                header('Location: ../admin/profile.php?oauth=connected');
                exit;
            }
            smartlearn_oauth_callback_error($pending, 'That provider identity is already connected to another SmartLearn account.');
        }
        $check = $mysqli->prepare('SELECT user_id, status FROM users WHERE user_id = ? LIMIT 1');
        if (!$check) {
            throw new RuntimeException('Could not verify the current account.');
        }
        $link_user_id = (int) $pending['link_user_id'];
        $check->bind_param('i', $link_user_id);
        $check->execute();
        $target_result = $check->get_result();
        $target_user = $target_result ? $target_result->fetch_assoc() : null;
        $check->close();
        if (!$target_user || in_array($target_user['status'], ['suspended', 'deleted'], true)) {
            smartlearn_oauth_callback_error($pending, 'This account cannot connect another sign-in provider.');
        }
        $mysqli->begin_transaction();
        try {
            smartlearn_oauth_insert_identity($mysqli, $link_user_id, $provider, $issuer, $subject);
            $mysqli->commit();
        } catch (Throwable $exception) {
            $mysqli->rollback();
            throw $exception;
        }
        header('Location: ../admin/profile.php?oauth=connected');
        exit;
    }

    if ($identity_user) {
        if (!smartlearn_oauth_user_can_sign_in($identity_user)) {
            smartlearn_oauth_callback_error($pending, 'This account cannot sign in right now. Contact SmartLearn support.');
        }
        smartlearn_oauth_login($mysqli, $identity_user, $provider, $issuer, $subject);
    }

    $email_claim = $claims['email'] ?? $claims['preferred_username'] ?? '';
    $email = is_string($email_claim) ? trim($email_claim) : '';
    if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
    }
    $email_verified = $provider === 'google'
        && (($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true');
    if ($email !== '' && $email_verified) {
        $same_email_user = smartlearn_oauth_find_user_by_email($mysqli, $email);
        if ($same_email_user) {
            if (!smartlearn_oauth_user_can_sign_in($same_email_user)) {
                smartlearn_oauth_callback_error($pending, 'This account cannot sign in right now. Contact SmartLearn support.');
            }
            $mysqli->begin_transaction();
            try {
                smartlearn_oauth_insert_identity($mysqli, $same_email_user['user_id'], $provider, $issuer, $subject);
                $mysqli->commit();
            } catch (Throwable $exception) {
                $mysqli->rollback();
                throw $exception;
            }
            smartlearn_oauth_login($mysqli, $same_email_user, $provider, $issuer, $subject);
        }
    } elseif ($email !== '' && smartlearn_oauth_find_user_by_email($mysqli, $email)) {
        smartlearn_oauth_callback_error($pending, 'An account already uses this email. Sign in with that account, then connect this provider from your profile.');
    }

    $full_name = is_string($claims['name'] ?? null) ? trim($claims['name']) : '';
    $first_name = is_string($claims['given_name'] ?? null) ? trim($claims['given_name']) : '';
    $last_name = is_string($claims['family_name'] ?? null) ? trim($claims['family_name']) : '';
    if ($first_name === '' && $full_name !== '') {
        $name_parts = preg_split('/\s+/', $full_name, 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $last_name !== '' ? $last_name : (string) ($name_parts[1] ?? '');
    }
    $_SESSION['smartlearn_oauth_registration'] = [
        'provider' => $provider,
        'issuer' => $issuer,
        'subject' => $subject,
        'email' => $email,
        'email_verified' => $email_verified,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'started_at' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
    header('Location: ../pages/oauth_complete.php');
    exit;
} catch (Throwable $exception) {
    error_log('OAuth callback failed for ' . (string) ($pending['provider'] ?? 'unknown') . ': ' . $exception->getMessage());
    smartlearn_oauth_callback_error($pending, 'Sign-in could not be completed. Check the provider setup and try again.');
}
