<?php
session_start();

// Check if user is logged in (optional - allow unregistered users to view)
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? ($_SESSION['username'] ?? '') : '';
$userRole = $isLoggedIn ? ($_SESSION['user_type'] ?? '') : '';
$userId = $isLoggedIn ? ($_SESSION['user_id'] ?? '') : '';

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
    
    // Fetch property amenities
    $amenities = [];
    // Check if amenities table exists
    $check_amenities_table = "SHOW TABLES LIKE 'amenities'";
    $amenities_table_result = $conn->query($check_amenities_table);
    
    if ($amenities_table_result && $amenities_table_result->num_rows > 0) {
        // Check if icon column exists
        $check_icon_column = "SHOW COLUMNS FROM amenities LIKE 'icon'";
        $icon_column_result = $conn->query($check_icon_column);
        $has_icon = $icon_column_result && $icon_column_result->num_rows > 0;
        
        $amenitiesSql = "SELECT a.name" . ($has_icon ? ", a.icon" : "") . " FROM amenities a 
                         INNER JOIN property_amenities pa ON a.id = pa.amenity_id 
                         WHERE pa.property_id = ?";
        $amenitiesStmt = $conn->prepare($amenitiesSql);
        if ($amenitiesStmt) {
            $amenitiesStmt->bind_param("i", $propertyId);
            $amenitiesStmt->execute();
            $amenitiesResult = $amenitiesStmt->get_result();
            while ($amenity = $amenitiesResult->fetch_assoc()) {
                $amenities[] = $amenity;
            }
            $amenitiesStmt->close();
        }
    }
    
    // Check if user has already applied for this property (only if logged in as tenant)
    $hasApplied = false;
    if ($isLoggedIn && $userRole === 'tenant') {
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
            background: white;
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        /* Navbar Styles from index.php */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        /* Mobile Navigation Styles */
        .mobile-nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-nav-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 300px;
            max-width: 80%;
            height: 100vh;
            z-index: 100;
            background: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }

        .mobile-nav-container.open {
            transform: translateX(0);
        }

        .mobile-nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .mobile-nav-content {
            max-height: calc(100vh - 80px);
            overflow-y: auto;
            background: white;
        }

        .mobile-nav-item {
            display: block;
            padding: 1rem 1.5rem;
            color: #374151;
            font-weight: 500;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s;
            text-decoration: none;
        }

        .mobile-nav-item:hover {
            background-color: #f9fafb;
            color: #3b82f6;
        }

        .mobile-nav-user-section {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            border-top: 2px solid #e5e7eb;
        }

        .mobile-nav-username {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .mobile-menu-btn {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: none;
            border: none;
            padding: 0;
            margin: 0;
        }

        .mobile-menu-btn span {
            display: block;
            height: 3px;
            width: 100%;
            background: #374151;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .mobile-menu-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .mobile-menu-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .mobile-menu-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        @media (min-width: 769px) {
            .mobile-only {
                display: none !important;
            }
            
            .mobile-nav-container {
                display: none !important;
            }
            
            .mobile-menu-btn {
                display: none !important;
            }
        }

        @media (max-width: 768px) {
            .desktop-only {
                display: none !important;
            }
            
            .mobile-menu-btn {
                display: flex !important;
            }
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

        /* Professional Image Gallery */
        .property-image-gallery {
            width: 100%;
        }

        .main-gallery-image {
            position: relative;
            width: 70%;
            height: 300px;
            border-radius: 1rem;
            overflow: hidden;
            background: #f3f4f6;
            margin: 0 auto 1rem auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .main-gallery-image img {
            width: auto;
            max-width: 80%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease, opacity 0.5s ease;
            animation: fadeIn 0.5s ease-in-out;
        }

        .main-gallery-image img:hover {
            transform: scale(1.05);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out;
        }

        .animate-slideInLeft {
            animation: slideInLeft 0.6s ease-out;
        }

        .animate-slideInRight {
            animation: slideInRight 0.6s ease-out;
        }

        .animation-delay-200 {
            animation-delay: 0.2s;
        }

        .image-counter {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .gallery-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .gallery-arrow:hover {
            background: white;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .gallery-arrow-prev {
            left: 1rem;
        }

        .gallery-arrow-next {
            right: 1rem;
        }

        .gallery-arrow i {
            color: #1e40af;
            font-size: 1.25rem;
        }

        .thumbnail-strip {
            display: flex;
            gap: 0.75rem;
            overflow-x: auto;
            padding: 0.5rem 0;
            scrollbar-width: thin;
        }

        .thumbnail-strip::-webkit-scrollbar {
            height: 6px;
        }

        .thumbnail-strip::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .thumbnail-strip::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .thumbnail-strip::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .thumbnail-item {
            flex-shrink: 0;
            width: 120px;
            height: 80px;
            border-radius: 0.5rem;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .thumbnail-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .thumbnail-item.active {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        }

        .thumbnail-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .thumbnail-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
        }

        /* Fallback for missing images */
        .image-placeholder {
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 1.2rem;
            width: 100%;
            height: 100%;
        }

        @media (max-width: 768px) {
            .main-gallery-image {
                height: 300px;
            }

            .thumbnail-item {
                width: 100px;
                height: 70px;
            }

            .gallery-arrow {
                width: 40px;
                height: 40px;
            }
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

        .footer {
            background: black;
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
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
</style>
</head>
<!-- Mobile Navigation Overlay -->
    <div id="mobileNavOverlay" class="mobile-nav-overlay" onclick="closeMobileNav()"></div>

    <!-- Mobile Navigation -->
    <div id="mobileNavContainer" class="mobile-nav-container">
        <div class="mobile-nav-header">
            <div>
                <h1 class="text-xl font-bold text-gray-900">EasyRent</h1>
                <p class="text-xs text-blue-600">Property Management</p>
            </div>
            <button onclick="closeMobileNav()" class="text-gray-700 hover:text-red-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="mobile-nav-content">
            <!-- Main Navigation Links -->
            <a href="../index.php" class="mobile-nav-item" onclick="closeMobileNav()">
                <i class="fas fa-home mr-3"></i>Home
            </a>
            <a href="../index.php#properties" class="mobile-nav-item" onclick="closeMobileNav()">
                <i class="fas fa-building mr-3"></i>Properties
            </a>
            <?php if($isLoggedIn): ?>
            <div class="mobile-nav-user-section">
                <div class="mobile-nav-username">
                    <i class="fas fa-user-circle mr-2"></i>
                    <?php echo htmlspecialchars($username); ?>
                </div>
                <a href="<?php echo $userRole === 'tenant' ? 'tenant_dashboard.php' : 'landlord_dashboard.php'; ?>" class="mobile-nav-item" onclick="closeMobileNav()">
                    <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                </a>
                <a href="#" onclick="confirmLogout(); return false;" class="mobile-nav-item text-red-600 hover:bg-red-50">
                    <i class="fas fa-sign-out-alt mr-3"></i>Logout
                </a>
            </div>
            <?php else: ?>
            <div class="mobile-nav-user-section">
                <a href="../auth/login.php" class="block w-full text-center bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold mb-3 hover:bg-blue-700 transition-colors" onclick="closeMobileNav()">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </a>
                <a href="../auth/register.php" class="block w-full text-center border-2 border-blue-600 text-blue-600 py-3 px-4 rounded-lg font-semibold hover:bg-blue-50 transition-colors" onclick="closeMobileNav()">
                    <i class="fas fa-user-plus mr-2"></i>Register
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Desktop Navigation -->
    <nav class="navbar fixed w-full top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="../index.php" class="flex items-center">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">EasyRent</h1>
                            <p class="text-xs text-blue-600">Property Management</p>
                        </div>
                    </a>
                </div>
                
                <!-- Desktop Navigation Links -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="../index.php" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">Home</a>
                    <a href="../index.php#properties" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">Properties</a>
                    <a href="../index.php#about" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">About</a>
                    <a href="../index.php#contact" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">Contact</a>
                    
                    <!-- Show Dashboard link when logged in -->
                    <?php if($isLoggedIn): ?>
                    <a href="<?php echo $userRole === 'tenant' ? 'tenant_dashboard.php' : 'landlord_dashboard.php'; ?>" class="text-blue-600 hover:text-blue-800 transition-colors font-semibold">
                        <i class="fas fa-tachometer-alt mr-2"></i><?php echo $userRole === 'tenant' ? 'Tenant Dashboard' : 'Landlord Dashboard'; ?>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Desktop User Section -->
                <div class="hidden md:flex items-center space-x-4">
                    <?php if(!$isLoggedIn): ?>
                    <!-- Guest Links -->
                    <div class="flex items-center space-x-4">
                        <a href="../auth/login.php" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login
                        </a>
                        <a href="../auth/register.php" class="btn-primary px-4 py-2 rounded-lg text-white font-semibold">
                            <i class="fas fa-user-plus mr-2"></i>Register
                        </a>
                    </div>
                    <?php else: ?>
                    <!-- Logged In Links -->
                    <div class="flex items-center space-x-4">
                        <a href="#" class="text-gray-700 hover:text-blue-600 transition-colors font-medium" 
                           onclick="confirmLogout(); return false;">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="md:hidden flex items-center">
                    <button onclick="toggleMobileNav()" class="mobile-menu-btn" id="mobileMenuBtn">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
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

       <!-- Property Header and Images - Combined Container -->
<div class="bg-white shadow rounded-2xl p-6 mb-6">
    <!-- Property Header -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 animate-fadeInUp">
      <div>
        <h1 class="text-2xl font-bold animate-slideInLeft"><?php echo htmlspecialchars($property['title']); ?></h1>
        <p class="flex items-center text-base text-gray-600 mt-2 animate-slideInLeft animation-delay-200">
          <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
          <?php echo htmlspecialchars($property['address']); ?>
        </p>
      </div>
      <div class="flex flex-col items-end animate-slideInRight">
        <span class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full mb-2">Available</span>
        <div class="text-2xl font-bold text-green-600">
          R<?php echo number_format($property['rent_amount']); ?>/month
        </div>
      </div>
    </div>

    <!-- Property Images - Professional Gallery -->
    <h2 class="text-2xl font-bold mb-4 flex items-center">
        <i class="fas fa-images text-blue-500 mr-3"></i>
        Property Images
        <?php if (!empty($images)): ?>
            <span class="ml-2 text-sm font-normal text-gray-500">(<?php echo count($images); ?> <?php echo count($images) == 1 ? 'image' : 'images'; ?>)</span>
        <?php endif; ?>
    </h2>
    <?php if (!empty($images)): ?>
        <div class="property-image-gallery">
            <!-- Main Large Image -->
            <div class="main-gallery-image">
                <?php 
                $mainImage = $images[0];
                $isUrl = strpos($mainImage['image_url'], 'http') === 0;
                $imagePath = $isUrl ? $mainImage['image_url'] : '../uploads/properties/' . $mainImage['image_url'];
                ?>
                <img src="<?php echo $imagePath; ?>" 
                     alt="Main property image" 
                     id="mainGalleryImage"
                     onclick="openModal(0)"
                     class="cursor-pointer"
                     onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'500\'%3E%3Crect fill=\'%23e5e7eb\' width=\'800\' height=\'500\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%236b7280\' font-family=\'Arial\' font-size=\'20\'%3EImage not available%3C/text%3E%3C/svg%3E';">
                
                <!-- Image Counter -->
                <div class="image-counter">
                    <span id="currentImageIndex">1</span> / <?php echo count($images); ?>
                </div>
                
                <!-- Navigation Arrows -->
                <?php if (count($images) > 1): ?>
                    <button class="gallery-arrow gallery-arrow-prev" onclick="changeMainImage(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="gallery-arrow gallery-arrow-next" onclick="changeMainImage(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Thumbnail Strip -->
            <?php if (count($images) > 1): ?>
                <div class="thumbnail-strip">
                    <?php foreach ($images as $index => $image): ?>
                        <?php 
                        $isUrl = strpos($image['image_url'], 'http') === 0;
                        $thumbSrc = $isUrl ? $image['image_url'] : '../uploads/properties/' . $image['image_url'];
                        ?>
                        <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                             onclick="setMainImage(<?php echo $index; ?>)"
                             data-index="<?php echo $index; ?>">
                            <img src="<?php echo $thumbSrc; ?>" 
                                 alt="Thumbnail <?php echo $index + 1; ?>"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'thumbnail-placeholder\'><i class=\'fas fa-image\'></i></div>';">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="flex flex-col justify-center items-center h-64 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg text-gray-500">
            <i class="fas fa-image text-6xl mb-4 text-gray-400"></i>
            <p class="text-lg font-medium">No Images Available</p>
            <p class="text-sm mt-2">Images will be displayed here once uploaded</p>
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
                  <div class="font-semibold"><?php echo htmlspecialchars($property['bedrooms'] ?? 'N/A'); ?></div>
                  <div class="text-sm text-gray-600">Bedrooms</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                  <i class="fas fa-bath text-2xl text-blue-500 mb-2"></i>
                  <div class="font-semibold"><?php echo htmlspecialchars($property['bathrooms'] ?? 'N/A'); ?></div>
                  <div class="text-sm text-gray-600">Bathrooms</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                  <i class="fas fa-expand-arrows-alt text-2xl text-blue-500 mb-2"></i>
                  <div class="font-semibold"><?php echo htmlspecialchars($property['square_meters'] ?? 'N/A'); ?> m²</div>
                  <div class="text-sm text-gray-600">Square Meters</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                  <i class="fas fa-home text-2xl text-blue-500 mb-2"></i>
                  <div class="font-semibold"><?php echo ucfirst(htmlspecialchars($property['property_type'] ?? 'N/A')); ?></div>
                  <div class="text-sm text-gray-600">Type</div>
                </div>
              </div>
              
              <h3 class="text-xl font-semibold mb-3">Description</h3>
              <p class="text-gray-600 leading-relaxed mb-6">
                <?php echo nl2br(htmlspecialchars($property['description'] ?? 'No description available.')); ?>
              </p>

              <!-- Location Details -->
              <div class="border-t pt-6">
                <h3 class="text-xl font-semibold mb-4">Location Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="flex items-start">
                    <i class="fas fa-map-marker-alt text-blue-500 mr-3 mt-1"></i>
                    <div>
                      <div class="font-semibold text-gray-700">Address</div>
                      <div class="text-gray-600"><?php echo htmlspecialchars($property['address'] ?? 'N/A'); ?></div>
                    </div>
                  </div>
                  <div class="flex items-start">
                    <i class="fas fa-city text-blue-500 mr-3 mt-1"></i>
                    <div>
                      <div class="font-semibold text-gray-700">City</div>
                      <div class="text-gray-600"><?php echo htmlspecialchars($property['city'] ?? 'N/A'); ?></div>
                    </div>
                  </div>
                  <div class="flex items-start">
                    <i class="fas fa-map text-blue-500 mr-3 mt-1"></i>
                    <div>
                      <div class="font-semibold text-gray-700">State/Province</div>
                      <div class="text-gray-600"><?php echo htmlspecialchars($property['state'] ?? 'N/A'); ?></div>
                    </div>
                  </div>
                  <div class="flex items-start">
                    <i class="fas fa-mail-bulk text-blue-500 mr-3 mt-1"></i>
                    <div>
                      <div class="font-semibold text-gray-700">Postal Code</div>
                      <div class="text-gray-600"><?php echo htmlspecialchars($property['postal_code'] ?? 'N/A'); ?></div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Property Features -->
              <div class="border-t pt-6 mt-6">
                <h3 class="text-xl font-semibold mb-4">Property Features</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                  <div class="flex items-center">
                    <i class="fas fa-<?php echo ($property['utilities_included'] ?? 0) ? 'check-circle text-green-500' : 'times-circle text-gray-400'; ?> mr-3"></i>
                    <span class="text-gray-700">Utilities Included</span>
                  </div>
                  <div class="flex items-center">
                    <i class="fas fa-<?php echo ($property['parking_available'] ?? 0) ? 'check-circle text-green-500' : 'times-circle text-gray-400'; ?> mr-3"></i>
                    <span class="text-gray-700">Parking Available</span>
                  </div>
                  <div class="flex items-center">
                    <i class="fas fa-<?php echo ($property['pet_friendly'] ?? 0) ? 'check-circle text-green-500' : 'times-circle text-gray-400'; ?> mr-3"></i>
                    <span class="text-gray-700">Pet Friendly</span>
                  </div>
                  <div class="flex items-center">
                    <i class="fas fa-<?php echo ($property['furnished'] ?? 0) ? 'check-circle text-green-500' : 'times-circle text-gray-400'; ?> mr-3"></i>
                    <span class="text-gray-700">Furnished</span>
                  </div>
                </div>
              </div>

              <!-- Availability & Lease -->
              <div class="border-t pt-6 mt-6">
                <h3 class="text-xl font-semibold mb-4">Availability & Lease Terms</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="flex items-start">
                    <i class="fas fa-calendar-check text-blue-500 mr-3 mt-1"></i>
                    <div>
                      <div class="font-semibold text-gray-700">Available From</div>
                      <div class="text-gray-600"><?php echo $property['available_from'] ? date('F j, Y', strtotime($property['available_from'])) : 'N/A'; ?></div>
                    </div>
                  </div>
                  <div class="flex items-start">
                    <i class="fas fa-calendar-alt text-blue-500 mr-3 mt-1"></i>
                    <div>
                      <div class="font-semibold text-gray-700">Lease Duration</div>
                      <div class="text-gray-600">
                        <?php 
                        $duration = $property['lease_duration_months'] ?? 0;
                        if ($duration == 0) {
                            echo 'Month-to-Month';
                        } else {
                            echo $duration . ' Month' . ($duration > 1 ? 's' : '');
                        }
                        ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <?php if (!empty($amenities)): ?>
              <div class="bg-white shadow rounded-2xl p-6 mb-6">
                <h2 class="text-2xl font-bold mb-4 flex items-center">
                  <i class="fas fa-star text-yellow-500 mr-3"></i>
                  Amenities & Features
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <?php foreach ($amenities as $amenity): ?>
                    <div class="flex items-center p-3 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg border border-blue-200 hover:shadow-md transition-shadow">
                      <i class="fas fa-<?php echo htmlspecialchars($amenity['icon'] ?? 'check-circle'); ?> text-blue-500 mr-3 text-lg"></i>
                      <span class="font-medium text-gray-700"><?php echo htmlspecialchars($amenity['name']); ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Sidebar -->
          <div>
            <div class="bg-white shadow rounded-2xl p-6 mb-6">
              <h3 class="text-xl font-bold mb-4">Contact Landlord</h3>
              <?php if ($isLoggedIn && $userRole === 'tenant'): ?>
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
              <?php else: ?>
              <div class="bg-gray-100 border border-gray-300 text-gray-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-lock mr-2"></i> Contact information is available to registered tenants only. <a href="../auth/login.php" class="text-blue-600 hover:underline">Sign in</a> or <a href="../auth/register.php" class="text-blue-600 hover:underline">register</a> to view contact details.
              </div>
              <?php endif; ?>
              <?php if ($isLoggedIn && $userRole === 'tenant'): ?>
                <?php if ($hasApplied): ?>
                  <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded mb-3">
                    <i class="fas fa-check-circle mr-2"></i> You have already applied for this property
                  </div>
                <?php else: ?>
                  <button onclick="applyForProperty()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors mb-3">
                    <i class="fas fa-paper-plane mr-2"></i>Apply for Property
                  </button>
                <?php endif; ?>
              <?php elseif (!$isLoggedIn): ?>
                <div class="bg-blue-100 border border-blue-300 text-blue-700 px-4 py-3 rounded mb-3">
                  <i class="fas fa-info-circle mr-2"></i> Sign in to apply for this property
                </div>
                <a href="../auth/login.php" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors mb-3">
                  <i class="fas fa-sign-in-alt mr-2"></i>Login to Apply
                </a>
                <a href="../auth/register.php" class="block w-full text-center border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-semibold py-3 px-4 rounded-lg transition-colors">
                  <i class="fas fa-user-plus mr-2"></i>Register
                </a>
              <?php else: ?>
                <div class="bg-blue-100 border border-blue-300 text-blue-700 px-4 py-3 rounded">
                  <i class="fas fa-info-circle mr-2"></i> Contact information is available to tenants only
                </div>
              <?php endif; ?>
            </div>

            <div class="bg-white shadow rounded-2xl p-6 mb-6">
              <h3 class="text-xl font-bold mb-4">Pricing Information</h3>
              <div class="space-y-4">
                <div class="flex justify-between items-center pb-3 border-b">
                  <span class="text-gray-600">Monthly Rent:</span>
                  <span class="font-bold text-blue-600 text-lg">R<?php echo number_format($property['rent_amount'] ?? 0, 2); ?></span>
                </div>
                <?php if (!empty($property['deposit_amount'])): ?>
                  <div class="flex justify-between items-center pb-3 border-b">
                    <span class="text-gray-600">Security Deposit:</span>
                    <span class="font-semibold">R<?php echo number_format($property['deposit_amount'], 2); ?></span>
                  </div>
                <?php endif; ?>
                <div class="pt-2">
                  <div class="text-sm text-gray-500 mb-1">Total First Payment:</div>
                  <div class="text-2xl font-bold text-green-600">
                    R<?php echo number_format(($property['rent_amount'] ?? 0) + ($property['deposit_amount'] ?? 0), 2); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white shadow rounded-2xl p-6">
              <h3 class="text-xl font-bold mb-4">Property Information</h3>
              <div class="space-y-3">
                <div class="flex justify-between">
                  <span class="text-gray-600">Property ID:</span>
                  <span class="font-semibold">#<?php echo str_pad($property['id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Listed on:</span>
                  <span class="font-semibold"><?php echo date('M d, Y', strtotime($property['created_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Property Type:</span>
                  <span class="font-semibold"><?php echo ucfirst(htmlspecialchars($property['property_type'] ?? 'N/A')); ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Bedrooms:</span>
                  <span class="font-semibold"><?php echo htmlspecialchars($property['bedrooms'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Bathrooms:</span>
                  <span class="font-semibold"><?php echo htmlspecialchars($property['bathrooms'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-600">Size:</span>
                  <span class="font-semibold"><?php echo htmlspecialchars($property['square_meters'] ?? 'N/A'); ?> m²</span>
                </div>
                <?php if (!empty($property['lease_duration_months'])): ?>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Lease Duration:</span>
                    <span class="font-semibold">
                      <?php 
                      $duration = $property['lease_duration_months'];
                      if ($duration == 0) {
                          echo 'Month-to-Month';
                      } else {
                          echo $duration . ' Month' . ($duration > 1 ? 's' : '');
                      }
                      ?>
                    </span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($property['available_from'])): ?>
                  <div class="flex justify-between">
                    <span class="text-gray-600">Available From:</span>
                    <span class="font-semibold"><?php echo date('M d, Y', strtotime($property['available_from'])); ?></span>
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
// Image gallery functionality
const images = <?php 
    $imageUrls = [];
    foreach ($images as $image) {
        if (!empty($image['image_url'])) {
            $isUrl = strpos($image['image_url'], 'http') === 0;
            $imageUrls[] = $isUrl ? $image['image_url'] : '../uploads/properties/' . $image['image_url'];
        }
    }
    echo json_encode($imageUrls);
?>;
        
        let currentImageIndex = 0;

        // Set main image from thumbnail
        function setMainImage(index) {
            if (!images || images.length === 0 || index < 0 || index >= images.length) {
                return;
            }
            
            currentImageIndex = index;
            const mainImage = document.getElementById('mainGalleryImage');
            const currentIndexSpan = document.getElementById('currentImageIndex');
            
            if (mainImage && images[index]) {
                mainImage.src = images[index];
            }
            
            if (currentIndexSpan) {
                currentIndexSpan.textContent = index + 1;
            }
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail-item').forEach((thumb, i) => {
                if (i === index) {
                    thumb.classList.add('active');
                } else {
                    thumb.classList.remove('active');
                }
            });
        }

        // Change main image with arrows
        function changeMainImage(direction) {
            if (!images || images.length === 0) return;
            
            currentImageIndex += direction;
            if (currentImageIndex >= images.length) {
                currentImageIndex = 0;
            } else if (currentImageIndex < 0) {
                currentImageIndex = images.length - 1;
            }
            
            setMainImage(currentImageIndex);
        }

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
        // Mobile Navigation Functions
        function toggleMobileNav() {
            const overlay = document.getElementById('mobileNavOverlay');
            const container = document.getElementById('mobileNavContainer');
            const btn = document.getElementById('mobileMenuBtn');
            
            if (overlay && container && btn) {
                overlay.classList.toggle('show');
                container.classList.toggle('open');
                btn.classList.toggle('active');
            }
        }

        function closeMobileNav() {
            const overlay = document.getElementById('mobileNavOverlay');
            const container = document.getElementById('mobileNavContainer');
            const btn = document.getElementById('mobileMenuBtn');
            
            if (overlay && container && btn) {
                overlay.classList.remove('show');
                container.classList.remove('open');
                btn.classList.remove('active');
            }
        }

        // Close mobile nav when clicking overlay
        document.addEventListener('click', function(event) {
            const overlay = document.getElementById('mobileNavOverlay');
            const container = document.getElementById('mobileNavContainer');
            
            if (overlay && container && event.target === overlay) {
                closeMobileNav();
            }
        });

        // Logout confirmation
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You will be logged out from your account.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../auth/logout.php';
                }
            });
        }
    </script>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <div class="modal-content">
            <img id="modalImage" class="modal-image" src="" alt="Property image">
            <?php if (count($images) > 1): ?>
                <button class="prev" onclick="changeImage(-1)">&#10094;</button>
                <button class="next" onclick="changeImage(1)">&#10095;</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer py-12">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center mb-4">
                        <h1 class="text-2xl font-bold text-white">EasyRent</h1>
                        <p class="text-xs text-blue-200 ml-2">Property Management</p>
                    </div>
                    <p class="text-gray-300 mb-4">
                        Professional property management solutions for landlords and tenants. Making rental processes simple and efficient.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="../index.php" class="text-gray-300 hover:text-white transition-colors">Home</a></li>
                        <li><a href="../index.php#properties" class="text-gray-300 hover:text-white transition-colors">Properties</a></li>
                        <li><a href="../index.php#about" class="text-gray-300 hover:text-white transition-colors">About Us</a></li>
                        <li><a href="../index.php#contact" class="text-gray-300 hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>

                <!-- Services -->
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Services</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Property Listing</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Tenant Screening</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Lease Management</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Maintenance</a></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-t border-gray-700 mt-8 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400 text-sm">
                        &copy; <?php echo date('Y'); ?> EasyRent. All rights reserved.
                    </p>
                    <div class="flex space-x-6 mt-4 md:mt-0">
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Privacy Policy</a>
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Terms of Service</a>
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Cookie Policy</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
