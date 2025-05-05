<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Titre de la page, utilise $title défini par la page PHP ou une valeur par défaut -->
    <title><?php echo $title ?? 'Croissant Gastronomy'; ?></title>
    <!-- Fichiers CSS et polices -->
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <!-- Bibliothèque jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- Favicon -->
    <link rel="icon" href="favicon.ico" type="image/x-icon" />
</head>
<body>

<?php
    // Démarrer la session seulement si elle n'est pas déjà active
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Récupérer le nom d'utilisateur et le rôle depuis la session (ou chaîne vide si non défini)
    $username = $_SESSION['username'] ?? '';
    $role = $_SESSION['role'] ?? '';
?>

<!-- En-tête fixe -->
<header>
    <nav>
        <!-- Logo et titre du site -->
        <a href="index.php" class="logo">
            <img src="favicon.ico" alt="Logo" class="logo-image">
            <span class="page-title"> Croissant Gastronomy </span>
        </a>

        <?php if (empty($role)): // Si l'utilisateur N'EST PAS connecté ?>
            <!-- Barre de navigation pour utilisateurs non connectés -->
            <div class="auth-container">
                <!-- Formulaire de connexion/inscription -->
                <form id="auth">
                    <label id="lusername" data-translate="labels.lusername">Utilisateur :</label>
                    <input type="text" id="username" name="username" required>
                    <label id="lpassword" data-translate="labels.lpassword">Mot de passe :</label>
                    <input type="password" id="password" name="password" required>
                    <button type="button" id="login" class="button button-primary" data-translate="buttons.login">Se connecter</button>
                    <button type="button" id="signup" class="button button-secondary" data-translate="buttons.signup">S'inscrire</button>
                </form>

                <!-- Sélection du rôle pour l'inscription (caché par défaut) -->
                <div id="role-selection" style="display: none;">
                     <label data-translate="labels.select_role">Demander un rôle (optionnel) :</label>
                     <div class="role-options">
                        <div class="role-radio">
                            <input type="radio" id="roleDemandeChef" name="roleRequest" value="DemandeChef">
                            <label for="roleDemandeChef" data-translate="roles.DemandeChef">Demander Rôle Chef</label>
                        </div>
                        <div class="role-radio">
                            <input type="radio" id="roleDemandeTraducteur" name="roleRequest" value="DemandeTraducteur">
                            <label for="roleDemandeTraducteur" data-translate="roles.DemandeTraducteur">Demander Rôle Traducteur</label>
                        </div>
                    </div>
                    <button type="button" id="validate-signup" class="button button-primary" data-translate="buttons.validate">Valider</button>
                </div>
            </div>

        <?php else: // Si l'utilisateur EST connecté ?>
            <!-- Barre de navigation pour utilisateurs connectés -->
            <div class="logged-in-nav">
                <a href="profile.php" class="button button-primary profile-button" data-translate="buttons.profile">Mon Profil</a>
                <!-- Formulaire pour la déconnexion (méthode POST recommandée) -->
                <form id="logout-form" action="logout.php" method="POST">
                    <button type="submit" id="logout" class="button button-secondary" data-translate="buttons.logout">Déconnexion</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Le tips du jour -->
        <span id="tip-of-the-day" class="nav-tip"></span>

        <!-- Conteneur pour les boutons spécifiques au rôle et le changement de langue -->
        <div class="role-lang-container">
             <?php if (!empty($role)): // Affiche les boutons d'action si l'utilisateur est connecté ?>
                 <?php if ($role === 'Chef'): // Bouton pour les Chefs ?>
                     <a href="create_recipe.php" class="button button-primary action-button" data-translate="buttons.create_recipe">Créer recette</a>
                 <?php elseif ($role === 'Administrateur'): // Bouton pour les Admins ?>
                     <a href="admin.php" class="button button-primary action-button" data-translate="buttons.admin_panel">Panel Admin</a>
                 <?php endif; ?>
             <?php endif; ?>
             <!-- Bouton pour changer la langue -->
             <button id="changeLang" class="button button-primary" aria-label="Changer la langue" data-translate="buttons.changeLang">Version en français</button>
        </div>
    </nav>

    <!-- Conteneur pour afficher les messages de feedback AJAX (succès, erreur) -->
    <div id="message-container"></div>

</header>

<!-- Contenu principal de la page (injecté par la variable $content des fichiers PHP) -->
<main>
    <?php echo $content ?? '<div class="message error">ERREUR CHARGEMENT CONTENU</div>'; // Affiche le contenu ou un message d'erreur ?>
