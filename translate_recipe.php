<?php
/**
 * Page permettant aux utilisateurs autorisés (Traducteur, Chef auteur, Admin)
 * de traduire une recette de l'anglais vers le français.
 * Affiche une interface à deux colonnes : Anglais (lecture seule) et Français (éditable sous conditions).
 */

// Démarrer la session seulement si aucune session n'est déjà active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Récupération des informations nécessaires ---

// Récupérer l'ID de la recette depuis les paramètres GET de l'URL.
$recipeId = (int) $_GET['id']; 

// Récupérer le nom d'utilisateur et le rôle de l'utilisateur actuellement connecté.
$currentUser = $_SESSION['username'] ?? null;
$currentRole = $_SESSION['role'] ?? null;

// --- Vérifications initiales ---

// Si l'utilisateur n'est pas connecté, il ne peut pas accéder à cette page.
// Redirection vers la page d'accueil.
if (!isset($currentUser)) {
    $_SESSION['flash_message'] = ['type' => 'error', 'key' => 'messages.login_required']; // Utilise une clé de traduction
    header('Location: index.php');
    exit; 
}

// --- Chargement et validation de la recette ---

$recipesFile = 'recipes.json';
$recipe = null;      // Contiendra les données de la recette à traduire.
$recipeIndex = null; // Contiendra l'index (clé) de la recette dans le tableau $recipes.

    $recipes = json_decode(file_get_contents($recipesFile), true);
    // Chercher la recette par son ID et récupérer son index.
    foreach ($recipes as $index => $r) {
        // Comparaison lâche (==) intentionnelle ici pour gérer les types mixtes potentiels.
        if (isset($r['id']) && $r['id'] == $recipeId) {
            $recipeIndex = $index;
            $recipe = $r;
            break; // Sortir de la boucle une fois la recette trouvée.
        }
    }
    

// Si la recette n'a pas été trouvée 
if ($recipe === null || $recipeIndex === null) {
    // Stocker un message d'erreur dans la session pour l'afficher après la redirection.
    $_SESSION['flash_message'] = ['type' => 'error', 'key' => 'messages.recipe_not_found'];
    header('Location: index.php');
    exit;
}

// --- Vérification des droits d'accès et d'édition ---

// Déterminer les capacités de l'utilisateur par rapport à CETTE recette.
$isAuthor = isset($recipe['Author']) && $recipe['Author'] === $currentUser;
$isTranslator = $currentRole === 'Traducteur';
$isAdmin = $currentRole === 'Administrateur';
$isChef = $currentRole === 'Chef';

// Un utilisateur peut éditer TOUS les champs s'il est Admin OU s'il est Chef ET l'auteur de la recette.
$isAllowedEditor = ($isChef && $isAuthor) || $isAdmin;

// Un utilisateur peut accéder à la page de traduction s'il est Traducteur OU s'il est un Éditeur Autorisé.
$canAccessPage = $isTranslator || $isAllowedEditor;

// Si l'utilisateur n'a pas les droits suffisants pour accéder à cette page.
if (!$canAccessPage) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

// --- Fonctions Utilitaires ---

/**
 * Sépare une chaîne de quantité (ex: "1/2 cup", "100 g") en sa partie numérique
 * et son unité/label textuel. Utilise une expression régulière.
 *
 * @param string $quantity La chaîne de quantité complète.
 * @return array Un tableau associatif ['value' => (string) valeur numérique, 'label' => (string) unité/label].
 */
function splitQuantityLabel($quantity) {
    $quantity = trim($quantity);
    // Regex: capture les chiffres, points, slashs, espaces au début (^[\d\s\.\/]+)
    // puis capture tout le reste (.*$) comme label.
    if (preg_match('/^([\d\s\.\/]+)(.*)$/', $quantity, $matches)) {
        // Retourne la partie numérique et le label (unité), trimés.
        return ['value' => trim($matches[1]), 'label' => trim($matches[2])];
    }
    // Si la regex ne correspond pas (ex: "to taste"), retourne une valeur vide et le texte entier comme label.
    return ['value' => '', 'label' => $quantity];
}

