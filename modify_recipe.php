<?php
/**
 * Page de modification de recette 
 */


// Demarrer la session si ce n'est pas deja fait
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Recuperer l'ID de la recette depuis GET
$recipeId = (int) $_GET['id'];

// Si l'utilisateur n'est pas connecte -> refuser l'access
if (!isset($_SESSION['username'])) { 
    header("Location: index.php"); 
    exit; 
}

// Recuperer les variables de la session
$currentUser = $_SESSION['username'];
$currentRole = $_SESSION['role'];

// Recuperer les recettes depuis le fichier JSON
$recipesFile = 'recipes.json';
$recipeToModify = null;
$recipeKey = null;

$recipes = json_decode(file_get_contents($recipesFile), true);

// Trouver l'index de la recette correspondante
foreach ($recipes as $key => $recipe) {
    if ($recipe['id'] == $recipeId){
        $recipeToModify = $recipe; 
        $recipeKey = $key; 
        break;
    }
}

$isAuthor = isset($recipeToModify['Author']) && $recipeToModify['Author'] === $currentUser;
$isAdmin = $currentRole === 'Administrateur';

if (!$isAdmin && !$isAuthor) { 
    header('HTTP/1.1 403 Forbidden');
    die(); 
}


// Traitement POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $nameFR = $_POST['nameFR'] ?? '';
    $without = $_POST['without'] ?? [];

    $ingredients = $_POST['ingredients'] ?? [];
    $ingredientsFR = $_POST['ingredientsFR'] ?? [];
    $steps = $_POST['steps'] ?? [];
    $stepsFR = $_POST['stepsFR'] ?? [];
    $timers = $_POST['timers'] ?? [];


    $imageURL = $_POST['imageURL'] ?? '';
    $originalURL = $_POST['originalURL'] ?? '';

    // Mise à jour recette
    $recipes[$recipeKey] = [
        "id" => $recipeId, 
        "name" => $name, 
        "nameFR" => $nameFR,
        "Author" => $recipeToModify['Author'], // Garder l'auteur initial
        "Without" => $without,
        "ingredients" => $ingredients, 
        "ingredientsFR" => $ingredientsFR,
        "steps" => $steps, 
        "stepsFR" => $stepsFR, 
        "timers" => $timers,
        "imageURL" => $imageURL, 
        "originalURL" => $originalURL,
        "likes" => $recipeToModify['likes'] ?? [],  // Garder les likes
        "comments" => $recipeToModify['comments'] ?? [], // Garder les commentaires 
        "validated" => ($isAdmin) ? 1 : 0 // Mettre la validation a 0 pour toute modification sauf si c'est l'administrateur qui modifie (approuvee par defaut)
    ];

    // Sauvegarde et redirection
    file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    header("Location: recipe.php?id=" . $recipeId);
    exit;
}




// Construction directe du HTML du formulaire sur PHP 
$content = '
<div class="modify-recipe-container">
    <h1 data-translate="labels.modify_recipe">Modifier la Recette : ' . htmlspecialchars($recipeToModify['name'], ENT_QUOTES, 'UTF-8') . '</h1>
    <p> Modifiez les champs existants. Laissez les champs vides pour les supprimer. </p>

    <form method="POST" action="modify_recipe.php?id=' . $recipeId . '">

        <label for="name" data-translate="labels.recipe_name_en_req">Nom Recette (Anglais) : *</label>
        <input type="text" id="name" name="name" value="' . htmlspecialchars($recipeToModify['name'] ?? '', ENT_QUOTES, 'UTF-8') . '" required>

        <label for="nameFR" data-translate="labels.recipe_name_fr">Nom Recette (Français) :</label>
        <input type="text" id="nameFR" name="nameFR" value="' . htmlspecialchars($recipeToModify['nameFR'] ?? '', ENT_QUOTES, 'UTF-8') . '">

        <div class="checkbox-group">
            <label data-translate="labels.dietary_restrictions">Restrictions alimentaires :</label>';
            $allRestrictions = ['NoGluten', 'NoMilk', 'Vegetarian', 'Vegan'];
            $currentRestrictions = $recipeToModify['Without'] ?? [];
            foreach ($allRestrictions as $restriction) {
                $checked = in_array($restriction, $currentRestrictions) ? 'checked' : '';
                // Utilise data-translate pour les labels des checkboxes
                $content .= '
                <div>
                    <input type="checkbox" id="' . $restriction . '" name="without[]" value="' . $restriction . '" ' . $checked . '>
                    <label for="' . $restriction . '" data-translate="labels.' . $restriction . '">' . $restriction . '</label>
                </div>';
            }
