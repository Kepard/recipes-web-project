<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is an admin
// Use strict comparison and check if session variable exists
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    header('HTTP/1.1 403 Forbidden');
    die();
    
} else {
    // Basic HTML structure - content will be generated dynamically inside corresponding containers
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
            <tbody> </tbody>
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
            <tbody> </tbody>
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
                            <button class="update-password button button-secondary" data-username="${username}" data-translate="buttons.update_password">${translations.buttons.update_password}</button>
                            <button class="remove-user button button-danger" data-username="${username}" data-translate="buttons.remove_user">${translations.buttons.remove_user}</button>
                        </td>
                    </tr>
                `;
                usersTableBody.append(row);
            }
        }
    });

     // --- Load Unvalidated Recipes ---
     $.getJSON("recipes.json?v=" + Date.now(), function (recipes) { // Add cache buster
        // Ensure recipes is an array
        const recipeArray = Array.isArray(recipes) ? recipes : Object.values(recipes);
        const unvalidatedRecipes = recipeArray.filter(recipe => recipe && recipe.validated == 0); // Check recipe exists

        if (unvalidatedRecipes.length === 0) {
             recipesTableBody.html(`<tr><td colspan="3">${translations.messages.no_unvalidated_recipes}</td></tr>`);
         } else {
             unvalidatedRecipes.forEach(recipe => {
                 const recipeName = lang === "fr" && recipe.nameFR ? recipe.nameFR : recipe.name;
                 const row = `
                     <tr data-id="${recipe.id}">
                         <td><a href="recipe.php?id=${recipe.id}">${recipeName}</a></td>
                         <td>${recipe.Author || translations.labels.unknown}</td>
                         <td>
                             <button class="validate-recipe button button-success" data-id="${recipe.id}" data-translate="buttons.validate">${translations.buttons.validate}</button>
                         </td>
                     </tr>
                 `;
                 recipesTableBody.append(row);
             });
         }
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
                    showMessage(translations.messages.role_updated, 'success');
                }
            }
        });
    });

    // Handle password updates
    usersTableBody.on("click", ".update-password", function() {
        const username = $(this).attr("data-username");
        const promptMessage = (translations.messages.enter_new_password).replace('{username}', username);
        const newPassword = prompt(promptMessage);

        if (newPassword) { // Only proceed if a password was entered
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
                        showMessage(translations.messages.password_updated, 'success');
                    }
                }
            });
        }
    });

    // Handle user removal
    usersTableBody.on("click", ".remove-user", function() {
        const username = $(this).attr("data-username");
        const confirmMessage = (translations.messages.confirm_remove_user).replace('{username}', username);

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
                        showMessage(translations.messages.user_removed, 'success');
                        // Remove the row from the table
                        $(`tr[data-username="${username}"]`).fadeOut(500, function() { $(this).remove(); });
                    }
                },
            });
        }
    });

     // Handle recipe validation
    recipesTableBody.on("click", ".validate-recipe", function() {
        const recipeId = $(this).attr("data-id");
        const $button = $(this); // Reference the button
        const $row = $button.closest("tr");

        $.ajax({
            url: "update_recipes.php",
            method: "POST",
            data: { id: recipeId },
            // dataType: "text", // Expecting simple text response based on update_recipes.php
            success: function(response) {
                showMessage(translations.messages.recipe_validated, 'success');
                $row.fadeOut(500, function() { $(this).remove(); });
                 // Check if table is empty after removal
                 if (recipesTableBody.find('tr').length === 0) {
                     recipesTableBody.html(`<tr><td colspan="3">${translations.messages.no_unvalidated_recipes}</td></tr>`);
                 }
            }
        });
    });

} 
</script>