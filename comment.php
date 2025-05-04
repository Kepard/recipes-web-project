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
    echo json_encode(["success" => false]);
    exit;
}

$recipesFile = 'recipes.json';
$recipes = json_decode(file_get_contents($recipesFile), true);

foreach ($recipes as &$recipe) {
    if ($recipe['id'] == $recipeId) { // Loose comparison to handle string vs numeric
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