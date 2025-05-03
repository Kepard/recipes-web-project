<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate recipe ID
$recipeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$currentUser = $_SESSION['username'];
$currentRole = $_SESSION['role'];

if (!isset($currentUser)){
    header('Location: index.php');
    exit;
}

// Load recipes from JSON
$recipesFile = 'recipes.json';
$recipes = [];
$recipe = null;
$recipeIndex = null; // Use index for updating

if ($recipeId && file_exists($recipesFile)) {
    $recipesData = file_get_contents($recipesFile);
    $recipes = json_decode($recipesData, true);

    if (is_array($recipes)) {
        foreach ($recipes as $index => $r) {
             if (isset($r['id']) && $r['id'] == $recipeId) {
                $recipeIndex = $index;
                $recipe = $r;
                break;
            }
        }
    }
}

if ($recipe === null || $recipeIndex === null) {
    // Use flash message for redirection
    $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Recipe not found or invalid ID.']; // Use key later
    header('Location: index.php');
    exit;
}


// --- Permission Checks ---
$isAuthor = isset($recipe['Author']) && $recipe['Author'] === $currentUser;
$isTranslator = $currentRole === 'Traducteur';
$isAdmin = $currentRole === 'Administrateur';
$isChef = $currentRole === 'Chef';
$isAllowedEditor = ($isChef && $isAuthor) || $isAdmin;
$canAccessPage = $isTranslator || $isAllowedEditor;

if (!$canAccessPage) {
    header('HTTP/1.1 403 Forbidden');
    die();
}


// --- Helper function ---
function splitQuantityLabel($quantity) {
    $quantity = trim($quantity);
    if (preg_match('/^([\d\s\.\/]+)(.*)$/', $quantity, $matches)) {
        return ['value' => trim($matches[1]), 'label' => trim($matches[2])];
    }
    return ['value' => '', 'label' => $quantity];
}


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipeBeforeUpdate = $recipes[$recipeIndex];

    // Update Name FR
    $postedNameFR = trim(htmlspecialchars($_POST['nameFR'] ?? ''));
    $recipe['nameFR'] = $postedNameFR;

    // Update Ingredients FR
    if (isset($_POST['ingredientsFR']) && is_array($_POST['ingredientsFR'])) {
        $originalIngredients = $recipeBeforeUpdate['ingredients'] ?? [];
        $originalIngredientsFR = $recipeBeforeUpdate['ingredientsFR'] ?? [];
        $newIngredientsFR = $recipe['ingredientsFR'] ?? [];

        if (count($_POST['ingredientsFR']) === count($originalIngredients)) {
            foreach ($_POST['ingredientsFR'] as $index => $postedIngredient) {
                 if (!isset($originalIngredients[$index])) continue;
                 $originalENIngredient = (array) $originalIngredients[$index];
                 $originalFRIngredient = (array) ($originalIngredientsFR[$index] ?? []);
                 $postedUnitLabel = trim(htmlspecialchars($postedIngredient['quantity'] ?? ''));
                 $postedName = trim(htmlspecialchars($postedIngredient['name'] ?? ''));
                 $postedType = trim(htmlspecialchars($postedIngredient['type'] ?? ''));
                 $originalQuantityValue = splitQuantityLabel($originalENIngredient['quantity'] ?? '')['value'];
                 $finalTranslatedQuantity = $originalFRIngredient['quantity'] ?? '';
                 $finalTranslatedName = $originalFRIngredient['name'] ?? '';
                 $finalTranslatedType = $originalFRIngredient['type'] ?? '';
                 $finalTranslatedQuantity = trim($originalQuantityValue . ' ' . $postedUnitLabel);
                 $finalTranslatedName = $postedName;
                 $finalTranslatedType = $postedType;
                 $newIngredientsFR[$index] = ['quantity' => $finalTranslatedQuantity, 'name' => $finalTranslatedName, 'type' => $finalTranslatedType ];
            }
            $recipe['ingredientsFR'] = $newIngredientsFR;
        } 
    }

    // Update Steps FR
    if (isset($_POST['stepsFR']) && is_array($_POST['stepsFR'])) { 
        $originalSteps = $recipeBeforeUpdate['steps'] ?? [];
        $originalStepsFR = $recipeBeforeUpdate['stepsFR'] ?? [];
        $newStepsFR = $recipe['stepsFR'] ?? [];

        if (count($_POST['stepsFR']) === count($originalSteps)) {
            foreach ($_POST['stepsFR'] as $index => $postedValue) {
                 if (!isset($originalSteps[$index])) continue;
                 $originalENStep = trim($originalSteps[$index] ?? '');
                 $originalFRStep = trim($originalStepsFR[$index] ?? '');
                 $postedStep = trim(htmlspecialchars($postedValue));
                 $newStepsFR[$index] = $postedStep;
            }
            $recipe['stepsFR'] = $newStepsFR;
        }
    }

    // --- Save the updated recipe data ---
    $recipes[$recipeIndex] = $recipe;

    file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    header("Location: recipe.php?id=" . $recipeId); // Redirect back to recipe page
}


