<?php
/**
 * Page de traduction (EN -> FR) 
 */

// Démarrage session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupération des informations nécessaires
$recipeId = (int) $_GET['id'];
$currentUser = $_SESSION['username'] ?? null;
$currentRole = $_SESSION['role'] ?? null;

// Vérifications initiales
if (!isset($currentUser)) {
    header('index.php');
    exit;
}

// Chargement et validation de la recette
$recipesFile = 'recipes.json';
$recipe = null;
$recipeIndex = null;

$recipes = json_decode(file_get_contents($recipesFile), true);
    foreach ($recipes as $index => $r) {
        if ($r['id'] == $recipeId) {
            $recipeIndex = $index;
            $recipe = $r;
            break;
        }
    }


//  Vérification des droits d'accès 
// Traducteur, Admin, ou Chef (Auteur) peuvent accéder
$isAuthor = isset($recipe['Author']) && $recipe['Author'] === $currentUser;
$isTranslator = $currentRole === 'Traducteur';
$isAdmin = $currentRole === 'Administrateur';
$isChef = $currentRole === 'Chef';
$canAccessPage = $isTranslator || $isAdmin || ($isChef && $isAuthor) ;

if (!$canAccessPage) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

// --- Traitement de la soumission du formulaire  ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupère TOUTES les données FR soumises directement.
    $recipe['nameFR'] = $_POST['nameFR'];

    // Récupère ingredientsFR et stepsFR tels quels
    $recipe['ingredientsFR'] = $_POST['ingredientsFR'];
    $recipe['stepsFR'] = $_POST['stepsFR'];

    // Mettre à jour la recette dans le tableau global
    $recipes[$recipeIndex] = $recipe;

    // Sauvegarder les modifications
    file_put_contents(
        $recipesFile,
        json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    // Redirection SANS message flash pour simplifier
    header("Location: recipe.php?id=" . $recipeId);
    exit;
}

// Génération directe du Contenu HTML avec traductions


$recipeNamePHP = $recipe['name'] ;
$nameFR_current = htmlspecialchars($recipe['nameFR'] ?? '', ENT_QUOTES, 'UTF-8');

$content = '
<div class="translation-container">
    <h1 data-translate="labels.translate_recipe_title_base">Traduire la Recette :
        <span id="recipe-name-display">' . $recipeNamePHP . '</span>
    </h1>
    <p> Entrez les traductions françaises dans les champs de droite. Assurez-vous d\'avoir le même nombre d\'ingrédients et d\'étapes que l\'original. </p>

    <form method="POST" id="translation-form" action="translate_recipe.php?id=' . $recipeId . '">
        <div class="translation-columns">

            <div class="translation-column original-column">
                <h2 data-translate="labels.original_english">Original (Anglais)</h2>

                <div class="form-group">
                    <label data-translate="labels.recipe_name">Nom Recette :</label>
                    <div class="original-field">' . htmlspecialchars($recipe['name'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                </div>

                <div class="form-group">
                    <label data-translate="labels.ingredients">Ingrédients :</label>';
                    if (!empty($recipe['ingredients'])) {
                        foreach ($recipe['ingredients'] as $ingredient) {
                            $content .= '
                            <div class="translation-row">
                                <div class="original-field" title="Quantité EN">' . htmlspecialchars($ingredient['quantity'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                                <div class="original-field" title="Nom EN">' . htmlspecialchars($ingredient['name'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                                <div class="original-field" title="Type EN">' . htmlspecialchars($ingredient['type'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                            </div>';
                        }
                    } 
$content .= '
                </div>

                <div class="form-group">
                    <label data-translate="labels.steps">Étapes :</label>';
                     if (!empty($recipe['steps'])) {
                        foreach ($recipe['steps'] as $index => $step) {
                            $content .= '
                            <div class="translation-row">
                                <div class="original-field">' . htmlspecialchars($step ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                            </div>';
                        }
                    }
$content .= '
                </div>
            </div>

            <div class="translation-column translation-form-column">
                <h2 data-translate="labels.translation_french">Traduction (Français)</h2>

                 <div class="form-group">
                    <label for="nameFR" data-translate="labels.recipe_name">Nom Recette :</label>
                    <input type="text" id="nameFR" name="nameFR" value="' . $nameFR_current . '" ' . (($isTranslator && !empty($recipe['nameFR'][$index])) ? 'readonly' : '') . '>
                </div>

                <div class="form-group">
                    <label data-translate="labels.ingredients">Ingrédients :</label>';
                     // Génère un champ FR pour chaque champ EN, pré-rempli si possible
                     if (!empty($recipe['ingredients']) && is_array($recipe['ingredients'])) {
                        foreach ($recipe['ingredients'] as $index => $ingredientEN) {
                            // Récupère la traduction FR existante ou des valeurs vides
                            $ingredientFR = $recipe['ingredientsFR'][$index] ?? [];
                            $quantityFR = htmlspecialchars($ingredientFR['quantity'] ?? '', ENT_QUOTES, 'UTF-8');
                            $nameFR_val = htmlspecialchars($ingredientFR['name'] ?? '', ENT_QUOTES, 'UTF-8');
                            $ingredientTypeValFR = htmlspecialchars($ingredientFR['type'] ?? '', ENT_QUOTES, 'UTF-8');

                            $content .= '
                            <div class="translation-row">
                                <input type="text" name="ingredientsFR[' . $index . '][quantity]" value="' . $quantityFR . '" ' . (($isTranslator && !empty($ingredientFR['type'])) ? 'readonly' : '') . ' ">
                                <input type="text" name="ingredientsFR[' . $index . '][name]" value="' . $nameFR_val . '" ' . (($isTranslator && !empty($ingredientFR['type'])) ? 'readonly' : '') . '>
                                <input type="text" name="ingredientsFR[' . $index . '][type]" value="' . $ingredientTypeValFR . '" ' . (($isTranslator && !empty($ingredientFR['type'])) ? 'readonly' : '') . '>
                            </div>';
                        }
                    }
$content .= '
                </div>

                 <div class="form-group">
                    <label data-translate="labels.steps">Étapes :</label>';
                     if (!empty($recipe['steps']) && is_array($recipe['steps'])) {
                         foreach ($recipe['steps'] as $index => $stepEN) {
                            $stepFR_current = htmlspecialchars($recipe['stepsFR'][$index] ?? '', ENT_QUOTES, 'UTF-8');
                             $content .= '
                            <div class="translation-row">
                                <textarea name="stepsFR[' . $index . ']" ' . (($isTranslator && !empty($recipe['stepsFR'][$index])) ? 'readonly' : '') . '>' . $stepFR_current . '</textarea>
                            </div>';
                        }
                    }
$content .= '
                </div>
            </div> 
        </div> 

        <div class="form-actions">
            <button type="submit" class="button button-primary btn-save" data-translate="buttons.save_translation">Sauvegarder Traduction</button>
            <a href="recipe.php?id=' . $recipeId . '" class="button button-secondary btn-cancel" data-translate="buttons.cancel">Annuler</a>
        </div>
    </form>
</div>'; // Fin translation-container

// Titre et Inclusion Header
$title = "Traduire Recette";
include 'header.php';
?>

<script>
$(document).ready(function() {

    // Pas de JS necessaire 

});
</script>