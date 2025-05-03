<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Recettes de Mamie'; ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link rel="icon" href="favicon.ico" type="image/x-icon" />
</head>
<body>

<?php
    // Start session only if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $username = $_SESSION['username'] ?? '';
    $role = $_SESSION['role'] ?? '';
?>

<div id="snow-container"></div>

<header>
    <nav>
        <a href="index.php" class="logo">
            <img src="favicon.ico" alt="Logo" class="logo-image">
            <span class="page-title"> Recettes de Mamie </span>
        </a>

        <?php if (empty($role)): ?>
            <!-- Navbar for logged-out users -->
            <div class="auth-container">
                <form id="auth">
                    <label id="lusername" data-translate="labels.lusername">Username:</label>
                    <input type="text" id="username" name="username" required>
                    <label id="lpassword" data-translate="labels.lpassword">Password:</label>
                    <input type="password" id="password" name="password" required>
                    <button type="button" id="login" class="button button-primary" data-translate="buttons.login">Log In</button>
                    <button type="button" id="signup" class="button button-secondary" data-translate="buttons.signup">Sign Up</button>
                </form>

                <!-- Role selection (initially hidden) -->
                <div id="role-selection" style="display: none;"> <!-- Hidden by default -->
                     <label data-translate="labels.select_role">Select Request (optional):</label>
                     <div class="role-options">
                        <div class="role-radio">
                            <input type="radio" id="roleDemandeChef" name="roleRequest" value="DemandeChef">
                            <label for="roleDemandeChef" data-translate="roles.DemandeChef">Request Chef Role</label> <!-- Update data-translate key -->
                        </div>
                        <div class="role-radio">
                            <input type="radio" id="roleDemandeTraducteur" name="roleRequest" value="DemandeTraducteur">
                            <label for="roleDemandeTraducteur" data-translate="roles.DemandeTraducteur">Request Translator Role</label> <!-- Update data-translate key -->
                        </div>
                    </div>
                    <button type="button" id="validate-signup" class="button button-primary" data-translate="buttons.validate">Validate</button>
                </div>
            </div>
        <?php else: ?>
            <!-- Navbar for logged-in users -->
            <div class="logged-in-nav">
                <a href="profile.php" class="button button-primary profile-button" data-translate="buttons.profile">My Profile</a>
                <form id="logout-form" action="logout.php" method="POST"> 
                    <button type="submit" id="logout" class="button button-secondary" data-translate="buttons.logout">Logout</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Role-based button -->
        <div class="role-lang-container">
             <?php if (!empty($role)): ?>
                 <?php if ($role === 'Chef'): ?>
                     <a href="create_recipe.php" class="button button-primary action-button" data-translate="buttons.create_recipe">Create recipe</a>
                 <?php elseif ($role === 'Administrateur'): ?>
                     <a href="admin.php" class="button button-primary action-button" data-translate="buttons.admin_panel">Admin panel</a>
                 <?php endif; ?>
             <?php endif; ?>
             <button id="changeLang" class="button button-primary" aria-label="Change language" data-translate="buttons.changeLang">Version en français</button>
        </div>
    </nav>

    <!-- Message Container for AJAX feedback -->
    <div id="message-container"></div>

</header>

<main>
    <?php echo $content ?? '<div class="message error">ERROR LOADING CONTENT</div>'; // Display error in message style ?>
</main>

<footer>
    <p>© <?php echo date('Y'); ?> Gorelov Bogdan | Université Paris-Saclay</p>
</footer>


<script>
// Global variable for translation data
let datafile;
let currentLang = localStorage.getItem("lang") || "fr";
let currentTranslations; // To store translations for the current language

// Function to safely get nested translation keys
function getNestedTranslation(translations, keyString) {
    if (!translations || !keyString) return null;
    const keys = keyString.split('.');
    let result = translations;
    try {
        for (const key of keys) {
            result = result[key];
            if (result === undefined) return null; // Key not found
        }
        return typeof result === 'string' ? result : null; // Return only if it's a string
    } catch (e) {
        console.warn(`Error accessing translation key: ${keyString}`, e);
        return null;
    }
}


