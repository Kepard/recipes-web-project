$(document).ready(function () {
    // --- Initialize counters based on the number of existing fields ---
    // We assume the initial HTML (especially on modify page) might have differing counts,
    // but from now on, ingredient and step counts should ideally be synced.
    // We'll use the English count as the 'master' for index generation, but reindex both.
    let ingredientIndex = $("#ingredients-container .dynamic-field").length;
    let ingredientFrIndex = $("#ingredients-fr-container .dynamic-field").length; // Keep track separately for reindexing FR
    let stepIndex = $("#steps-container .dynamic-field").length;
    let stepFrIndex = $("#steps-fr-container .dynamic-field").length; // Keep track separately for reindexing FR
    let timerIndex = $("#timers-container .dynamic-field").length || 1; // Timer is independent

    // --- Helper Functions ---

    // Function to add a PAIR of ingredient fields (EN + FR)
    function addIngredientPair() {
        const currentIndex = $("#ingredients-container .dynamic-field").length; // Get current count before adding

        // English Ingredient HTML
        const newIngredientEn = `
            <div class="dynamic-field">
                <div class="ingredient">
                    <input type="text" name="ingredients[${currentIndex}][quantity]" placeholder="Quantity" required>
                    <input type="text" name="ingredients[${currentIndex}][name]" placeholder="Ingredient Name" required>
                    <input type="text" name="ingredients[${currentIndex}][type]" placeholder="Type">
                </div>
                <button type="button" class="remove-field button button-danger" data-sync-type="ingredient">×</button>
            </div>
        `;

        // French Ingredient HTML (empty)
        const newIngredientFr = `
            <div class="dynamic-field">
                <div class="ingredient">
                    <input type="text" name="ingredientsFR[${currentIndex}][quantity]" placeholder="Quantité">
                    <input type="text" name="ingredientsFR[${currentIndex}][name]" placeholder="Nom ingrédient">
                    <input type="text" name="ingredientsFR[${currentIndex}][type]" placeholder="Type">
                </div>
                <button type="button" class="remove-field button button-danger" data-sync-type="ingredient">×</button>
            </div>
        `;

        $("#ingredients-container").append(newIngredientEn);
        $("#ingredients-fr-container").append(newIngredientFr);

        // Update indices (though using length directly in reindex might be safer)
        ingredientIndex++;
        ingredientFrIndex++;
    }

    // Function to add a PAIR of step fields (EN + FR)
    function addStepPair() {
        const currentIndex = $("#steps-container .dynamic-field").length; // Get current count

        // English Step HTML
        const newStepEn = `
            <div class="dynamic-field">
                <textarea name="steps[${currentIndex}]" placeholder="Step ${currentIndex + 1}" required></textarea>
                <button type="button" class="remove-field button button-danger" data-sync-type="step">×</button>
            </div>
        `;

        // French Step HTML (empty)
        const newStepFr = `
            <div class="dynamic-field">
                <textarea name="stepsFR[${currentIndex}]" placeholder="Étape ${currentIndex + 1}"></textarea>
                <button type="button" class="remove-field button button-danger" data-sync-type="step">×</button>
            </div>
        `;

        $("#steps-container").append(newStepEn);
        $("#steps-fr-container").append(newStepFr);

        // Update indices
        stepIndex++;
        stepFrIndex++;
    }

    // Function to add a Timer field (independent)
    function addTimerField() {
        // Use current length for the index of the new timer
        const currentIndex = $("#timers-container .dynamic-field").length;
        const newTimer = `
            <div class="dynamic-field">
                <input type="number" name="timers[${currentIndex}]" placeholder="Timer for Step ${currentIndex + 1}" min="0" required>
                <button type="button" class="remove-field button button-danger" data-sync-type="timer">×</button>
            </div>
        `;
        $("#timers-container").append(newTimer);
        timerIndex++; // Increment timer specific index
    }


    // --- Event Handlers for Adding Fields ---

    // Add Ingredient Pair (triggered by either button)
    $("#add-ingredient, #add-ingredient-fr").click(function () {
        addIngredientPair();
    });

    // Add Step Pair (triggered by either button)
    $("#add-step, #add-step-fr").click(function () {
        addStepPair();
    });

    // Add Timer (independent)
    $("#add-timer").click(function () {
        addTimerField();
    });

    // --- Event Handler for Removing Fields (with Synchronization) ---

    $(document).on('click', '.remove-field', function() {
        const $button = $(this);
        const syncType = $button.data('sync-type'); // ingredient, step, or timer
        const $fieldToRemove = $button.closest('.dynamic-field');
        const $container = $fieldToRemove.parent();
        const containerId = $container.attr('id');

        // Check if it's the last field IN ITS CONTAINER
        if ($fieldToRemove.siblings('.dynamic-field').length === 0) {
             // Prevent removing the last field pair (or the last timer)
             let message = "You must keep at least one item.";
             if (typeof showMessage === 'function' && typeof currentTranslations !== 'undefined') {
                message = currentTranslations.messages?.cannot_remove_last || message;
                showMessage(message, 'error');
             } else {
                alert(message);
             }
             return; // Stop the removal
        }

        // Find the index of the field being removed within its container
        const indexToRemove = $fieldToRemove.index();

        // --- Synchronized Removal for Ingredients and Steps ---
        if (syncType === 'ingredient' || syncType === 'step') {
            let siblingContainerId;
            let $siblingContainer;

            if (containerId.includes('-fr')) {
                // Removing from French, find English sibling
                siblingContainerId = containerId.replace('-fr', '');
            } else {
                // Removing from English, find French sibling
                siblingContainerId = containerId + '-fr';
            }
            $siblingContainer = $('#' + siblingContainerId);

            // Find the corresponding field in the sibling container using the index
            const $siblingFieldToRemove = $siblingContainer.find('.dynamic-field').eq(indexToRemove);

            // Remove both fields
            $fieldToRemove.remove();
            $siblingFieldToRemove.remove(); // Remove the corresponding sibling

            // Reindex both containers
            reindexFields($container);
            reindexFields($siblingContainer);

        }
        // --- Independent Removal for Timers ---
        else if (syncType === 'timer') {
            $fieldToRemove.remove();
            reindexFields($container); // Reindex only the timer container
        }
         else {
             console.error("Unknown sync type or button missing data-sync-type attribute.");
         }
    });


    // --- Function to Reindex Fields within a specific container ---
    // This function now correctly updates names and placeholders based on the new index
    function reindexFields(container) {
        const containerId = container.attr('id');
        const fields = container.find('.dynamic-field');
        const fieldCount = fields.length; // Get the new count after removal

        fields.each(function(index) {
            const $field = $(this);

            if (containerId === 'ingredients-container') {
                $field.find('input[name^="ingredients["]').each(function() {
                    const oldName = $(this).attr('name');
                    const newName = oldName.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
                 // Update placeholder if needed (less critical maybe)
                 ingredientIndex = fieldCount; // Update the global counter to the new length
            } else if (containerId === 'ingredients-fr-container') {
                $field.find('input[name^="ingredientsFR["]').each(function() {
                    const oldName = $(this).attr('name');
                    const newName = oldName.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
                 ingredientFrIndex = fieldCount; // Update the global counter
            } else if (containerId === 'steps-container') {
                const textarea = $field.find('textarea');
                const oldName = textarea.attr('name');
                const newName = oldName.replace(/\[\d+\]/, '[' + index + ']');
                textarea.attr('name', newName).attr('placeholder', 'Step ' + (index + 1));
                 stepIndex = fieldCount; // Update the global counter
            } else if (containerId === 'steps-fr-container') {
                const textarea = $field.find('textarea');
                const oldName = textarea.attr('name');
                const newName = oldName.replace(/\[\d+\]/, '[' + index + ']');
                textarea.attr('name', newName).attr('placeholder', 'Étape ' + (index + 1));
                 stepFrIndex = fieldCount; // Update the global counter
            } else if (containerId === 'timers-container') {
                const input = $field.find('input[type="number"]');
                const oldName = input.attr('name');
                const newName = oldName.replace(/\[\d+\]/, '[' + index + ']');
                input.attr('name', newName).attr('placeholder', 'Timer for Step ' + (index + 1));
                 timerIndex = fieldCount; // Update the global counter
            }
        });

         // Ensure counters reflect the actual count after reindex
         ingredientIndex = $('#ingredients-container .dynamic-field').length;
         ingredientFrIndex = $('#ingredients-fr-container .dynamic-field').length;
         stepIndex = $('#steps-container .dynamic-field').length;
         stepFrIndex = $('#steps-fr-container .dynamic-field').length;
         timerIndex = $('#timers-container .dynamic-field').length;

         // console.log("Indices after reindex:", ingredientIndex, ingredientFrIndex, stepIndex, stepFrIndex, timerIndex);
    }

    // --- Initial Reindex on Page Load (important for modify page) ---
    // Reindex all containers to ensure indices are correct from the start
    reindexFields($('#ingredients-container'));
    reindexFields($('#ingredients-fr-container'));
    reindexFields($('#steps-container'));
    reindexFields($('#steps-fr-container'));
    reindexFields($('#timers-container'));

    // Log initial indices after load and reindex
    console.log("Initial Indices:", ingredientIndex, ingredientFrIndex, stepIndex, stepFrIndex, timerIndex);
});