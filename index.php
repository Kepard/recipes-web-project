<?php
    /**
     * Page d'accueil principale : Affiche la liste des recettes (cartes).
     * Inclut une barre de recherche et gère l'affichage initial.
     */

    // Démarrage session si nécessaire
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Récupère infos utilisateur connecté (ou null si non connecté)
    $username = $_SESSION['username'] ?? '';
    $role = $_SESSION['role'] ?? ''; // Récupère aussi le rôle

    // Contenu HTML de base de la page (barre recherche + conteneur grille recettes)
    $content = '
    <div class="search-container">
        <input type="text" id="search-input" placeholder="Search recipes..." data-translate="labels.search_placeholder">
        <button id="search-button" class="button button-primary" data-translate="buttons.search">Search</button>
    </div>

    <div class="grid-container" id="recipe-grid">
        <p id="loading-message">Loading recipes...</p>
    </div>
    ';

    // Titre de la page HTML
    $title = "Croissant Gastronomy";
    // Inclusion de l'en-tête (qui affichera $content et $title)
    include 'header.php';
?>

<script>
// Variables globales pour stocker toutes les recettes chargées et l'utilisateur courant
let allRecipes = []; // Tableau pour stocker toutes les recettes du JSON
let currentUser = <?php echo json_encode($username); ?>; // Passe le nom d'utilisateur PHP au JS

/**
 * Fonction appelée par header.php après chargement des traductions.
 * Charge les recettes via AJAX et initialise l'affichage et les interactions.
 */
function initializePageContent(translations, lang) {
    // Stocke les traductions pour usage global dans ce script
    currentTranslations = translations;
    const gridContainer = $("#recipe-grid"); // Conteneur pour les cartes recettes
    const loadingMessage = $("#loading-message"); // Message "Chargement..."

    // Vide la grille et affiche le message de chargement
    gridContainer.empty().append(loadingMessage);

    // Charge les recettes depuis recipes.json via AJAX (avec cache busting)
    $.getJSON("recipes.json?v=" + Date.now(), function (recipes) {

        // console.log("Recipes loaded successfully.");

        allRecipes = recipes; // Stocke les recettes dans la variable globale pour eviter de appeler getJSON a chaque fois

        // Cache le message de chargement
        loadingMessage.hide();

        // Affiche initialement toutes les recettes *validées*
        displayRecipes(allRecipes, lang);

        // Met en place la fonctionnalité de recherche
        setupSearch();
        // Met en place la fonctionnalité des boutons "Like"
        setupLikeButtons();

    });
};

/**
 * Affiche les recettes fournies dans la grille.
 * Filtre pour ne montrer que les recettes validées.
 */
function displayRecipes(recipesToDisplay, lang) {
    const gridContainer = $("#recipe-grid");
    gridContainer.empty(); // Vide la grille avant d'ajouter les nouvelles cartes

    // Filtre pour ne garder que les recettes validées (validated === 1)
    const validatedRecipes = recipesToDisplay.filter(recipe => recipe.validated === 1);

    // Si aucune recette validée (ou après filtrage), affiche un message
    if (validatedRecipes.length === 0) {
        gridContainer.html(`<p class="message info">${currentTranslations.messages.no_recipes_found}</p>`);
        return; // Arrête la fonction
    }

    // Pour chaque recette validée, crée et ajoute une carte HTML
    validatedRecipes.forEach(recipe => {
        // Choix du nom selon la langue
        const recipeName = lang === "fr" && recipe.nameFR ? recipe.nameFR : recipe.name;
        // Sécurité : vérifie que likes/comments sont des tableaux
        const likesArray = recipe.likes;
        const commentsArray = recipe.comments;

        // Détermine si l'utilisateur actuel a liké cette recette
        const hasLiked = currentUser && likesArray.includes(currentUser);
        const likedClass = hasLiked ? 'liked' : ''; // Classe CSS pour le bouton like

        // Calcul du temps total 
        let totalTime = recipe.timers.reduce((sum, timer) => sum + (parseInt(timer, 10) || 0), 0);
        

        // Affichage des restrictions (texte simple ici, pourrait être les icônes)
        const restrictions = recipe.Without.length > 0
            ? recipe.Without.join(", ") // Liste séparée par virgules
            : (currentTranslations.labels.none); // Texte "None" traduit si vide

        // Construction de la carte HTML
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
        // Ajoute la carte construite à la grille
        gridContainer.append(card);
    });
}

/**
 * Met en place les écouteurs d'événements pour la barre de recherche
 * (bouton + touche Entrée).
 */
