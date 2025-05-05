<?php
/**
 * Affiche les détails d'une recette spécifique, commentaires et actions possibles.
 */

// Démarrage session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupération ID recette et infos utilisateur
$recipeId = (int) $_GET['id'];
$currentUsername = $_SESSION['username'] ?? null;
$currentRole = $_SESSION['role'] ?? null;

// Chargement des recettes
$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

// --- Vérification d'accès aux recettes non validées ---
// Bloque l'accès aux recettes non validées si l'utilisateur n'est ni l'auteur, ni admin.
foreach ($recipes as $recipe) { 
    if (isset($recipe['id']) && $recipe['id'] == $recipeId && $currentRole != 'Administrateur') {
        $isAuthor = isset($recipe['Author']) && $recipe['Author'] === $currentUsername;
        if (($recipe['validated'] ?? 1) == 0 && !$isAuthor) { // Ajout ?? 1 par sécurité
            $content = "<div class='message error'> Vous n'avez pas la permission d'accéder à cette page.</div>"; // Message direct
            $title = "Recette non validée";
            include 'header.php';
            exit;
        }
        break;
    }
    elseif (isset($recipe['id']) && $recipe['id'] == $recipeId && $currentRole == 'Administrateur') {
         break; // L'admin a accès, on sort
    }
}

// Structure HTML de base (contenu injecté par JS)
$content = '<div class="recipe-details" id="recipe-container"> </div>';
$title = "Détails de la recette"; // Titre par défaut

// Inclusion de l'en-tête
include 'header.php';
?>

<script>
/**
 * Initialise le contenu de la page après chargement des traductions.
 * Récupère les détails de la recette via AJAX et construit l'affichage HTML.
 */
