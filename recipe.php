<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the recipe ID from the URL, ensuring it's an integer
$recipeId = filter_var($_GET['id'], FILTER_VALIDATE_INT);

// Get current user info
$currentUsername = $_SESSION['username'] ?? null;
$currentRole = $_SESSION['role'] ?? null;

// Load recipes
$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

foreach ($recipes as &$recipe) {
    if (isset($recipe['id']) && $recipe['id'] == $recipeId && $currentRole != 'Administrateur') {
        $isAuthor = isset($recipe['Author']) && $recipe['Author'] === $currentUsername;
        if ($recipe['validated'] == 0 && !$isAuthor) {
            $content = "<div class='message error'> You do not have permission to access this page.</div>";
            $title = "Unvalidated Recipe";
            include 'header.php';
            exit;
        }
        break;
    }
}

// Basic HTML structure - content will be dynamically generated
$content = '<div class="recipe-details" id="recipe-container"> </div>';

$title = "Recipe Details"; // Default title

include 'header.php'; // Include header AFTER setting content
?>

<script>
// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {
    const recipeId = <?php echo json_encode($recipeId); ?>;
    const currentUser = <?php echo json_encode($currentUsername); ?>;
    const currentRole = <?php echo json_encode($currentRole); ?>;
    const recipeContainer = $("#recipe-container");

    if (!recipeId) {
        recipeContainer.html(`<p class="message error">${translations.messages.recipe_not_found}</p>`);
        return;
    }

    // Load recipes data with cache busting
    $.getJSON("recipes.json?v=" + Date.now(), function(recipes) {
        // Ensure recipes is an array
        const recipeArray = Array.isArray(recipes) ? recipes : Object.values(recipes);
        // Find the selected recipe using strict comparison after ensuring ID is numeric
        const recipe = recipeArray.find(r => r && r.id === recipeId);

        if (!recipe) {
            recipeContainer.html(`<p class="message error">${translations.messages.recipe_not_found}</p>`);
        return;
    }

        // --- Prepare Recipe Data ---
        const recipeName = lang === 'fr' && recipe.nameFR ? recipe.nameFR : recipe.name;
        document.title = recipeName || "Recipe Details"; // Update page title

        // Use French fields if available and lang is 'fr', otherwise default to English/base fields
        const ingredients = (lang === 'fr' && Array.isArray(recipe.ingredientsFR) && recipe.ingredientsFR.length > 0) ? recipe.ingredientsFR : (recipe.ingredients || []);
        const steps = (lang === 'fr' && Array.isArray(recipe.stepsFR) && recipe.stepsFR.length > 0) ? recipe.stepsFR : (recipe.steps || []);
        // Assuming 'Without' doesn't have a specific French version in data structure, use base 'Without'
        const without = recipe.Without || [];
        const likes = recipe.likes || [];
        const comments = recipe.comments || [];

        const hasLiked = currentUser && Array.isArray(likes) && likes.includes(currentUser);
        const isAuthor = currentUser && recipe.Author === currentUser;
        const isAdmin = currentRole === 'Administrateur';
        const isTranslator = currentRole === 'Traducteur';
        const isChef = currentRole === 'Chef';

        // --- Build HTML ---

        // Role Actions Buttons
        let roleActionsHTML = '<div class="role-actions">';
        if (isAdmin) {
            roleActionsHTML += `<a href="modify_recipe.php?id=${recipe.id}" class="button button-primary admin-button" data-translate="buttons.modify_recipe">${translations.buttons.modify_recipe}</a>`;
            roleActionsHTML += `<a href="translate_recipe.php?id=${recipe.id}" class="button button-secondary action-button" data-translate="buttons.translate_recipe">${translations.buttons.translate_recipe}</a>`;
            roleActionsHTML += `<a href="remove_recipe.php?id=${recipe.id}" class="button button-danger admin-button" onclick="return confirm('${translations.messages.confirm_remove_recipe}');" data-translate="buttons.remove_recipe">${translations.buttons.remove_recipe}</a>`;
        } else if (isChef && isAuthor) {
            roleActionsHTML += `<a href="modify_recipe.php?id=${recipe.id}" class="button button-primary action-button" data-translate="buttons.modify_recipe">${translations.buttons.modify_recipe}</a>`;
        }
        // Translate button available for Translator, or Chef who is the Author
        if (isTranslator || (isChef && isAuthor)) {
             roleActionsHTML += `<a href="translate_recipe.php?id=${recipe.id}" class="button button-secondary action-button" data-translate="buttons.translate_recipe">${translations.buttons.translate_recipe}</a>`;
        }
        roleActionsHTML += '</div>';


        // Calculate total time
        let totalTime = 0;
        if (Array.isArray(recipe.timers)) {
            totalTime = recipe.timers.reduce((sum, timer) => sum + (parseInt(timer, 10) || 0), 0);
        }

        // Ingredients list
        const ingredientsListHTML = ingredients.map(ing => {
            // Handle both object and simple string ingredients
            const ingredientText = (typeof ing === 'object' && ing !== null)
                ? `${ing.quantity || ''} ${ing.name || ''}`.trim() // Display quantity and name
                : (typeof ing === 'string' ? ing : ''); // Display string directly
            return `<li>${ingredientText || (translations.labels.unknown_ingredient)}</li>`;
        }).join('');

        // Steps with timers
        const stepsListHTML = steps.map((step, index) => {
            const timerValue = recipe.timers && recipe.timers[index] ? parseInt(recipe.timers[index], 10) : 0;
            const timerHTML = timerValue > 0
                ? `<span class="timer">${timerValue} <span data-translate="labels.minutes">${translations.labels.minutes}</span></span>`
                : '';
            // Ensure step is a string before displaying
            const stepText = typeof step === 'string' ? step : (translations.labels.invalid_step);
            return `<li>${stepText}${timerHTML}</li>`;
        }).join('');

        // Comments section
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

        // Comment form or login prompt
        const commentFormHTML = currentUser ? `
            <form id="commentForm">
                <textarea id="commentInput" placeholder="${translations.placeholders.add_comment}" required></textarea>
                <input type="text" id="imageUrlInput" placeholder="${translations.placeholders.image_url}">
                <label for="imageFileInput">${translations.labels.upload_image}</label>
                <input type="file" id="imageFileInput" accept="image/*">
                <button type="submit" class="button button-primary">${translations.buttons.post_comment}</button>
            </form>
        ` : `<p data-translate="messages.login_to_comment">${translations.messages.login_to_comment}</p>`;

        // Build the complete recipe HTML
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
                     <button id="like-button" class="like-button ${hasLiked ? 'liked' : ''}" ${!currentUser ? 'disabled' : ''} title="${!currentUser ? (translations.messages.login_to_like) : ''}">
                         ❤️ <span class="like-count">${likes.length}</span>
                     </button>
                 </div>
            </div>

            <div class="comments-section">
                <h2><span data-translate="labels.comments">${translations.labels.comments}</span> <span id="commentCount">(${comments.length})</span></h2>
                <div id="commentsList">${commentsListHTML || `<p>${translations.messages?.no_comments}</p>`}</div>
                ${commentFormHTML}
            </div>
        `;

        recipeContainer.html(recipeHTML);

        // --- Event Handlers ---
        setupLikeButton(recipeId, currentUser, translations);
        setupCommentForm(recipeId, currentUser, translations);

    });
}

function setupLikeButton(recipeId, currentUser, translations) {
    $("#like-button").off('click').on('click', function() {
        if (!currentUser) {
            showMessage(translations.messages.login_to_like, 'error');
            return;
        }

        const $button = $(this);
        $button.prop('disabled', true);

        $.ajax({
            url: 'like_recipe.php',
            method: 'POST',
            data: { id: recipeId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $button.toggleClass('liked', response.action === 'liked');
                    $button.find('.like-count').text(response.likeCount);
                } else {
                    showMessage(translations.messages.error_occurred, 'error');
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
}

function setupCommentForm(recipeId, currentUser, translations) {
    $("#commentForm").off('submit').on('submit', function(e) {
        e.preventDefault();

        if (!currentUser) {
             showMessage(translations.messages.login_to_comment, 'error');
             return;
        }

        const commentText = $("#commentInput").val().trim();
        const imageFile = $("#imageFileInput")[0].files[0];
        const imageUrl = $("#imageUrlInput").val().trim();

        if (!commentText) {
            showMessage(translations.messages.enter_comment, 'error');
            return;
        }

        const formData = new FormData();
        formData.append("id", recipeId);
        formData.append("comment", commentText);

        // Prefer uploaded file over URL if both are provided
        if (imageFile) {
            formData.append("image", imageFile);
        } else if (imageUrl) {
            formData.append("imageURL", imageUrl);
        }

        const $submitButton = $(this).find('button[type="submit"]');

        $.ajax({
            url: "comment.php",
            type: "POST",
            data: formData,
            processData: false, // Important for FormData
            contentType: false, // Important for FormData
            dataType: "json",
            success: function(response) {
                if (response && response.success) {
                    const newCommentHTML = `
                        <div class="comment" style="display: none;">
                            <strong>${response.author}:</strong>
                            <span>${response.content}</span>
                            ${response.imageurl ? `
                            <div class="comment-image">
                                <img src="${response.imageurl}" alt="Comment image" onerror="this.style.display='none'">
                            </div>` : ''}
                            <small>${response.date || new Date().toLocaleString()}</small>
                        </div>
                    `;

                    // Append and fade in the new comment
                    const $newComment = $(newCommentHTML);
                    $("#commentsList").append($newComment);
                    $newComment.fadeIn();


                    // Clear the form
                    $("#commentInput").val("");
                    $("#imageUrlInput").val("");
                    $("#imageFileInput").val(""); // Clear file input

                    // Update comment count
                    const $commentCountSpan = $("#commentCount");
                    const currentCountMatch = $commentCountSpan.text().match(/\d+/);
                    const currentCount = currentCountMatch ? parseInt(currentCountMatch[0], 10) : 0;
                    $commentCountSpan.text(`(${currentCount + 1})`);

                     showMessage(translations.messages.comment_posted, 'success');
                }
            },
            complete: function() {
                 $submitButton.prop('disabled', false).text(translations.buttons.post_comment);
            }
        });
    });
}

</script>