// --- Traitement de la soumission du formulaire (Méthode POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Conserver une copie de la recette avant les modifications. Utile pour comparer ou restaurer.
    $recipeBeforeUpdate = $recipes[$recipeIndex];

    // 1. Mettre à jour le nom français (NameFR)
    // Utilise htmlspecialchars pour prévenir les attaques XSS si cette donnée est réaffichée quelque part.
    $postedNameFR = trim(htmlspecialchars($_POST['nameFR'] ?? '', ENT_QUOTES, 'UTF-8'));
    $recipe['nameFR'] = $postedNameFR;

    // 2. Mettre à jour les ingrédients français (IngredientsFR)
    if (isset($_POST['ingredientsFR'])) {
        $originalIngredients = $recipeBeforeUpdate['ingredients'] ?? []; // Tableau EN original
        $originalIngredientsFR = $recipeBeforeUpdate['ingredientsFR'] ?? []; // Tableau FR avant modif (pour garder valeurs non modifiées)
        $newIngredientsFR = $recipe['ingredientsFR'] ?? []; // Tableau FR à mettre à jour

        foreach ($_POST['ingredientsFR'] as $index => $postedIngredient) {
            // Vérifier si l'index existe dans les ingrédients originaux EN.
            if (!isset($originalIngredients[$index])) continue; // Passer à l'ingrédient suivant si incohérence

            // Assurer que ce sont des tableaux pour éviter les erreurs.
            $originalENIngredient = $originalIngredients[$index];
            $originalFRIngredient = ($originalIngredientsFR[$index] ?? []); // Utiliser le FR d'avant ou un tableau vide

            // Récupérer les données soumises pour cet ingrédient FR, en les protégeant.
            $postedUnitLabel = trim(htmlspecialchars($postedIngredient['quantity'] ?? '', ENT_QUOTES, 'UTF-8')); // Seul le label est soumis
            $postedName = trim(htmlspecialchars($postedIngredient['name'] ?? '', ENT_QUOTES, 'UTF-8'));
            $postedType = trim(htmlspecialchars($postedIngredient['type'] ?? '', ENT_QUOTES, 'UTF-8'));

            // Récupérer la partie *valeur numérique* de la quantité EN originale.
            $originalQuantityValue = splitQuantityLabel($originalENIngredient['quantity'] ?? '')['value'];

            // Reconstruire la chaîne 'quantity' pour le JSON FR:
            // On garde la valeur numérique EN et on ajoute le label FR soumis.
            $finalTranslatedQuantity = trim($originalQuantityValue . ' ' . $postedUnitLabel);

            // Assigner les nouvelles valeurs FR (ou les anciennes si non soumises/vides ?)
            // Ici, on écrase avec les valeurs soumises.
            $finalTranslatedName = $postedName;
            $finalTranslatedType = $postedType;

            // Mettre à jour le tableau $newIngredientsFR à l'index courant.
            $newIngredientsFR[$index] = [
                'quantity' => $finalTranslatedQuantity,
                'name' => $finalTranslatedName,
                'type' => $finalTranslatedType
            ];
        }
        // Remplacer l'ancien tableau d'ingrédients FR par le nouveau dans la recette.
        $recipe['ingredientsFR'] = $newIngredientsFR;
        
    }

    // 3. Mettre à jour les étapes françaises (StepsFR)
    if (isset($_POST['stepsFR'])) {
        $originalSteps = $recipeBeforeUpdate['steps'] ?? []; // Étapes EN originales
        $originalStepsFR = $recipeBeforeUpdate['stepsFR'] ?? []; // Étapes FR avant modif
        $newStepsFR = $recipe['stepsFR'] ?? []; // Étapes FR à mettre à jour

        foreach ($_POST['stepsFR'] as $index => $postedValue) {
            if (!isset($originalSteps[$index])) continue; // Ignorer si incohérence

            // Récupérer et protéger l'étape soumise.
            $postedStep = trim(htmlspecialchars($postedValue ?? '', ENT_QUOTES, 'UTF-8'));

            // Mettre à jour l'étape dans le tableau $newStepsFR.
            $newStepsFR[$index] = $postedStep;
        }
        // Remplacer l'ancien tableau d'étapes FR par le nouveau.
        $recipe['stepsFR'] = $newStepsFR;
    }

    // --- Sauvegarde et Redirection ---

    // Mettre à jour la recette (avec les modifications FR) dans le tableau global $recipes.
    $recipes[$recipeIndex] = $recipe;

    // Écrire le tableau $recipes complet mis à jour dans le fichier JSON.
    // Utilise les flags pour une meilleure lisibilité et gestion des caractères spéciaux/URLs.
    file_put_contents(
        $recipesFile,
        json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    // Rediriger l'utilisateur vers la page de visualisation de la recette
    header("Location: recipe.php?id=" . $recipeId);
    exit; // Terminer le script après la redirection.
}

// --- Génération du Contenu HTML pour l'affichage de la page ---

// Protéger le nom de la recette pour l'affichage dans le titre H1.
$recipeNamePHP = htmlspecialchars($recipe['name'], ENT_QUOTES, 'UTF-8');

