<?php
// Démarrer la session seulement si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et a le rôle de Chef ou Admin
$isAllowed = isset($_SESSION['role']) && ($_SESSION['role'] == 'Chef' || $_SESSION['role'] == 'Administrateur'); // Autoriser aussi l'Admin
if (!$isAllowed) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

$authorUsername = $_SESSION['username'];

// Gérer la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $nameFR = trim(htmlspecialchars($_POST['nameFR'] ?? ''));
    $without = $_POST['without'] ?? [];
    $ingredients = $_POST['ingredients'] ?? [];
    $ingredientsFR = $_POST['ingredientsFR'] ?? [];
    $steps = $_POST['steps'] ?? [];
    $stepsFR = $_POST['stepsFR'] ?? [];
    $timers = $_POST['timers'] ?? [];
    $imageURL = filter_var(trim($_POST['imageURL'] ?? ''), FILTER_SANITIZE_URL);
    $originalURL = filter_var(trim($_POST['originalURL'] ?? ''), FILTER_SANITIZE_URL);

    $recipesFile = 'recipes.json';
    $recipes = json_decode(file_get_contents($recipesFile), true);

    $maxId = 0;
    foreach ($recipes as $recipe) {
        if (isset($recipe['id']) && is_numeric($recipe['id'])) {
            $maxId = max($maxId, (int)$recipe['id']);
        }
    }
    // Calculer le nouvel ID
    $newId = $maxId + 1; // Définir le nouvel ID avant de l'utiliser

    $newRecipe = [
        "id" => $newId,                 // Utiliser le nouvel ID calculé
        "name" => $name,
        "nameFR" => $nameFR,
        "Author" => $authorUsername,    // Définir l'auteur à partir de la session
        "Without" => $without,          // Tableau des restrictions alimentaires
        "ingredients" => $ingredients,    // Tableau des ingrédients traités
        "ingredientsFR" => $ingredientsFR,  // Tableau des ingrédients français traités
        "steps" => $steps,              // Tableau des étapes traitées
        "stepsFR" => $stepsFR,          // Tableau des étapes françaises traitées
        "timers" => $timers,            // Tableau des minuteurs traités
        "imageURL" => $imageURL,
        "originalURL" => $originalURL,
        "likes" => [],                  // Initialiser les likes comme un tableau vide
        "comments" => [],               // Initialiser les commentaires comme un tableau vide
        "validated" => 0                // Par défaut non validé
    ];
    // ----------------------------------------------------------

    // Ajouter la recette nouvellement définie au tableau
    $recipes[] = $newRecipe;

    // Sauvegarder les recettes mises à jour dans le fichier JSON
    file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    header("Location: profile.php");
}



