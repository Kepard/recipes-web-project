<?php
// Démarrer la session seulement si aucune session n'est déjà active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifier que l'utilisateur a le role d'administrateur
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrateur') {
    header('HTTP/1.1 403 Forbidden');
    die();
}

// Structure HTML basique dans laquelle sera genere le contenu
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


$title = "Admin Panel"; // Titre de la page
include 'header.php'; 
?>

<script>
    // Fonction d'initialisation appellee directement dans header.php
function initializePageContent(translations, lang) {
    const adminContainer = $(".admin-container");
    const usersTableBody = $("#users-table tbody");
    const recipesTableBody = $("#recipes-table tbody");

    // Nettoyer le contenu precedent pour eviter la duplication lors du changement de la langue
    usersTableBody.empty();
    recipesTableBody.empty();

    // Charger les utilisateurs depuis le JSON en utilisant un cache buster
    $.getJSON("users.json?v=" + Date.now(), function (users) { // Date.now pour interdire de charger depuis le cache (bugs d'affichage)
        const availableRoles = ["Administrateur", "Traducteur", "Chef", "DemandeChef", "DemandeTraducteur", "Cuisinier"];

        for (const username in users) {
                 const user = users[username];
                 const currentRole = user.role;

                 // Creation d'option de roles avec les traductions
                 const roleOptions = availableRoles.map(availableRole => {
                     const translatedRole = translations.roles?.[availableRole];
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
    });

     // --- Charger les recettes en attente de validation ---
     $.getJSON("recipes.json?v=" + Date.now(), function (recipes) { // Add cache buster
        const unvalidatedRecipes = recipes.filter(recipe => recipe.validated == 0); 

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


    // Event Handlers 

    // Changer le role
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

    // Mettre a jour le mot de passe
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

    // Supprimer l'utilisateur
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
                        // Supprimer la ligne dans le tableau avec une animation
                        $(`tr[data-username="${username}"]`).fadeOut(500, function() { $(this).remove(); });
                    }
                },
            });
        }
    });

     // Valider une recette en attente
    recipesTableBody.on("click", ".validate-recipe", function() {
        const recipeId = $(this).attr("data-id");
        const $button = $(this); // Reference button
        const $row = $button.closest("tr");

        $.ajax({
            url: "update_recipes.php",
            method: "POST",
            data: { id: recipeId },
            success: function(response) {
                showMessage(translations.messages.recipe_validated, 'success');
                $row.fadeOut(500, function() { $(this).remove(); });
                 // Verifier qu'il reste des recettes en attente, sinon afficher le message
                 if (recipesTableBody.find('tr').length === 0) {
                     recipesTableBody.html(`<tr><td colspan="3">${translations.messages.no_unvalidated_recipes}</td></tr>`);
                 }
            }
        });
    });

} 
</script>