// Function to translate static elements on the page
function translatePage(translations) {
    if (!translations) return;
    currentTranslations = translations; // Store for global use (e.g., in showMessage)

    $('[data-translate]').each(function() {
        const key = $(this).data('translate');
        const translation = getNestedTranslation(translations, key);

        if (translation !== null) {
            if ($(this).is('input[type="text"], input[type="password"], input[type="search"], textarea')) {
                $(this).attr('placeholder', translation);
            } else if ($(this).is('button, a, span, label, h1, h2, h3, h4, h5, h6, p, strong, th, option')) {
                 // Use html() to allow for potential HTML entities like ❤️
                 $(this).html(translation);
            } else {
                 $(this).text(translation); // Fallback for other elements
            }
        } else {
             // Keep existing text or placeholder if translation not found
             console.warn(`Translation not found for key: ${key}`);
        }
    });
}

// Function to display messages dynamically
function showMessage(message, type = 'info') { // Default type 'info' if needed
    // Use currentTranslations for error messages if message key is provided
    const messageText = getNestedTranslation(currentTranslations?.messages, message) || message;

    const messageElement = `<div class="message ${type}">${messageText}</div>`;
    const $messageContainer = $('#message-container');

    $messageContainer.append(messageElement);
    const $newMessage = $messageContainer.children().last();

    // Fade out and remove the message
    setTimeout(() => {
        $newMessage.fadeOut(500, function() {
            $(this).remove();
        });
    }, 3500); // Keep message slightly longer
}

$(document).ready(function () {
    // --- Initialization ---
    $.getJSON("data.json", function (data) {
        datafile = data; // Store all translations
        let translations = datafile[currentLang];

        // 1. Translate static elements
        translatePage(translations);

        // 2. Initialize page-specific content and interactions
        //    This function MUST be defined in the specific page's script (index.php, admin.php, etc.)
        if (typeof initializePageContent === 'function') {
            initializePageContent(translations, currentLang);
        } else {
            console.warn("Function initializePageContent() is not defined for this page.");
        }

    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error("Failed to load data.json:", textStatus, errorThrown);
        showMessage("Failed to load language data.", "error");
    });

    // --- Event Handlers ---

    // Language Change
    $("#changeLang").click(function () {
        currentLang = (currentLang === "en") ? "fr" : "en";
        localStorage.setItem("lang", currentLang);
        let translations = datafile[currentLang];

        translatePage(translations); // Re-translate static parts

        // Re-initialize page-specific content with the new language
        if (typeof initializePageContent === 'function') {
            initializePageContent(translations, currentLang);
        }
    });

    // Authentication Logic
    $('#auth').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            // Check if signup fields are visible, if so, trigger validate, otherwise login
            if ($('#role-selection').is(':visible')) {
                 $('#validate-signup').click();
            } else {
                sendAuthRequest('login');
            }
        }
    });

    $('#login').click(function () {
        sendAuthRequest('login');
    });

    $('#signup').click(function () {
        // Toggle role selection visibility
         $('#role-selection').toggle(); // Use toggle for show/hide
    });

    $('#validate-signup').click(function() {
        const username = $('#username').val().trim();
        const password = $('#password').val().trim();
        let role = $('input[name="roleRequest"]:checked').val() || "Cuisinier"; // Default role

        if (!username || !password) {
            showMessage(currentTranslations.messages.missing_fields, 'error');
            return;
        }

        $.ajax({
            url: 'auth.php',
            method: 'POST',
            data: {
                action: 'signup',
                username: username,
                password: password,
                role: role
            },
            dataType: 'json', // Expect JSON response
            success: function (response) {
                if (response.success) {
                    showMessage(response.message, 'success');
                    $('#role-selection').hide(); // Hide selection on success
                    $('#username').val(''); // Clear fields
                    $('#password').val('');
                    $('input[name="roleRequest"]').prop('checked', false); // Uncheck radio
                    // Optionally log the user in automatically after signup
                }
            }
        });
    });

    function sendAuthRequest(action) {
        const username = $('#username').val().trim();
        const password = $('#password').val().trim();

        if (!username || !password) {
            showMessage(currentTranslations.messages.missing_fields, 'error');
            return;
        }

        $.ajax({
            url: 'auth.php',
            method: 'POST',
            data: {
                action: action,
                username: username,
                password: password
            },
            dataType: 'json', // Expect JSON response
            success: function (response) {
                if (response.success) {
                    if (action === 'login') {
                        // Login success message is good, but reload handles the UI update
                        showMessage(response.message, 'success'); // Optional: show message before reload
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500); // 1.5 second delay before reloading
                    }
                    // No specific action needed for signup success here, handled by validate-signup handler
                } else {
                    showMessage(response.message, 'error');
                }
            }
        });
    }
});

</script>
</body>
</html>