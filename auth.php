<?php
session_start();

// La langue par defaut est le francais
$lang = 'fr';

// Charger les traductions depuis le json data
$data = json_decode(file_get_contents('data.json'), true);

// Stocker les messages traduits 
$messages = $data[$lang]['messages'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recuperer les donnees du formulaire
    $action = $_POST['action'];
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $filename = 'users.json';

    // Charger les utilisateurs
    $users = json_decode(file_get_contents($filename), true);

    // Preparer la reponsse
    $response = ['success' => false, 'message' => 'undefined'];

    // Log-in
    if ($action === 'login') {
        if (isset($users[$username]) && password_verify($password, $users[$username]["password"])) {
            // Definir les variables de la session
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $users[$username]['role'];

            // Envoyer la reponse success
            $response['success'] = true;
            $response['message'] = $messages['login_success'];
            $response['username'] = $username; 
            $response['role'] = $users[$username]['role']; 
        } else {
            $response['message'] = $messages['invalid_credentials'];
        }
        // Sign-up
    } elseif ($action === 'signup') {
        if (isset($users[$username])) {
            $response['message'] = $messages['username_exists'];
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Recuperer le role choisi. Sinon selectionner Cuisinier par defaut
            $role = isset($_POST['role']) ? $_POST['role'] : "Cuisinier";


            $users[$username] = [
                "password" => $hashedPassword,
                "role" => $role
            ];

            // Stocker le nouveau utilisateur cree
            file_put_contents($filename, json_encode($users, JSON_PRETTY_PRINT));
            $response['success'] = true;
            $response['message'] = $messages['account_created'];
        }
    }

    echo json_encode($response);
    exit;
}
?>