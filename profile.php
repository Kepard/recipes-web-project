<?php
/**
 * Affiche la page de profil de l'utilisateur connecté.
 * Permet de voir son rôle, de demander un changement de rôle (Chef/Traducteur)
 * et, si l'utilisateur est Chef, affiche ses recettes en attente de validation.
 */

// Démarrage session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Vérification de connexion ---
// Vérifie si l'utilisateur est connecté.
if (!isset($_SESSION['username'])) {
    // Si non connecté, affiche un message d'erreur.
    $content = '
    <div class="profile-container">
        <div class="message error" data-translate="messages.login_to_view_profile">Veuillez vous connecter pour voir votre profil.</div>
    </div>
    ';
    $title = "Profil"; // Titre pour utilisateur non connecté
} 

    // Si connecté, récupère les informations de l'utilisateur depuis la session.
    // htmlspecialchars est utilisé pour sécuriser l'affichage.
    $username = htmlspecialchars($_SESSION['username']);
    $role = htmlspecialchars($_SESSION['role']);

    // --- Construction du contenu HTML pour utilisateur connecté ---
    $content = '
    <div class="profile-container" id="profile-container">
        <h1 data-translate="labels.user_profile">Profil Utilisateur</h1>
        <div class="profile-info">
            <p><strong data-translate="labels.username">Nom d\'utilisateur:</strong> <span id="profileUsername">' . $username . '</span></p>
            <p><strong data-translate="labels.role">Rôle:</strong> <span id="userRole" data-role="' . $role . '">' . $role . '</span></p>
        </div>
        <div class="profile-actions">
            <button id="requestChef" class="button button-primary action-button" data-translate="buttons.request_chef">Demander Rôle Chef</button>
            <button id="requestTranslator" class="button button-primary action-button" data-translate="buttons.request_translator">Demander Rôle Traducteur</button>
        </div>'; // Le div principal reste ouvert pour ajouter la section Chef potentiellement

    // --- Section spécifique pour les Chefs ---
    // Si l'utilisateur a le rôle 'Chef', ajoute une table pour ses recettes non validées.
    if ($role === 'Chef') {
        $content .= '
        <div class="chef-pending-recipes-section">
            <h2 data-translate="labels.chef_pending_recipes_title">Mes Recettes en Attente de Validation</h2>
            <table id="chef-pending-recipes-table">
                <thead>
                    <tr>
                        <th data-translate="labels.recipe_name">Nom de la recette</th>
                        <th data-translate="labels.status">Statut</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        ';
    }

    // --- Fermeture du conteneur principal et Easter Egg ---
    $content .= '
        <div class="easter-egg">
            <img src="https://media1.tenor.com/m/g37xCu5wzPIAAAAd/tayomaki-hasbulla.gif" alt="Hasbulla GIF">
        </div>
    </div>
    '; // Ferme div.profile-container
    $title = "Mon Profil"; // Titre pour utilisateur connecté


// Inclusion de l'en-tête (qui affichera $content)
include 'header.php';
?>

<script>
/**
 * Initialise le contenu JS de la page après chargement des traductions.
 * Gère la logique des boutons de demande de rôle et le chargement des recettes en attente pour les chefs.
 */
function initializePageContent(translations, lang) {

    // Vérifie si le conteneur du profil existe (càd, si l'utilisateur est connecté)
    if ($("#profile-container").length) {
        // Récupère le rôle actuel affiché pour la logique JS
        const currentRole = $("#userRole").text();
        const requestChefBtn = $("#requestChef");
        const requestTranslatorBtn = $("#requestTranslator");

        /**
         * Met à jour l'état (activé/désactivé) des boutons de demande de rôle
         * en fonction du rôle actuel de l'utilisateur.
         * On ne peut pas demander un rôle qu'on a déjà, ou si on est admin, ou si une demande est déjà en cours.
         * @param {string} role Le rôle actuel de l'utilisateur.
         */
        function updateButtonStates(role) {
            requestChefBtn.prop("disabled", role === "Chef" || role === "Administrateur" || role === "DemandeChef");
            requestTranslatorBtn.prop("disabled", role === "Traducteur" || role === "Administrateur" || role === "DemandeTraducteur");
        }

        // Met à jour l'état initial des boutons au chargement
        updateButtonStates(currentRole);

        // Attache les événements 'click' aux boutons de demande
        requestChefBtn.on('click', function() {
             // Ne lance l'appel AJAX que si le bouton n'est pas désactivé
             if (!$(this).prop('disabled')) { updateRoleRequest("DemandeChef", translations); }
        });
        requestTranslatorBtn.on('click', function() {
            if (!$(this).prop('disabled')) { updateRoleRequest("DemandeTraducteur", translations); }
        });

        // --- Logique spécifique aux Chefs : Chargement des recettes en attente ---
        if (currentRole === 'Chef') {
            loadChefPendingRecipes(translations, lang);
        }
    }
} // Fin de initializePageContent