</main>

<!-- Pied de page -->
<footer>
    <p>© <?php echo date('Y'); ?> Gorelov Bogdan | Université Paris-Saclay</p>
</footer>


<script>
// Variables globales pour les données de traduction et la langue
let datafile; // Stockera toutes les traductions de data.json
let currentLang = localStorage.getItem("lang") || "fr"; // Langue actuelle (FR par défaut)
let currentTranslations; // Stockera les traductions pour la langue actuelle

/**
 * Récupère une valeur de traduction nichée (ex: "messages.login_success").
 */
function getNestedTranslation(translations, keyString) {
    if (!translations || !keyString) return null;
    const keys = keyString.split('.');
    let result = translations;
    try {
        for (const key of keys) {
            result = result[key];
            if (result === undefined) return null; // Clé non trouvée
        }
        return result ; // Retourne seulement si c'est une chaîne
    } catch (e) {
        console.warn(`Erreur accès clé traduction: ${keyString}`, e);
        return null;
    }
}


/**
 * Traduit les éléments statiques de la page ayant l'attribut [data-translate].
 */
function translatePage(translations) {
    if (!translations) return;
    currentTranslations = translations; // Stocke globalement pour showMessage, etc.

    // Parcourt tous les éléments avec l'attribut [data-translate]
    $('[data-translate]').each(function() {
        const key = $(this).data('translate'); // Récupère la clé (ex: 'buttons.login')
        const translation = getNestedTranslation(translations, key); // Trouve la traduction

        if (translation !== null) {
            // Applique la traduction selon le type d'élément
            if ($(this).is('input[type="text"], input[type="password"], input[type="search"], textarea')) {
                $(this).attr('placeholder', translation); // Pour les placeholders
            } else if ($(this).is('button, a, span, label, h1, h2, h3, h4, h5, h6, p, strong, th, option')) {
                 $(this).html(translation); // Utilise .html() pour permettre les entités HTML (ex: ❤️)
            } else {
                 $(this).text(translation); // Pour les autres éléments simples
            }
        } else {
             // Avertissement si une clé n'est pas trouvée dans data.json
             console.warn(`Traduction non trouvée pour la clé: ${key}`);
        }
    });
}

/**
 * Affiche un message temporaire à l'utilisateur (succès, erreur, info).
 */
function showMessage(message, type = 'info') {
    // Tente de traduire le message s'il correspond à une clé dans messages.*
    const messageText = getNestedTranslation(currentTranslations?.messages, message) || message;

    // Crée l'élément HTML du message
    const messageElement = `<div class="message ${type}">${messageText}</div>`;
    const $messageContainer = $('#message-container'); // Conteneur cible

    // Ajoute le message au conteneur
    $messageContainer.append(messageElement);
    const $newMessage = $messageContainer.children().last(); // Référence au message ajouté

    // Fait disparaître et supprime le message après un délai
    setTimeout(() => {
        $newMessage.fadeOut(500, function() {
            $(this).remove();
        });
    }, 3500); // Délai d'affichage (3.5 secondes)
}


/**
 * Sélectionne et affiche une astuce de cuisine aléatoire.
 * @param {object} translations - L'objet des traductions pour la langue actuelle.
 */
function displayRandomTip(translations) {
    const tipContainer = $('#tip-of-the-day');
    const tipsArray = translations.tips; // Accède au tableau des astuces
    const tipPrefix = translations.labels.tip_prefix || 'Tip:'; // Préfixe traduit ou défaut
    // Choisit un index aléatoire
    const randomIndex = Math.floor(Math.random() * tipsArray.length);
    // Récupère l'astuce aléatoire
    const randomTip = tipsArray[randomIndex];
    // Affiche l'astuce dans le conteneur
    tipContainer.text(tipPrefix + ' ' + randomTip); // Affiche "Astuce : texte de l'astuce"
}


