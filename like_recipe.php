<?php
session_start();

// Verifier que l'utilisateur est connecte
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false]);
    exit;
}

// Recuperer l'ID de la recette depuis le POST
$recipeId = $_POST['id'];

// Charger les recettes
$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

// Trouver la recette grace a son id
foreach ($recipes as &$recipe) {
    if ($recipe['id'] == $recipeId) {        
        // Verifier si l'utilisateur a deja liker la recette
        $username = $_SESSION['username'];
        $userIndex = array_search($username, $recipe['likes']);
        
        if ($userIndex === false) {
            // Ajouter un like
            $recipe['likes'][] = $username;
            $action = 'liked';
        } else {
            // Retirer un like
            array_splice($recipe['likes'], $userIndex, 1);
            $action = 'unliked';
        }
        
        break;
    }
}

// Sauvegarder
file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT));

// Envoyer la reponse de success au AJAX
echo json_encode([
    'success' => true,
    'action' => $action,
    'likeCount' => count($recipe['likes'])
]);