/**
 * Charge et affiche les recettes créées par le Chef qui sont en attente de validation (validated === 0).
 */
function loadChefPendingRecipes(translations, lang) {
    // Récupère le nom d'utilisateur du chef depuis la page
    const chefUsername = $("#profileUsername").text();
    // Cible le corps du tableau où insérer les lignes
    const $tableBody = $("#chef-pending-recipes-table tbody");

    // Charge les recettes via AJAX
    $.getJSON("recipes.json?v=" + Date.now(), function (recipes) {
        // Vide le tableau avant d'ajouter les nouvelles lignes (évite duplication si rechargement)
        $tableBody.empty();

        // Filtre les recettes pour ne garder que celles de ce chef et non validées
        const pendingRecipes = recipes.filter(recipe =>
            recipe.Author === chefUsername &&
            (recipe.validated === 0 || recipe.validated === "0") // Gère 0 numérique ou chaîne
        );

        // Si aucune recette en attente trouvée
        if (pendingRecipes.length === 0) {
            // Affiche un message dans le tableau
            $tableBody.html('<tr><td colspan="2">' + (translations.messages.no_pending_recipes) + '</td></tr>');
        } else {
            // Sinon, pour chaque recette en attente, crée une ligne de tableau
            pendingRecipes.forEach(recipe => {
                 // Choix du nom selon la langue
                 const recipeName = lang === "fr" && recipe.nameFR ? recipe.nameFR : recipe.name;
                 // Texte du statut (traduit)
                 const statusText = translations.labels.pending_validation;
                 // Construction de la ligne HTML
                 const row = `
                    <tr>
                        <td><a href="recipe.php?id=${recipe.id}">${recipeName}</a></td>
                        <td>${statusText}</td>
                    </tr>
                `;
                // Ajoute la ligne au tableau
                $tableBody.append(row);
            });
        }
    });
}


/**
 * Envoie une requête AJAX pour demander un nouveau rôle (DemandeChef ou DemandeTraducteur).
 * @param {string} newRole Le rôle demandé ("DemandeChef" ou "DemandeTraducteur").
 * @param {object} translations L'objet de traductions.
 */
function updateRoleRequest(newRole, translations) {
    // Récupère le nom d'utilisateur depuis la page
    const username = $("#profileUsername").text();
    // Message de confirmation traduit
    let confirmMsg = (getNestedTranslation(translations, 'messages.confirm_role_request')).replace('{role}', newRole);

    // Demande confirmation à l'utilisateur
    if (!confirm(confirmMsg)) { return; } // Arrête si annulation

    // Cible le bouton correspondant pour le désactiver
    const $buttonToDisable = (newRole === "DemandeChef") ? $("#requestChef") : $("#requestTranslator");
    // Désactive le bouton pendant l'appel AJAX
    $buttonToDisable.prop('disabled', true);

    // Appel AJAX vers le script PHP qui gère la mise à jour du rôle (maintenant update_users.php)
    $.ajax({
        url: "update_users.php", // URL du script PHP unifié
        method: "POST",
        data: { action: "request_role", username: username, role: newRole }, // Action spécifique + données
        dataType: "json",
        success: function(response) {
            // Si la requête réussit et renvoie le nouveau rôle
            if (response.success && response.newRole) {
                // Met à jour le rôle affiché sur la page
                $("#userRole").text(response.newRole);
                // Met à jour l'état des boutons (désactive celui qui vient d'être cliqué)
                updateButtonStates(response.newRole);
                // Affiche un message de succès
                showMessage(translations.messages.role_request_sent, 'success');
            }
        }
    });
}

/**
 * Fonction utilitaire pour mettre à jour l'état des boutons de demande de rôle.
 */
function updateButtonStates(role) {
    $("#requestChef").prop("disabled", role === "Chef" || role === "Administrateur" || role === "DemandeChef");
    $("#requestTranslator").prop("disabled", role === "Traducteur" || role === "Administrateur" || role === "DemandeTraducteur");
}

</script>