<?php
/**
 * Affiche les détails d'une recette spécifique, commentaires et actions possibles.
 */

// Démarrage session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupération données et utilisateur 
$recipeId = (int) $_GET['id'];
$currentUsername = $_SESSION['username'] ?? null;
$currentRole = $_SESSION['role'] ?? null;

//  Vérification de connexion 
if (!isset($currentUsername)) {
    header("Location: index.php"); // Rediriger si non connecté
    exit;
}

//  Chargement de la recette---
$recipesFile = 'recipes.json';
$recipes = [];
$recipeData = null; // Contiendra les données de la recette trouvée
$allRecipes = json_decode(file_get_contents($recipesFile), true);

// Recherche simplifiée de la recette par ID
    foreach ($allRecipes as $recipe) {
        if (isset($recipe['id']) && $recipe['id'] == $recipeId) {
            $recipeData = $recipe;
            break;
        }
    }


//  Vérification d'accès pour recette non validée 
$isAuthor = isset($recipeData['Author']) && $recipeData['Author'] === $currentUsername;
$isAdmin = $currentRole === 'Administrateur';

if (!$isAdmin && ($recipeData['validated'] ?? 1) == 0 && !$isAuthor) {
    die("Vous n'avez pas la permission d'accéder à cette page.");
}


// Préparation structure HTML 
$content = '<div class="recipe-details" id="recipe-container"></div>';
$title = "Détails de la recette"; // Titre par défaut 

//  Inclusion Header
include 'header.php';
?>

<script>
/**
 * Initialise le contenu de la page après chargement des traductions.
 */
function initializePageContent(translations, lang) {
    const recipeId = <?php echo json_encode($recipeId); ?>;
    const currentUser = <?php echo json_encode($currentUsername); ?>;
    const currentRole = <?php echo json_encode($currentRole); ?>;
    const recipeContainer = $("#recipe-container");

   
    // Chargement AJAX 
    $.getJSON("recipes.json?v=" + Date.now(), function(recipes) {
        const recipe = recipes.find(r => r.id === recipeId); 

        // Préparation des données 
        const recipeName = lang === 'fr' && recipe.nameFR ? recipe.nameFR : recipe.name;
        document.title = recipeName; // Definir le titre de la page

        const ingredients = (lang === 'fr' && recipe.ingredientsFR) ? recipe.ingredientsFR : (recipe.ingredients || []);
        const steps = (lang === 'fr' && recipe.stepsFR) ? recipe.stepsFR : (recipe.steps || []);
        const without = recipe.Without || [];
        const likes = recipe.likes || [];
        const comments = recipe.comments || [];
        const timers = recipe.timers || []; 

        const hasLiked = currentUser && likes.includes(currentUser);
        const isAuthor = currentUser && recipe.Author === currentUser;
        const isAdmin = currentRole === 'Administrateur';
        const isTranslator = currentRole === 'Traducteur';
        const isChef = currentRole === 'Chef';

        // Construction des éléments HTML (peut être optimisé)

        // 1. Actions (Modify, Translate, Remove)
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

        // 2. Calcul temps total
        let totalTime = timers.reduce((sum, timer) => sum + (parseInt(timer, 10) || 0), 0);

        // 3. Liste Ingrédients ---
        let ingredientsSectionHTML = '';
        // Filtre d'abord les ingrédients non vides
        const validIngredients = ingredients.filter(ing => (ing.quantity || ing.name));

        if (validIngredients.length > 0) {
            // Construit la liste UNIQUEMENT avec les ingrédients valides (car nous avons souvent des champs vides)
            const listItems = validIngredients.map(ing => {
                const ingredientText = `${ing.quantity || ''} ${ing.name || ''}`.trim();
                return `<li>${ingredientText}</li>`;
            }).join('');

            ingredientsSectionHTML = `
                <h2><span data-translate="labels.ingredients">${translations.labels.ingredients}</span></h2>
                <ul>${listItems}</ul>`;
        } // Si validIngredients est vide, la section ne sera pas affichée
        else {
            ingredientsSectionHTML = `
                <h2><span data-translate="labels.ingredients">${translations.labels.ingredients}</span></h2>
                <p class="message info">${translations.messages?.no_ingredients_found}</p> `;
        }

        // 4. Liste Étapes ---
        let stepsSectionHTML = '';
        // Filtre d'abord les étapes non vides
        const validSteps = steps.filter(step => step.trim()); 
        if (validSteps.length > 0) {
            // Construit la liste UNIQUEMENT avec les étapes valides
            const listItems = validSteps.map((step, index) => {
                const timerValue = timers[index] ? parseInt(timers[index], 10) : 0; 
                const timerHTML = timerValue > 0
                    ? `<span class="timer">${timerValue} <span data-translate="labels.minutes">${translations.labels.minutes}</span></span>`
                    : '';
                // Afficher l'étape (qui est forcément non vide ici)
                return `<li>${step}${timerHTML}</li>`;
            }).join('');
            stepsSectionHTML = `
                <h2><span data-translate="labels.steps">${translations.labels?.steps || 'Steps'}</span></h2>
                <ol>${listItems}</ol>`;
        } // Si validSteps est vide
        else {
            stepsSectionHTML = `
                 <h2><span data-translate="labels.steps">${translations.labels.steps}</span></h2>
                 <p class="message info">${translations.messages.no_steps_found}</p> `;
        }

        // 5. Liste Commentaires
        let commentsListHTML = '';
        if (comments.length > 0) {
            commentsListHTML = comments.map(comment => `
                <div class="comment">
                    <strong>${comment.author || translations.labels.unknown}:</strong>
                    <span>${comment.content || ''}</span>
                    <div class="comment-image"><img src="${comment.imageurl}" alt="Comment image" onerror="this.style.display='none';"></div>
                    <small>${comment.date || ''}</small>
                </div>
            `).join('');
        } else {
             commentsListHTML = `<p>${translations.messages.no_comments}</p>`; // Message si vide
        }

        // 6. Formulaire Commentaire
        const commentFormHTML = currentUser ? `
            <form id="commentForm">
                <textarea id="commentInput" placeholder="${translations.placeholders.add_comment}" required></textarea>
                <input type="text" id="imageUrlInput" placeholder="${translations.placeholders.image_url}">
                <label for="imageFileInput">${translations.labels.upload_image}</label>
                <input type="file" id="imageFileInput" name="image" accept="image/*">
                <button type="submit" class="button button-primary">${translations.buttons.post_comment}</button>
            </form>
        ` : `<p data-translate="messages.login_to_comment">${translations.messages.login_to_comment}</p>`;

        // 7. Assemblage final
        const recipeHTML = `
            ${roleActionsHTML}
            <h1>${recipeName}</h1>
            <img src="${recipe.imageURL || 'placeholder.png'}" alt="${recipeName}" onerror="this.src='placeholder.png';">

            ${ingredientsSectionHTML}
            ${stepsSectionHTML}

            <div class="recipe-footer">
                <p><strong><span data-translate="labels.author">${translations.labels.author}:</span></strong> ${recipe.Author || (translations.labels.unknown)}</p>
                <p><strong><span data-translate="labels.dietary_restrictions">${translations.labels.dietary_restrictions}:</span></strong> ${without.join(', ') || (translations.labels.none)}</p>
                <p><strong><span data-translate="labels.total_time">${translations.labels.total_time}:</span></strong> ${totalTime} <span data-translate="labels.minutes">${translations.labels.minutes}</span></p>
                <div class="like-section">
                     <button id="like-button" class="like-button ${hasLiked ? 'liked' : ''}" title="${!currentUser ? translations.messages.login_to_like : ''}">
                         ❤️ <span class="like-count">${likes.length}</span>
                     </button>
                 </div>
            </div>

            <div class="comments-section">
                <h2><span data-translate="labels.comments">${translations.labels.comments}</span> <span id="commentCount">(${comments.length})</span></h2>
                <div id="commentsList">${commentsListHTML}</div> 
                ${commentFormHTML}
            </div>
        `;

        // Injection HTML
        recipeContainer.html(recipeHTML);

        // Initialisation handlers 
        setupLikeButton(recipeId, currentUser, translations);
        setupCommentForm(recipeId, currentUser, translations);

    }); 
} 

