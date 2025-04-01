<?php
// Start de session
session_start();

// Verification de droits admin
if ($_SESSION['role'] !== 'Administrateur') {
    // Si le role != Administrateur, return JSON erreur et arreter l'execution (die)
    die(json_encode(['error' => 'Permission denied']));
}

// Charger les utilisateurs depuis le json
$users = json_decode(file_get_contents('users.json'), true);

// Recuperer l'action et le username depuis la requete POST 
$action = $_POST['action'] ?? '';
$username = $_POST['username'] ?? '';

// Gestion de differentes actions
switch ($action) {
    case 'update_role':
        if (isset($users[$username])) {
            $users[$username]['role'] = $_POST['role'];
            $message = 'Role updated';
        }
        break;
        
    case 'update_password':
        if (isset($users[$username])) {
            $users[$username]['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $message = 'Password updated';
        }
        break;
        
    case 'remove_user':
        if (isset($users[$username])) {
            unset($users[$username]);
            $message = 'User removed';
        }
        break;
        
    // Si action invalide -> retourner l'erreur
    default:
        die(json_encode(['error' => 'Invalid action']));
}

// Sauvegarder les changements dans le json
file_put_contents('users.json', json_encode($users));
// Envoyer un message de success au AJAX
echo json_encode(['success' => true, 'message' => $message]);
