<?php
/**
 * Page permettant aux Chefs/Admins de créer une nouvelle recette.
 */

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier accès Chef ou Admin
$isAllowed = isset($_SESSION['role']) && ($_SESSION['role'] == 'Chef' || $_SESSION['role'] == 'Administrateur');
if (!$isAllowed) {
    header('HTTP/1.1 403 Forbidden');
    die(); 
}

$authorUsername = $_SESSION['username']; // Nom du chef connecté

// Traitement POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage
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


    // Validation cohérence étapes/minuteurs 
    if (count($steps) !== count($timers)) {
        die("Erreur: Le nombre d'étapes (".count($steps).") et de minuteurs (".count($timers).") doit correspondre.");
    }


    //Calcul Nouvel ID et Création Recette
    $recipesFile = 'recipes.json';
    $recipes = [];
    $recipes = json_decode(file_get_contents($recipesFile), true);

    $maxId = 0;
    foreach ($recipes as $recipe) {
            $maxId = max($maxId, (int)$recipe['id']);
    }
    $newId = $maxId + 1;

    $newRecipe = [
        "id" => $newId, 
        "name" => $name, 
        "nameFR" => $nameFR,
        "Author" => $authorUsername, 
        "Without" => $without,
        "ingredients" => $ingredients, 
        "ingredientsFR" => $ingredientsFR,
        "steps" => $steps,
        "stepsFR" => $stepsFR,
        "timers" => $timers,
        "imageURL" => $imageURL, 
        "originalURL" => $originalURL,
        "likes" => [], 
        "comments" => [], 
        "validated" => 0
    ];

    // Ajout et Sauvegarde
    $recipes[] = $newRecipe;
    file_put_contents(
        $recipesFile,
        json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    // Redirection vers le profil après création
    header("Location: profile.php");
    exit;
}


// Construction directe du HTML du formulaire 
$content = '
<div class="create-recipe-container">
    <h1 data-translate="labels.create_recipe_title">Créer une Nouvelle Recette</h1>

    <p data-translate="labels.infocreate"> 10 champs ingrédients et 10 champs étapes/minuteurs sont affichés. Laissez vides ceux dont vous n avez pas besoin.</p>

    <form method="POST" action="create_recipe.php">

        <label for="name" data-translate="labels.recipe_name_en_req">Nom Recette (Anglais) : *</label>
        <input type="text" id="name" name="name" required>

        <label for="nameFR" data-translate="labels.recipe_name_fr">Nom Recette (Français) :</label>
        <input type="text" id="nameFR" name="nameFR">

        <div class="checkbox-group">
            <label data-translate="labels.dietary_restrictions">Restrictions alimentaires :</label>';
            $allRestrictions = ['NoGluten', 'NoMilk', 'Vegetarian', 'Vegan'];
            foreach ($allRestrictions as $restriction) {
                // Clés de traduction pour les labels des checkboxes
                $labelKey = 'labels.' . $restriction;
                $content .= '
                <div>
                    <input type="checkbox" id="' . $restriction . '" name="without[]" value="' . $restriction . '">
                    <label for="' . $restriction . '" data-translate="'.$labelKey.'">' . $restriction . '</label>
                </div>';
            }
$content .= '
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.ingredients_en_req">Ingrédients (Anglais) : *</label>
            <div id="ingredients-container">';
            // Boucle pour générer un nombre FIXE de champs ingrédients EN vides
            for ($i = 0; $i < 10; $i++) {
                // Le champ required est mis uniquement sur les noms des X premiers (ex: 1er seul) 
                $content .= '
                <div class="dynamic-field">
                    <div class="ingredient">
                         <input type="text" name="ingredients[' . $i . '][quantity]"
                               placeholder="Quantity" data-translate-placeholder="placeholders.quantity">
                        <input type="text" name="ingredients[' . $i . '][name]"
                               placeholder="Ingredient Name" data-translate-placeholder="placeholders.ingredient_name" '.($i === 0 ? 'required' : '').'>
                        <input type="text" name="ingredients[' . $i . '][type]"
                               placeholder="Type (e.g. Meat)" data-translate-placeholder="placeholders.ingredient_type_en">
                    </div>
                </div>';
            }
