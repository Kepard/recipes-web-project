<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and has the role of Chef or Admin
$isAllowed = isset($_SESSION['role']) && ($_SESSION['role'] == 'Chef' || $_SESSION['role'] == 'Administrateur'); // Allow Admin too
if (!$isAllowed) {
    // Use flash message and redirect
    $_SESSION['flash_message'] = ['type' => 'error', 'key' => 'messages.permission_denied'];
    header("Location: index.php");
    exit;
}

$authorUsername = $_SESSION['username'];

// Handle form submission
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



    // ... (Load recipes, find max ID logic remains the same) ...
    $recipesFile = 'recipes.json';
    $recipes = [];
    if (file_exists($recipesFile)) {
         $recipesData = file_get_contents($recipesFile);
         $recipes = json_decode($recipesData, true);
        if (!is_array($recipes)) { $recipes = []; }
    }
    $maxId = 0;
    foreach ($recipes as $recipe) {
        if (isset($recipe['id']) && is_numeric($recipe['id'])) {
            $maxId = max($maxId, (int)$recipe['id']);
        }
    }
    // Calculate the new ID
    $newId = $maxId + 1; // Define new ID before using it

    // --- *** FIX: Define the actual $newRecipe array *** ---
    $newRecipe = [
        "id" => $newId,                 // Use the calculated new ID
        "name" => $name,
        "nameFR" => $nameFR,
        "Author" => $authorUsername,    // Set the author from session
        "Without" => $without,          // Dietary restrictions array
        "ingredients" => $ingredients,    // Processed ingredients array
        "ingredientsFR" => $ingredientsFR,  // Processed French ingredients array
        "steps" => $steps,              // Processed steps array
        "stepsFR" => $stepsFR,          // Processed French steps array
        "timers" => $timers,            // Processed timers array
        "imageURL" => $imageURL,
        "originalURL" => $originalURL,
        "likes" => [],                  // Initialize likes as empty array
        "comments" => [],               // Initialize comments as empty array
        "validated" => 0                // Default to not validated
    ];
    // ----------------------------------------------------------

    // Add the newly defined recipe to the array
    $recipes[] = $newRecipe;

    // Save updated recipes back to the JSON file
    file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    header("Location: profile.php"); 
}



// --- HTML Form Generation with data-translate attributes ---
// (Keep the rest of the file exactly as you provided it in the previous message)
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
                        <input type="text" name="ingredients[0][type]" data-translate-placeholder="placeholders.ingredient_type_en">
                    </div>
                    <!-- Button text is handled by dynamic_fields.js now -->
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
                        <input type="text" name="ingredientsFR[0][quantity]" data-translate-placeholder="placeholders.quantity_fr">
                        <input type="text" name="ingredientsFR[0][name]" data-translate-placeholder="placeholders.ingredient_name_fr">
                        <input type="text" name="ingredientsFR[0][type]" data-translate-placeholder="placeholders.ingredient_type_fr">
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
                    <textarea name="steps[0]" data-translate-placeholder="placeholders.step_1" data-placeholder-index="1" required></textarea>
                    <button type="button" class="remove-field button button-danger" data-sync-type="step">×</button>
                </div>
            </div>
            <button type="button" id="add-step" class="button button-secondary" data-translate="buttons.add_step">Add Step</button>
        </div>

        <div class="dynamic-fields-section">
             <label data-translate="labels.steps_fr">Steps (French):</label>
             <div id="steps-fr-container">
                 <div class="dynamic-field">
                    <textarea name="stepsFR[0]" data-translate-placeholder="placeholders.step_1_fr" data-placeholder-index="1"></textarea>
                    <button type="button" class="remove-field button button-danger" data-sync-type="step">×</button>
                 </div>
             </div>
             <button type="button" id="add-step-fr" class="button button-secondary" data-translate="buttons.add_step_fr">Add Step (French)</button>
        </div>

        <div class="dynamic-fields-section">
            <label data-translate="labels.timers_req">Timers (in minutes, one per step): *</label>
            <div id="timers-container">
                 <div class="dynamic-field">
                     <input type="number" name="timers[0]" data-translate-placeholder="placeholders.timer_1" data-placeholder-index="1" min="0" required>
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

$title = "Create Recipe"; // Default title, JS can update later if needed
include 'header.php';
?>

<!-- Include the shared dynamic fields script -->
<script src="dynamic_fields.js"></script>

<!-- Specific JS for this page (remains the same) -->
<script>
// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {
    // Store translations globally for this page context if needed by dynamic_fields.js
    // If dynamic_fields.js uses the global 'currentTranslations', ensure it's set here.
    window.currentTranslations = translations; // Make it explicit global (alternative)

    // Translate the error message placeholder if it exists
    const $errorMsg = $('.message.error[data-translate-key]');
    if ($errorMsg.length) {
        const key = $errorMsg.data('translate-key');
        const errorText = getNestedTranslation(translations, key) || "An error occurred.";
        $errorMsg.text(errorText); // Set the translated text
        $errorMsg.removeAttr('data-translate-key'); // Clean up attribute
    }

     // Translate dynamic placeholders for the *initial* fields added by PHP
     translateDynamicPlaceholders(translations);

     // Translate the page title (optional)
     const pageTitle = getNestedTranslation(translations, 'labels.create_recipe_title');
     if(pageTitle) document.title = pageTitle;
}

// Helper function to translate dynamic placeholders (can be shared or page-specific)
function translateDynamicPlaceholders(translations) {
    $('[data-translate-placeholder][data-placeholder-index]').each(function() {
        const $el = $(this);
        const key = $el.data('translate-placeholder');
        const index = $el.data('placeholder-index');
        let placeholderText = getNestedTranslation(translations, key) || '';
        // Basic replacement, assumes placeholder key doesn't need {n}
        // If keys like 'placeholders.step_n' are used, this needs updating
        if (placeholderText.includes('{n}')) { // Simpler check
             placeholderText = placeholderText.replace(/\{n\}/g, index); // Use regex global replace
        }
        $el.attr('placeholder', placeholderText);
    });
     // Translate static placeholders too
     $('[data-translate-placeholder]:not([data-placeholder-index])').each(function() {
         const $el = $(this);
         const key = $el.data('translate-placeholder');
         const placeholderText = getNestedTranslation(translations, key) || '';
         $el.attr('placeholder', placeholderText);
     });
}


$(document).ready(function() {
    // Validation logic remains the same, but use translated message
    $('form').submit(function(e) {
        const stepCount = $('#steps-container .dynamic-field').length;
        const timerCount = $('#timers-container .dynamic-field').length;

        if (stepCount !== timerCount) {
            e.preventDefault(); // Prevent submission
            // Get translated message using currentTranslations (available globally via header.php)
            let alertMsg = "Number of steps ({stepCount}) must match number of timers ({timerCount}). Add 0 for steps without a timer."; // Default
             // Safely check if translations and the specific message exist
             if (typeof currentTranslations !== 'undefined' && currentTranslations.messages && currentTranslations.messages.steps_timers_mismatch) {
                 alertMsg = currentTranslations.messages.steps_timers_mismatch;
             }
             alertMsg = alertMsg.replace('{stepCount}', stepCount).replace('{timerCount}', timerCount);

              // Use showMessage if available, otherwise alert
             if (typeof showMessage === 'function') {
                 showMessage(alertMsg, 'error');
             }
            return false;
        }
        return true; // Allow submission
    });
});
</script>