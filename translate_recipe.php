<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate recipe ID
$recipeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}
$currentUser = $_SESSION['username'];
$currentRole = $_SESSION['role'];

// Load recipes from JSON
$recipesFile = 'recipes.json';
$recipes = [];
$recipe = null;
$recipeIndex = null; // Use index for updating

if ($recipeId && file_exists($recipesFile)) {
    $recipesData = file_get_contents($recipesFile);
    $recipes = json_decode($recipesData, true);

    if (is_array($recipes)) {
        // Find the recipe and its index
        foreach ($recipes as $index => $r) {
            // Use loose comparison temporarily if IDs might be strings
             if (isset($r['id']) && $r['id'] == $recipeId) {
                $recipeIndex = $index;
                $recipe = $r;
                break;
            }
        }
    } else {
         $recipes = []; // Reset if JSON invalid
    }
}

// If recipe not found or ID invalid
if ($recipe === null || $recipeIndex === null) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Recipe not found or invalid ID.'];
    header('Location: index.php');
    exit;
}

// Check permissions: Translator OR Chef who is the Author OR Admin
$isAuthor = isset($recipe['Author']) && $recipe['Author'] === $currentUser;
$isTranslator = $currentRole === 'Traducteur';
$isAdmin = $currentRole === 'Administrateur';
$isChef = $currentRole === 'Chef';

$allowed = $isTranslator || ($isChef && $isAuthor) || $isAdmin;

if (!$allowed) {
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'You do not have permission to translate this recipe.'];
    header('Location: recipe.php?id=' . $recipeId);
    exit;
}

// --- Helper function to split quantity (value and unit/label) ---
function splitQuantityLabel($quantity) {
    $quantity = trim($quantity);
    // Matches numbers, fractions, decimals at the start, captures rest as label
    if (preg_match('/^([\d\s\.\/]+)(.*)$/', $quantity, $matches)) {
        // Further trim the label part
        return ['value' => trim($matches[1]), 'label' => trim($matches[2])];
    }
    // If no numeric part found, assume the whole string is the label (e.g., "Pinch")
    return ['value' => '', 'label' => $quantity];
}


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // --- Permission Check Again ---
     if (!$allowed) { die("Permission denied."); }

    // Update French fields if they exist in POST data
    $recipe['nameFR'] = trim(htmlspecialchars($_POST['nameFR'] ?? $recipe['nameFR'] ?? ''));

    // Update Ingredients FR
    if (isset($_POST['ingredientsFR']) && is_array($_POST['ingredientsFR'])) {
        $updatedIngredientsFR = [];
        foreach ($_POST['ingredientsFR'] as $index => $postedIngredient) {
             // Ensure the original English ingredient exists at this index
            if (isset($recipe['ingredients'][$index])) {
                 $originalQuantityValue = splitQuantityLabel($recipe['ingredients'][$index]['quantity'] ?? '')['value'];
                 $translatedUnit = trim(htmlspecialchars($postedIngredient['quantity'] ?? '')); // Only the unit/label part is submitted for translation
                 $translatedName = trim(htmlspecialchars($postedIngredient['name'] ?? ''));
                 $translatedType = trim(htmlspecialchars($postedIngredient['type'] ?? ''));

                 // Combine original value with translated unit/label
                 $finalTranslatedQuantity = trim($originalQuantityValue . ' ' . $translatedUnit);

                 $updatedIngredientsFR[$index] = [
                     'quantity' => $finalTranslatedQuantity,
                     'name' => $translatedName,
                     'type' => $translatedType
                 ];
            }
        }
         // Ensure the count matches the original English ingredients count
         if(count($updatedIngredientsFR) === count($recipe['ingredients'] ?? [])) {
             $recipe['ingredientsFR'] = $updatedIngredientsFR;
         } else {
              // Handle mismatch error - maybe log it, show message
              $_SESSION['form_error'] = "Ingredient count mismatch during translation.";
              // Don't update ingredientsFR if counts don't match
         }
    }

    // Update Steps FR
    if (isset($_POST['stepsFR']) && is_array($_POST['stepsFR'])) {
         $updatedStepsFR = [];
         foreach ($_POST['stepsFR'] as $index => $value) {
             // Ensure the original English step exists at this index
             if (isset($recipe['steps'][$index])) {
                $updatedStepsFR[$index] = trim(htmlspecialchars($value));
             }
         }
          // Ensure the count matches the original English steps count
         if(count($updatedStepsFR) === count($recipe['steps'] ?? [])) {
             $recipe['stepsFR'] = $updatedStepsFR;
         } else {
              $_SESSION['form_error'] = "Steps count mismatch during translation.";
              // Don't update stepsFR if counts don't match
         }
    }

     // If there was a count mismatch error, redirect back to form
     if (isset($_SESSION['form_error'])) {
         header('Location: translate_recipe.php?id=' . $recipeId);
         exit;
     }


    // --- Save the updated recipe data ---
    $recipes[$recipeIndex] = $recipe; // Update the recipe in the main array using its index

    $fp = fopen($recipesFile, 'w');
    if ($fp && flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        flock($fp, LOCK_UN);
        fclose($fp);
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Translation saved successfully!'];
        header('Location: recipe.php?id=' . $recipeId);
        exit;
    } else {
        if ($fp) fclose($fp);
        $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error saving translation. Could not write to file.'];
        header('Location: translate_recipe.php?id=' . $recipeId);
        exit;
    }
}