// --- Generate HTML Content ---
$recipeNamePHP = htmlspecialchars($recipe['name']); // Store recipe name for JS

$content = '
<div class="translation-container">
    <!-- MODIFIED H1 Structure -->
    <h1>
        <span data-translate="labels.translate_recipe_title_base">Translate Recipe</span>:
        <span id="recipe-name-display">' . $recipeNamePHP . '</span>
    </h1>

    <form method="POST" id="translation-form">
        <div class="translation-columns">

            <!-- Original (English) Column -->
            <div class="translation-column original-column">
                <h2 data-translate="labels.original_english">Original (English)</h2>
                ' . ($isTranslator ? '<p><small data-translate="messages.translator_readonly_hint_base">Read-only source text.</small></p>' : '') . '

                <div class="form-group">
                    <label data-translate="labels.recipe_name">Recipe Name:</label>
                    <div class="original-field">' . htmlspecialchars($recipe['name'] ?? '') . '</div>
                </div>

                <div class="form-group">
                    <label data-translate="labels.ingredients">Ingredients:</label>';
                    if (!empty($recipe['ingredients']) && is_array($recipe['ingredients'])) {
                        foreach ($recipe['ingredients'] as $index => $ingredient) {
                            $ingredient = (array) $ingredient;
                            $quantityParts = splitQuantityLabel($ingredient['quantity'] ?? '');
                            $content .= '
                            <div class="translation-row">
                                <div class="original-field quantity-value" title="Original Quantity Value">' . htmlspecialchars($quantityParts['value']) . '</div>
                                <div class="original-field quantity-unit" title="Original Unit/Label">' . htmlspecialchars($quantityParts['label']) . '</div>
                                <div class="original-field" title="Original Name">' . htmlspecialchars($ingredient['name'] ?? '') . '</div>
                                <div class="original-field" title="Original Type">' . htmlspecialchars($ingredient['type'] ?? '') . '</div>
                            </div>';
                        }
                    } else { $content .= '<p data-translate="messages.no_en_ingredients">No English ingredients found.</p>'; }
$content .= '
                </div>

                <div class="form-group">
                    <label data-translate="labels.steps">Steps:</label>';
                     if (!empty($recipe['steps']) && is_array($recipe['steps'])) {
                        foreach ($recipe['steps'] as $index => $step) {
                            $content .= '
                            <div class="translation-row">
                                <div class="original-field">' . htmlspecialchars($step ?? '') . '</div>
                            </div>';
                        }
                    } else { $content .= '<p data-translate="messages.no_en_steps">No English steps found.</p>'; }
