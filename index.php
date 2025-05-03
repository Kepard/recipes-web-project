<?php
    // Start session only if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Get username from session, default to empty string if not set
    $username = $_SESSION['username'] ?? '';
    $role = $_SESSION['role'] ?? ''; // Also get role if needed

    // Define page content
    $content = '
    <div class="search-container">
        <input type="text" id="search-input" placeholder="Search recipes..." data-translate="labels.search_placeholder">
        <button id="search-button" class="button button-primary" data-translate="buttons.search">Search</button>
    </div>

    <div class="grid-container" id="recipe-grid">
        
        <p id="loading-message">Loading recipes...</p> 
    </div>
    ';

    $title = "Recettes de Mamie"; // Set page title
    include 'header.php'; // Include header AFTER setting content
?>

<script>
// Global variables for recipes and translations
let allRecipes = [];
let currentUser = <?php echo json_encode($username); ?>; // Get username from PHP

// This function is called by header.php after translations are loaded
function initializePageContent(translations, lang) {
    currentTranslations = translations; // Store translations globally for this page
    const gridContainer = $("#recipe-grid");
    const loadingMessage = $("#loading-message");

    gridContainer.empty().append(loadingMessage); // Clear grid and show loading message

    // Load recipes using AJAX with cache busting
    $.getJSON("recipes.json?v=" + Date.now(), function (recipes) {

        console.log("Recipes loaded successfully."); // Message de succès pour le debug
        loadingMessage.hide();
        displayRecipes(allRecipes, lang);

        // Ensure recipes is an array
        allRecipes = Array.isArray(recipes) ? recipes : Object.values(recipes);
        loadingMessage.hide(); // Hide loading message
        displayRecipes(allRecipes, lang); // Display all validated recipes initially
        setupSearch(); // Setup search functionality
        setupLikeButtons(); // Setup like button listeners
    });
};

function displayRecipes(recipesToDisplay, lang) {
    const gridContainer = $("#recipe-grid");
    gridContainer.empty(); // Clear previous recipes

    // Filter for validated recipes only
    const validatedRecipes = recipesToDisplay.filter(recipe => recipe && recipe.validated === 1);

    if (validatedRecipes.length === 0) {
        gridContainer.html(`<p class="message info">${currentTranslations.messages.no_recipes_found}</p>`);
        return;
    }

    // Build and append recipe cards
    validatedRecipes.forEach(recipe => {
        const recipeName = lang === "fr" && recipe.nameFR ? recipe.nameFR : recipe.name;
        // Defensive check for likes array
        const likesArray = Array.isArray(recipe.likes) ? recipe.likes : [];
        const commentsArray = Array.isArray(recipe.comments) ? recipe.comments : [];

        const hasLiked = currentUser && likesArray.includes(currentUser);
        const likedClass = hasLiked ? 'liked' : '';

        // Calculate total time safely
        let totalTime = 0;
        if (Array.isArray(recipe.timers)) {
            totalTime = recipe.timers.reduce((sum, timer) => sum + (parseInt(timer, 10) || 0), 0);
        }

        // Dietary restrictions - join if array, else show default
         const restrictions = Array.isArray(recipe.Without) && recipe.Without.length > 0 ? recipe.Without.join(", ") : (currentTranslations.labels.none);


        const card = `
            <div class="recipe-card">
                <a href="recipe.php?id=${recipe.id}">
                    <img src="${recipe.imageURL || 'placeholder.png'}" alt="${recipeName}" onerror="this.onerror=null;this.src='placeholder.png';">
                    <div class="content">
                        <h2>${recipeName}</h2>
                        <p><strong>${currentTranslations.labels.author || "Author"}:</strong> ${recipe.Author || currentTranslations.labels.unknown}</p>
                        <p><strong>${currentTranslations.labels.dietary_restrictions}:</strong> ${restrictions}</p>
                        <p><strong>${currentTranslations.labels.total_time}:</strong> ${totalTime} ${currentTranslations.labels.minutes}</p>
                    </div>
                </a>
                <div class="footer">
                    <button class="like-button ${likedClass}" data-recipe-id="${recipe.id}">
                        ❤️ <span class="like-count">${likesArray.length}</span>
                    </button>
                    <span class="comment-icon">💬 ${commentsArray.length}</span>
                </div>
            </div>
        `;
        gridContainer.append(card);
    });
}

function setupSearch() {
    $('#search-button').off('click').on('click', performSearch); // Use .off().on() to prevent multiple bindings
    $('#search-input').off('keypress').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            performSearch();
        }
    });
}

