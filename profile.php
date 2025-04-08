<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    // Message for non-logged-in users
    $content = '
    <div class="profile-container">
        <div class="message error" data-translate="messages.login_to_view_profile">Please log in to view your profile.</div>
    </div>
    ';
    $title = "Profile";
} else {
    // Fetch user data from the session
    $username = htmlspecialchars($_SESSION['username']); // Sanitize output
    $role = htmlspecialchars($_SESSION['role']);     // Sanitize output

    // --- Start Building Content ---
    $content = '
    <div class="profile-container" id="profile-container">
        <h1 data-translate="labels.user_profile">User Profile</h1>
        <div class="profile-info">
            <p><strong data-translate="labels.username">Username:</strong> <span id="profileUsername">' . $username . '</span></p>
            <p><strong data-translate="labels.role">Role:</strong> <span id="userRole" data-role="' . $role . '">' . $role . '</span></p>
        </div>
        <div class="profile-actions">
            <button id="requestChef" class="button button-primary action-button" data-translate="buttons.request_chef">Request Chef Role</button>
            <button id="requestTranslator" class="button button-primary action-button" data-translate="buttons.request_translator">Request Translator Role</button>
        </div>'; // Keep content open

    // --- Add Chef's Pending Recipes Table (Conditionally) ---
    if ($role === 'Chef') {
        $content .= '
        <div class="chef-pending-recipes-section">
            <h2 data-translate="labels.chef_pending_recipes_title">My Recipes Pending Validation</h2>
            <table id="chef-pending-recipes-table">
                <thead>
                    <tr>
                        <th data-translate="labels.recipe_name">Recipe Name</th>
                        <th data-translate="labels.status">Status</th>
                        <!-- Add more columns if needed, e.g., Date Submitted -->
                    </tr>
                </thead>
                <tbody>
                    <!-- Rows will be added by JavaScript -->
                    <tr><td colspan="2" data-translate="messages.loading">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        ';
    }

    // --- Close Profile Container ---
    $content .= '
        <div class="easter-egg">
            <img src="https://media1.tenor.com/m/g37xCu5wzPIAAAAd/tayomaki-hasbulla.gif" alt="Hasbulla GIF">
        </div>
    </div>
    '; // Close the main profile container div
    $title = "My Profile";
}

include 'header.php';
?>

<script>
// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {

    // --- Profile Role Request Logic ---
    if ($("#profile-container").length) {
        const userRoleElement = $("#userRole");
        const currentRole = userRoleElement.text(); // Get current role displayed
        const requestChefBtn = $("#requestChef");
        const requestTranslatorBtn = $("#requestTranslator");

        function updateButtonStates(role) {
            requestChefBtn.prop("disabled", role === "Chef" || role === "Administrateur" || role === "DemandeChef");
            requestTranslatorBtn.prop("disabled", role === "Traducteur" || role === "Administrateur" || role === "DemandeTraducteur");
        }

        updateButtonStates(currentRole);

        requestChefBtn.on('click', function() {
             if (!$(this).prop('disabled')) { updateRoleRequest("DemandeChef", translations); }
        });
        requestTranslatorBtn.on('click', function() {
            if (!$(this).prop('disabled')) { updateRoleRequest("DemandeTraducteur", translations); }
        });

        // --- Chef's Pending Recipes Logic ---
        if (currentRole === 'Chef') {
            loadChefPendingRecipes(translations, lang);
        }
    }
} // End of initializePageContent