$content .= '
            </div>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.ingredients_fr">Ingrédients (Français) :</label>
            <div id="ingredients-fr-container">';
             // Boucle pour générer un nombre FIXE de champs ingrédients FR vides
             for ($i = 0; $i < 10; $i++) {
                  $content .= '
                 <div class="dynamic-field">
                     <div class="ingredient">
                        <input type="text" name="ingredientsFR[' . $i . '][quantity]"
                                placeholder="Quantité" data-translate-placeholder="placeholders.quantity_fr">
                         <input type="text" name="ingredientsFR[' . $i . '][name]"
                                placeholder="Nom ingrédient" data-translate-placeholder="placeholders.ingredient_name_fr">
                         <input type="text" name="ingredientsFR[' . $i . '][type]"
                                placeholder="Type (e.g. Viande)" data-translate-placeholder="placeholders.ingredient_type_fr">
                     </div>
                 </div>';
            }
$content .= '
            </div>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.steps_en_req">Étapes (Anglais) : *</label>
            <div id="steps-container">';
             // Boucle pour générer un nombre FIXE de champs étapes EN vides
             for ($i = 0; $i < 10; $i++) {
                 $content .= '
                 <div class="dynamic-field">
                     <textarea name="steps[' . $i . ']"
                               placeholder="' . 'Step ' . ($i + 1) . '"
                               data-translate-placeholder="placeholders.step_n"
                               data-placeholder-index="' . ($i + 1) . '"
                               '.($i === 0 ? 'required' : '').'></textarea>
                 </div>';
            }
$content .= '
            </div>
        </div>

        <div class="dynamic-fields-section">
             <label data-translate="labels.steps_fr">Étapes (Français) :</label>
             <div id="steps-fr-container">';
             // Boucle pour générer un nombre FIXE de champs étapes FR vides
             for ($i = 0; $i < 10; $i++) {
                $content .= '
                <div class="dynamic-field">
                   <textarea name="stepsFR[' . $i . ']"
                              placeholder="' . 'Étape ' . ($i + 1) . '"
                              data-translate-placeholder="placeholders.step_n"
                              data-placeholder-index="' . ($i + 1) . '"></textarea>
                </div>';
             }
$content .= '
             </div>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.timers_req">Minuteurs (en minutes, un par étape) : *</label>
            <div id="timers-container">';
             // Boucle pour générer un nombre FIXE de champs minuteurs vides
             for ($i = 0; $i < 10; $i++) { 
                  $content .= '
                 <div class="dynamic-field">
                      <input type="number" name="timers[' . $i . ']"
                            placeholder="' . 'Timer for Step ' . ($i + 1) . '"
                            data-translate-placeholder="placeholders.timer_n"
                            data-placeholder-index="' . ($i + 1) . '"
                            min="0" '.($i === 0 ? 'required' : '').'>
                 </div>';
            }
$content .= '
            </div>
        </div>

        <label for="imageURL" data-translate="labels.image_url">URL Image :</label>
         <input type="url" id="imageURL" name="imageURL" placeholder="https://exemple.com/image.jpg" data-translate-placeholder="placeholders.image_url_recipe">

        <label for="originalURL" data-translate="labels.original_url">URL Recette Originale :</label>
        <input type="url" id="originalURL" name="originalURL" placeholder="https://source.com/recette" data-translate-placeholder="placeholders.original_url_recipe">

         <p><small data-translate="labels.required_fields_note">* Champs requis (premier ingrédient, première étape, premier minuteur)</small></p>

        <button type="submit" class="button button-primary" style="width: 100%; margin-top: 20px;" data-translate="buttons.create_recipe">Créer Recette</button>
    </form>
</div>
';

// Titre de la page
$title = "Créer Recette";
// Inclusion de l'en-tête
include 'header.php';
?>

<script>
$(document).ready(function() {

    // Pas besoin de JS ici car les traductions sont geres par header.php
});
</script>