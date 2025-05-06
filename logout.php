<?php
session_start(); // Demarrer la session (pas forcement necessaire)
session_unset(); // Nettoyer toutes les variables de la session
session_destroy(); // Detruire la session
header("Location: index.php"); // Rediriger sur la page d'acceuil
exit;
?>