// Display potential error message from session
$formError = '';
if (isset($_SESSION['form_error'])) {
    $formError = '<p class="message error">' . htmlspecialchars($_SESSION['form_error']) . '</p>';
    unset($_SESSION['form_error']); // Clear the message
}

// --- Generate HTML Content ---
$content = '
<div class="translation-container">
    <h1>Translate Recipe: ' . htmlspecialchars($recipe['name'] ?? 'N/A') . '</h1>
    ' . $formError . ' <!-- Display form error message here -->

    <form method="POST" id="translation-form">
        <div class="translation-columns">

            <!-- Original (English) Column -->
            <div class="translation-column original-column">
                <h2>Original (English)</h2>

                <div class="form-group">
                    <label>Recipe Name:</label>
                    <div class="original-field">' . htmlspecialchars($recipe['name'] ?? '') . '</div>
                </div>

                <div class="form-group">
                    <label>Ingredients:</label>';
                    if (!empty($recipe['ingredients']) && is_array($recipe['ingredients'])) {
                        foreach ($recipe['ingredients'] as $index => $ingredient) {
                            $ingredient = (array) $ingredient; // Ensure array access
                            $quantityParts = splitQuantityLabel($ingredient['quantity'] ?? '');
                            $content .= '
                            <div class="translation-row">
                                <div class="original-field quantity-value">' . htmlspecialchars($quantityParts['value']) . '</div>
                                <div class="original-field quantity-unit">' . htmlspecialchars($quantityParts['label']) . '</div>
                                <div class="original-field">' . htmlspecialchars($ingredient['name'] ?? '') . '</div>
                                <div class="original-field">' . htmlspecialchars($ingredient['type'] ?? '') . '</div>
                            </div>';
                        }
                    } else {
                         $content .= '<p>No English ingredients found.</p>';
                    }
$content .= '
                </div>

                <div class="form-group">
                    <label>Steps:</label>';
                     if (!empty($recipe['steps']) && is_array($recipe['steps'])) {
                        foreach ($recipe['steps'] as $index => $step) {
                            $content .= '
                            <div class="translation-row">
                                <div class="original-field">' . htmlspecialchars($step ?? '') . '</div>
                            </div>';
                        }
                    } else {
                         $content .= '<p>No English steps found.</p>';
                    }
$content .= '
                </div>
            </div>

            <!-- Translation (French) Column -->
            <div class="translation-column translation-form-column">
                <h2>Translation (Français)</h2>

                 <div class="form-group">
                    <label for="nameFR">Nom de la recette:</label>
                    <input type="text" id="nameFR" name="nameFR" value="' . htmlspecialchars($recipe['nameFR'] ?? '') . '" placeholder="Translate name here...">
                </div>

                <div class="form-group">
                    <label>Ingrédients:</label>';
                     // Iterate based on English ingredients to ensure structure matches
                     if (!empty($recipe['ingredients']) && is_array($recipe['ingredients'])) {
                        foreach ($recipe['ingredients'] as $index => $ingredientEN) {
                             $ingredientEN = (array) $ingredientEN; // Ensure array access
                             // Get corresponding French data, default to empty if not set
                             $ingredientFR = (array) ($recipe['ingredientsFR'][$index] ?? []);

                             $quantityPartsEN = splitQuantityLabel($ingredientEN['quantity'] ?? '');
                             // For French quantity, we only care about the label/unit part for translation input
                             $quantityPartsFR = splitQuantityLabel($ingredientFR['quantity'] ?? '');


                            $content .= '
                            <div class="translation-row">
                                 <!-- Readonly English value -->
                                <input type="text" class="quantity-value" value="' . htmlspecialchars($quantityPartsEN['value']) . '" readonly title="Original Quantity Value (Readonly)">

                                <!-- Editable French unit/label -->
                                <input type="text" name="ingredientsFR[' . $index . '][quantity]"
                                       value="' . htmlspecialchars($quantityPartsFR['label']) . '" placeholder="Unité/Label (ex: tasse)" class="quantity-unit" title="Translate Unit/Label">

                                <!-- Editable French name -->
                                <input type="text" name="ingredientsFR[' . $index . '][name]"
                                       value="' . htmlspecialchars($ingredientFR['name'] ?? '') . '" placeholder="Nom ingrédient" title="Translate Ingredient Name">

                                <!-- Editable French type -->
                                <input type="text" name="ingredientsFR[' . $index . '][type]"
                                       value="' . htmlspecialchars($ingredientFR['type'] ?? '') . '" placeholder="Type" title="Translate Ingredient Type">
                            </div>';
                        }
                    } else {
                         $content .= '<p>Cannot translate ingredients without English source.</p>';
                    }