function setupSearch() {
    // Attache l'événement au clic sur le bouton (évite doublons avec .off().on())
    $('#search-button').off('click').on('click', performSearch);
    // Attache l'événement à la pression de touche dans l'input (évite doublons)
    $('#search-input').off('keypress').on('keypress', function(e) {
        if (e.which === 13) { // Si la touche est "Entrée"
            performSearch(); // Lance la recherche
        }
    });
}

/**
 * Exécute la recherche de recettes basée sur le terme entré.
 * Filtre `allRecipes` et appelle `displayRecipes` avec les résultats.
 */
function performSearch() {
    // Récupère le terme de recherche, le met en minuscule et enlève les espaces superflus
    const searchTerm = $('#search-input').val().toLowerCase().trim();
    // Récupère la langue actuelle pour l'affichage des résultats
    const currentLang = localStorage.getItem("lang") || "fr";

    // Si la recherche est vide, affiche toutes les recettes validées
    if (!searchTerm) {
        displayRecipes(allRecipes, currentLang);
        return;
    }

    // Filtre le tableau `allRecipes`
    const filteredRecipes = allRecipes.filter(recipe => {
        // Vérifie si le terme est inclus (insensible à la casse) dans :
        // Nom (FR/EN)
        const nameMatch = (recipe.name && recipe.name.toLowerCase().includes(searchTerm)) ||
                         (recipe.nameFR && recipe.nameFR.toLowerCase().includes(searchTerm));
        // Auteur
        const authorMatch = recipe.Author && recipe.Author.toLowerCase().includes(searchTerm);
        // Ingrédients EN (nom)
        const ingredientsMatch = recipe.ingredients.some(ingredient => {
            if (ingredient !== null) { 
                return (ingredient.name && ingredient.name.toLowerCase().includes(searchTerm));
                       
            } 
            return false;
        });
         // Ingrédients FR (nom)
        const ingredientsFRMatch = recipe.ingredientsFR.some(ingredient => {
            if (ingredient !== null) {
                return (ingredient.name && ingredient.name.toLowerCase().includes(searchTerm));
            }
            return false;
        });
        // Étapes (FR/EN)
        const stepsMatch = (recipe.steps.some(step => step.toLowerCase().includes(searchTerm))) ||
                          (recipe.stepsFR.some(step => step.toLowerCase().includes(searchTerm)));
        // Restrictions (éléments du tableau Without)
        const restrictionsMatch = recipe.Without.some(restriction => restriction.toLowerCase().includes(searchTerm));

        // Retourne true si AU MOINS UN des critères correspond
        return nameMatch || authorMatch || ingredientsMatch || ingredientsFRMatch || stepsMatch || restrictionsMatch;
    });

    // Affiche les résultats filtrés (qui seront re-filtrés par displayRecipes pour ne garder que les validés)
    displayRecipes(filteredRecipes, currentLang);
}

/**
 * Met en place les écouteurs d'événements pour les boutons "Like" sur les cartes.
 * Utilise la délégation d'événements car les cartes sont ajoutées dynamiquement.
 */
function setupLikeButtons() {
    // Attache l'événement 'click' au conteneur '#recipe-grid', mais ne réagit
    // que si l'élément cliqué a la classe '.like-button'.
    $('#recipe-grid').off('click', '.like-button').on('click', '.like-button', function() {
        // Récupère l'ID de la recette depuis l'attribut data-recipe-id du bouton
        const recipeId = $(this).data('recipe-id');
        const $button = $(this); // Référence au bouton cliqué

        // Vérifie si l'utilisateur est connecté
        if (!currentUser) {
             showMessage(currentTranslations.messages.login_to_like, 'error'); // Message d'erreur
             return; // Arrête
        }

        // Désactive temporairement le bouton
        $button.prop('disabled', true);

        // Appel AJAX vers like_recipe.php
        $.ajax({
            url: 'like_recipe.php',
            method: 'POST',
            data: { id: recipeId }, // Envoie l'ID
            dataType: 'json', // Attend du JSON
            success: function(response) { // Si succès
                if (response.success) {
                    // Met à jour l'apparence du bouton (classe 'liked')
                    $button.toggleClass('liked', response.action === 'liked');
                    // Met à jour le compteur de likes
                    const likeCount = response.likeCount !== undefined ? response.likeCount : 0;
                    $button.find('.like-count').text(likeCount);
                }
            },
            complete: function() { // Après succès ou échec
                 // Réactive le bouton
                 $button.prop('disabled', false);
            }
        });
    });
}

</script>

</body>
</html>