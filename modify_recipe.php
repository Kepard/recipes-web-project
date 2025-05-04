<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get recipe ID from URL and validate
$recipeId = (int) $_GET['id'];

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php"); // Redirect if not logged in
    exit;
}
$currentUser = $_SESSION['username'];
$currentRole = $_SESSION['role'];


// Load existing recipes
$recipesFile = 'recipes.json';
$recipes = [];
$recipeToModify = null;
$recipeKey = null; // To store the array key/index

if (file_exists($recipesFile)) {
    $recipesData = file_get_contents($recipesFile);
    $recipes = json_decode($recipesData, true);
    if (!is_array($recipes)) {
        $recipes = []; // Reset if JSON is invalid
    } else {
        // Find the recipe to modify using the numeric ID and store its key
        foreach ($recipes as $key => $recipe) {
            // Use loose comparison '==' temporarily if IDs might be strings in JSON, but strict '===' is better if IDs are consistently numbers
             if (isset($recipe['id']) && $recipe['id'] == $recipeId) {
                $recipeToModify = $recipe;
                $recipeKey = $key; // Store the key
                break;
            }
        }
    }
}

// If recipe not found or ID invalid
if (!$recipeToModify || $recipeId === null) {
     // Set flash message and redirect
     $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Recipe not found or invalid ID.'];
     header("Location: index.php");
     exit;
    // die("Recipe not found or invalid ID.");
}

// --- Permission Check ---
$isAuthor = isset($recipeToModify['Author']) && $recipeToModify['Author'] === $currentUser;
$isAdmin = $currentRole === 'Administrateur';

if (!$isAdmin && !$isAuthor) {
     $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'You do not have permission to modify this recipe.'];
     header("Location: recipe.php?id=" . $recipeId); // Redirect back to recipe page
     exit;
    // die("You do not have permission to modify this recipe.");
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate and sanitize input data (similar to create_recipe)
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

    // Update the recipe data IN PLACE using the stored key
    $recipes[$recipeKey] = [
        "id" => $recipeId, // Keep original ID
        "name" => $name,
        "nameFR" => $nameFR,
        "Author" => $recipeToModify['Author'], // Keep original author
        "Without" => $without,
        "ingredients" => $ingredients,
        "ingredientsFR" => $ingredientsFR,
        "steps" => $steps,
        "stepsFR" => $stepsFR,
        "timers" => $timers,
        "imageURL" => $imageURL,
        "originalURL" => $originalURL,
        "likes" => $recipeToModify['likes'] ?? [], // Preserve existing likes
        "comments" => $recipeToModify['comments'] ?? [], // Preserve existing comments
        "validated" => ($isAdmin) ? 1 : 0 // Default validation to 0, but if the admin modifies the recipe its automatically validated
    ];

    // Save updated recipes back to the JSON file 
    file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    header("Location: recipe.php?id=" . $recipeId); // Redirect back to recipe page
}


// --- Function to generate dynamic fields populated with existing data ---
function generateDynamicFields($data, $type, $lang = '') {
    $fields = '';
    $namePrefix = $type . ($lang ? 'FR' : ''); // Corrected prefix generation

    if (!empty($data) && is_array($data)) {
        foreach ($data as $index => $item) {
        // Ajoute l'attribut data-sync-type basé sur le $type ('ingredients', 'steps', 'timers')
        $syncType = ($type === 'ingredients' || $type === 'steps') ? strtolower(rtrim($type, 's')) : 'timer'; // Simplifie 'ingredients'->'ingredient', 'steps'->'step', 'timers'->'timer'
        $removeButton = '<button type="button" class="remove-field button button-danger" data-sync-type="' . $syncType . '">×</button>';            $fieldContent = '';

            if ($type === 'ingredients') {
                 $item = (array) $item; // Ensure item is an array
                 $quantity = htmlspecialchars($item['quantity'] ?? '');
                 $name = htmlspecialchars($item['name'] ?? '');
                 $type = htmlspecialchars($item['type'] ?? ''); 
                 $placeholderQty = ($lang === 'FR' ? 'Quantité' : 'Quantity');
                 $placeholderName = ($lang === 'FR' ? 'Nom ingrédient' : 'Ingredient Name');
                 $placeholderType = ($lang === 'FR' ? 'Type' : 'Type');

                 $fieldContent = '
                    <div class="ingredient">
                        <input type="text" name="' . $namePrefix . '[' . $index . '][quantity]" value="' . $quantity . '" placeholder="'.$placeholderQty.'">
                        <input type="text" name="' . $namePrefix . '[' . $index . '][name]" value="' . $name . '" placeholder="'.$placeholderName.'">
                        <input type="text" name="' . $namePrefix . '[' . $index . '][type]" value="' . $type . '" placeholder="'.$placeholderType.'">
                    </div>';
            } elseif ($type === 'steps') {
                $stepText = htmlspecialchars($item ?? '');
                $placeholder = ($lang === 'FR' ? 'Étape ' : 'Step ') . ($index + 1);
                $fieldContent = '<textarea name="' . $namePrefix . '[' . $index . ']" placeholder="' . $placeholder . '">' . $stepText . '</textarea>';
            } elseif ($type === 'timers') {
                $timerValue = htmlspecialchars($item ?? '');
                $placeholder = ($lang === 'FR' ? 'Minuteur Étape ' : 'Timer Step ') . ($index + 1);
                $fieldContent = '<input type="number" name="' . $namePrefix . '[' . $index . ']" value="' . $timerValue . '" placeholder="' . $placeholder . '" min="0">';
            }

            if (!empty($fieldContent)) {
                 $fields .= '<div class="dynamic-field">' . $fieldContent . $removeButton . '</div>';
            }
        }
    }

     // If $fields is still empty after the loop (meaning $data was empty or invalid), add one empty field
     if (empty($fields)) {
         $removeButton = '<button type="button" class="remove-field button button-danger">×</button>';
         $fieldContent = '';
         $index = 0; // Start with index 0 for the empty field
         if ($type === 'ingredients') {
             $placeholderQty = ($lang === 'FR' ? 'Quantité' : 'Quantity');
             $placeholderName = ($lang === 'FR' ? 'Nom ingrédient' : 'Ingredient Name');
             $placeholderType = ($lang === 'FR' ? 'Type' : 'Type');
             $fieldContent = '
                <div class="ingredient">
                    <input type="text" name="' . $namePrefix . '[0][quantity]" placeholder="'.$placeholderQty.'">
                    <input type="text" name="' . $namePrefix . '[0][name]" placeholder="'.$placeholderName.'">
                    <input type="text" name="' . $namePrefix . '[0][type]" placeholder="'.$placeholderType.'">
                </div>';
         } elseif ($type === 'steps') {
             $placeholder = ($lang === 'FR' ? 'Étape 1' : 'Step 1');
             $fieldContent = '<textarea name="' . $namePrefix . '[0]" placeholder="' . $placeholder . '"></textarea>';
         } elseif ($type === 'timers') {
             $placeholder = ($lang === 'FR' ? 'Minuteur Étape 1' : 'Timer Step 1');
             $fieldContent = '<input type="number" name="' . $namePrefix . '[0]" placeholder="' . $placeholder . '" min="0">';
         }
          if (!empty($fieldContent)) {
             $fields .= '<div class="dynamic-field">' . $fieldContent . $removeButton . '</div>';
          }
     }


    return $fields;
}