$content .= '
                </div>
            </div>

            <!-- Translation (French) Column -->
            <div class="translation-column translation-form-column">
                <h2 data-translate="labels.translation_french">Translation (Français)</h2>
                <p><small data-translate="messages.translator_edit_hint">Edit only empty fields where English source exists.</small></p>
                 <div class="form-group">';
                    $nameEN = trim($recipe['name'] ?? '');
                    $nameFR = trim($recipe['nameFR'] ?? '');
                    $nameReadonly = $isTranslator && (!empty($nameFR) || empty($nameEN));
                    $nameTitleKey = $nameReadonly ? 'messages.translator_readonly_hint' : 'placeholders.translate_name';
                    $nameClass = $nameReadonly ? 'readonly-translator' : '';
                    $content .= '
                    <label for="nameFR" data-translate="labels.recipe_name">Nom de la recette:</label>
                    <input type="text" id="nameFR" name="nameFR" value="' . htmlspecialchars($nameFR) . '"
                           data-translate-placeholder="placeholders.translate_name" data-translate-title="' . $nameTitleKey . '"
                           ' . ($nameReadonly ? 'readonly' : '') . ' class="' . $nameClass . '">';
$content .= '
                </div>

                <div class="form-group">
                    <label data-translate="labels.ingredients">Ingrédients:</label>';
                     if (!empty($recipe['ingredients']) && is_array($recipe['ingredients'])) {
                        foreach ($recipe['ingredients'] as $index => $ingredientEN) {
                             $ingredientEN = (array) $ingredientEN;
                             $ingredientFR = (array) ($recipe['ingredientsFR'][$index] ?? []);
                             $quantityPartsEN = splitQuantityLabel($ingredientEN['quantity'] ?? '');
                             $quantityPartsFR = splitQuantityLabel($ingredientFR['quantity'] ?? '');

                             $enQtyFilled = !empty(trim($ingredientEN['quantity'] ?? ''));
                             $enNameFilled = !empty(trim($ingredientEN['name'] ?? ''));
                             $enTypeFilled = !empty(trim($ingredientEN['type'] ?? '')); // Consider type filled if EN type exists
                             $frUnitLabelEmpty = empty(trim($quantityPartsFR['label']));
                             $frNameEmpty = empty(trim($ingredientFR['name'] ?? ''));
                             $frTypeEmpty = empty(trim($ingredientFR['type'] ?? ''));

                             $unitReadonly = $isTranslator && (!$frUnitLabelEmpty || !$enQtyFilled);
                             $nameReadonly = $isTranslator && (!$frNameEmpty || !$enNameFilled);
                             $typeReadonly = $isTranslator && (!$frTypeEmpty || !$enTypeFilled); // Allow editing type even if EN type is empty?

                             $unitTitleKey = $unitReadonly ? 'messages.translator_readonly_hint' : 'placeholders.translate_unit';
                             $nameTitleKey = $nameReadonly ? 'messages.translator_readonly_hint' : 'placeholders.translate_ing_name';
                             $typeTitleKey = $typeReadonly ? 'messages.translator_readonly_hint' : 'placeholders.translate_ing_type';

                            $content .= '
                            <div class="translation-row">
                                <input type="text" class="quantity-value" value="' . htmlspecialchars($quantityPartsEN['value']) . '" readonly title="Original Quantity Value (Readonly)">
                                <input type="text" name="ingredientsFR[' . $index . '][quantity]" value="' . htmlspecialchars($quantityPartsFR['label']) . '"
                                       data-translate-placeholder="placeholders.translate_unit" data-translate-title="' . $unitTitleKey . '"
                                       class="quantity-unit ' . ($unitReadonly ? 'readonly-translator' : '') . '" ' . ($unitReadonly ? 'readonly' : '') . '>
                                <input type="text" name="ingredientsFR[' . $index . '][name]" value="' . htmlspecialchars($ingredientFR['name'] ?? '') . '"
                                       data-translate-placeholder="placeholders.translate_ing_name" data-translate-title="' . $nameTitleKey . '"
                                       ' . ($nameReadonly ? 'readonly' : '') . ' class="' . ($nameReadonly ? 'readonly-translator' : '') . '">
                                <input type="text" name="ingredientsFR[' . $index . '][type]" value="' . htmlspecialchars($ingredientFR['type'] ?? '') . '"
                                       data-translate-placeholder="placeholders.translate_ing_type" data-translate-title="' . $typeTitleKey . '"
                                       ' . ($typeReadonly ? 'readonly' : '') . ' class="' . ($typeReadonly ? 'readonly-translator' : '') . '">
                            </div>';
                        }
                    } else { $content .= '<p data-translate="messages.cannot_translate_no_source">Cannot translate ingredients without English source.</p>'; }
