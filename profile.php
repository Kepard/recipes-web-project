<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    // Redirect or show message for non-logged-in users
    // header('Location: index.php');
    // exit;
    $content = '
    <div class="profile-container">
        <p class="message info" data-translate="messages.login_to_view_profile">Please log in to view your profile.</p>
    </div>
    ';
    $title = "Profile";
} else {
    // Fetch user data from the session
    $username = htmlspecialchars($_SESSION['username']); // Sanitize output
    $role = htmlspecialchars($_SESSION['role']);     // Sanitize output

    // Basic HTML structure - content will be enhanced by JavaScript
    $content = '
    <div class="profile-container" id="profile-container">
        <h1 data-translate="labels.user_profile">User Profile</h1>
        <div class="profile-info">
            <p><strong data-translate="labels.username">Username:</strong> <span id="profileUsername">' . $username . '</span></p>
            <p><strong data-translate="labels.role">Role:</strong> <span id="userRole" data-role="' . $role . '">' . $role . '</span></p> <!-- Store original role maybe -->
        </div>
        <div class="profile-actions">
            <!-- Buttons are initially enabled, JS will disable them based on role -->
            <button id="requestChef" class="button button-primary action-button" data-translate="buttons.request_chef">Request Chef Role</button>
            <button id="requestTranslator" class="button button-primary action-button" data-translate="buttons.request_translator">Request Translator Role</button>
        </div>
    </div>
    ';
    $title = "My Profile";
}

include 'header.php';
?>

<script>
// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {

    // Only run profile logic if the container exists (i.e., user is logged in)
    if ($("#profile-container").length) {

        const userRoleElement = $("#userRole");
        const currentRole = userRoleElement.text(); // Get current role displayed

        const requestChefBtn = $("#requestChef");
        const requestTranslatorBtn = $("#requestTranslator");

        // Function to update button states based on role
        function updateButtonStates(role) {
            // Disable Chef request if already Chef, Admin, or requested Chef
            requestChefBtn.prop("disabled",
                role === "Chef" || role === "Administrateur" || role === "DemandeChef"
            );

            // Disable Translator request if already Translator, Admin, or requested Translator
            requestTranslatorBtn.prop("disabled",
                role === "Traducteur" || role === "Administrateur" || role === "DemandeTraducteur"
            );

             // Add visual cue for disabled buttons (handled by CSS :disabled pseudo-class now)
        }

        // Initial button state setup
        updateButtonStates(currentRole);

        // Handle button clicks
        requestChefBtn.on('click', function() {
             if (!$(this).prop('disabled')) { // Check if not disabled before sending request
                 updateRoleRequest("DemandeChef", translations);
             }
        });

        requestTranslatorBtn.on('click', function() {
            if (!$(this).prop('disabled')) { // Check if not disabled
                 updateRoleRequest("DemandeTraducteur", translations);
             }
        });
    }
} // End of initializePageContent


function updateRoleRequest(newRole, translations) {
    const username = $("#profileUsername").text(); // Get username from the page

     // Add confirmation before sending request
    const confirmMsg = (translations.messages?.confirm_role_request || "Are you sure you want to request the role: {role}?").replace('{role}', newRole);
    if (!confirm(confirmMsg)) {
        return; // Stop if user cancels
    }


    const $buttonToDisable = (newRole === "DemandeChef") ? $("#requestChef") : $("#requestTranslator");
    $buttonToDisable.prop('disabled', true).text('Sending...'); // Disable button during request

    $.ajax({
        url: "update_role.php", // Endpoint to handle role update for the *current* user
        method: "POST",
        data: {
            // Username is implicit from session on the backend for security,
            // but sending it can be useful if the endpoint is generic.
            // For a profile page, backend should use $_SESSION['username'].
            // username: username, // Optional: depends on backend implementation
            role: newRole
        },
        dataType: "json",
        success: function(response) {
            if (response.success && response.newRole) {
                // Update the role displayed on the page
                $("#userRole").text(response.newRole);

                // Update button states based on the new role
                updateButtonStates(response.newRole);

                // Show success message from response or default
                 showMessage(response.message || translations.messages?.role_request_sent || "Role request sent successfully!", 'success');
            } else {
                // Show error message from response or default
                showMessage(response.message || translations.messages?.error || "Failed to send role request.", 'error');
                 // Re-enable the specific button if the request failed
                 $buttonToDisable.prop('disabled', false).text(translations.buttons?.[newRole === "DemandeChef" ? 'request_chef' : 'request_translator'] || 'Request Role');
            }
        },
        error: function() {
            // Show generic error message
            showMessage(translations.messages?.error || "An error occurred while communicating with the server.", 'error');
            // Re-enable the specific button on error
            $buttonToDisable.prop('disabled', false).text(translations.buttons?.[newRole === "DemandeChef" ? 'request_chef' : 'request_translator'] || 'Request Role');
        }
        // No 'complete' needed here as success/error handle button state
    });
}

// Helper function to update button states - kept from original logic
function updateButtonStates(role) {
    const requestChefBtn = $("#requestChef");
    const requestTranslatorBtn = $("#requestTranslator");

    requestChefBtn.prop("disabled",
        role === "Chef" || role === "Administrateur" || role === "DemandeChef"
    );
    requestTranslatorBtn.prop("disabled",
        role === "Traducteur" || role === "Administrateur" || role === "DemandeTraducteur"
    );
}


</script>