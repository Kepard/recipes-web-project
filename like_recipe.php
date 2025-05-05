<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false]);
    exit;
}

// Get recipe ID from POST data
$recipeId = $_POST['id'];

// Load recipes
$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

// Find the recipe
foreach ($recipes as &$recipe) {
    if (isset($recipe['id']) && $recipe['id'] == $recipeId) {        
        // Initialize likes array if it doesn't exist
        if (!isset($recipe['likes'])) {
            $recipe['likes'] = [];
        }
        
        // Check if user already liked this recipe
        $username = $_SESSION['username'];
        $userIndex = array_search($username, $recipe['likes']);
        
        if ($userIndex === false) {
            // Add like
            $recipe['likes'][] = $username;
            $action = 'liked';
        } else {
            // Remove like
            array_splice($recipe['likes'], $userIndex, 1);
            $action = 'unliked';
        }
        
        break;
    }
}

file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'action' => $action,
    'likeCount' => count($recipe['likes'])
]);