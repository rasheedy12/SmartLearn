<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '../');
}

$message = "";
$message_type = "danger";
$subscriptionPreferences = [
    'news' => 'Latest News',
    'updates' => 'Product Updates',
    'events' => 'Educational Events',
    'tips' => 'Learning Tips'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $preferences = isset($_POST['preferences']) ? $_POST['preferences'] : [];
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';

    if (empty($email)) {
        $message = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        try {
            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));
            
            // Current timestamp
            $created_at = date('Y-m-d H:i:s');
            
            // Prepare and execute the SQL statement
            $stmt = $pdo->prepare("INSERT INTO subscribers (email, name, verification_token, preferences, created_at) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$email, $name, $verification_token, json_encode($preferences), $created_at]);

            // Prepare HTML email
            $verification_link = BASE_URL . "verify.php?token=" . $verification_token;
            $to = $email;
            $subject = "Welcome to SmartLearn Newsletter!";
            
            $html_message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .button { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                    </style>
                </head>
                <body>
                    <h2>Welcome to SmartLearn!</h2>
                    <p>Thank you for subscribing to our newsletter. To complete your subscription, please click the button below:</p>
                    <p><a href='{$verification_link}' class='button'>Verify Email</a></p>
                    <p>Selected preferences:</p>
                    <ul>";
            
            foreach ($preferences as $pref) {
                if (isset($subscriptionPreferences[$pref])) {
                    $html_message .= "<li>" . $subscriptionPreferences[$pref] . "</li>";
                }
            }
            
            $html_message .= "
                    </ul>
                    <p>If you didn't subscribe to our newsletter, please ignore this email.</p>
                </body>
                </html>";

            // Email headers
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: SmartLearn <noreply@smartlearn.com>" . "\r\n";
            
            if (mail($to, $subject, $html_message, $headers)) {
                $message = "Thank you for subscribing! Please check your email to verify your subscription.";
                $message_type = "success";
            } else {
                throw new Exception("Failed to send verification email.");
            }

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "This email is already subscribed!";
            } else {
                $message = "An error occurred. Please try again later.";
                error_log("Subscribe error: " . $e->getMessage());
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            error_log("Email error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Subscription - SmartLearn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/style.css">
    <style>
        .subscription-form {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .preference-item {
            margin-bottom: 0.5rem;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="subscription-form">
            <h2 class="text-center mb-4">Subscribe to Our Newsletter</h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="name" class="form-label">Name (Optional)</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Your name">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email address *</label>
                    <input type="email" class="form-control" id="email" name="email" required 
                           placeholder="your@email.com">
                    <div class="invalid-feedback">
                        Please provide a valid email address.
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Subscription Preferences:</label>
                    <?php foreach ($subscriptionPreferences as $key => $label): ?>
                        <div class="preference-item">
                            <input class="form-check-input" type="checkbox" name="preferences[]" 
                                   value="<?php echo htmlspecialchars($key); ?>" id="<?php echo htmlspecialchars($key); ?>">
                            <label class="form-check-label" for="<?php echo htmlspecialchars($key); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Subscribe</button>
                    <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-outline-secondary">Back to Homepage</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>