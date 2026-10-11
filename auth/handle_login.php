<?php
session_start(); 

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
        header("location: " . BASE_URL . "login.php?error=" . urlencode("Email and password are required."));
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
                        header("location: " . BASE_URL . "login.php?error=" . urlencode("Your account is " . $status . ". Please contact support."));
                        exit;
                    }
                    if($status === 'pending_approval' && $role !== 'student'){ // Instructors/Admins might need approval
                        header("location: " . BASE_URL . "login.php?error=" . urlencode("Your account is pending approval. Please wait or contact support."));
                        exit;
                    }

                    if(password_verify($password_attempt, $hashed_password)){
                        // Password is correct, so start a new session

                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);

                        // Store data in session variables
                        $_SESSION["loggedin"] = true; // General login flag
                        $_SESSION["user_id"] = $user_id;
                        $_SESSION["user_name"] = $first_name . " " . $last_name;
                        $_SESSION["user_email"] = $db_email;
                        $_SESSION["user_role"] = $role;
                        $_SESSION["user_avatar"] = $avatar_url ? BASE_URL . $avatar_url : BASE_URL . 'assets/images/user_avatar_placeholder.png';

                        // Update last login time
                        $update_login_sql = "UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE user_id = ?";
                        if($update_stmt = $mysqli->prepare($update_login_sql)){
                            $update_stmt->bind_param("i", $user_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }

                        // Redirect user to appropriate dashboard
                        if ($role === "admin") {
                            header("location: " . BASE_URL . "admin_dashboard.php");
                        } elseif ($role === "instructor") {
                            header("location: " . BASE_URL . "instructor_dashboard.php");
                        } else { // student
                            header("location: " . BASE_URL . "student_dashboard.php");
                        }
                        exit;
                    } else{
                        // Password is not valid
                        header("location: " . BASE_URL . "login.php?error=" . urlencode("Invalid email or password."));
                        exit;
                    }
                }
            } else{
                // Email doesn't exist
                header("location: " . BASE_URL . "login.php?error=" . urlencode("Invalid email or password."));
                exit;
            }
        } else{
            error_log("Auth Error: Statement execution failed. " . $stmt->error);
            header("location: " . BASE_URL . "login.php?error=" . urlencode("An error occurred. Please try again."));
            exit;
        }
        // Close statement
        $stmt->close();
    } else {
        error_log("Auth Error: Statement preparation failed. " . $mysqli->error);
        header("location: " . BASE_URL . "login.php?error=" . urlencode("An error occurred. Please try again."));
        exit;
    }
    // Close connection
    $mysqli->close();
} else {
    // If not a POST request, redirect to login page
    header("Location: " . BASE_URL . "login.php");
    exit;
}
?>
