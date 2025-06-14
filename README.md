# Projet de Gestion de Recettes de Cuisine

Ce projet a été développé dans le cadre d'un module d'initiation à la programmation web. Il s'agit d'une application web simple permettant de gérer, visualiser, créer, modifier et traduire des recettes de cuisine.

## Aperçu

L'application supporte deux langues (Français et Anglais) et intègre différents rôles utilisateurs (Cuisinier, Chef, Traducteur, Administrateur) avec des permissions spécifiques. Les données sont stockées dans des fichiers JSON. Une attention particulière a été portée à l'utilisation d'AJAX pour offrir une expérience utilisateur fluide avec peu de rechargements de page.

## Technologies Utilisées

*   **Frontend :** HTML5, CSS3, JavaScript (avec jQuery pour la manipulation du DOM et AJAX)
*   **Backend :** PHP
*   **Stockage de Données :** Fichiers JSON (`recipes.json`, `users.json`, `data.json` pour les traductions)

## Fonctionnalités Principales

*   Affichage de la liste des recettes avec recherche et filtres basiques.
*   Visualisation détaillée d'une recette (ingrédients, étapes, temps, commentaires).
*   Système d'authentification (connexion, inscription) et gestion de session.
*   Gestion des rôles utilisateurs avec permissions distinctes :
    *   **Cuisinier :** Peut commenter, liker, ajouter des photos aux commentaires.
    *   **Chef :** Peut créer de nouvelles recettes, modifier ses propres recettes.
    *   **Traducteur :** Peut traduire les recettes d'une langue à l'autre (interface dédiée).
    *   **Administrateur :** Peut valider/supprimer/modifier/traduire toutes les recettes, gérer les utilisateurs et leurs rôles.
*   Interface multilingue (Français/Anglais) avec traduction dynamique des éléments.
*   Formulaires dynamiques pour l'ajout d'ingrédients et d'étapes lors de la création/modification de recettes.
*   Fonctionnalité "Astuce du Jour" affichée aléatoirement dans la barre de navigation.

## Pour Commencer

1.  Clonez ce dépôt.
2.  Assurez-vous d'avoir un serveur web configuré pour PHP (ex: XAMPP, WAMP, MAMP, ou le serveur PHP intégré).
3.  Placez les fichiers du projet dans le répertoire racine de votre serveur web.
4.  Accédez à `index.php` via votre navigateur.
5.  Les fichiers JSON (`users.json`, `recipes.json`, `data.json`) doivent être présents à la racine et accessibles en lecture/écriture par le serveur PHP.

## Documentation Détaillée

Pour une explication plus approfondie de l'architecture, du fonctionnement des appels AJAX, du rôle de chaque fichier, des choix de conception et des difficultés rencontrées, veuillez consulter le rapport détaillé du projet :
**➡️ `rapport.pdf`** (disponible dans ce dépôt).

---
