$(document).ready(function () {
    // Initialize counters based on existing fields (for modify page)
    let ingredientIndex = $("#ingredients-container .dynamic-field").length || 1;
    let ingredientFrIndex = $("#ingredients-fr-container .dynamic-field").length || 1;
    let stepIndex = $("#steps-container .dynamic-field").length || 1;
    let stepFrIndex = $("#steps-fr-container .dynamic-field").length || 1;
    let timerIndex = $("#timers-container .dynamic-field").length || 1;

     // Helper function to create a field
    function createField(containerSelector, fieldHtml, indexVarName) {
        $(containerSelector).append(fieldHtml);
        // Increment the corresponding index using eval (or a more complex switch/if structure)
        // For simplicity here, we assume the index variables are globally accessible or passed appropriately
        // In a real scenario, might use data attributes or a more structured approach
         if (indexVarName === 'ingredientIndex') ingredientIndex++;
         else if (indexVarName === 'ingredientFrIndex') ingredientFrIndex++;
         else if (indexVarName === 'stepIndex') stepIndex++;
         else if (indexVarName === 'stepFrIndex') stepFrIndex++;
         else if (indexVarName === 'timerIndex') timerIndex++;
    }

    // Add ingredient field (English)
    $("#add-ingredient").click(function () {
        const newIngredient = `
            <div class="dynamic-field">
                <div class="ingredient">
                    <input type="text" name="ingredients[${ingredientIndex}][quantity]" placeholder="Quantity" required>
                    <input type="text" name="ingredients[${ingredientIndex}][name]" placeholder="Ingredient Name" required>
                    <input type="text" name="ingredients[${ingredientIndex}][type]" placeholder="Type">
                </div>
                <button type="button" class="remove-field button button-danger">×</button>
            </div>
        `;
        createField("#ingredients-container", newIngredient, 'ingredientIndex');
    });

    // Add ingredient field (French)
    $("#add-ingredient-fr").click(function () {
        const newIngredient = `
            <div class="dynamic-field">
                <div class="ingredient">
                    <input type="text" name="ingredientsFR[${ingredientFrIndex}][quantity]" placeholder="Quantité">
                    <input type="text" name="ingredientsFR[${ingredientFrIndex}][name]" placeholder="Nom ingrédient">
                    <input type="text" name="ingredientsFR[${ingredientFrIndex}][type]" placeholder="Type">
                </div>
                <button type="button" class="remove-field button button-danger">×</button>
            </div>
        `;
         createField("#ingredients-fr-container", newIngredient, 'ingredientFrIndex');
    });

    // Add step field (English)
    $("#add-step").click(function () {
        const newStep = `
            <div class="dynamic-field">
                <textarea name="steps[${stepIndex}]" placeholder="Step ${stepIndex + 1}" required></textarea>
                <button type="button" class="remove-field button button-danger">×</button>
            </div>
        `;
        createField("#steps-container", newStep, 'stepIndex');
    });

    // Add step field (French)
    $("#add-step-fr").click(function () {
        const newStep = `
            <div class="dynamic-field">
                <textarea name="stepsFR[${stepFrIndex}]" placeholder="Étape ${stepFrIndex + 1}"></textarea>
                <button type="button" class="remove-field button button-danger">×</button>
            </div>
        `;
        createField("#steps-fr-container", newStep, 'stepFrIndex');
    });

    // Add timer field
    $("#add-timer").click(function () {
        const newTimer = `
            <div class="dynamic-field">
                <input type="number" name="timers[${timerIndex}]" placeholder="Timer for Step ${timerIndex + 1}" required>
                <button type="button" class="remove-field button button-danger">×</button>
            </div>
        `;
        createField("#timers-container", newTimer, 'timerIndex');
    });

    // Remove field handler (works for all dynamic fields)
    // Use event delegation for dynamically added elements
    $(document).on('click', '.remove-field', function() {
        const container = $(this).closest('.dynamic-field').parent();
        // Check if it's the last field within its specific container
        if ($(this).closest('.dynamic-field').siblings('.dynamic-field').length > 0) {
            $(this).closest('.dynamic-field').remove();
            reindexFields(container); // Pass the container to reindex only relevant fields
        } else {
            // Optionally, provide feedback instead of alert for better UX
             console.warn("Cannot remove the last item.");
            // Or use the showMessage function if available globally
            if (typeof showMessage === 'function') {
                // Assuming translations are loaded and available
                // showMessage(translations.messages.cannot_remove_last || "Cannot remove the last item.", 'error');
            } else {
                 alert("You need to keep at least one item in this section.");
            }
        }
    });

    // Function to reindex fields within a specific container after removal
    function reindexFields(container) {
        const containerId = container.attr('id');

        if (containerId === 'ingredients-container') {
            container.find('.dynamic-field').each(function(index) {
                $(this).find('input[name^="ingredients["]').each(function() {
                    const newName = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
                 // Update placeholders if needed (though less critical here)
            });
            ingredientIndex = container.find('.dynamic-field').length; // Update counter
        } else if (containerId === 'ingredients-fr-container') {
            container.find('.dynamic-field').each(function(index) {
                $(this).find('input[name^="ingredientsFR["]').each(function() {
                    const newName = $(this).attr('name').replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                });
            });
            ingredientFrIndex = container.find('.dynamic-field').length; // Update counter
        } else if (containerId === 'steps-container') {
            container.find('.dynamic-field').each(function(index) {
                const textarea = $(this).find('textarea');
                const newName = textarea.attr('name').replace(/\[\d+\]/, '[' + index + ']');
                textarea.attr('name', newName).attr('placeholder', 'Step ' + (index + 1));
            });
             stepIndex = container.find('.dynamic-field').length; // Update counter
        } else if (containerId === 'steps-fr-container') {
            container.find('.dynamic-field').each(function(index) {
                const textarea = $(this).find('textarea');
                const newName = textarea.attr('name').replace(/\[\d+\]/, '[' + index + ']');
                textarea.attr('name', newName).attr('placeholder', 'Étape ' + (index + 1));
            });
            stepFrIndex = container.find('.dynamic-field').length; // Update counter
        } else if (containerId === 'timers-container') {
            container.find('.dynamic-field').each(function(index) {
                const input = $(this).find('input[type="number"]');
                const newName = input.attr('name').replace(/\[\d+\]/, '[' + index + ']');
                input.attr('name', newName).attr('placeholder', 'Timer for Step ' + (index + 1));
            });
            timerIndex = container.find('.dynamic-field').length; // Update counter
        }
    }

    // Initial reindex on page load for modify page
    reindexFields($('#ingredients-container'));
    reindexFields($('#ingredients-fr-container'));
    reindexFields($('#steps-container'));
    reindexFields($('#steps-fr-container'));
    reindexFields($('#timers-container'));
});