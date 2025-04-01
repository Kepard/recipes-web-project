<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is an admin
// Use strict comparison and check if session variable exists
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    // Redirect or display error message instead of dying immediately
    // header('Location: index.php'); // Or an access denied page
    // exit;
     // For SPA feel, we might let the JS handle this, but PHP check is safer
     $content = '<div class="message error">Access Denied. You must be an Administrator.</div>';
} else {
    // Basic HTML structure - content will be generated dynamically
    $content = '
    <div class="admin-container">
        <h1 data-translate="labels.manage_users">Manage Users</h1>
        <table id="users-table">
            <thead>
                <tr>
                    <th data-translate="labels.username">Username</th>
                    <th data-translate="labels.role">Role</th>
                    <th data-translate="labels.actions">Actions</th>
                </tr>
            </thead>
            <tbody><!-- User rows will be added here --></tbody>
        </table>

        <h1 data-translate="labels.unvalidated_recipes">Unvalidated Recipes</h1>
         <table id="recipes-table">
            <thead>
                <tr>
                    <th data-translate="labels.recipe_name">Recipe Name</th>
                    <th data-translate="labels.author">Author</th>
                    <th data-translate="labels.actions">Actions</th>
                </tr>
            </thead>
            <tbody><!-- Recipe rows will be added here --></tbody>
        </table>
    </div>
    ';
}

$title = "Admin Panel"; // Set a title for the page
include 'header.php'; // Include header AFTER setting content
?>