// --- Function to Load Chef's Pending Recipes ---
function loadChefPendingRecipes(translations, lang) {
    const chefUsername = $("#profileUsername").text();
    const $tableBody = $("#chef-pending-recipes-table tbody");

    // Show loading state
    $tableBody.html('<tr><td colspan="2">' + (translations.messages?.loading || 'Loading...') + '</td></tr>');

    $.getJSON("recipes.json?v=" + Date.now(), function (recipes) {
        const recipeArray = Array.isArray(recipes) ? recipes : Object.values(recipes);

        const pendingRecipes = recipeArray.filter(recipe =>
            recipe &&
            recipe.Author === chefUsername &&
            recipe.validated == 0 // Use == for potential string/number difference, === 0 is safer if validated is always number
        );

        $tableBody.empty(); // Clear loading row

        if (pendingRecipes.length === 0) {
            $tableBody.html('<tr><td colspan="2">' + (translations.messages?.no_pending_recipes || 'No recipes pending.') + '</td></tr>');
        } else {
            pendingRecipes.forEach(recipe => {
                 const recipeName = lang === "fr" && recipe.nameFR ? recipe.nameFR : recipe.name;
                 const statusText = translations.labels?.pending_validation || 'Pending Validation';

                 const row = `
                    <tr>
                        <td><a href="recipe.php?id=${recipe.id}">${recipeName || (translations.labels?.unnamed_recipe || 'Unnamed Recipe')}</a></td>
                        <td>${statusText}</td>
                    </tr>
                `;
                $tableBody.append(row);
            });
        }
    }).fail(function() {
        $tableBody.empty(); // Clear loading row
        $tableBody.html('<tr><td colspan="2" class="message error">' + (translations.messages?.error_loading_pending_recipes || 'Error loading recipes.') + '</td></tr>');
    });
}


// --- Function for Role Request AJAX ---
function updateRoleRequest(newRole, translations) {
    const username = $("#profileUsername").text();
    const confirmMsgKey = 'messages.confirm_role_request';
    const confirmMsgDefault = "Are you sure you want to request the role: {role}?";
    let confirmMsg = (getNestedTranslation(translations, confirmMsgKey) || confirmMsgDefault).replace('{role}', newRole);

    if (!confirm(confirmMsg)) { return; }

    const $buttonToDisable = (newRole === "DemandeChef") ? $("#requestChef") : $("#requestTranslator");
    const originalButtonTextKey = (newRole === "DemandeChef") ? 'buttons.request_chef' : 'buttons.request_translator';

    $buttonToDisable.prop('disabled', true);

    $.ajax({
        url: "update_role.php",
        method: "POST",
        data: { username: username, role: newRole },
        dataType: "json",
        success: function(response) {
            if (response.success && response.newRole) {
                $("#userRole").text(response.newRole);
                updateButtonStates(response.newRole); // Defined within initializePageContent scope or needs to be global
                showMessage(response.message || translations.messages?.role_request_sent || "Role request sent!", 'success');
            } else {
                showMessage(response.message || translations.messages?.error || "Failed request.", 'error');
                 // Re-enable button and restore text on failure
                 $buttonToDisable.prop('disabled', false).text(getNestedTranslation(translations, originalButtonTextKey) || (newRole === "DemandeChef" ? "Request Chef Role" : "Request Translator Role"));
            }
        },
        error: function() { // Handle AJAX errors
            showMessage(translations.messages?.error || "AJAX Error.", 'error');
             // Re-enable button and restore text on AJAX error
             $buttonToDisable.prop('disabled', false).text(getNestedTranslation(translations, originalButtonTextKey) || (newRole === "DemandeChef" ? "Request Chef Role" : "Request Translator Role"));
        }
        // Removed complete handler as success/error handle button state now
    });
}

// Helper function (either make updateButtonStates global or pass it if needed outside initializePageContent)
// For now, keep it assuming updateRoleRequest is called from within event handlers where updateButtonStates is accessible
function updateButtonStates(role) { // Duplicate for access outside initialize scope if needed, or structure differently
    $("#requestChef").prop("disabled", role === "Chef" || role === "Administrateur" || role === "DemandeChef");
    $("#requestTranslator").prop("disabled", role === "Traducteur" || role === "Administrateur" || role === "DemandeTraducteur");
}

</script>