function initializePageContent(translations, lang) {
    // Récupération variables PHP
    const recipeId = <?php echo json_encode($recipeId); ?>;
    const currentUser = <?php echo json_encode($currentUsername); ?>;
    const currentRole = <?php echo json_encode($currentRole); ?>;
    const recipeContainer = $("#recipe-container"); // Conteneur cible

    // Gestion ID invalide
    if (!recipeId) {
        recipeContainer.html(`<p class="message error">${translations.messages.recipe_not_found}</p>`);
        return;
    }

    // Chargement AJAX des données de recettes (avec cache busting)
    $.getJSON("recipes.json?v=" + Date.now(), function(recipes) {
        // Recherche de la recette spécifique
        const recipe = recipes.find(r => r.id === recipeId);

        // Gestion recette non trouvée
        if (!recipe) {
            recipeContainer.html(`<p class="message error">${translations.messages.recipe_not_found}</p>`);
            return;
        }

        // --- Préparation des données pour affichage (selon langue) ---
        const recipeName = lang === 'fr' && recipe.nameFR ? recipe.nameFR : recipe.name;
        document.title = recipeName; // Met à jour titre onglet
        // Choix des ingrédients/étapes selon la langue
        const ingredients = (lang === 'fr' && recipe.ingredientsFR.length > 0) ? recipe.ingredientsFR : (recipe.ingredients || []);
        const steps = (lang === 'fr' && recipe.stepsFR.length > 0) ? recipe.stepsFR : (recipe.steps || []);
        const without = recipe.Without || [];
        const likes = recipe.likes || [];
        const comments = recipe.comments || [];

        // --- Détermination des permissions/états utilisateur ---
        const hasLiked = currentUser && likes.includes(currentUser);
        const isAuthor = currentUser && recipe.Author === currentUser;
        const isAdmin = currentRole === 'Administrateur';
        const isTranslator = currentRole === 'Traducteur';
        const isChef = currentRole === 'Chef';

        // --- Construction dynamique du HTML ---
        // (Les commentaires sur la construction HTML PHP ont été omis comme demandé)
        let roleActionsHTML = '<div class="role-actions">';
        if (isAdmin) {
            roleActionsHTML += `<a href="modify_recipe.php?id=${recipe.id}" class="button button-primary admin-button" data-translate="buttons.modify_recipe">${translations.buttons.modify_recipe}</a>`;
            roleActionsHTML += `<a href="translate_recipe.php?id=${recipe.id}" class="button button-secondary action-button" data-translate="buttons.translate_recipe">${translations.buttons.translate_recipe}</a>`;
            roleActionsHTML += `<a href="remove_recipe.php?id=${recipe.id}" class="button button-danger admin-button" onclick="return confirm('${translations.messages.confirm_remove_recipe}');" data-translate="buttons.remove_recipe">${translations.buttons.remove_recipe}</a>`;
        } else if (isChef && isAuthor) {
            roleActionsHTML += `<a href="modify_recipe.php?id=${recipe.id}" class="button button-primary action-button" data-translate="buttons.modify_recipe">${translations.buttons.modify_recipe}</a>`;
        }
        if (isTranslator || (isChef && isAuthor && !isAdmin)) {
             roleActionsHTML += `<a href="translate_recipe.php?id=${recipe.id}" class="button button-secondary action-button" data-translate="buttons.translate_recipe">${translations.buttons.translate_recipe}</a>`;
        }
        roleActionsHTML += '</div>';

        let totalTime = recipe.timers.reduce((sum, timer) => sum + (parseInt(timer, 10) || 0), 0);

        const ingredientsListHTML = ingredients.map(ing => {
            const ingredientText = `${ing.quantity || ''} ${ing.name || ''}`.trim();
            return `<li>${ingredientText || (translations.labels.unknown_ingredient)}</li>`;
        }).join('');

        const stepsListHTML = steps.map((step, index) => {
            const timerValue = recipe.timers && recipe.timers[index] ? parseInt(recipe.timers[index], 10) : 0;
            const timerHTML = timerValue > 0
                ? `<span class="timer">${timerValue} <span data-translate="labels.minutes">${translations.labels.minutes}</span></span>`
                : '';
            return `<li>${step}${timerHTML}</li>`;
        }).join('');

        const commentsListHTML = comments.map(comment => `
            <div class="comment">
                <strong>${comment.author}:</strong>
                <span>${comment.content}</span>
                ${comment.imageurl ? `
                <div class="comment-image">
                    <img src="${comment.imageurl}" alt="Comment image" onerror="this.style.display='none'">
                </div>` : ''}
                <small>${comment.date || ''}</small>
            </div>
        `).join('');

        const commentFormHTML = currentUser ? `
            <form id="commentForm">
                <textarea id="commentInput" placeholder="${translations.placeholders.add_comment}" required></textarea>
                <input type="text" id="imageUrlInput" placeholder="${translations.placeholders.image_url}">
                <label for="imageFileInput">${translations.labels.upload_image}</label>
                <input type="file" id="imageFileInput" name="image" accept="image/*">
                <button type="submit" class="button button-primary">${translations.buttons.post_comment}</button>
            </form>
        ` : `<p data-translate="messages.login_to_comment">${translations.messages.login_to_comment}</p>`;

        const recipeHTML = `
            ${roleActionsHTML}
            <h1>${recipeName}</h1>
            <img src="${recipe.imageURL || 'placeholder.png'}" alt="${recipeName}" onerror="this.onerror=null;this.src='placeholder.png';">

            <h2><span data-translate="labels.ingredients">${translations.labels.ingredients}</span></h2>
            <ul>${ingredientsListHTML || `<li>${translations.labels.no_ingredients}</li>`}</ul>

            <h2><span data-translate="labels.steps">${translations.labels.steps}</span></h2>
            <ol>${stepsListHTML || `<li>${translations.labels.no_steps}</li>`}</ol>

            <div class="recipe-footer">
                <p><strong><span data-translate="labels.author">${translations.labels.author}:</span></strong> ${recipe.Author || translations.labels.unknown}</p>
                <p><strong><span data-translate="labels.dietary_restrictions">${translations.labels.dietary_restrictions}:</span></strong> ${without.join(', ') || translations.labels.none}</p>
                <p><strong><span data-translate="labels.total_time">${translations.labels.total_time}:</span></strong> ${totalTime} <span data-translate="labels.minutes">${translations.labels.minutes}</span></p>
                <div class="like-section">
                     <button id="like-button" class="like-button ${hasLiked ? 'liked' : ''}" title="${!currentUser ? (translations.messages.login_to_like) : ''}">
                         ❤️ <span class="like-count">${likes.length}</span>
                     </button>
                 </div>
            </div>

            <div class="comments-section">
                <h2><span data-translate="labels.comments">${translations.labels.comments}</span> <span id="commentCount">(${comments.length})</span></h2>
                <div id="commentsList">${commentsListHTML || `<p>${translations.messages.no_comments}</p>`}</div>
                ${commentFormHTML}
            </div>
        `;

        // Injection HTML dans la page
        recipeContainer.html(recipeHTML);

        // --- Initialisation des gestionnaires d'événements ---
        setupLikeButton(recipeId, currentUser, translations);
        setupCommentForm(recipeId, currentUser, translations);

    });
} // Fin initializePageContent

