<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "easyrent_db";

$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Process image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_images'])) {
    $property_id = (int)$_POST['property_id'];
    
    // Validate property exists
    $query = "SELECT id FROM properties WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $property_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        die("Invalid property ID");
    }
    
    mysqli_stmt_close($stmt);
    
    // Upload directory
    $upload_dir = '../uploads/properties/';
    
    // Process each uploaded file
    foreach ($_FILES['new_images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['new_images']['error'][$key] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $_FILES['new_images']['name'][$key],
                'type' => $_FILES['new_images']['type'][$key],
                'tmp_name' => $tmp_name,
                'error' => $_FILES['new_images']['error'][$key],
                'size' => $_FILES['new_images']['size'][$key]
            ];
            
            // Upload image
            $image_url = uploadImage($file, $upload_dir);
            
            if ($image_url) {
                // Save to database
                $query = "INSERT INTO property_images (property_id, image_url) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "is", $property_id, $image_url);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Redirect back to property images
    header("Location: property_images.php?id=$property_id");
    exit();
}

// Image upload function
function uploadImage($file, $upload_dir) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . $extension;
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $filename;
    }
    
    return false;
}
?>