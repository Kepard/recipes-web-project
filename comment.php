<?php
session_start();

if (!isset($_SESSION['username'])) {
    echo json_encode(["success" => false, "message" => "Vous devez être connecté pour commenter."]);
    exit;
}

$recipeId = $_POST['id'] ?? null;
$commentText = trim($_POST['comment'] ?? '');
$imageURL = $_POST['imageURL'] ?? null;

// Handle file upload
if (!empty($_FILES['image']['name'])) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
    $uploadFile = $uploadDir . $fileName;
    $fileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));

    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($fileType, $allowedTypes)) {
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
            $imageURL = $uploadFile;
        }
    }
}

if (empty($commentText)) {
    echo json_encode(["success" => false, "message" => "Le commentaire ne peut pas être vide."]);
    exit;
}

$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

// Fix 1: Handle case where recipes is null or invalid
if ($recipes === null) {
    $recipes = [];
}

// Fix 2: Handle numeric vs string recipe IDs
$recipeFound = false;
foreach ($recipes as &$recipe) {
    if ($recipe['id'] == $recipeId) { // Loose comparison to handle string vs numeric
        $recipeFound = true;
        
        // Fix 3: Initialize comments array if it doesn't exist
        if (!isset($recipe['comments'])) {
            $recipe['comments'] = [];
        }

        $newComment = [
            'id' => count($recipe['comments']) + 1,
            'date' => date('Y-m-d H:i:s'),
            'author' => $_SESSION['username'],
            'content' => $commentText,
            'imageurl' => $imageURL
        ];

        $recipe['comments'][] = $newComment;
        break;
    }
}

// Fix 4: Use file locking to prevent corruption
$fp = fopen($recipesFile, 'w');
if (flock($fp, LOCK_EX)) {
    fwrite($fp, json_encode($recipes, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
} else {
    echo json_encode(["success" => false, "message" => "Impossible de verrouiller le fichier pour écriture."]);
    fclose($fp);
    exit;
}
fclose($fp);

echo json_encode([
    "success" => true,
    "author" => $_SESSION['username'],
    "content" => $commentText,
    "date" => date('Y-m-d H:i:s'),
    "imageurl" => $imageURL
]);
exit;