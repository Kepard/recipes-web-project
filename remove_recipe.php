<?php
session_start();

// Check if user is logged in and is an administrator
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Administrateur') {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['success' => false, 'message' => 'Permission denied']));
}

// Get recipe ID from URL
$recipeId = $_GET['id'] ?? null;

// Load recipes
$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

// Find and remove the recipe
foreach ($recipes as $key => $recipe) {
    if (isset($recipe['id']) && $recipe['id'] == $recipeId) {
        unset($recipes[$key]);
        break;
    }
}

// Reindex array (optional, removes gaps in array keys)
$recipes = array_values($recipes);

// Save back to file
file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT));
// Redirect back to homepage with success message
$_SESSION['flash_message'] = 'Recipe successfully removed';
header('Location: index.php');
exit;