// --- Génération du formulaire HTML avec les attributs data-translate ---
$content = '
<div class="create-recipe-container">
    <h1 data-translate="labels.create_recipe_title">Create a New Recipe</h1>
    <form method="POST" action="create_recipe.php">

        <label for="name" data-translate="labels.recipe_name_en_req">Recipe Name (English): *</label>
        <input type="text" id="name" name="name" required>

        <label for="nameFR" data-translate="labels.recipe_name_fr">Recipe Name (French):</label>
        <input type="text" id="nameFR" name="nameFR">

        <div class="checkbox-group">
            <label data-translate="labels.dietary_restrictions">Dietary Restrictions:</label>
            <div>
                <input type="checkbox" id="noGluten" name="without[]" value="NoGluten">
                <label for="noGluten" data-translate="labels.no_gluten">No Gluten</label>
            </div>
            <div>
                <input type="checkbox" id="noMilk" name="without[]" value="NoMilk">
                <label for="noMilk" data-translate="labels.no_milk">No Milk</label>
            </div>
            <div>
                <input type="checkbox" id="vegetarian" name="without[]" value="Vegetarian">
                <label for="vegetarian" data-translate="labels.vegetarian">Vegetarian</label>
            </div>
             <div>
                <input type="checkbox" id="vegan" name="without[]" value="Vegan">
                <label for="vegan" data-translate="labels.vegan">Vegan</label>
            </div>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.ingredients_en_req">Ingredients (English): *</label>
            <div id="ingredients-container">
                 <div class="dynamic-field">
                    <div class="ingredient">
                        <input type="text" name="ingredients[0][quantity]" data-translate-placeholder="placeholders.quantity" required>
                        <input type="text" name="ingredients[0][name]" data-translate-placeholder="placeholders.ingredient_name" required>
                        <input type="text" name="ingredients[0][type]" data-translate-placeholder="placeholders.ingredient_type">
                    </div>
                    <button type="button" class="remove-field button button-danger" data-sync-type="ingredient">×</button>
                </div>
            </div>
            <button type="button" id="add-ingredient" class="button button-secondary" data-translate="buttons.add_ingredient">Add Ingredient</button>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.ingredients_fr">Ingredients (French):</label>
            <div id="ingredients-fr-container">
                 <div class="dynamic-field">
                    <div class="ingredient">
                        <input type="text" name="ingredientsFR[0][quantity]" data-translate-placeholder="placeholders.quantity">
                        <input type="text" name="ingredientsFR[0][name]" data-translate-placeholder="placeholders.ingredient_name">
                        <input type="text" name="ingredientsFR[0][type]" data-translate-placeholder="placeholders.ingredient_type">
                    </div>
                    <button type="button" class="remove-field button button-danger" data-sync-type="ingredient">×</button>
                </div>
            </div>
            <button type="button" id="add-ingredient-fr" class="button button-secondary" data-translate="buttons.add_ingredient_fr">Add Ingredient (French)</button>
        </div>

         <div class="dynamic-fields-section">
            <label data-translate="labels.steps_en_req">Steps (English): *</label>
            <div id="steps-container">
                <div class="dynamic-field">
                    <textarea name="steps[0]" data-translate-placeholder="placeholders.step_n" data-placeholder-index="1" required></textarea>
                    <button type="button" class="remove-field button button-danger" data-sync-type="step">×</button>
                </div>
            </div>
            <button type="button" id="add-step" class="button button-secondary" data-translate="buttons.add_step">Add Step</button>
        </div>

        <div class="dynamic-fields-section">
             <label data-translate="labels.steps_fr">Steps (French):</label>
             <div id="steps-fr-container">
                 <div class="dynamic-field">
                    <textarea name="stepsFR[0]" data-translate-placeholder="placeholders.step_n" data-placeholder-index="1"></textarea>
                    <button type="button" class="remove-field button button-danger" data-sync-type="step">×</button>
                 </div>
             </div>
             <button type="button" id="add-step-fr" class="button button-secondary" data-translate="buttons.add_step">Add Step (French)</button>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.timers_req">Timers (in minutes, one per step): *</label>
            <div id="timers-container">
                 <div class="dynamic-field">
                     <input type="number" name="timers[0]" data-translate-placeholder="placeholders.timer_n" data-placeholder-index="1" min="0" required>
                     <button type="button" class="remove-field button button-danger" data-sync-type="timer">×</button>
                 </div>
            </div>
            <button type="button" id="add-timer" class="button button-secondary" data-translate="buttons.add_timer">Add Timer</button>
        </div>


        <label for="imageURL" data-translate="labels.image_url">Image URL:</label>
        <input type="url" id="imageURL" name="imageURL" data-translate-placeholder="placeholders.image_url_recipe">

        <label for="originalURL" data-translate="labels.original_url">Original Recipe URL:</label>
        <input type="url" id="originalURL" name="originalURL" data-translate-placeholder="placeholders.original_url_recipe">

        <p><small data-translate="labels.required_fields_note">* Required fields</small></p>

        <button type="submit" class="button button-primary" style="width: 100%; margin-top: 20px;" data-translate="buttons.create_recipe">Create Recipe</button>
    </form>
</div>
';

$title = "Create Recipe"; // Titre par défaut
include 'header.php';
?>

<!-- Inclure le script partagé des champs dynamiques -->
<script src="dynamic_fields.js"></script>

<script>
// Cette fonction est appelée par header.php après le chargement des traductions
function initializePageContent(translations, lang) {
     // Traduire les placeholders dynamiques pour les champs *initiaux* ajoutés par PHP
     translateDynamicPlaceholders(translations);

     // Traduire le titre de la page (optionnel)
     const pageTitle = getNestedTranslation(translations, 'labels.create_recipe_title');
     document.title = pageTitle;
}

// Fonction d'aide pour traduire les placeholders dynamiques (peut être partagée ou spécifique à la page)
function translateDynamicPlaceholders(translations) {
    $('[data-translate-placeholder][data-placeholder-index]').each(function() {
        const $el = $(this);
        const key = $el.data('translate-placeholder');
        const index = $el.data('placeholder-index');
        let placeholderText = getNestedTranslation(translations, key) || '';
        // Remplacement basique, suppose que la clé du placeholder n'a pas besoin de {n}
        // Si des clés comme 'placeholders.step_n' sont utilisées, ceci doit être mis à jour
        if (placeholderText.includes('{n}')) { // Vérification plus simple
             placeholderText = placeholderText.replace(/\{n\}/g, index); // Utiliser le remplacement global regex
        }
        $el.attr('placeholder', placeholderText);
    });
     // Traduire aussi les placeholders statiques
     $('[data-translate-placeholder]:not([data-placeholder-index])').each(function() {
         const $el = $(this);
         const key = $el.data('translate-placeholder');
         const placeholderText = getNestedTranslation(translations, key) || '';
         $el.attr('placeholder', placeholderText);
     });
}


$(document).ready(function() {
    // La logique de validation reste la même, mais utilise le message traduit
    $('form').submit(function(e) {
        const stepCount = $('#steps-container .dynamic-field').length;
        const timerCount = $('#timers-container .dynamic-field').length;

        if (stepCount !== timerCount) {
            e.preventDefault(); // Empêcher la soumission
            showMessage(currentTranslations.messages.steps_timers_mismatch, 'error');

            return false;
        }
        return true; // Autoriser la soumission
    });
});
</script>