// Début de la construction de la chaîne HTML pour le contenu principal de la page.
$content = '
<div class="translation-container">
    <h1>
        <span data-translate="labels.translate_recipe_title_base">Translate Recipe</span>:
        <span id="recipe-name-display">' . $recipeNamePHP . '</span>
    </h1>

    <form method="POST" id="translation-form" action="translate_recipe.php?id=' . $recipeId . '"> 
        <div class="translation-columns">

            <div class="translation-column original-column">
                <h2 data-translate="labels.original_english">Original (English)</h2>
                ' . ($isTranslator ? '<p><small data-translate="messages.translator_readonly_hint_base">Read-only source text.</small></p>' : '') . '
                <div class="form-group">
                    <label data-translate="labels.recipe_name">Recipe Name:</label>
                    <div class="original-field">' . htmlspecialchars($recipe['name'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                </div>

                <div class="form-group">
                    <label data-translate="labels.ingredients">Ingredients:</label>';
                    // Vérifier s'il y a des ingrédients EN à afficher.
                    if (!empty($recipe['ingredients'])) {
                        foreach ($recipe['ingredients'] as $index => $ingredient) {
                            // Séparer quantité et unité pour un affichage plus clair.
                            $quantityParts = splitQuantityLabel($ingredient['quantity'] ?? '');
                            $content .= '
                            <div class="translation-row">
                                <div class="original-field quantity-value" title="Original Quantity Value">' . htmlspecialchars($quantityParts['value'], ENT_QUOTES, 'UTF-8') . '</div>
                                <div class="original-field quantity-unit" title="Original Unit/Label">' . htmlspecialchars($quantityParts['label'], ENT_QUOTES, 'UTF-8') . '</div>
                                <div class="original-field" title="Original Name">' . htmlspecialchars($ingredient['name'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                                <div class="original-field" title="Original Type">' . htmlspecialchars($ingredient['type'] ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                            </div>';
                        }
                    } else {
                        // Message si aucun ingrédient EN n'est trouvé.
                        $content .= '<p data-translate="messages.no_en_ingredients">No English ingredients found.</p>';
                    }
$content .= '
                </div>

                <div class="form-group">
                    <label data-translate="labels.steps">Steps:</label>';
                     // Vérifier s'il y a des étapes EN à afficher.
                     if (!empty($recipe['steps'])) {
                        foreach ($recipe['steps'] as $index => $step) {
                            $content .= '
                            <div class="translation-row">
                                <div class="original-field">' . htmlspecialchars($step ?? '', ENT_QUOTES, 'UTF-8') . '</div>
                            </div>';
                        }
                    } else {
                         // Message si aucune étape EN n'est trouvée.
                        $content .= '<p data-translate="messages.no_en_steps">No English steps found.</p>';
                    }
$content .= '
                </div>
            </div>

            <div class="translation-column translation-form-column">
                <h2 data-translate="labels.translation_french">Translation (French)</h2>
                <p><small data-translate="messages.translator_edit_hint">Edit only empty fields where English source exists.</small></p>

                 <div class="form-group">';
                    // Logique pour déterminer si le champ Nom FR doit être en lecture seule pour un Traducteur.
                    $nameEN = trim($recipe['name'] ?? '');
                    $nameFR = trim($recipe['nameFR'] ?? '');
                    // Readonly si: (utilisateur est Traducteur ET (le champ FR n'est PAS vide OU le champ EN EST vide))
                    // OU si l'utilisateur n'est PAS un éditeur autorisé (Admin ou Chef Auteur).
                    $nameReadonly = !$isAllowedEditor && ($isTranslator && (!empty($nameFR) || empty($nameEN)));
                    // Définir la clé de traduction pour le tooltip (title).
                    $nameClass = $nameReadonly ? 'readonly-translator' : ''; // Classe CSS pour le style readonly.
                    $content .= '
                    <label for="nameFR" data-translate="labels.recipe_name">Nom de la recette:</label>
                    <input type="text" id="nameFR" name="nameFR" value="' . htmlspecialchars($nameFR, ENT_QUOTES, 'UTF-8') . '"
                           data-translate-placeholder="placeholders.translate_name" data-translate-title="' . '"
                           ' . ($nameReadonly ? 'readonly' : '') . ' class="' . $nameClass . '">';
$content .= '
                </div>

                <div class="form-group">
                    <label data-translate="labels.ingredients">Ingrédients:</label>';
                     // Générer les champs FR seulement s'il y a des ingrédients EN correspondants.
                     if (!empty($recipe['ingredients'])) {
                        foreach ($recipe['ingredients'] as $index => $ingredientEN) {
                             $ingredientFR = $recipe['ingredientsFR'][$index] ?? []; // FR actuel ou tableau vide
                             $quantityPartsEN = splitQuantityLabel($ingredientEN['quantity'] ?? '');
                             $quantityPartsFR = splitQuantityLabel($ingredientFR['quantity'] ?? ''); // Label FR actuel

                             // Vérifier si les champs sources EN sont remplis.
                             $enQtyFilled = !empty(trim($ingredientEN['quantity'] ?? '')); // On se base sur la quantité globale EN
                             $enNameFilled = !empty(trim($ingredientEN['name'] ?? ''));
                             $enTypeFilled = !empty(trim($ingredientEN['type'] ?? ''));

                             // Vérifier si les champs cibles FR sont vides (pour la logique readonly du traducteur).
                             $frUnitLabelEmpty = empty(trim($quantityPartsFR['label']));
                             $frNameEmpty = empty(trim($ingredientFR['name'] ?? ''));
                             $frTypeEmpty = empty(trim($ingredientFR['type'] ?? ''));

                             // Déterminer le statut readonly pour chaque sous-champ de l'ingrédient FR.
                             // Readonly si PAS éditeur autorisé ET (est Traducteur ET (champ FR NON vide OU champ EN correspondant EST vide))
                             $unitReadonly = !$isAllowedEditor && ($isTranslator && (!$frUnitLabelEmpty || !$enQtyFilled));
                             $nameReadonly = !$isAllowedEditor && ($isTranslator && (!$frNameEmpty || !$enNameFilled));
                             $typeReadonly = !$isAllowedEditor && ($isTranslator && (!$frTypeEmpty || !$enTypeFilled)); // Règle pour le type

                             // Générer la ligne de formulaire pour cet ingrédient FR.
                            $content .= '
                            <div class="translation-row">
                                <input type="text" class="quantity-value" value="' . htmlspecialchars($quantityPartsEN['value'], ENT_QUOTES, 'UTF-8') . '" readonly title="Original Quantity Value (Readonly)">
                                <input type="text" name="ingredientsFR[' . $index . '][quantity]" value="' . htmlspecialchars($quantityPartsFR['label'], ENT_QUOTES, 'UTF-8') . '"
                                       data-translate-placeholder="placeholders.translate_unit" data-translate-title="' . '"
                                       class="quantity-unit ' . ($unitReadonly ? 'readonly-translator' : '') . '" ' . ($unitReadonly ? 'readonly' : '') . '>
                                <input type="text" name="ingredientsFR[' . $index . '][name]" value="' . htmlspecialchars($ingredientFR['name'] ?? '', ENT_QUOTES, 'UTF-8') . '"
                                       data-translate-placeholder="placeholders.translate_ing_name" data-translate-title="' . '"
                                       ' . ($nameReadonly ? 'readonly' : '') . ' class="' . ($nameReadonly ? 'readonly-translator' : '') . '">
                                <input type="text" name="ingredientsFR[' . $index . '][type]" value="' . htmlspecialchars($ingredientFR['type'] ?? '', ENT_QUOTES, 'UTF-8') . '"
                                       data-translate-placeholder="placeholders.translate_ing_type" data-translate-title="' . '"
                                       ' . ($typeReadonly ? 'readonly' : '') . ' class="' . ($typeReadonly ? 'readonly-translator' : '') . '">
                            </div>';
                        }
                    } else {
                        // Message si impossible de traduire (pas de source EN).
                        $content .= '<p data-translate="messages.cannot_translate_no_source">Cannot translate ingredients without English source.</p>';
                    }
$content .= '
                </div>

                 <div class="form-group">
                    <label data-translate="labels.steps">Étapes:</label>';
                     // Générer les champs FR seulement s'il y a des étapes EN correspondantes.
                     if (!empty($recipe['steps'])) {
                         foreach ($recipe['steps'] as $index => $stepEN) {
                            $stepEN = trim($stepEN ?? '');
                            $stepFR = trim($recipe['stepsFR'][$index] ?? ''); // Étape FR actuelle ou chaîne vide

                            // Déterminer le statut readonly pour le textarea de l'étape FR.
                            // Readonly si PAS éditeur autorisé ET (est Traducteur ET (étape FR NON vide OU étape EN EST vide))
                            $stepReadonly = !$isAllowedEditor && ($isTranslator && (!empty($stepFR) || empty($stepEN)));
                            $stepPlaceholderKey = 'placeholders.translate_step_n'; // Clé pour placeholder
                            $stepClass = $stepReadonly ? 'readonly-translator' : ''; // Classe CSS

                            // Générer le textarea pour cette étape FR.
                            $content .= '
                            <div class="translation-row">
                                <textarea name="stepsFR[' . $index . ']"
                                          data-translate-placeholder="' . $stepPlaceholderKey . '"
                                          data-placeholder-index="' . ($index + 1) . '" {/* Index pour JS */}
                                          data-translate-title="' . '"
                                          ' . ($stepReadonly ? 'readonly' : '') . ' class="' . $stepClass . '">'
                                          . htmlspecialchars($stepFR, ENT_QUOTES, 'UTF-8') . /* Afficher le contenu FR actuel */
                                '</textarea>
                            </div>';
                        }
                    } else {
                        // Message si impossible de traduire (pas de source EN).
                        $content .= '<p data-translate="messages.cannot_translate_no_source">Cannot translate steps without English source.</p>';
                    }
$content .= '
                </div>
            </div> 
        </div> 

        <div class="form-actions">
            <button type="submit" class="button button-primary btn-save" data-translate="buttons.save_translation">Save Translation</button>
            <a href="recipe.php?id=' . $recipeId . '" class="button button-secondary btn-cancel" data-translate="buttons.cancel">Cancel</a>
        </div>
    </form>
</div>'; // Fin de translation-container

// Définir le titre de la page HTML.
$title = "Translate Recipe";
// Inclure le fichier d'en-tête commun (qui affichera $content et $title).
include 'header.php';
?>

<script>
// Cette fonction est appelée par le script dans header.php une fois que
// les traductions globales (data.json) sont chargées.
function initializePageContent(translations, lang) {
      // Traduire les placeholders dynamiques (ex: "Traduire étape {n}") en utilisant les data-attributes.
      $('[data-translate-placeholder][data-placeholder-index]').each(function() {
            const $el = $(this);
            const key = $el.data('translate-placeholder'); // Clé de traduction (ex: 'placeholders.translate_step_n')
            const index = $el.data('placeholder-index'); // Numéro (ex: 1, 2, 3...)
            let placeholderText = getNestedTranslation(translations, key); // Fonction de header.php
            // Remplacer le marqueur {n} par le numéro réel.
            if (placeholderText) {
                 placeholderText = placeholderText.replace('{n}', index);
            }
            $el.attr('placeholder', placeholderText || ''); // Appliquer le texte traduit comme placeholder.
      });

      // Le reste des éléments statiques (boutons, labels H2...) est traduit
      // automatiquement par la fonction translatePage() de header.php.
 }

// Exécuter ce code une fois que la page est entièrement chargé.
$(document).ready(function() {

    // --- Validation du formulaire avant soumission ---
    $('#translation-form').submit(function(e) {
        let hasContent = false; // Flag pour savoir si au moins un champ a été rempli.
        // Sélecteur pour cibler uniquement les champs éditables dans la colonne de traduction.
        const selector = '.translation-form-column input:not([readonly]), .translation-form-column textarea:not([readonly])';

        // Parcourir chaque champ éditable.
        $(selector).each(function() {
             // Si la valeur du champ (sans espaces avant/après) n'est pas vide.
             if ($(this).val().trim() !== '') {
                 hasContent = true; // Marquer qu'on a trouvé du contenu.
                 return false; // Sortir de la boucle .each() dès qu'on trouve du contenu.
             }
         });

        // Si aucun contenu n'a été trouvé dans les champs éditables.
        if (!hasContent) {
            // Afficher un message d'erreur à l'utilisateur (utilise la fonction de header.php).
            showMessage(currentTranslations.messages.translation_missing_fields, 'error');
            e.preventDefault(); // Empêcher la soumission du formulaire.
            return false; // Confirmer l'arrêt de la soumission.
        }
        // Si du contenu a été trouvé, laisser le formulaire se soumettre normalement.
        return true;
    });

    // --- Mise en évidence visuelle des champs modifiés ---
    // Attacher un écouteur d'événement 'input' (chaque fois que l'utilisateur tape quelque chose)
    // aux champs éditables de la colonne de traduction.
    $('.translation-form-column input:not([readonly]), .translation-form-column textarea:not([readonly])').on('input', function() {
        // Ajoute ou retire la classe CSS 'changed' basée sur si le champ a du contenu ou non.
        // La classe 'changed' peut être stylée en CSS (ex: bordure de couleur différente).
        $(this).toggleClass('changed', $(this).val().trim() !== '');
    });

    // Appliquer la classe 'changed' initialement aux champs qui sont déjà remplis au chargement de la page.
    $('.translation-form-column input:not([readonly]), .translation-form-column textarea:not([readonly])').each(function() {
         $(this).toggleClass('changed', $(this).val().trim() !== '');
    });
});
</script>