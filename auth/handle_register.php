<?php
session_start(); 

if (!defined('BASE_URL')) {
    define('BASE_URL', '../');
}
require_once BASE_URL . 'includes/db_config.php';

$errors = []; // Array to store validation errors

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (
        !isset($_POST['csrf_token'], $_SESSION['registration_csrf_token'])
        || !hash_equals($_SESSION['registration_csrf_token'], (string) $_POST['csrf_token'])
    ) {
        $_SESSION['register_errors'] = ['Your request could not be verified. Refresh the page and try again.'];
        header("Location: " . BASE_URL . "pages/register.php");
        exit;
    }

    // Sanitize and retrieve form data
    $first_name = trim((string) ($_POST['first_name'] ?? ''));
    $last_name = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $agree_terms = isset($_POST['agree_terms']);

    // --- Validation ---
    if (empty($first_name)) { $errors[] = "First name is required."; }
    if (empty($last_name)) { $errors[] = "Last name is required."; }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    // Add more password complexity rules if desired (e.g., numbers, uppercase, special chars)

    if (!in_array($role, ['student', 'instructor'])) {
        $errors[] = "Invalid role selected.";
    }
    if (!$agree_terms) {
        $errors[] = "You must agree to the Terms of Service and Privacy Policy.";
    }

    // Check if email already exists
    if (empty($errors)) {
        $sql_check_email = "SELECT user_id FROM users WHERE email = ?";
        if ($stmt_check = $mysqli->prepare($sql_check_email)) {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $errors[] = "This email address is already registered.";
            }
            $stmt_check->close();
        } else {
            $errors[] = "Database error checking email. Please try again.";
            error_log("Register Error: Email check statement preparation failed. " . $mysqli->error);
        }
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Determine user status based on role
        $status = ($role === 'instructor') ? 'pending_approval' : 'active'; // Instructors might need admin approval

        // Prepare an insert statement
        $sql_insert_user = "INSERT INTO users (first_name, last_name, email, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";

        if ($stmt_insert = $mysqli->prepare($sql_insert_user)) {
            $stmt_insert->bind_param("ssssss", $first_name, $last_name, $email, $hashed_password, $role, $status);

            if ($stmt_insert->execute()) {
                // Registration successful
                $new_user_id = $stmt_insert->insert_id; // Get the ID of the newly inserted user

                // Optional: Automatically log in the user or send to a success page/login page
                // For this example, redirect to login page with a success message
                header("Location: " . BASE_URL . "pages/login.php?success=" . urlencode("Registration successful! Please login."));
                exit;
            } else {
                $errors[] = "Registration failed. Please try again later.";
                error_log("Register Error: User insert statement execution failed. " . $stmt_insert->error);
            }
            $stmt_insert->close();
        } else {
            $errors[] = "Database error preparing registration. Please try again.";
            error_log("Register Error: User insert statement preparation failed. " . $mysqli->error);
        }
    }

    // If there were errors, redirect back to registration page with errors
    if (!empty($errors)) {
        $_SESSION['register_errors'] = $errors; // Store errors in session to display on register page
        // You might also want to repopulate form fields
        $form_data = $_POST;
        unset($form_data['password']);
        $_SESSION['register_form_data'] = $form_data;
        header("Location: " . BASE_URL . "pages/register.php");
        exit;
    }

    $mysqli->close();

} else {
    // Not a POST request
    header("Location: " . BASE_URL . "pages/register.php");
    exit;
}
?>
