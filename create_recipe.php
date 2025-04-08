<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and has the role of Chef
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
    // ... (validation and sanitization logic remains the same) ...
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

    // Basic Validation
    if (empty($name) || empty($ingredients) || !is_array($ingredients) || empty($steps) || !is_array($steps) || empty($timers) || !is_array($timers)) {
         $_SESSION['form_error_key'] = 'messages.missing_fields_create'; // Use key
         header("Location: create_recipe.php");
         exit;
    }

     // Process arrays (remains the same)
     $ingredients = array_values(array_filter($ingredients, fn($ing) => !empty($ing['name'])));
     $ingredientsFR = array_values(array_filter($ingredientsFR, fn($ing) => !empty($ing['name'])));
     $steps = array_values(array_filter($steps, fn($step) => !empty(trim($step))));
     $stepsFR = array_values(array_filter($stepsFR, fn($step) => !empty(trim($step))));
     $timers = array_values(array_filter($timers, fn($timer) => is_numeric($timer)));


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

    // Create new recipe structure (remains the same)
    $newRecipe = [ /* ... */ ];
    $recipes[] = $newRecipe;

    // Save updated recipes (remains the same, but use keys for messages)
    $fp = fopen($recipesFile, 'w');
    if ($fp && flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        flock($fp, LOCK_UN);
        fclose($fp);
        $_SESSION['flash_message'] = ['type' => 'success', 'key' => 'messages.recipe_created', 'params' => ['name' => $name]]; // Pass name for potential use in message
        header("Location: recipe.php?id=" . ($maxId + 1));
        exit;
    } else {
         if ($fp) fclose($fp);
         $_SESSION['form_error_key'] = 'messages.error_saving_recipe'; // Use key
         header("Location: create_recipe.php");
         exit;
    }
}

// Display potential error message from session (using keys)
$formErrorHTML = '';
if (isset($_SESSION['form_error_key'])) {
    $errorKey = $_SESSION['form_error_key'];
    // We need JS to translate this properly later
    $formErrorHTML = '<p class="message error" data-translate-key="' . $errorKey . '">Error occurred.</p>'; // Placeholder text
    unset($_SESSION['form_error_key']);
}


// --- HTML Form Generation with data-translate attributes ---
$content = '
<div class="create-recipe-container">
    <h1 data-translate="labels.create_recipe_title">Create a New Recipe</h1>
    ' . $formErrorHTML . ' <!-- Display form error message placeholder -->
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

<!-- Specific JS for this page -->
<script>
// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {
    // Store translations globally for this page context if needed by dynamic_fields.js
    // If dynamic_fields.js uses the global 'currentTranslations', ensure it's set here.
    // window.currentTranslations = translations; // Make it explicit global (alternative)

    // Translate the error message placeholder if it exists
    const $errorMsg = $('.message.error[data-translate-key]');
    if ($errorMsg.length) {
        const key = $errorMsg.data('translate-key');
        const errorText = getNestedTranslation(translations, key) || "An error occurred.";
        $errorMsg.text(errorText); // Set the translated text
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
        if (key.includes('step_')) { // Example check for dynamic keys
             placeholderText = placeholderText.replace('{n}', index);
         } else if (key.includes('timer_')) {
             placeholderText = placeholderText.replace('{n}', index);
         } // Add more rules if needed
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
             if (typeof currentTranslations !== 'undefined' && currentTranslations.messages?.steps_timers_mismatch) {
                 alertMsg = currentTranslations.messages.steps_timers_mismatch;
             }
             alertMsg = alertMsg.replace('{stepCount}', stepCount).replace('{timerCount}', timerCount);

              // Use showMessage if available, otherwise alert
             if (typeof showMessage === 'function') {
                 showMessage(alertMsg, 'error');
             } else {
                 alert(alertMsg);
             }
            return false;
        }
        return true; // Allow submission
    });
});
</script>