/**
 * Attache la logique au bouton "Like".
 */
function setupLikeButton(recipeId, currentUser, translations) {
    // Attachement de l'événement click (évite doublons avec .off().on())
    $("#like-button").off('click').on('click', function() {
        // Vérif connexion
        if (!currentUser) { 
            showMessage(translations.messages.login_to_like, 'error'); 
            return; 
        }

        const $button = $(this);
        $button.prop('disabled', true); // Désactive bouton

        // Appel AJAX vers like_recipe.php
        $.ajax({
            url: 'like_recipe.php', 
            method: 'POST', 
            data: { id: recipeId }, 
            dataType: 'json',
            success: function(response) {
                if (response.success) { // Met à jour UI si succès
                    $button.toggleClass('liked', response.action === 'liked');
                    $button.find('.like-count').text(response.likeCount);
                } 
            },
            complete: function() { $button.prop('disabled', false); } // Réactive bouton
        });
    });
} // Fin setupLikeButton

/**
 * Attache la logique à la soumission du formulaire de commentaire.
 * Gère la récupération des données et l'envoi via FormData (pour l'image).
 */
function setupCommentForm(recipeId, currentUser, translations) {
    // Attachement de l'événement submit (évite doublons avec .off().on())
    $("#commentForm").off('submit').on('submit', function(e) {
        e.preventDefault(); // Empêche rechargement
        // Vérif connexion
        if (!currentUser) { showMessage(translations.messages.login_to_comment, 'error'); return; }
        // Récup données formulaire
        const commentText = $("#commentInput").val().trim();
        const imageFile = $("#imageFileInput")[0].files[0];
        const imageUrl = $("#imageUrlInput").val().trim();
        // Vérif commentaire non vide
        if (!commentText) { showMessage(translations.messages.enter_comment, 'error'); return; }

        // Création FormData
        const formData = new FormData();
        formData.append("id", recipeId);
        formData.append("comment", commentText);
        if (imageFile) { formData.append("image", imageFile); }
        else if (imageUrl) { formData.append("imageURL", imageUrl); }

        // Feedback bouton submit
        const $submitButton = $(this).find('button[type="submit"]');
        const originalButtonText = $submitButton.html();
        $submitButton.prop('disabled', true).html(translations.buttons.posting_comment);

        // Appel AJAX vers comment.php
        $.ajax({
            url: "comment.php", type: "POST", data: formData,
            processData: false, contentType: false, dataType: "json",
            success: function(response) {
                if (response.success) {
                    // Construit et ajoute nouveau commentaire au DOM
                    const newCommentHTML = `
                        <div class="comment" style="display: none;">
                            <strong>${response.author}:</strong> <span>${response.content}</span>
                            ${response.imageurl ? `<div class="comment-image"><img src="${response.imageurl}" alt="Image commentaire" onerror="this.style.display='none'"></div>` : ''}
                            <small>${response.date || new Date().toLocaleString()}</small>
                        </div>
                    `;
                    const $newComment = $(newCommentHTML);
                    if ($("#commentsList").find('.comment').length === 0 && $("#commentsList").find('p').length > 0) { $("#commentsList").empty(); } // Enlève "pas de comm"
                    $("#commentsList").append($newComment);
                    $newComment.fadeIn();
                    // Vide formulaire
                    $("#commentInput").val(""); $("#imageUrlInput").val(""); $("#imageFileInput").val("");
                    // Met à jour compteur
                    const $commentCountSpan = $("#commentCount");
                    const currentCountMatch = $commentCountSpan.text().match(/\d+/);
                    const currentCount = currentCountMatch ? parseInt(currentCountMatch[0], 10) : 0;
                    $commentCountSpan.text(`(${currentCount + 1})`);
                    // Message succès
                    showMessage(translations.messages.comment_posted, 'success');
                }
            },
            complete: function() { $submitButton.prop('disabled', false).html(originalButtonText); } // Réactive bouton
        });
    });
} // Fin setupCommentForm

</script>