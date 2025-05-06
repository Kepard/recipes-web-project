<?php
session_start();

if (!isset($_SESSION['username'])) {
    echo json_encode(["success" => false]);
    exit;
}

$recipeId = $_POST['id'];
$commentText = $_POST['comment'];
$imageURL = $_POST['imageURL'];

// Dans le cas ou l'utilisateur souhaite uploader un fichier
if (!empty($_FILES['image']['name'])) {
    $uploadDir = 'uploads/';
    $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
    $uploadFile = $uploadDir . $fileName;
    $fileType = pathinfo($uploadFile, PATHINFO_EXTENSION);

    // Noter l'url locale du ficher dans la variable imageURL
    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
        $imageURL = $uploadFile;
    }
    
}

if (empty($commentText)) {
    echo json_encode(["success" => false]);
    exit;
}

$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

foreach ($recipes as &$recipe) {
    if ($recipe['id'] == $recipeId) {
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

file_put_contents($recipesFile, json_encode($recipes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));


echo json_encode([
    "success" => true,
    "author" => $_SESSION['username'],
    "content" => $commentText,
    "date" => date('Y-m-d H:i:s'),
    "imageurl" => $imageURL
]);
exit;