function performSearch() {
    const searchTerm = $('#search-input').val().toLowerCase().trim();
    const currentLang = localStorage.getItem("lang") || "fr";

    // If search term is empty, show all validated recipes
    if (!searchTerm) {
        displayRecipes(allRecipes, currentLang);
        return;
    }

    const filteredRecipes = allRecipes.filter(recipe => {
        if (!recipe) return false; // Skip invalid recipe entries

        // Check name (both languages, case-insensitive)
        const nameMatch = (recipe.name && recipe.name.toLowerCase().includes(searchTerm)) ||
                         (recipe.nameFR && recipe.nameFR.toLowerCase().includes(searchTerm));

        // Check author (case-insensitive)
        const authorMatch = recipe.Author && recipe.Author.toLowerCase().includes(searchTerm);

        // Check ingredients (handle array of objects, check name in both languages)
        const ingredientsMatch = Array.isArray(recipe.ingredients) && recipe.ingredients.some(ingredient => {
            if (typeof ingredient === 'object' && ingredient !== null) {
                return (ingredient.name && ingredient.name.toLowerCase().includes(searchTerm)) ||
                       (ingredient.nameFR && ingredient.nameFR.toLowerCase().includes(searchTerm)); // Check French name too if exists
            } else if (typeof ingredient === 'string') {
                return ingredient.toLowerCase().includes(searchTerm); // Handle simple string ingredients if they exist
            }
            return false;
        });
         // Check French ingredients too
        const ingredientsFRMatch = Array.isArray(recipe.ingredientsFR) && recipe.ingredientsFR.some(ingredient => {
            if (typeof ingredient === 'object' && ingredient !== null) {
                return (ingredient.name && ingredient.name.toLowerCase().includes(searchTerm)); // Only need to check French name here
            }
            return false;
        });


        // Check steps (both languages, case-insensitive)
        const stepsMatch = (Array.isArray(recipe.steps) && recipe.steps.some(step => typeof step === 'string' && step.toLowerCase().includes(searchTerm))) ||
                          (Array.isArray(recipe.stepsFR) && recipe.stepsFR.some(step => typeof step === 'string' && step.toLowerCase().includes(searchTerm)));

        // Check dietary restrictions (case-insensitive)
        const restrictionsMatch = Array.isArray(recipe.Without) &&
                               recipe.Without.some(restriction => typeof restriction === 'string' && restriction.toLowerCase().includes(searchTerm));

        return nameMatch || authorMatch || ingredientsMatch || ingredientsFRMatch || stepsMatch || restrictionsMatch;
    });

    // Display only validated recipes from the filtered results
    const validatedResults = filteredRecipes.filter(recipe => recipe.validated === 1);
    displayRecipes(validatedResults, currentLang);
}

function setupLikeButtons() {
    // Use event delegation for like buttons added dynamically
    $('#recipe-grid').off('click', '.like-button').on('click', '.like-button', function() {
        const recipeId = $(this).data('recipe-id');
        const $button = $(this); // Reference the clicked button

        // Check if user is logged in
        if (!currentUser) {
             showMessage(currentTranslations.messages.login_to_like, 'error');
             return;
        }

        // Disable button temporarily to prevent double-clicks
        $button.prop('disabled', true);

        $.ajax({
            url: 'like_recipe.php',
            method: 'POST',
            data: { id: recipeId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update button appearance
                    $button.toggleClass('liked', response.action === 'liked'); // Add/remove 'liked' class based on action

                    // Update like count using the count from the response
                    const likeCount = response.likeCount !== undefined ? response.likeCount : 0;
                    $button.find('.like-count').text(likeCount);

                     // Update the like status in the global allRecipes array (optional but good for consistency)
                     const recipeIndex = allRecipes.findIndex(r => r && r.id == recipeId);
                     if (recipeIndex > -1) {
                         if (!Array.isArray(allRecipes[recipeIndex].likes)) {
                             allRecipes[recipeIndex].likes = [];
                         }
                         if (response.action === 'liked') {
                              if (!allRecipes[recipeIndex].likes.includes(currentUser)) {
                                 allRecipes[recipeIndex].likes.push(currentUser);
                              }
                         } else { // unliked
                             const userIndex = allRecipes[recipeIndex].likes.indexOf(currentUser);
                             if (userIndex > -1) {
                                 allRecipes[recipeIndex].likes.splice(userIndex, 1);
                             }
                         }
                     }

                }
            },
            complete: function() {
                 // Re-enable button after request completes (success or error)
                 $button.prop('disabled', false);
            }
        });
    });
}

</script>

</body>
</html>