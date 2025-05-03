<?php
// Start de session
session_start();

// Verifier si l'utilisateur est authentifie
if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

// Recuperer le username et le nouveau role depuis la requete POST 
$username = $_POST['username'] ?? '';
$newRole = $_POST['role'] ?? '';

// Charger les utilisateurs depuis le json
$usersFile = 'users.json';
$users = json_decode(file_get_contents($usersFile), true);

// Mettre a jour le role de l'utilisateur courant 
$users[$username]['role'] = $newRole;

// Sauvegarder les changements dans le json
file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

// Mettre a jour le role de la variable session
$_SESSION['role'] = $newRole;

// Envoyer un message de success au AJAX
echo json_encode([
    'success' => true,
    'newRole' => $newRole
]);