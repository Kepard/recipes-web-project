<?php
// Démarrer la session
session_start();

// Charger les utilisateurs depuis le json
$usersFile = 'users.json';
$users = json_decode(file_get_contents($usersFile), true);

// Récupérer l'action, le username cible, et d'autres données POST
$action = $_POST['action'] ?? '';
$targetUsername = $_POST['username'] ?? ''; // L'utilisateur dont le profil est modifié
$loggedInUsername = $_SESSION['username'] ?? null; // L'utilisateur connecté
$loggedInRole = $_SESSION['role'] ?? null; // Le rôle de l'utilisateur connecté

// --- Vérification des Permissions ---
$isAdmin = ($loggedInRole === 'Administrateur');
$isSelfAction = ($loggedInUsername === $targetUsername);
$allowedToProceed = false;
$newRole = ''; // Pour stocker le nouveau rôle pour la réponse JSON

switch ($action) {
    case 'update_role':
    case 'update_password':
    case 'remove_user':
        // Ces actions nécessitent que l'utilisateur connecté soit Administrateur
        if ($isAdmin) {
            $allowedToProceed = true;
        } else {
             header('HTTP/1.1 403 Forbidden');
             die(json_encode(['success' => false]));
        }
        break;

    case 'request_role':
        // L'utilisateur demande un rôle pour LUI-MEME
        if ($loggedInUsername && $isSelfAction) {
            $requestedRole = $_POST['role'] ?? '';
            // Autoriser uniquement les demandes pour devenir Chef ou Traducteur
            if ($requestedRole === 'DemandeChef' || $requestedRole === 'DemandeTraducteur') {
                $allowedToProceed = true;
                $newRole = $requestedRole; // Stocke le rôle demandé pour la réponse
            } else {
                 header('HTTP/1.1 400 Bad Request');
                 die(json_encode(['success' => false]));
            }
        } else {
            // Soit non connecté, soit essaie de changer le rôle d'un autre via cette action
             header('HTTP/1.1 403 Forbidden');
             die(json_encode(['success' => false]));
        }
        break;
}

// Si les permissions sont accordées, exécuter l'action
if ($allowedToProceed) {

    // --- Exécution de l'action ---
    $updateSuccess = false;
    switch ($action) {
        case 'update_role':
            $roleToSet = $_POST['role'] ?? '';
            // Validation simple du rôle (pourrait être une liste définie)
            $validRoles = ["Administrateur", "Traducteur", "Chef", "DemandeChef", "DemandeTraducteur", "Cuisinier"];
            if (in_array($roleToSet, $validRoles)) {
                $users[$targetUsername]['role'] = $roleToSet;
                $updateSuccess = true;
                // Si l'admin modifie le rôle de l'utilisateur actuel, mettre à jour la session aussi
                if ($isSelfAction) {
                    $_SESSION['role'] = $roleToSet;
                }
            } else {
                 die(json_encode(['success' => false]));
            }
            break;

        case 'request_role': 
            $users[$targetUsername]['role'] = $newRole; // Utilise $newRole défini plus haut
            $_SESSION['role'] = $newRole; // Met à jour la session de l'utilisateur actuel
            $updateSuccess = true;
            break;

        case 'update_password':
            $newPassword = $_POST['password'];
            $users[$targetUsername]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateSuccess = true;
            break;

        case 'remove_user':
            // Sécurité : Empêcher l'admin de se supprimer lui-même 
            if ($isAdmin && $isSelfAction) {
                 die(json_encode(['success' => false, 'message' => 'Admin cannot remove themselves.']));
            }
            unset($users[$targetUsername]);
            $updateSuccess = true;
            break;
    }

    // --- Sauvegarder les changements dans le json ---
    if ($updateSuccess) {
        // Utiliser JSON_PRETTY_PRINT pour la lisibilité
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
        // Préparer la réponse JSON
        $response = ['success' => true];
        // Inclure le nouveau rôle dans la réponse si l'action était une mise à jour/demande de rôle
        if (($action === 'update_role' || $action === 'request_role') && $newRole !== '') {
            $response['newRole'] = $newRole;
        }
        echo json_encode($response);
    } 

}
// Pas besoin de else ici, car les cas non autorisés meurent plus tôt avec die()

?>