// HTML form for modifying the recipe, pre-filled with data
$content = '
<div class="modify-recipe-container">
    <h1>Modify Recipe: ' . htmlspecialchars($recipeToModify['name']) . '</h1>
    <form method="POST" action="modify_recipe.php?id=' . $recipeId . '">

        <label for="name">Recipe Name (English): *</label>
        <input type="text" id="name" name="name" value="' . htmlspecialchars($recipeToModify['name'] ?? '') . '" required>

        <label for="nameFR">Recipe Name (French):</label>
        <input type="text" id="nameFR" name="nameFR" value="' . htmlspecialchars($recipeToModify['nameFR'] ?? '') . '">

        <div class="checkbox-group">
            <label>Dietary Restrictions:</label>';
            // Define possible restrictions
            $allRestrictions = ['NoGluten', 'NoMilk', 'Vegetarian', 'Vegan']; // Add more if needed
            $currentRestrictions = $recipeToModify['Without'] ?? [];
            foreach ($allRestrictions as $restriction) {
                $checked = in_array($restriction, $currentRestrictions) ? 'checked' : '';
                $content .= '
                <div>
                    <input type="checkbox" id="' . strtolower($restriction) . '" name="without[]" value="' . $restriction . '" ' . $checked . '>
                    <label for="' . strtolower($restriction) . '">' . $restriction . '</label> <!-- Simple label, translation can be added -->
                </div>';
            }
$content .= '
        </div>

         <div class="dynamic-fields-section">
            <label>Ingredients (English): *</label>
            <div id="ingredients-container">' .
                generateDynamicFields($recipeToModify['ingredients'] ?? [], 'ingredients') . '
            </div>
            <button type="button" id="add-ingredient" class="button button-secondary">Add Ingredient</button>
        </div>

        <div class="dynamic-fields-section">
            <label>Ingredients (French):</label>
            <div id="ingredients-fr-container">' .
                generateDynamicFields($recipeToModify['ingredientsFR'] ?? [], 'ingredients', 'FR') . '
            </div>
            <button type="button" id="add-ingredient-fr" class="button button-secondary">Add Ingredient (French)</button>
        </div>

         <div class="dynamic-fields-section">
            <label>Steps (English): *</label>
            <div id="steps-container">' .
                generateDynamicFields($recipeToModify['steps'] ?? [], 'steps') . '
            </div>
            <button type="button" id="add-step" class="button button-secondary">Add Step</button>
        </div>

        <div class="dynamic-fields-section">
             <label>Steps (French):</label>
             <div id="steps-fr-container">' .
                 generateDynamicFields($recipeToModify['stepsFR'] ?? [], 'steps', 'FR') . '
             </div>
             <button type="button" id="add-step-fr" class="button button-secondary">Add Step (French)</button>
        </div>

         <div class="dynamic-fields-section">
            <label>Timers (in minutes, one per step): *</label>
            <div id="timers-container">' .
                generateDynamicFields($recipeToModify['timers'] ?? [], 'timers') . '
            </div>
            <button type="button" id="add-timer" class="button button-secondary">Add Timer</button>
        </div>


        <label for="imageURL">Image URL:</label>
        <input type="url" id="imageURL" name="imageURL" value="' . htmlspecialchars($recipeToModify['imageURL'] ?? '') . '">

        <label for="originalURL">Original Recipe URL:</label>
        <input type="url" id="originalURL" name="originalURL" value="' . htmlspecialchars($recipeToModify['originalURL'] ?? '') . '">

         <p><small>* Required fields</small></p>

        <button type="submit" class="button button-primary" style="width: 100%; margin-top: 20px;">Save Changes</button>
    </form>
</div>
';

$title = "Modify Recipe";
include 'header.php';
?>

<!-- Include the shared dynamic fields script -->
<script src="dynamic_fields.js"></script>

<!-- Specific JS for modify page -->
<script>
$(document).ready(function() {
    // Add any specific JS validation or behavior for the modify page here
    $('form').submit(function() {
         // Example validation: Ensure number of timers matches number of steps
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