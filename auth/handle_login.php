<?php
require_once __DIR__ . '/../includes/auth_session.php';
smartlearn_start_secure_session();

// Define BASE_URL relative to the auth_handlers directory
if (!defined('BASE_URL')) {
    define('BASE_URL', '../'); // Goes up one level to the project root
}

// Include the database configuration file
require_once BASE_URL . 'includes/db_config.php'; // Correct path to db_config.php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password_attempt = $_POST['password']; // User's plain text password attempt

    // Basic validation
    if(empty($email) || empty($password_attempt)){
        header("location: " . BASE_URL . "pages/login.php?error=" . urlencode("Email and password are required."));
        exit;
    }

    // Prepare SQL statement to prevent SQL injection
    $sql = "SELECT user_id, first_name, last_name, email, password_hash, role, avatar_url, status FROM users WHERE email = ?";

    if($stmt = $mysqli->prepare($sql)){
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("s", $param_email);

        // Set parameters
        $param_email = $email;

        // Attempt to execute the prepared statement
        if($stmt->execute()){
            // Store result
            $stmt->store_result();

            // Check if email exists, if yes then verify password
            if($stmt->num_rows == 1){
                // Bind result variables
                $stmt->bind_result($user_id, $first_name, $last_name, $db_email, $hashed_password, $role, $avatar_url, $status);
                if($stmt->fetch()){
                    if($status === 'suspended' || $status === 'deleted'){
                        header("location: " . BASE_URL . "pages/login.php?error=" . urlencode("Your account is " . $status . ". Please contact support."));
                        exit;
                    }
                    if($status === 'pending_approval' && $role !== 'student'){ // Instructors/Admins might need approval
                        header("location: " . BASE_URL . "pages/login.php?error=" . urlencode("Your account is pending approval. Please wait or contact support."));
                        exit;
                    }

                    if(password_verify($password_attempt, $hashed_password)){
                        smartlearn_complete_login($mysqli, [
                            'user_id' => $user_id,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'email' => $db_email,
                            'role' => $role,
                            'avatar_url' => $avatar_url,
                            'status' => $status,
                        ], BASE_URL, 'Signed in successfully.');
                    } else{
                        // Password is not valid
                        header("location: " . BASE_URL . "pages/login.php?error=" . urlencode("Invalid email or password."));
                        exit;
                    }
                }
            } else{
                // Email doesn't exist
                header("location: " . BASE_URL . "pages/login.php?error=" . urlencode("Invalid email or password."));
                exit;
            }
        } else{
            error_log("Auth Error: Statement execution failed. " . $stmt->error);
            header("location: " . BASE_URL . "pages/login.php?error=" . urlencode("An error occurred. Please try again."));
            exit;
        }
        // Close statement
        $stmt->close();
    } else {
        error_log("Auth Error: Statement preparation failed. " . $mysqli->error);
        header("location: " . BASE_URL . "pages/login.php?error=" . urlencode("An error occurred. Please try again."));
        exit;
    }
    // Close connection
    $mysqli->close();
} else {
    // If not a POST request, redirect to login page
    header("Location: " . BASE_URL . "pages/login.php");
    exit;
}
?>