$content .= '
                </div>

                 <div class="form-group">
                    <label>Étapes:</label>';
                    // Iterate based on English steps
                     if (!empty($recipe['steps']) && is_array($recipe['steps'])) {
                         foreach ($recipe['steps'] as $index => $stepEN) {
                            // Get corresponding French data, default to empty
                            $stepFR = $recipe['stepsFR'][$index] ?? '';
                            $content .= '
                            <div class="translation-row">
                                <textarea name="stepsFR[' . $index . ']" placeholder="Traduire l\'étape ' . ($index + 1) . '">' . htmlspecialchars($stepFR) . '</textarea>
                            </div>';
                        }
                    } else {
                         $content .= '<p>Cannot translate steps without English source.</p>';
                    }
$content .= '
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button-primary btn-save">Save Translation</button>
            <a href="recipe.php?id=' . $recipeId . '" class="button button-secondary btn-cancel">Cancel</a>
        </div>
    </form>
</div>';

$title = "Translate Recipe";
include 'header.php';
?>

<script>
// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {
     // Optional: Translate any static text specific to this page if needed
     // Example: $('some_element').text(translations.labels?.some_key);
 }

$(document).ready(function() {
    // --- Form Validation (Optional client-side check) ---
    $('#translation-form').submit(function(e) {
        let hasContent = false;
        let ingredientCountEN = $('.original-column .form-group:nth-child(2) .translation-row').length; // Count EN ingredients rows
        let stepCountEN = $('.original-column .form-group:nth-child(3) .translation-row').length; // Count EN steps rows

        let ingredientCountFR = 0;
        let stepCountFR = 0;

        // Check if any French field has content
        $('input[name="nameFR"], textarea[name^="stepsFR"], input[name^="ingredientsFR"]').each(function() {
             if ($(this).val().trim() !== '') {
                 hasContent = true;
            }
             // Count non-empty French fields (could be more specific if needed)
             if ($(this).attr('name').startsWith('ingredientsFR') && $(this).closest('.translation-row').find('input[name$="[name]"]').val().trim() !== '') {
                 // Count only if the name field in the row is filled
                 // This logic might need refinement based on how you count 'filled' ingredients
             }
             if ($(this).attr('name').startsWith('stepsFR') && $(this).val().trim() !== '') {
                  stepCountFR++;
             }
        });

        // Basic check if anything was filled
        if (!hasContent) {
            // Use showMessage if available from header.php
            if(typeof showMessage === 'function') {
                showMessage("Please fill in at least one translation field.", 'error');
            } else {
                alert("Please fill in at least one translation field.");
            }
            e.preventDefault(); // Prevent submission
            return false;
        }

         // Optional: Basic count check (Backend check is more reliable)
         /*
         if (ingredientCountFR !== ingredientCountEN || stepCountFR !== stepCountEN) {
              if(typeof showMessage === 'function') {
                  showMessage("Warning: Number of translated items might not match the original.", 'error');
              } else {
                  alert("Warning: Number of translated items might not match the original.");
              }
              // Decide whether to prevent submission or just warn
              // e.preventDefault();
              // return false;
         }
         */

        return true; // Allow submission
    });

    // --- Highlight Changes ---
    $('.translation-form-column input, .translation-form-column textarea').on('input', function() {
        // Add 'changed' class if value is not empty, remove if empty
        $(this).toggleClass('changed', $(this).val().trim() !== '');
    });
});
</script>