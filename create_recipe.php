<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in and has the role of Chef or Admin
$allowed = isset($_SESSION['role']) && ($_SESSION['role'] === 'Chef' || $_SESSION['role'] === 'Administrateur');
if (!$allowed) {
    // Redirect non-chefs/admins
    header("Location: index.php");
    // echo "<p class='message error'>You do not have permission to access this page.</p>"; // Alternative message
    exit;
}

$authorUsername = $_SESSION['username']; // Get username for the recipe author field

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic sanitization (can be improved)
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $nameFR = trim(htmlspecialchars($_POST['nameFR'] ?? ''));
    $without = $_POST['without'] ?? []; // Array of dietary restrictions
    $ingredients = $_POST['ingredients'] ?? []; // Array of ingredients EN
    $ingredientsFR = $_POST['ingredientsFR'] ?? []; // Array of ingredients FR
    $steps = $_POST['steps'] ?? []; // Array of steps EN
    $stepsFR = $_POST['stepsFR'] ?? []; // Array of steps FR
    $timers = $_POST['timers'] ?? []; // Array of timers
    $imageURL = filter_var(trim($_POST['imageURL'] ?? ''), FILTER_SANITIZE_URL);
    $originalURL = filter_var(trim($_POST['originalURL'] ?? ''), FILTER_SANITIZE_URL);

    // Basic Validation
    if (empty($name) || empty($ingredients) || !is_array($ingredients) || empty($steps) || !is_array($steps) || empty($timers) || !is_array($timers)) {
         // Use session flash message for error feedback after potential redirect
         $_SESSION['form_error'] = "Please fill in all required fields (Name, Ingredients, Steps, Timers).";
         // Reload the form page - data won't be preserved without extra logic
         header("Location: create_recipe.php");
         exit;
        // die("Please fill in all required fields (Name, Ingredients, Steps, Timers)."); // Or die
    }

     // Further process arrays - remove empty entries, maybe sanitize more
     $ingredients = array_values(array_filter($ingredients, fn($ing) => !empty($ing['name'])));
     $ingredientsFR = array_values(array_filter($ingredientsFR, fn($ing) => !empty($ing['name']))); // Filter FR too
     $steps = array_values(array_filter($steps, fn($step) => !empty(trim($step))));
     $stepsFR = array_values(array_filter($stepsFR, fn($step) => !empty(trim($step))));
     $timers = array_values(array_filter($timers, fn($timer) => is_numeric($timer))); // Ensure timers are numeric


    // Load existing recipes
    $recipesFile = 'recipes.json';
    $recipes = []; // Default to empty array
    if (file_exists($recipesFile)) {
         $recipesData = file_get_contents($recipesFile);
         $recipes = json_decode($recipesData, true);
         // Handle potential JSON decode error or if file is not an array
        if (!is_array($recipes)) {
             $recipes = [];
        }
    }


    // Find the highest existing ID
    $maxId = 0;
    foreach ($recipes as $recipe) {
        if (isset($recipe['id']) && is_numeric($recipe['id'])) {
            $maxId = max($maxId, (int)$recipe['id']);
        }
    }

    // Create new recipe structure
    $newRecipe = [
        "id" => $maxId + 1,
        "name" => $name,
        "nameFR" => $nameFR,
        "Author" => $authorUsername, // Use session username
        "Without" => $without,
        "ingredients" => $ingredients,
        "ingredientsFR" => $ingredientsFR,
        "steps" => $steps,
        "stepsFR" => $stepsFR,
        "timers" => $timers,
        "imageURL" => $imageURL,
        "originalURL" => $originalURL,
        "likes" => [], // Initialize likes
        "comments" => [], // Initialize comments
        "validated" => 0 // New recipes need validation by default
    ];

    // Add new recipe to the array
    $recipes[] = $newRecipe;

    // Save updated recipes back to the JSON file with locking
    $fp = fopen($recipesFile, 'w');
    if ($fp && flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        flock($fp, LOCK_UN); // Release the lock
        fclose($fp);
        // Set success message
        $_SESSION['flash_message'] = "Recipe '$name' created successfully!";
        // Redirect to the index page or the new recipe page
        header("Location: recipe.php?id=" . ($maxId + 1)); // Redirect to the new recipe
        exit;
    } else {
         // Handle file writing error
         if ($fp) fclose($fp); // Close if opened but locking failed
         $_SESSION['form_error'] = "Error saving the recipe. Could not write to file.";
         header("Location: create_recipe.php");
         exit;
         // die("Error saving the recipe. Could not write to file.");
    }
}

// Display potential error message from session
$formError = '';
if (isset($_SESSION['form_error'])) {
    $formError = '<p class="message error">' . htmlspecialchars($_SESSION['form_error']) . '</p>';
    unset($_SESSION['form_error']); // Clear the message after displaying
}


