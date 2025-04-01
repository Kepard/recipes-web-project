<?php
session_start();

// Set default language to French
$lang = 'fr';

// Load messages from data.json
$data = json_decode(file_get_contents('data.json'), true);

// Get messages for the selected language
$messages = $data[$lang]['messages'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the form data
    $action = $_POST['action'];
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $filename = 'users.json';

    // Load existing users
    $users = json_decode(file_get_contents($filename), true);

    // Prepare the response
    $response = ['success' => false, 'message' => 'undefined'];

    // Check if the action is to log in or sign up
    if ($action === 'login') {
        if (isset($users[$username]) && password_verify($password, $users[$username]["password"])) {
            // Set session variables
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $users[$username]['role'];

            // Set success response
            $response['success'] = true;
            $response['message'] = $messages['login_success'];
            $response['username'] = $username; // Include username in the response
            $response['role'] = $users[$username]['role']; // Include role in the response
        } else {
            // Set error response
            $response['message'] = $messages['invalid_credentials'];
        }
    } elseif ($action === 'signup') {
        if (isset($users[$username])) {
            $response['message'] = $messages['username_exists'];
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Get the role from POST data or default to "Cuisinier"
            $role = isset($_POST['role']) ? $_POST['role'] : "Cuisinier";


            $users[$username] = [
                "password" => $hashedPassword,
                "role" => $role
            ];
            file_put_contents($filename, json_encode($users, JSON_PRETTY_PRINT));
            $response['success'] = true;
            $response['message'] = $messages['account_created'];
        }
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>