// Code exécuté une fois la page entièrement chargé
$(document).ready(function () {
    // --- Initialisation ---
    // Charge les traductions depuis data.json
    $.getJSON("data.json", function (data) {
        datafile = data; // Stocke toutes les langues dans la variable globale
        let translations = datafile[currentLang]; // Sélectionne la langue actuelle

        // 1. Traduit les éléments statiques présents au chargement
        translatePage(translations);

        // AFFICHE L'ASTUCE DU JOUR INITIALE
        displayRandomTip(translations);

        // 2. Appelle la fonction d'initialisation spécifique à la page actuelle
        //    (doit être définie dans le <script> de la page PHP comme index.php, admin.php...)
        if (typeof initializePageContent === 'function') {
            initializePageContent(translations, currentLang);
        } else {
            // Avertissement si la fonction n'est pas définie pour cette page
            console.warn("La fonction initializePageContent() n'est pas définie pour cette page.");
        }

    });

    // --- Gestionnaires d'Événements Globaux ---

    // Changement de Langue
    $("#changeLang").click(function () {
        // Bascule la langue actuelle (fr <-> en)
        currentLang = (currentLang === "en") ? "fr" : "en";
        // Sauvegarde la nouvelle langue dans le localStorage du navigateur
        localStorage.setItem("lang", currentLang);
        // Récupère les traductions pour la nouvelle langue
        let translations = datafile[currentLang];

        // Retraduit les éléments statiques de la page
        translatePage(translations);

        // R AFFICHE UNE NOUVELLE ASTUCE DANS LA NOUVELLE LANGUE
        displayRandomTip(translations);

        // Réinitialise le contenu spécifique de la page avec la nouvelle langue
        initializePageContent(translations, currentLang);
        
    });

    // Logique d'Authentification (Login / Signup)

    // Gestion touche "Entrée" dans le formulaire d'authentification
    $('#auth').on('keypress', function(e) {
        if (e.which === 13) { // Si touche "Entrée"
            e.preventDefault(); // Empêche soumission standard (vide)
            // Si la sélection de rôle est visible (mode signup avancé), valide le signup
            if ($('#role-selection').is(':visible')) {
                 $('#validate-signup').click();
            } else { // Sinon (mode login ou signup simple), tente le login
                sendAuthRequest('login');
            }
        }
    });

    // Clic sur le bouton "Se connecter"
    $('#login').click(function () {
        sendAuthRequest('login'); // Lance la requête de connexion
    });

    // Clic sur le bouton "S'inscrire"
    $('#signup').click(function () {
        // Affiche/cache la section de sélection de rôle
         $('#role-selection').toggle();
    });

    // Clic sur le bouton "Valider" (après avoir choisi un rôle optionnel pour l'inscription)
    $('#validate-signup').click(function() {
        // Récupère les identifiants et le rôle demandé (ou Cuisinier par défaut)
        const username = $('#username').val().trim();
        const password = $('#password').val().trim();
        let role = $('input[name="roleRequest"]:checked').val() || "Cuisinier";

        // Vérification simple des champs
        if (!username || !password) {
            showMessage(currentTranslations.messages.missing_fields, 'error');
            return;
        }

        // Envoi de la requête AJAX d'inscription vers auth.php
        $.ajax({
            url: 'auth.php',
            method: 'POST',
            data: {
                action: 'signup', // Action = inscription
                username: username,
                password: password,
                role: role // Rôle initial (Cuisinier ou Demande...)
            },
            dataType: 'json',
            success: function (response) { // Si la requête réussit
                if (response.success) {
                    // Affiche message succès, cache sélection rôle, vide les champs
                    showMessage(response.message, 'success');
                    $('#role-selection').hide();
                    $('#username').val('');
                    $('#password').val('');
                    $('input[name="roleRequest"]').prop('checked', false);
                    // Optionnel: connecter automatiquement l'utilisateur ici
                } else {
                    // Affiche message d'erreur du serveur (ex: utilisateur existe déjà)
                    showMessage(response.message, 'error');
                }
            }
        });
    });

    /**
     * Fonction pour envoyer la requête AJAX de connexion.
     */
    function sendAuthRequest(action) {
        // Récupère les identifiants
        const username = $('#username').val().trim();
        const password = $('#password').val().trim();

        // Vérification simple
        if (!username || !password) {
            showMessage(currentTranslations.messages.missing_fields, 'error');
            return;
        }

        // Envoi requête AJAX de connexion vers auth.php
        $.ajax({
            url: 'auth.php',
            method: 'POST',
            data: {
                action: action, // Action = login
                username: username,
                password: password
            },
            dataType: 'json',
            success: function (response) { // Si succès
                if (response.success) {
                    if (action === 'login') {
                        // Affiche message succès et recharge la page après un délai
                        showMessage(response.message, 'success');
                        setTimeout(() => {
                            window.location.reload(); // Recharge pour mettre à jour l'état de connexion
                        }, 1500); // Délai de 1.5s
                    }
                    // Pas d'action spécifique pour signup ici (géré par #validate-signup)
                } else {
                    // Affiche message d'erreur du serveur (ex: identifiants invalides)
                    showMessage(response.message, 'error');
                }
            }
        });
    }
}); 

</script>
</body>
</html>