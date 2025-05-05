$(document).ready(function () {
    // --- Initialisation ---
    // Pas besoin de compteurs globaux complexes. On se basera sur le nombre
    // d'éléments existants dans le DOM au moment où on en a besoin.

    // --- Fonctions d'aide (Helpers) ---

    /**
     * Ajoute une PAIRE de champs pour un ingrédient (Anglais + Français).
     * Les champs français sont initialement vides.
     */
    function addIngredientPair() {
        // Trouve le nombre actuel de champs ingrédients anglais pour déterminer le nouvel index
        const currentIndex = $("#ingredients-container .dynamic-field").length;

        // Crée le HTML pour le nouvel ingrédient anglais
        const newIngredientEnHtml = `
            <div class="dynamic-field">
                <div class="ingredient">
                    <input type="text" name="ingredients[${currentIndex}][quantity]" data-translate-placeholder="placeholders.quantity" required>
                    <input type="text" name="ingredients[${currentIndex}][name]" data-translate-placeholder="placeholders.ingredient_name" required>
                    <input type="text" name="ingredients[${currentIndex}][type]" data-translate-placeholder="placeholders.ingredient_type_en">
                </div>
                <button type="button" class="remove-field button button-danger" data-sync-type="ingredient">×</button>
            </div>
        `;

        // Crée le HTML pour le nouvel ingrédient français (placeholders spécifiques FR)
        const newIngredientFrHtml = `
            <div class="dynamic-field">
                <div class="ingredient">
                    <input type="text" name="ingredientsFR[${currentIndex}][quantity]" data-translate-placeholder="placeholders.quantity_fr">
                    <input type="text" name="ingredientsFR[${currentIndex}][name]" data-translate-placeholder="placeholders.ingredient_name_fr">
                    <input type="text" name="ingredientsFR[${currentIndex}][type]" data-translate-placeholder="placeholders.ingredient_type_fr">
                </div>
                <button type="button" class="remove-field button button-danger" data-sync-type="ingredient">×</button>
            </div>
        `;

        // Ajoute les nouveaux éléments au DOM
        $("#ingredients-container").append(newIngredientEnHtml);
        $("#ingredients-fr-container").append(newIngredientFrHtml);

        // Traduit les placeholders des nouveaux champs ajoutés dynamiquement
        translateDynamicPlaceholders(currentTranslations); // Assurez-vous que currentTranslations est accessible
    }

    /**
     * Ajoute une PAIRE de champs pour une étape (Anglais + Français).
     * Le champ français est initialement vide.
     */
    function addStepPair() {
        // Trouve le nombre actuel de champs étapes anglais pour déterminer le nouvel index et le placeholder
        const currentIndex = $("#steps-container .dynamic-field").length;
        const nextStepNumber = currentIndex + 1;

        // Crée le HTML pour la nouvelle étape anglaise
        // Utilise data-attributes pour la traduction dynamique du placeholder
        const newStepEnHtml = `
            <div class="dynamic-field">
                <textarea name="steps[${currentIndex}]"
                          data-translate-placeholder="placeholders.step_n"
                          data-placeholder-index="${nextStepNumber}" required></textarea>
                <button type="button" class="remove-field button button-danger" data-sync-type="step">×</button>
            </div>
        `;

        // Crée le HTML pour la nouvelle étape française
        const newStepFrHtml = `
            <div class="dynamic-field">
                <textarea name="stepsFR[${currentIndex}]"
                          data-translate-placeholder="placeholders.step_n_fr"
                          data-placeholder-index="${nextStepNumber}"></textarea>
                <button type="button" class="remove-field button button-danger" data-sync-type="step">×</button>
            </div>
        `;

        // Ajoute les nouveaux éléments au DOM
        $("#steps-container").append(newStepEnHtml);
        $("#steps-fr-container").append(newStepFrHtml);

        // Traduit les placeholders des nouveaux champs
        translateDynamicPlaceholders(currentTranslations);
    }

    /**
     * Ajoute un champ pour un minuteur (indépendant des langues).
     */
    function addTimerField() {
        // Trouve le nombre actuel de champs minuteurs pour déterminer le nouvel index et le placeholder
        const currentIndex = $("#timers-container .dynamic-field").length;
        const nextStepNumber = currentIndex + 1;

        // Crée le HTML pour le nouveau minuteur
        // Utilise data-attributes pour la traduction dynamique du placeholder
        const newTimerHtml = `
            <div class="dynamic-field">
                <input type="number" name="timers[${currentIndex}]" min="0" required
                       data-translate-placeholder="placeholders.timer_n"
                       data-placeholder-index="${nextStepNumber}">
                <button type="button" class="remove-field button button-danger" data-sync-type="timer">×</button>
            </div>
        `;

        // Ajoute le nouvel élément au DOM
        $("#timers-container").append(newTimerHtml);

        // Traduit les placeholders des nouveaux champs
        translateDynamicPlaceholders(currentTranslations);
    }

    /**
     * Réindexe les attributs 'name' et les placeholders des champs
     * dans un conteneur donné après une suppression.
     * @param {jQuery} $container Le conteneur jQuery (ex: $("#ingredients-container"))
     */
    function reindexFields($container) {
        const containerId = $container.attr('id');
        // Sélectionne tous les champs dynamiques directement enfants du conteneur
        const $fields = $container.children('.dynamic-field');

        // Parcours chaque champ pour mettre à jour son index
        $fields.each(function(newIndex) {
            const $field = $(this);

            // --- Mise à jour pour les ingrédients (Anglais) ---
            if (containerId === 'ingredients-container') {
                // Met à jour l'index dans l'attribut 'name' de chaque input
                $field.find('input[name^="ingredients["]').each(function() {
                    // Remplace l'ancien index (ex: [1]) par le nouveau (ex: [0])
                    const oldName = $(this).attr('name');
                    const newName = oldName.replace(/\[\d+\]/, `[${newIndex}]`);
                    $(this).attr('name', newName);
                });
            }
            // --- Mise à jour pour les ingrédients (Français) ---
            else if (containerId === 'ingredients-fr-container') {
                $field.find('input[name^="ingredientsFR["]').each(function() {
                    const oldName = $(this).attr('name');
                    const newName = oldName.replace(/\[\d+\]/, `[${newIndex}]`);
                    $(this).attr('name', newName);
                });
            }
            // --- Mise à jour pour les étapes (Anglais) ---
            else if (containerId === 'steps-container') {
                const $textarea = $field.find('textarea');
                const oldName = $textarea.attr('name');
                // Met à jour l'index dans le 'name'
                const newName = oldName.replace(/\[\d+\]/, `[${newIndex}]`);
                $textarea.attr('name', newName);
                $textarea.attr('data-placeholder-index', newIndex + 1);
            }
            // --- Mise à jour pour les étapes (Français) ---
            else if (containerId === 'steps-fr-container') {
                const $textarea = $field.find('textarea');
                const oldName = $textarea.attr('name');
                const newName = oldName.replace(/\[\d+\]/, `[${newIndex}]`);
                $textarea.attr('name', newName);
                $textarea.attr('data-placeholder-index', newIndex + 1);
            }
            // --- Mise à jour pour les minuteurs ---
            else if (containerId === 'timers-container') {
                const $input = $field.find('input[type="number"]');
                const oldName = $input.attr('name');
                const newName = oldName.replace(/\[\d+\]/, `[${newIndex}]`);
                $input.attr('name', newName);
                $input.attr('data-placeholder-index', newIndex + 1);
            }
        });

        // Après la réindexation, on retraduit les placeholders dynamiques du conteneur
        translateDynamicPlaceholders(currentTranslations, $container);
    }

    /**
     * Traduit les placeholders dynamiques qui utilisent data-translate-placeholder
     * et potentiellement data-placeholder-index.
     * @param {object} translations - L'objet de traductions pour la langue actuelle.
     * @param {jQuery} [$scope=null] - Optionnel: Un conteneur jQuery pour limiter la traduction.
     */
    function translateDynamicPlaceholders(translations, $scope = null) {
        if (!translations) return; // Ne rien faire si les traductions ne sont pas chargées

        const $elementsToIndex = $scope
            ? $scope.find('[data-translate-placeholder][data-placeholder-index]') // Dans un scope précis
            : $('[data-translate-placeholder][data-placeholder-index]'); // Sur toute la page

        $elementsToIndex.each(function() {
            const $el = $(this);
            const key = $el.data('translate-placeholder');
            const index = $el.data('placeholder-index');
            let placeholderText = getNestedTranslation(translations, key) || ''; // Utilise la fonction globale de header.php

            // Remplace {n} par l'index réel
            if (placeholderText && typeof placeholderText.replace === 'function') {
                 placeholderText = placeholderText.replace(/\{n\}/g, index); // g = remplacement global
            }
            $el.attr('placeholder', placeholderText);
        });

        // Traduit aussi les placeholders statiques (sans data-placeholder-index) dans le scope
        const $elementsStatic = $scope
            ? $scope.find('[data-translate-placeholder]:not([data-placeholder-index])')
            : $('[data-translate-placeholder]:not([data-placeholder-index])');

        $elementsStatic.each(function() {
            const $el = $(this);
            const key = $el.data('translate-placeholder');
            const placeholderText = getNestedTranslation(translations, key) || '';
            $el.attr('placeholder', placeholderText);
        });
    }

    // --- Gestionnaires d'Événements (Event Handlers) ---

    // Clic sur les boutons "Ajouter Ingrédient" (EN ou FR)
    $("#add-ingredient, #add-ingredient-fr").click(function () {
        addIngredientPair();
    });

    // Clic sur les boutons "Ajouter Étape" (EN ou FR)
    $("#add-step, #add-step-fr").click(function () {
        addStepPair();
    });

    // Clic sur le bouton "Ajouter Minuteur"
    $("#add-timer").click(function () {
        addTimerField();
    });

    // Clic sur N'IMPORTE QUEL bouton "Supprimer" (classe .remove-field)
    // Utilise la délégation d'événements pour fonctionner aussi sur les éléments ajoutés dynamiquement.
    $(document).on('click', '.remove-field', function() {
        const $button = $(this); // Le bouton 'x' cliqué
        // Récupère le type de champ à synchroniser (ingredient, step, timer) depuis l'attribut data-sync-type
        const syncType = $button.data('sync-type');
        // Trouve le conteneur parent '.dynamic-field' du bouton
        const $fieldToRemove = $button.closest('.dynamic-field');
        // Trouve le conteneur principal (ex: #ingredients-container)
        const $container = $fieldToRemove.parent();
        const containerId = $container.attr('id');

        // --- Vérification de Sécurité : Ne pas supprimer le dernier élément ---
        // Vérifie s'il reste d'autres champs du même type DANS CE CONTENEUR SPÉCIFIQUE
        if ($fieldToRemove.siblings('.dynamic-field').length === 0) {
            // Affiche un message d'erreur (utilise la fonction showMessage de header.php)
            showMessage(currentTranslations.messages.cannot_remove_last, 'error');
            return; // Arrête l'exécution de la fonction de suppression
        }

        // --- Logique de Suppression ---

        // Trouve l'index (position) du champ à supprimer parmi ses frères
        const indexToRemove = $fieldToRemove.index();

        // 1. Cas synchronisé : Ingrédients ou Étapes
        if (syncType === 'ingredient' || syncType === 'step') {
            let siblingContainerId;
            let $siblingContainer;

            // Détermine l'ID du conteneur frère (EN <-> FR)
            if (containerId.includes('-fr')) {
                // Si on supprime depuis FR, le frère est EN
                siblingContainerId = containerId.replace('-fr', '');
            } else {
                // Si on supprime depuis EN, le frère est FR
                siblingContainerId = containerId + '-fr';
            }
            // Sélectionne le conteneur frère avec jQuery
            $siblingContainer = $('#' + siblingContainerId);

            // Trouve le champ correspondant dans le conteneur frère en utilisant le même index
            const $siblingFieldToRemove = $siblingContainer.children('.dynamic-field').eq(indexToRemove);

            // Supprime les DEUX champs (celui cliqué et son frère)
            $fieldToRemove.remove();
            if ($siblingFieldToRemove.length > 0) { // Sécurité: s'assurer qu'on a bien trouvé le frère
                 $siblingFieldToRemove.remove();
            }

            // Réindexe les champs restants dans les DEUX conteneurs
            reindexFields($container);
            if ($siblingContainer.length > 0) { // Sécurité: s'assurer que le conteneur frère existe
                reindexFields($siblingContainer);
            }

        }
        // 2. Cas indépendant : Minuteurs
        else if (syncType === 'timer') {
            // Supprime juste le champ cliqué
            $fieldToRemove.remove();
            // Réindexe seulement le conteneur des minuteurs
            reindexFields($container);
        }
        
    });

    // --- Réindexation Initiale au Chargement de la Page ---
    // C'est important surtout pour la page 'modify_recipe.php' où les champs
    // sont pré-remplis et pourraient avoir des index incohérents initialement.
    // On s'assure que tous les index sont corrects dès le départ.
    console.log("Reindexing fields on page load...");
    reindexFields($('#ingredients-container'));
    reindexFields($('#ingredients-fr-container'));
    reindexFields($('#steps-container'));
    reindexFields($('#steps-fr-container'));
    reindexFields($('#timers-container'));
    console.log("Initial reindexing complete.");

});