$content .= '
                </div>

                 <div class="form-group">
                    <label data-translate="labels.steps">Étapes:</label>';
                     if (!empty($recipe['steps']) && is_array($recipe['steps'])) {
                         foreach ($recipe['steps'] as $index => $stepEN) {
                            $stepEN = trim($stepEN ?? '');
                            $stepFR = trim($recipe['stepsFR'][$index] ?? '');
                            $stepReadonly = $isTranslator && (!empty($stepFR) || empty($stepEN));
                            $stepTitleKey = $stepReadonly ? 'messages.translator_readonly_hint' : 'placeholders.translate_step_n'; // Use dynamic placeholder key
                            $stepPlaceholderKey = 'placeholders.translate_step_n';
                            $stepClass = $stepReadonly ? 'readonly-translator' : '';

                            $content .= '
                            <div class="translation-row">
                                <textarea name="stepsFR[' . $index . ']" data-translate-placeholder="' . $stepPlaceholderKey . '" data-placeholder-index="' . ($index + 1) . '"
                                          data-translate-title="' . $stepTitleKey . '" ' . ($stepReadonly ? 'readonly' : '') . ' class="' . $stepClass . '">'
                                          . htmlspecialchars($stepFR) .
                                '</textarea>
                            </div>';
                        }
                    } else { $content .= '<p data-translate="messages.cannot_translate_no_source">Cannot translate steps without English source.</p>'; }
$content .= '
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button-primary btn-save" data-translate="buttons.save_translation">Save Translation</button>
            <a href="recipe.php?id=' . $recipeId . '" class="button button-secondary btn-cancel" data-translate="buttons.cancel">Cancel</a>
        </div>
    </form>
</div>';

$title = "Translate Recipe"; 
include 'header.php';
?>

<script>
// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {
     // Translate the error message placeholder if it exists
     const $errorMsg = $('.message.error[data-translate-key]');
     if ($errorMsg.length) {
         const key = $errorMsg.data('translate-key');
         const type = $errorMsg.data('translate-type');
         let errorText = getNestedTranslation(translations, key);
         if (type) {
             errorText = errorText.replace('{type}', type);
         }
         $errorMsg.text(errorText); // Set the translated text
     }

      // Translate dynamic placeholders like "Translate step {n}"
      $('[data-translate-placeholder][data-placeholder-index]').each(function() {
            const $el = $(this);
            const key = $el.data('translate-placeholder');
            const index = $el.data('placeholder-index');
            let placeholderText = getNestedTranslation(translations, key);
            placeholderText = placeholderText.replace('{n}', index);
            $el.attr('placeholder', placeholderText);
      });

      // Translate dynamic titles like the readonly hint
       $('[data-translate-title]').each(function() {
            const $el = $(this);
            const key = $el.data('translate-title');
            const titleText = getNestedTranslation(translations, key);
            $el.attr('title', titleText);
       });


 }

$(document).ready(function() {
    // --- Form Validation ---
    $('#translation-form').submit(function(e) {
        let hasContent = false;
        const selector = '.translation-form-column input:not([readonly]), .translation-form-column textarea:not([readonly])';

        $(selector).each(function() {
             if ($(this).val().trim() !== '') {
                 hasContent = true;
                 return false; // Exit loop
             }
         });

        if (!hasContent) {
            if(typeof showMessage === 'function' && typeof currentTranslations !== 'undefined') {
                 msg = currentTranslations.messages?.translation_missing_fields;
                 showMessage(msg, 'error');
             } else { alert(msg); }
            e.preventDefault();
            return false;
        }
        return true;
    });

    // --- Highlight Changes ---
    $('.translation-form-column input:not([readonly]), .translation-form-column textarea:not([readonly])').on('input', function() {
        $(this).toggleClass('changed', $(this).val().trim() !== '');
    });
});
</script>

