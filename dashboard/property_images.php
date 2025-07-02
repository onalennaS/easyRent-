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

// Get property ID
$property_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch property details
$property = [];
$images = [];

if ($property_id) {
    $query = "SELECT * FROM properties WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $property_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $property = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $query = "SELECT * FROM property_images WHERE property_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $property_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $images = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Handle image deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $image_id = (int)$_POST['image_id'];
    
    // Get image info
    $query = "SELECT * FROM property_images WHERE id = ? AND property_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $image_id, $property_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $image = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($image) {
        // Delete from database
        $query = "DELETE FROM property_images WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $image_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Delete from server
        $file_path = "../uploads/properties/" . $image['image_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Redirect to refresh page
        header("Location: property_images.php?id=$property_id");
        exit();
    }
}

// Handle set as primary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_primary'])) {
    $image_id = (int)$_POST['image_id'];
    
    // Reset current primary
    $query = "UPDATE property_images SET is_primary = 0 WHERE property_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $property_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Set new primary
    $query = "UPDATE property_images SET is_primary = 1 WHERE id = ? AND property_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $image_id, $property_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Redirect to refresh page
    header("Location: property_images.php?id=$property_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Images - Easy Rent Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header h1 {
            font-size: 28px;
            color: #333;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            background: #3b82f6;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }

        .back-btn i {
            margin-right: 5px;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }

        .gallery-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            aspect-ratio: 4/3;
        }

        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .gallery-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .gallery-actions {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            padding: 10px;
            display: flex;
            justify-content: center;
            gap: 10px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .gallery-item:hover .gallery-actions {
            transform: translateY(0);
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            color: white;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .primary-btn {
            background: #3b82f6;
        }

        .primary-btn:hover {
            background: #2563eb;
        }

        .delete-btn {
            background: #ef4444;
        }

        .delete-btn:hover {
            background: #dc2626;
        }

        .primary-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #10b981;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            z-index: 2;
        }

        .upload-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-top: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 22px;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .upload-form {
            display: flex;
            gap: 15px;
        }

        .file-input {
            flex: 1;
            padding: 12px;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
        }

        .upload-btn {
            padding: 12px 25px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .upload-btn:hover {
            background: #2563eb;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-top: 30px;
        }

        .empty-state i {
            font-size: 60px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #334155;
        }

        .empty-state p {
            color: #64748b;
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-images"></i>
                Property Images: <?php echo htmlspecialchars($property['title'] ?? 'Untitled Property'); ?>
            </h1>
            <a href="manage_properties.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Properties
            </a>
        </div>

        <?php if (!empty($images)): ?>
            <div class="gallery">
                <?php foreach ($images as $image): ?>
                    <div class="gallery-item">
                        <?php if ($image['is_primary']): ?>
                            <span class="primary-badge">Main Image</span>
                        <?php endif; ?>
                        <img src="../uploads/properties/<?php echo htmlspecialchars($image['image_url']); ?>" 
                             alt="Property Image" class="gallery-img">
                        <div class="gallery-actions">
                            <?php if (!$image['is_primary']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                    <button type="submit" name="set_primary" class="action-btn primary-btn" 
                                            title="Set as Main Image">
                                        <i class="fas fa-star"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" 
                                  onsubmit="return confirm('Are you sure you want to delete this image?');">
                                <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                <button type="submit" name="delete_image" class="action-btn delete-btn" 
                                        title="Delete Image">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-images"></i>
                <h3>No Images Found</h3>
                <p>This property doesn't have any images yet. Upload some images to showcase this property.</p>
            </div>
        <?php endif; ?>

        <div class="upload-section">
            <h2 class="section-title">
                <i class="fas fa-cloud-upload-alt"></i>
                Upload New Images
            </h2>
            <form method="POST" action="upload_image.php" enctype="multipart/form-data">
                <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
                <div class="upload-form">
                    <input type="file" name="new_images[]" class="file-input" accept="image/*" multiple>
                    <button type="submit" class="upload-btn">
                        <i class="fas fa-upload"></i> Upload Images
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
mysqli_close($conn);
?>