// HTML form for creating a new recipe
// Use data-translate attributes for labels/placeholders if needed
$content = '
<div class="create-recipe-container">
    <h1>Create a New Recipe</h1>
    ' . $formError . ' <!-- Display form error message here -->
    <form method="POST" action="create_recipe.php">

        <label for="name">Recipe Name (English): *</label>
        <input type="text" id="name" name="name" required>

        <label for="nameFR">Recipe Name (French):</label>
        <input type="text" id="nameFR" name="nameFR">

        <div class="checkbox-group">
            <label>Dietary Restrictions:</label>
            <div>
                <input type="checkbox" id="noGluten" name="without[]" value="NoGluten">
                <label for="noGluten">No Gluten</label>
            </div>
            <div>
                <input type="checkbox" id="noMilk" name="without[]" value="NoMilk">
                <label for="noMilk">No Milk</label>
            </div>
            <div>
                <input type="checkbox" id="vegetarian" name="without[]" value="Vegetarian">
                <label for="vegetarian">Vegetarian</label>
            </div>
             <div>
                <input type="checkbox" id="vegan" name="without[]" value="Vegan">
                <label for="vegan">Vegan</label>
            </div>
            <!-- Add more as needed -->
        </div>

        <div class="dynamic-fields-section">
            <label>Ingredients (English): *</label>
            <div id="ingredients-container">
                <!-- Initial empty field -->
                 <div class="dynamic-field">
                    <div class="ingredient">
                        <input type="text" name="ingredients[0][quantity]" placeholder="Quantity" required>
                        <input type="text" name="ingredients[0][name]" placeholder="Ingredient Name" required>
                        <input type="text" name="ingredients[0][type]" placeholder="Type (e.g., Meat)">
                    </div>
                    <button type="button" class="remove-field button button-danger">×</button>
                </div>
            </div>
            <button type="button" id="add-ingredient" class="button button-secondary">Add Ingredient</button>
        </div>

        <div class="dynamic-fields-section">
            <label>Ingredients (French):</label>
            <div id="ingredients-fr-container">
                <!-- Initial empty field -->
                 <div class="dynamic-field">
                    <div class="ingredient">
                        <input type="text" name="ingredientsFR[0][quantity]" placeholder="Quantité">
                        <input type="text" name="ingredientsFR[0][name]" placeholder="Nom ingrédient">
                        <input type="text" name="ingredientsFR[0][type]" placeholder="Type (e.g., Viande)">
                    </div>
                    <button type="button" class="remove-field button button-danger">×</button>
                </div>
            </div>
            <button type="button" id="add-ingredient-fr" class="button button-secondary">Add Ingredient (French)</button>
        </div>

         <div class="dynamic-fields-section">
            <label>Steps (English): *</label>
            <div id="steps-container">
                 <!-- Initial empty field -->
                <div class="dynamic-field">
                    <textarea name="steps[0]" placeholder="Step 1" required></textarea>
                    <button type="button" class="remove-field button button-danger">×</button>
                </div>
            </div>
            <button type="button" id="add-step" class="button button-secondary">Add Step</button>
        </div>

        <div class="dynamic-fields-section">
             <label>Steps (French):</label>
             <div id="steps-fr-container">
                 <!-- Initial empty field -->
                 <div class="dynamic-field">
                    <textarea name="stepsFR[0]" placeholder="Étape 1"></textarea>
                    <button type="button" class="remove-field button button-danger">×</button>
                 </div>
             </div>
             <button type="button" id="add-step-fr" class="button button-secondary">Add Step (French)</button>
        </div>

        <div class="dynamic-fields-section">
            <label>Timers (in minutes, one per step): *</label>
            <div id="timers-container">
                 <!-- Initial empty field -->
                 <div class="dynamic-field">
                     <input type="number" name="timers[0]" placeholder="Timer for Step 1" min="0" required> <!-- Add min="0" -->
                     <button type="button" class="remove-field button button-danger">×</button>
                 </div>
            </div>
            <button type="button" id="add-timer" class="button button-secondary">Add Timer</button>
        </div>


        <label for="imageURL">Image URL:</label>
        <input type="url" id="imageURL" name="imageURL" placeholder="https://example.com/image.jpg">

        <label for="originalURL">Original Recipe URL:</label>
        <input type="url" id="originalURL" name="originalURL" placeholder="https://source-website.com/recipe">

        <p><small>* Required fields</small></p>

        <button type="submit" class="button button-primary" style="width: 100%; margin-top: 20px;">Create Recipe</button>
    </form>
</div>
';

$title = "Create Recipe";
include 'header.php';
?>

<!-- Include the shared dynamic fields script -->
<script src="dynamic_fields.js"></script>

<!-- Specific JS for this page (if any needed beyond dynamic fields) -->
<script>
// Any specific JS for create_recipe page can go here
// For example, maybe some validation logic not covered by HTML5 required attribute
$(document).ready(function() {
    $('form').submit(function() {
        // Example: Ensure at least one ingredient, step, and timer exists beyond the template row
        if ($('#ingredients-container .dynamic-field').length < 1 || $('#steps-container .dynamic-field').length < 1 || $('#timers-container .dynamic-field').length < 1) {
             // Note: HTML required should handle empty fields, this is more for ensuring *at least one*
             // alert("Please add at least one ingredient, step, and timer.");
            // return false; // Prevent submission
        }
        // Ensure number of timers matches number of steps?
         const stepCount = $('#steps-container .dynamic-field').length;
         const timerCount = $('#timers-container .dynamic-field').length;
         if (stepCount !== timerCount) {
             alert(`Number of steps (${stepCount}) must match number of timers (${timerCount}). Add 0 for steps without a timer.`);
             return false; // Prevent submission
         }

        return true; // Allow submission
    });
});
</script>