$content .= '
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.ingredients_en_req">Ingrédients (Anglais) : *</label>
            <div id="ingredients-container">';
            // Boucle pour générer un nombre FIXE de champs ingrédients EN
            for ($i = 0; $i < 10; $i++) {
                // Récupère la valeur existante pour cet index
                $ingredient = $recipeToModify['ingredients'][$i];
                $quantity = htmlspecialchars($ingredient['quantity'] ?? '', ENT_QUOTES, 'UTF-8');
                $name_val = htmlspecialchars($ingredient['name'] ?? '', ENT_QUOTES, 'UTF-8'); // Éviter conflit avec name= field name
                $ingredientTypeVal = htmlspecialchars($ingredient['type'] ?? '', ENT_QUOTES, 'UTF-8');
                $content .= '
                <div class="dynamic-field"> 
                    <div class="ingredient">
                        <input type="text" name="ingredients[' . $i . '][quantity]" value="' . $quantity . '">
                        <input type="text" name="ingredients[' . $i . '][name]" value="' . $name_val . '">
                        <input type="text" name="ingredients[' . $i . '][type]" value="' . $ingredientTypeVal . '">
                    </div>
                </div>';
            }
$content .= '
            </div>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.ingredients_fr">Ingrédients (Français) :</label>
            <div id="ingredients-fr-container">';
             // Boucle pour générer un nombre FIXE de champs ingrédients FR
             for ($i = 0; $i < 10; $i++) {
                 $ingredientFR = $recipeToModify['ingredientsFR'][$i];
                 $quantityFR = htmlspecialchars($ingredientFR['quantity'] ?? '', ENT_QUOTES, 'UTF-8');
                 $nameFR_val = htmlspecialchars($ingredientFR['name'] ?? '', ENT_QUOTES, 'UTF-8');
                 $ingredientTypeValFR = htmlspecialchars($ingredientFR['type'] ?? '', ENT_QUOTES, 'UTF-8');
                  $content .= '
                 <div class="dynamic-field">
                     <div class="ingredient">
                         <input type="text" name="ingredientsFR[' . $i . '][quantity]" value="' . $quantityFR . '">
                         <input type="text" name="ingredientsFR[' . $i . '][name]" value="' . $nameFR_val . '">
                         <input type="text" name="ingredientsFR[' . $i . '][type]" value="' . $ingredientTypeValFR . '">
                     </div>
                 </div>';
            }
$content .= '
            </div>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.steps_en_req">Étapes (Anglais) : *</label>
            <div id="steps-container">';
             // Boucle pour générer un nombre FIXE de champs étapes EN
             for ($i = 0; $i < 10; $i++) {
                 $stepText = htmlspecialchars($recipeToModify['steps'][$i] ?? '', ENT_QUOTES, 'UTF-8');
                 $content .= '
                 <div class="dynamic-field">
                     <textarea name="steps[' . $i . ']"' . ($i + 1) . '">' . $stepText . '</textarea>
                 </div>';
            }
$content .= '
            </div>
        </div>

        <div class="dynamic-fields-section">
             <label data-translate="labels.steps_fr">Étapes (Français) :</label>
             <div id="steps-fr-container">';
             // Boucle pour générer un nombre FIXE de champs étapes FR
             for ($i = 0; $i < 10; $i++) {
                $stepTextFR = htmlspecialchars($recipeToModify['stepsFR'][$i] ?? '', ENT_QUOTES, 'UTF-8');
                $content .= '
                <div class="dynamic-field">
                    <textarea name="stepsFR[' . $i . ']"' . ($i + 1) . '">' . $stepTextFR . '</textarea>
                </div>';
             }
$content .= '
             </div>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.timers_req">Minuteurs (en minutes, un par étape) : *</label>
            <div id="timers-container">';
             // Boucle pour générer un nombre FIXE de champs minuteurs
             for ($i = 0; $i < 10; $i++) {  
                 $timerValue = htmlspecialchars($recipeToModify['timers'][$i] ?? '', ENT_QUOTES, 'UTF-8');
                  $content .= '
                 <div class="dynamic-field">
                     <input type="number" name="timers[' . $i . ']" value="' . $timerValue . '" min="0">
                 </div>';
            }
$content .= '
            </div>
        </div>

        <label for="imageURL" data-translate="labels.image_url">URL Image :</label>
        <input type="url" id="imageURL" name="imageURL" value="' . htmlspecialchars($recipeToModify['imageURL'] ?? '', ENT_QUOTES, 'UTF-8') . '">

        <label for="originalURL" data-translate="labels.original_url">URL Recette Originale :</label>
        <input type="url" id="originalURL" name="originalURL" value="' . htmlspecialchars($recipeToModify['originalURL'] ?? '', ENT_QUOTES, 'UTF-8') . '">

         <p><small data-translate="labels.required_fields_note">* Champs requis</small></p>

        <button type="submit" class="button button-primary" style="width: 100%; margin-top: 20px;" data-translate="buttons.save_changes">Sauvegarder Modifications</button>
    </form>
</div>
';

// Titre de la page
$title = "Modifier Recette";
// Inclusion de l'en-tête
include 'header.php';
?>


<script>
$(document).ready(function() {

        // Pas besoin de JS ici car les traductions sont geres par header.php

});
</script>