<script>
// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {
    const adminContainer = $(".admin-container");
    const usersTableBody = $("#users-table tbody");
    const recipesTableBody = $("#recipes-table tbody");

    // Clear previous content to prevent duplication on language change
    usersTableBody.empty();
    recipesTableBody.empty();

    // --- Load Users ---
    $.getJSON("users.json?v=" + Date.now(), function (users) { // Add cache buster
        const availableRoles = ["Administrateur", "Traducteur", "Chef", "DemandeChef", "DemandeTraducteur", "Cuisinier"];

        for (const username in users) {
            if (users.hasOwnProperty(username)) {
                 const user = users[username];
                 const currentRole = user.role;

                 // Create role options, translating the display text
                 const roleOptions = availableRoles.map(availableRole => {
                     const translatedRole = translations.roles?.[availableRole] || availableRole;
                     const selected = availableRole === currentRole ? "selected" : "";
                     return `<option value="${availableRole}" ${selected}>${translatedRole}</option>`;
                 }).join("");

                 const row = `
                    <tr data-username="${username}">
                        <td>${username}</td>
                        <td>
                            <select class="role-select">
                                ${roleOptions}
                            </select>
                        </td>
                        <td>
                            <button class="update-password button button-secondary" data-username="${username}" data-translate="buttons.update_password">${translations.buttons?.update_password || 'Update Password'}</button>
                            <button class="remove-user button button-danger" data-username="${username}" data-translate="buttons.remove_user">${translations.buttons?.remove_user || 'Remove User'}</button>
                        </td>
                    </tr>
                `;
                usersTableBody.append(row);
            }
        }
    }).fail(function() {
        showMessage(translations.messages?.error || "Failed to load users.", 'error');
    });

     // --- Load Unvalidated Recipes ---
     $.getJSON("recipes.json?v=" + Date.now(), function (recipes) { // Add cache buster
        // Ensure recipes is an array
        const recipeArray = Array.isArray(recipes) ? recipes : Object.values(recipes);
        const unvalidatedRecipes = recipeArray.filter(recipe => recipe && recipe.validated == 0); // Check recipe exists

        if (unvalidatedRecipes.length === 0) {
             recipesTableBody.html(`<tr><td colspan="3">${translations.messages?.no_unvalidated_recipes || 'No recipes waiting for validation.'}</td></tr>`);
         } else {
             unvalidatedRecipes.forEach(recipe => {
                 const recipeName = lang === "fr" && recipe.nameFR ? recipe.nameFR : recipe.name;
                 const row = `
                     <tr data-id="${recipe.id}">
                         <td><a href="recipe.php?id=${recipe.id}">${recipeName || (translations.labels?.unnamed_recipe || 'Unnamed Recipe')}</a></td>
                         <td>${recipe.Author || (translations.labels?.unknown || 'Unknown')}</td>
                         <td>
                             <button class="validate-recipe button button-success" data-id="${recipe.id}" data-translate="buttons.validate">${translations.buttons?.validate || 'Validate'}</button>
                         </td>
                     </tr>
                 `;
                 recipesTableBody.append(row);
             });
         }
     }).fail(function() {
         showMessage(translations.messages?.error || "Failed to load recipes.", 'error');
     });


    // --- Event Handlers (using event delegation) ---

    // Handle role changes
    usersTableBody.on("change", ".role-select", function() {
        const username = $(this).closest("tr").attr("data-username");
        const newRole = $(this).val();

        $.ajax({
            url: "update_users.php",
            method: "POST",
            data: {
                action: "update_role",
                username: username,
                role: newRole
            },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    showMessage(translations.messages?.role_updated || response.message || "Role updated!", 'success');
                } else {
                    showMessage(response.message || translations.messages?.error || "Failed to update role.", 'error');
                     // Revert dropdown if update failed? Optional.
                }
            },
            error: function() {
                 showMessage(translations.messages?.error || "Error communicating with server.", 'error');
            }
        });
    });

    // Handle password updates
    usersTableBody.on("click", ".update-password", function() {
        const username = $(this).attr("data-username");
        const promptMessage = (translations.messages?.enter_new_password || "Enter new password for {username}:").replace('{username}', username);
        const newPassword = prompt(promptMessage);

        if (newPassword) { // Only proceed if a password was entered
             if (newPassword.length < 4) { // Basic validation example
                 showMessage(translations.messages?.password_too_short || "Password should be at least 4 characters.", 'error');
                 return;
            }
            $.ajax({
                url: "update_users.php",
                method: "POST",
                data: {
                    action: "update_password",
                    username: username,
                    password: newPassword
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        showMessage(translations.messages?.password_updated || response.message || "Password updated!", 'success');
                    } else {
                        showMessage(response.message || translations.messages?.error || "Failed to update password.", 'error');
                    }
                },
                error: function() {
                     showMessage(translations.messages?.error || "Error communicating with server.", 'error');
                }
            });
        }
    });

    // Handle user removal
    usersTableBody.on("click", ".remove-user", function() {
        const username = $(this).attr("data-username");
        const confirmMessage = (translations.messages?.confirm_remove_user || "Are you sure you want to remove {username}?").replace('{username}', username);

        if (confirm(confirmMessage)) {
            $.ajax({
                url: "update_users.php",
                method: "POST",
                data: {
                    action: "remove_user",
                    username: username
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        showMessage(translations.messages?.user_removed || response.message || "User removed!", 'success');
                        // Remove the row from the table
                        $(`tr[data-username="${username}"]`).fadeOut(500, function() { $(this).remove(); });
                    } else {
                        showMessage(response.message || translations.messages?.error || "Failed to remove user.", 'error');
                    }
                },
                error: function() {
                     showMessage(translations.messages?.error || "Error communicating with server.", 'error');
                }
            });
        }
    });

     // Handle recipe validation
    recipesTableBody.on("click", ".validate-recipe", function() {
        const recipeId = $(this).attr("data-id");
        const $button = $(this); // Reference the button
        const $row = $button.closest("tr");

        $button.prop('disabled', true).text('Validating...'); // Provide visual feedback

        $.ajax({
            url: "update_recipes.php",
            method: "POST",
            data: { id: recipeId },
            // dataType: "text", // Expecting simple text response based on update_recipes.php
            success: function(response) {
                showMessage(response || translations.messages?.recipe_validated || "Recipe validated!", 'success');
                $row.fadeOut(500, function() { $(this).remove(); });
                 // Check if table is empty after removal
                 if (recipesTableBody.find('tr').length === 0) {
                     recipesTableBody.html(`<tr><td colspan="3">${translations.messages?.no_unvalidated_recipes || 'No recipes waiting for validation.'}</td></tr>`);
                 }
            },
            error: function() {
                 showMessage(translations.messages?.error || "Failed to validate recipe.", 'error');
                 $button.prop('disabled', false).text(translations.buttons?.validate || 'Validate'); // Re-enable button
            }
            // Using simple $.post is okay too, but $.ajax gives more control (like error handling)
            /*
            $.post("update_recipes.php", { id: recipeId }, function(response) {
                showMessage(response, 'success'); // Show simple response message
                $row.fadeOut(500, function() { $(this).remove(); });
            }).fail(function() {
                 showMessage(translations.messages?.error || "Failed to validate recipe.", 'error');
                 $button.prop('disabled', false).text(translations.buttons?.validate || 'Validate');
            });
            */
        });
    });

} // End of initializePageContent
</script>