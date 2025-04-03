<?php
// Start de session
session_start();

// Verification de droits admin
if ($_SESSION['role'] !== 'Administrateur') {
    header('HTTP/1.1 403 Forbidden');
    die();
}

// Recuperer le id de la recette depuis la requete POST 
$recipeId = $_POST['id'];

// Charger les recettes depuis le json
$recipes = json_decode(file_get_contents('recipes.json'), true);

$recipeIndex = null; // Initialisation de la variable pour garder l'index de la recette (au sein du JSON)

foreach ($recipes as $index => $recipe) {
    if ($recipe['id'] == $recipeId) {
        $recipeIndex = $index; // Garder l'index de la recette
        break; // Quitter la boucle
    }
}

// Mettre a jour le champ "validated" de la recette en question
$recipes[$recipeIndex]['validated'] = 1;

// Mettre a jour la recette dans le JSON
file_put_contents('recipes.json', json_encode($recipes, JSON_PRETTY_PRINT));

echo 'Recipe validated!';

?>
