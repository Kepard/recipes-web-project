<?php
session_start();

// Verifier la connection et les droits admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Administrateur') {
    header('HTTP/1.1 403 Forbidden');
    die();
}

// Recuperer l'id depuis le GET
$recipeId = $_GET['id'];

// Charger les recettes
$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

// Trouver et supprimer la recette
foreach ($recipes as $key => $recipe) {
    if (isset($recipe['id']) && $recipe['id'] == $recipeId) {
        unset($recipes[$key]);
        break;
    }
}

// Reindexer pour eviter de creer des gaps
$recipes = array_values($recipes);

// Sauvegarder dans le JSON
file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT));

// Envoyer un message de success au AJAX
echo json_encode(['success' => true]);

// Rediriger vers l'index
header('Location: index.php');
exit;