/**
 * Attache la logique au bouton "Like".
 */
function setupLikeButton(recipeId, currentUser, translations) {
    $("#like-button").off('click').on('click', function() {
        if (!currentUser) {
            showMessage(translations.messages.login_to_like, 'error');
            return;
        }
        const $button = $(this);
        $button.prop('disabled', true);
        $.ajax({
            url: 'like_recipe.php', method: 'POST', data: { id: recipeId }, dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $button.toggleClass('liked', response.action === 'liked');
                    $button.find('.like-count').text(response.likeCount);
                }
            },
            complete: function() { $button.prop('disabled', false); }
        });
    });
} 

/**
 * Attache la logique à la soumission du formulaire de commentaire.
 */
function setupCommentForm(recipeId, currentUser, translations) {
    $("#commentForm").off('submit').on('submit', function(e) {
        e.preventDefault(); // Eviter d'envoyer le commentaire vide
        if (!currentUser) {
            showMessage(translations.messages.login_to_comment, 'error');
            return;
        }
        const commentText = $("#commentInput").val().trim();
        const imageFile = $("#imageFileInput")[0].files[0];
        const imageUrl = $("#imageUrlInput").val().trim();

        if (!commentText) { // Vérifie juste si le texte est là
            showMessage(translations.messages.enter_comment, 'error');
            return;
        }

        const formData = new FormData(); // FormData est bien ici, car sauvegarde image potentielle
        formData.append("id", recipeId);
        formData.append("comment", commentText);

        if (imageFile) { formData.append("image", imageFile); }
        else if (imageUrl) { formData.append("imageURL", imageUrl); }

        const $submitButton = $(this).find('button[type="submit"]');
        const originalButtonText = $submitButton.html(); 
        $submitButton.prop('disabled', true).html(translations.buttons.posting_comment); 

        $.ajax({
            url: "comment.php", 
            type: "POST", 
            data: formData,
            processData: false, // Pour ne pas convertir l'image en string
            contentType: false, // Afin de denifir le Content-Type image automatiquement
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    const newCommentHTML = `
                        <div class="comment" style="display: none;">
                            <strong>${response.author}:</strong> <span>${response.content}</span>
                            ${response.imageurl ? `<div class="comment-image"><img src="${response.imageurl}" alt="Image commentaire" onerror="this.style.display='none';"></div>` : ''}
                            <small>${response.date || new Date().toLocaleString()}</small>
                        </div>
                    `;
                    const $newComment = $(newCommentHTML);
                    $("#commentsList").append($newComment);
                    $newComment.fadeIn();
                    // Vide formulaire
                    $("#commentInput").val(""); $("#imageUrlInput").val(""); $("#imageFileInput").val("");
                    // Met à jour compteur
                    const $commentCountSpan = $("#commentCount");
                    const currentCount = parseInt($commentCountSpan.text().replace(/\D/g,'')) || 0; // Extrait nombre plus simple
                    // Incremente le nombre de commentaires sur la page, sans recharger
                    $commentCountSpan.text(`(${currentCount + 1})`);

                    showMessage(translations.messages.comment_posted, 'success');
                }
            },
            complete: function() { $submitButton.prop('disabled', false).html(originalButtonText); }
        });
    });
} 

</script>