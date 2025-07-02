<?php
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    header("Location: auth/login.php");
    exit();
}

$username = $_SESSION['username'];
$userRole = $_SESSION['user_type'];
$userId = $_SESSION['user_id'];

// Get property ID from URL
$propertyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($propertyId <= 0) {
    header("Location: index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "easyrent_db";

try {
    $conn = new mysqli($servername, $username_db, $password_db, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Fetch property details with landlord information
    $sql = "SELECT p.*, u.username as landlord_name, u.email as landlord_email, u.phone as landlord_phone 
            FROM properties p 
            LEFT JOIN users u ON p.landlord_id = u.id 
            WHERE p.id = ? AND p.admin_approved = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $propertyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: index.php");
        exit();
    }
    
    $property = $result->fetch_assoc();
    
    // Fetch property images
    $imagesSql = "SELECT image_url, is_primary FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC";
    $imagesStmt = $conn->prepare($imagesSql);
    $imagesStmt->bind_param("i", $propertyId);
    $imagesStmt->execute();
    $imagesResult = $imagesStmt->get_result();
    
    $images = [];
    while ($row = $imagesResult->fetch_assoc()) {
        // Clean image URL by removing any leading slashes
        $row['image_url'] = ltrim($row['image_url'], '/');
        $images[] = $row;
    }
    
    // Check if user has already applied for this property
    $hasApplied = false;
    if ($userRole === 'tenant') {
        $applicationSql = "SELECT id FROM rental_applications WHERE property_id = ? AND tenant_id = ?";
        $applicationStmt = $conn->prepare($applicationSql);
        $applicationStmt->bind_param("ii", $propertyId, $userId);
        $applicationStmt->execute();
        $applicationResult = $applicationStmt->get_result();
        $hasApplied = $applicationResult->num_rows > 0;
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Function to check if file exists
function fileExists($path) {
    return file_exists($path) && is_file($path);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['title'] ?? 'Property Details'); ?> - EasyRent</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(30, 58, 138, 0.9)),
                url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=2073&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Reduced image gallery size */
        .image-gallery {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
            height: 350px; /* Reduced from 500px */
        }

        .main-image {
            background-size: cover;
            background-position: center;
            border-radius: 1rem;
            cursor: pointer;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .main-image:hover {
            transform: scale(1.02);
        }

        .thumbnail-grid {
            display: grid;
            grid-template-rows: repeat(2, 1fr);
            gap: 1rem;
        }

        .thumbnail {
            background-size: cover;
            background-position: center;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .thumbnail:hover {
            transform: scale(1.05);
        }

        .thumbnail.more-images {
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* Fallback for missing images */
        .image-placeholder {
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 1.2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .contact-card {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 1rem;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            background-color: #10b981;
        }

        /* Modal styles - reduced size */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
        }

        .modal-content {
            position: relative;
            margin: auto;
            padding: 0;
            width: 80%; /* Reduced from 90% */
            max-width: 800px; /* Reduced from 1200px */
            top: 50%;
            transform: translateY(-50%);
        }

        .modal-image {
            width: 100%;
            height: auto;
            max-height: 70vh; /* Limit height */
            object-fit: contain;
            border-radius: 1rem;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 1001;
        }

        .close:hover {
            color: #bbb;
        }

        .prev, .next {
            position: absolute;
            top: 50%;
            font-size: 18px;
            font-weight: bold;
            padding: 16px;
            color: white;
            background: rgba(0, 0, 0, 0.5);
            border: none;
            cursor: pointer;
            border-radius: 0 3px 3px 0;
            user-select: none;
            transform: translateY(-50%);
        }

        .next {
            right: 0;
            border-radius: 3px 0 0 3px;
        }

        .prev:hover, .next:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        @media (max-width: 768px) {
            .image-gallery {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .main-image {
                height: 250px; /* Fixed height for mobile */
            }
            
            .thumbnail-grid {
                grid-template-columns: repeat(4, 1fr);
                grid-template-rows: 1fr;
                height: 80px; /* Fixed height for mobile thumbnails */
            }
            
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }
        }

.image-gallery {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
            height: 350px;
        }

        .main-image {
            position: relative;
            border-radius: 1rem;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .main-image:hover {
            transform: scale(1.02);
        }

        .thumbnail-grid {
            display: grid;
            grid-template-rows: repeat(2, 1fr);
            gap: 1rem;
        }

        .thumbnail {
            position: relative;
            border-radius: 0.5rem;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .thumbnail:hover {
            transform: scale(1.05);
        }

        .thumbnail.more-images {
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* Fallback for missing images */
        .image-placeholder {
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 1.2rem;
            width: 100%;
            height: 100%;
        }
        /* Add this to your existing style section */
.main-image,
.thumbnail {
    position: relative;
    height: 100%;
    overflow: hidden;
}

.main-image img,
.thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #e5e7eb, #d1d5db);
    color: #6b7280;
    font-size: 1.2rem;
}

/* Remove conflicting background styles */
.main-image {
    /* Remove these properties */
    /* background-size: cover; */
    /* background-position: center; */
}

.thumbnail {
    /* Remove these properties */
    /* background-size: cover; */
    /* background-position: center; */
}

        /* Keep all other existing styles */
    </style>
    <style>
/* Add this to your existing style section */
.image-gallery {
    position: relative;
    height: 400px;
    overflow: hidden;
    border-radius: 1rem;
}

.gallery-slideshow {
    display: flex;
    transition: transform 0.5s ease-in-out;
    height: 100%;
}

.gallery-slide { min-width: 60%; height: 80%; position: relative; }

.gallery-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gallery-controls {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 10;
}

.gallery-control {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    border: none;
    padding: 0;
}

.gallery-control.active {
    background: white;
}

.gallery-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    border: none;
}

.gallery-prev {
    left: 20px;
}

.gallery-next {
    right: 20px;
}

/* For properties with less than 3 images */
.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    height: 400px;
}

.grid-item {
    height: 100%;
    border-radius: 1rem;
    overflow: hidden;
}

.grid-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
 /* Top Navigation */
        .top-nav {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 0 2rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }
</style>
</head>
<!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-home"></i>
                Easy Rent
            </div>
            
            <ul class="nav-menu">
                <li><a href="landlord_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="my_properties.php">My Properties</a></li>
                <li><a href="add_property.php">Add Property</a></li>
                <li><a href="maintenance.php">Maintenance</a></li>
                <li><a href="tenants.php">Tenants</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
        <!-- Back Link & User -->
        <div class="flex items-center space-x-6">
          
          </a>
          <div class="flex items-center space-x-2 text-white">
            <i class="fas fa-user-circle text-2xl"></i>
            <span><?php echo htmlspecialchars($username); ?></span>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="pt-24 pb-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

        <!-- Property Header -->
        <div class="bg-white shadow rounded-2xl p-6 mb-6">
          <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
            <div>
              <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($property['title']); ?></h1>
              <p class="flex items-center text-lg text-gray-600 mt-2">
                <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                <?php echo htmlspecialchars($property['address']); ?>
              </p>
            </div>
            <div class="flex flex-col items-end">
              <span class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-2">Available</span>
              <div class="text-3xl font-bold text-blue-600">
                $<?php echo number_format($property['rent_amount']); ?>/month
              </div>
            </div>
          </div>
        </div>

       <!-- Property Images -->
<div class="bg-white shadow rounded-2xl p-6 mb-6">
    <h2 class="text-2xl font-bold mb-4">Property Images</h2>
    <?php if (!empty($images)): ?>
        <?php if (count($images) >= 3): ?>
            <!-- Slideshow for 3+ images -->
            <div class="image-gallery" id="propertyGallery">
                <div class="gallery-slideshow">
                    <?php foreach ($images as $index => $image): ?>
                        <div class="gallery-slide">
                            <img src="/easyrent/uploads/properties/<?php echo $image['image_url']; ?>"
                                 alt="Property image <?php echo $index + 1; ?>"
                                 onclick="openModal(<?php echo $index; ?>)">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="gallery-nav gallery-prev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="gallery-nav gallery-next">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="gallery-controls">
                    <?php foreach ($images as $index => $image): ?>
                        <button class="gallery-control <?php echo $index === 0 ? 'active' : ''; ?>" 
                                data-index="<?php echo $index; ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Grid for less than 3 images -->
            <div class="image-grid">
                <?php foreach ($images as $image): ?>
                    <div class="grid-item">
                        <img src="/easyrent/uploads/properties/<?php echo $image['image_url']; ?>"
                             alt="Property image"
                             onclick="openModal(<?php echo array_search($image, $images); ?>)">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="flex justify-center items-center h-64 bg-gray-100 rounded-lg text-gray-500">
            <i class="fas fa-image mr-2"></i> No Images Available
        </div>
    <?php endif; ?>
</div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- Property Details -->
          <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-2xl p-6 mb-6">
              <h2 class="text-2xl font-bold mb-4">Property Details</h2>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                  <i class="fas fa-bed text-2xl text-blue-500 mb-2"></i>
                  <div class="font-semibold"><?php echo htmlspecialchars($property['bedrooms']); ?></div>
                  <div class="text-sm text-gray-600">Bedrooms</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                  <i class="fas fa-bath text-2xl text-blue-500 mb-2"></i>
                  <div class="font-semibold"><?php echo htmlspecialchars($property['bathrooms']); ?></div>
                  <div class="text-sm text-gray-600">Bathrooms</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                  <i class="fas fa-expand-arrows-alt text-2xl text-blue-500 mb-2"></i>
                  <div class="font-semibold"><?php echo htmlspecialchars($property['square_feet'] ?? 'N/A'); ?></div>
                  <div class="text-sm text-gray-600">Sq Ft</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                  <i class="fas fa-home text-2xl text-blue-500 mb-2"></i>
                  <div class="font-semibold"><?php echo htmlspecialchars($property['property_type'] ?? 'N/A'); ?></div>
                  <div class="text-sm text-gray-600">Type</div>
                </div>
              </div>
              <h3 class="text-xl font-semibold mb-3">Description</h3>
              <p class="text-gray-600 leading-relaxed">
                <?php echo nl2br(htmlspecialchars($property['description'])); ?>
              </p>
            </div>

            <?php if (!empty($property['amenities'])): ?>
              <div class="bg-white shadow rounded-2xl p-6 mb-6">
                <h2 class="text-2xl font-bold mb-4">Amenities & Features</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <?php foreach (explode(',', $property['amenities']) as $amenity): ?>
                    <?php if (trim($amenity)): ?>
                      <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <span><?php echo htmlspecialchars(trim($amenity)); ?></span>
                      </div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Sidebar -->
          <div>
            <div class="bg-white shadow rounded-2xl p-6 mb-6">
              <h3 class="text-xl font-bold mb-4">Contact Landlord</h3>
              <div class="space-y-3 mb-6">
                <div class="flex items-center">
                  <i class="fas fa-user mr-3 text-blue-500"></i>
                  <span class="font-semibold"><?php echo htmlspecialchars($property['landlord_name']); ?></span>
                </div>
                <div class="flex items-center">
                  <i class="fas fa-envelope mr-3 text-blue-500"></i>
                  <span><?php echo htmlspecialchars($property['landlord_email']); ?></span>
                </div>
                <?php if (!empty($property['landlord_phone'])): ?>
                  <div class="flex items-center">
                    <i class="fas fa-phone mr-3 text-blue-500"></i>
                    <span><?php echo htmlspecialchars($property['landlord_phone']); ?></span>
                  </div>
                <?php endif; ?>
              </div>
              <?php if ($userRole === 'tenant'): ?>
                <?php if ($hasApplied): ?>
                  <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded mb-3">
                    <i class="fas fa-check-circle mr-2"></i> You have already applied for this property
                  </div>
                <?php else: ?>
                  <button onclick="applyForProperty()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold mb-3">
                    <i class="fas fa-file-alt mr-2"></i> Apply Now
                  </button>
                <?php endif; ?>
                <button onclick="contactLandlord()" class="w-full bg-white text-blue-600 border border-blue-300 px-6 py-3 rounded-lg font-semibold hover:bg-blue-50">
                  <i class="fas fa-envelope mr-2"></i> Send Message
                </button>
              <?php else: ?>
                <div class="bg-blue-100 border border-blue-300 text-blue-700 px-4 py-3 rounded">
                  <i class="fas fa-info-circle mr-2"></i> Contact information is available to tenants only
                </div>
              <?php endif; ?>
            </div>

            <div class="bg-white shadow rounded-2xl p-6">
              <h3 class="text-xl font-bold mb-4">Property Information</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">Listed on:</span>
                  <span class="font-semibold"><?php echo date('M d, Y', strtotime($property['created_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Property ID:</span>
                  <span class="font-semibold">#<?php echo str_pad($property['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <?php if (!empty($property['lease_duration'])): ?>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Lease Duration:</span>
                    <span class="font-semibold"><?php echo htmlspecialchars($property['lease_duration']); ?></span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($property['deposit_amount'])): ?>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Security Deposit:</span>
                    <span class="font-semibold">$<?php echo number_format($property['deposit_amount']); ?></span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

        </div>

      <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
   <script>
// Image gallery functionality - FIXED
const images = <?php 
    $imageUrls = [];
    foreach ($images as $image) {
        if (!empty($image['image_url'])) {
            $imageUrls[] = '/easyrent/uploads/properties/' . $image['image_url'];
        }
    }
    echo json_encode($imageUrls);
?>;
        
        let currentImageIndex = 0;

        function openModal(index) {
            if (!images || images.length === 0 || index >= images.length || !images[index]) {
                return; // Don't open modal if no images or invalid index
            }
            
            currentImageIndex = index;
            document.getElementById('imageModal').style.display = 'block';
            document.getElementById('modalImage').src = images[currentImageIndex];
        }

        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        function changeImage(direction) {
            if (!images || images.length === 0) return;
            
            // Find next valid image
            let attempts = 0;
            do {
                currentImageIndex += direction;
                if (currentImageIndex >= images.length) currentImageIndex = 0;
                if (currentImageIndex < 0) currentImageIndex = images.length - 1;
                
                attempts++;
            } while (!images[currentImageIndex] && attempts < images.length);
            
            if (images[currentImageIndex]) {
                document.getElementById('modalImage').src = images[currentImageIndex];
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(event) {
            const modal = document.getElementById('imageModal');
            if (modal.style.display === 'block') {
                if (event.key === 'ArrowLeft') changeImage(-1);
                if (event.key === 'ArrowRight') changeImage(1);
                if (event.key === 'Escape') closeModal();
            }
        });

        // Apply for property
        function applyForProperty() {
            Swal.fire({
                title: 'Apply for Property',
                html: `
                    <div class="text-left">
                        <p class="mb-4">You are applying for: <strong><?php echo htmlspecialchars($property['title']); ?></strong></p>
                        <form id="applicationForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Employment Status</label>
                                <select id="employmentStatus" class="w-full px-3 py-2 border rounded-lg" required>
                                    <option value="">Select employment status</option>
                                    <option value="employed">Employed</option>
                                    <option value="self-employed">Self-Employed</option>
                                    <option value="unemployed">Unemployed</option>
                                    <option value="student">Student</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Monthly Income</label>
                                <input type="number" id="monthlyIncome" class="w-full px-3 py-2 border rounded-lg" placeholder="Enter monthly income" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Move-in Date</label>
                                <input type="date" id="moveInDate" class="w-full px-3 py-2 border rounded-lg" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Additional Notes</label>
                                <textarea id="applicationNotes" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="Any additional information..."></textarea>
                            </div>
                        </form>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit Application',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'btn-primary',
                    cancelButton: 'btn-secondary'
                },
                buttonsStyling: false,
                preConfirm: () => {
                    const employmentStatus = document.getElementById('employmentStatus').value;
                    const monthlyIncome = document.getElementById('monthlyIncome').value;
                    const moveInDate = document.getElementById('moveInDate').value;
                    
                    if (!employmentStatus || !monthlyIncome || !moveInDate) {
                        Swal.showValidationMessage('Please fill in all required fields');
                        return false;
                    }
                    
                    return {
                        employmentStatus: employmentStatus,
                        monthlyIncome: monthlyIncome,
                        moveInDate: moveInDate,
                        notes: document.getElementById('applicationNotes').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Send application data to server
                    const formData = new FormData();
                    formData.append('property_id', <?php echo $propertyId; ?>);
                    formData.append('employment_status', result.value.employmentStatus);
                    formData.append('monthly_income', result.value.monthlyIncome);
                    formData.append('move_in_date', result.value.moveInDate);
                    formData.append('notes', result.value.notes);
                    
                    fetch('apply_property.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Application Submitted!',
                                text: 'Your application has been submitted successfully',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload(); // Refresh page to update application status
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Failed to submit application',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred while submitting your application',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        }

        // Contact landlord function
        function contactLandlord() {
            Swal.fire({
                title: 'Contact Landlord',
                html: `
                    <div class="text-left">
                        <p class="mb-4">Contacting: <strong><?php echo htmlspecialchars($property['landlord_name']); ?></strong></p>
                        <form id="messageForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Subject</label>
                                <input type="text" id="messageSubject" class="w-full px-3 py-2 border rounded-lg" placeholder="Subject" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Message</label>
                                <textarea id="messageContent" rows="4" class="w-full px-3 py-2 border rounded-lg" placeholder="Your message..." required></textarea>
                            </div>
                        </form>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Send Message',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'btn-primary',
                    cancelButton: 'btn-secondary'
                },
                buttonsStyling: false,
                preConfirm: () => {
                    const subject = document.getElementById('messageSubject').value;
                    const content = document.getElementById('messageContent').value;
                    
                    if (!subject || !content) {
                        Swal.showValidationMessage('Please fill in all fields');
                        return false;
                    }
                    
                    return { subject, content };
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Send message data to server
                    const formData = new FormData();
                    formData.append('landlord_id', <?php echo $property['landlord_id']; ?>);
                    formData.append('subject', result.value.subject);
                    formData.append('content', result.value.content);
                    
                    fetch('send_message.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Message Sent!',
                                text: 'Your message has been sent successfully',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Failed to send message',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred while sending your message',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        }
        // Slideshow functionality for properties with 3+ images
const gallery = document.getElementById('propertyGallery');
if (gallery) {
    const slideshow = gallery.querySelector('.gallery-slideshow');
    const slides = gallery.querySelectorAll('.gallery-slide');
    const controls = gallery.querySelectorAll('.gallery-control');
    const prevBtn = gallery.querySelector('.gallery-prev');
    const nextBtn = gallery.querySelector('.gallery-next');
    let currentSlide = 0;
    
    function showSlide(index) {
        slideshow.style.transform = `translateX(-${index * 100}%)`;
        
        // Update controls
        controls.forEach(control => control.classList.remove('active'));
        controls[index].classList.add('active');
        
        currentSlide = index;
    }
    
    // Navigation controls
    prevBtn.addEventListener('click', () => {
        const prevIndex = (currentSlide - 1 + slides.length) % slides.length;
        showSlide(prevIndex);
    });
    
    nextBtn.addEventListener('click', () => {
        const nextIndex = (currentSlide + 1) % slides.length;
        showSlide(nextIndex);
    });
    
    // Dot controls
    controls.forEach((control, index) => {
        control.addEventListener('click', () => {
            showSlide(index);
        });
    });
    
    // Auto-advance slideshow every 5 seconds
    setInterval(() => {
        const nextIndex = (currentSlide + 1) % slides.length;
        showSlide(nextIndex);
    }, 5000);
}
    </script>
</body>
</html>