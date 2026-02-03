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
            WHERE p.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $propertyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: my_properties.php");
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
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['title'] ?? 'Property Details'); ?> - EasyRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles - From landlord_dashboard.php */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-user {
            padding: 1.5rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
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

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            list-style: none;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .logout-link {
            margin-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 999;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border: none;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .back-btn {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Property Header and Images - Combined Container */
        .property-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }

        .property-header {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .property-header-left h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .property-header-left p {
            display: flex;
            align-items: center;
            font-size: 1rem;
            color: #64748b;
        }

        .property-header-right {
            text-align: right;
        }

        .status-badge {
            display: inline-block;
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .property-price {
            font-size: 2rem;
            font-weight: bold;
            color: #059669;
        }

        /* Property Image Gallery */
        .property-image-gallery {
            width: 100%;
            margin-bottom: 2rem;
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
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease, opacity 0.5s ease;
            animation: fadeIn 0.5s ease-in-out;
        }

        .main-gallery-image img:hover {
            transform: scale(1.05);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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

        .gallery-arrow-prev { left: 1rem; }
        .gallery-arrow-next { right: 1rem; }

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

        .thumbnail-item {
            flex-shrink: 0;
            width: 120px;
            height: 80px;
            border-radius: 0.5rem;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
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

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .detail-item {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-radius: 12px;
        }

        .detail-item i {
            font-size: 2rem;
            color: #3b82f6;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .detail-label {
            font-size: 0.875rem;
            color: #64748b;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .description-text {
            color: #64748b;
            line-height: 1.8;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .amenity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-radius: 10px;
            border: 1px solid #bfdbfe;
        }

        .amenity-item i {
            color: #3b82f6;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .info-group {
            display: flex;
            align-items: start;
            margin-bottom: 1rem;
        }

        .info-group i {
            color: #3b82f6;
            margin-right: 0.75rem;
            margin-top: 0.25rem;
            font-size: 1.1rem;
        }

        .info-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #64748b;
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-item i {
            color: #10b981;
            font-size: 1rem;
        }

        /* Modal styles */
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
            width: 80%;
            max-width: 800px;
            top: 50%;
            transform: translateY(-50%);
        }

        .modal-image {
            width: 100%;
            height: auto;
            max-height: 70vh;
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

        /* Responsive Design */
        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .mobile-menu-btn {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .property-header {
                flex-direction: column;
                text-align: center;
            }

            .property-header-right {
                text-align: center;
            }

            .main-gallery-image {
                width: 100%;
                height: 250px;
            }

            .thumbnail-item {
                width: 100px;
                height: 70px;
            }

            .modal-content {
                width: 95%;
                margin: 20px auto;
            }
        }

        @media (max-width: 640px) {
            .details-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .amenities-grid {
                grid-template-columns: 1fr;
            }

            .feature-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-home"></i>
                Easy Rent
            </div>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php echo strtoupper(substr($username ?? 'L', 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($username ?? 'Landlord'); ?></div>
                <div class="user-role">Landlord</div>
            </div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a href="profile_landlord.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="landlord_dashboard.php" class="nav-link">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="my_properties.php" class="nav-link active">
                    <i class="fas fa-building"></i>
                    <span>My Properties</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="applications.php" class="nav-link">
                    <i class="fas fa-file-alt"></i>
                    <span>Applications</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="add_property.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Property</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="maintenance.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="tenants.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Tenants</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item logout-link">
                <a href="../auth/logout.php" class="nav-link" id="logoutLink">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($error)): ?>
            <div style="background: #fee; border: 1px solid #fcc; color: #c33; padding: 1rem; border-radius: 10px; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-building"></i>
                Property Details
            </h1>
            <a href="my_properties.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Properties
            </a>
        </div>

        <!-- Property Header and Images Container -->
        <div class="property-container">
            <!-- Property Header -->
            <div class="property-header">
                <div class="property-header-left">
                    <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                    <p>
                        <i class="fas fa-map-marker-alt" style="color: #3b82f6; margin-right: 0.5rem;"></i>
                        <?php echo htmlspecialchars($property['address']); ?>
                    </p>
                </div>
                <div class="property-header-right">
                    <span class="status-badge">Available</span>
                    <div class="property-price">
                        R<?php echo number_format($property['rent_amount']); ?>/month
                    </div>
                </div>
            </div>

            <!-- Property Images Gallery -->
            <h2 class="card-title">
                <i class="fas fa-images"></i>
                Property Images
                <?php if (!empty($images)): ?>
                    <span style="font-size: 0.875rem; font-weight: normal; color: #64748b;">(<?php echo count($images); ?> <?php echo count($images) == 1 ? 'image' : 'images'; ?>)</span>
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
                             onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'image-placeholder\'><i class=\'fas fa-image\'></i><p>Image not available</p></div>';">
                        
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
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'image-placeholder\'><i class=\'fas fa-image\'></i></div>';">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="image-placeholder" style="height: 300px; border-radius: 1rem;">
                    <i class="fas fa-image" style="font-size: 4rem; margin-bottom: 1rem; color: #9ca3af;"></i>
                    <p style="font-size: 1.1rem;">No Images Available</p>
                    <p style="font-size: 0.9rem; margin-top: 0.5rem;">Images will be displayed here once uploaded</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Left Column - Property Details -->
            <div>
                <!-- Property Details -->
                <div class="content-card">
                    <h2 class="card-title">Property Details</h2>
                    <div class="details-grid">
                        <div class="detail-item">
                            <i class="fas fa-bed"></i>
                            <div class="detail-value"><?php echo htmlspecialchars($property['bedrooms'] ?? 'N/A'); ?></div>
                            <div class="detail-label">Bedrooms</div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-bath"></i>
                            <div class="detail-value"><?php echo htmlspecialchars($property['bathrooms'] ?? 'N/A'); ?></div>
                            <div class="detail-label">Bathrooms</div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-expand-arrows-alt"></i>
                            <div class="detail-value"><?php echo htmlspecialchars($property['square_meters'] ?? 'N/A'); ?> m²</div>
                            <div class="detail-label">Square Meters</div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-home"></i>
                            <div class="detail-value"><?php echo ucfirst(htmlspecialchars($property['property_type'] ?? 'N/A')); ?></div>
                            <div class="detail-label">Type</div>
                        </div>
                    </div>
                    
                    <h3 class="section-title">Description</h3>
                    <p class="description-text">
                        <?php echo nl2br(htmlspecialchars($property['description'] ?? 'No description available.')); ?>
                    </p>
                </div>

                <!-- Location Details -->
                <div class="content-card">
                    <h2 class="card-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Location Details
                    </h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div class="info-group">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <div class="info-label">Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($property['address'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="info-group">
                            <i class="fas fa-city"></i>
                            <div>
                                <div class="info-label">City</div>
                                <div class="info-value"><?php echo htmlspecialchars($property['city'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="info-group">
                            <i class="fas fa-map"></i>
                            <div>
                                <div class="info-label">State/Province</div>
                                <div class="info-value"><?php echo htmlspecialchars($property['state'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                        <div class="info-group">
                            <i class="fas fa-mail-bulk"></i>
                            <div>
                                <div class="info-label">Postal Code</div>
                                <div class="info-value"><?php echo htmlspecialchars($property['postal_code'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Property Features -->
                <div class="content-card">
                    <h2 class="card-title">
                        <i class="fas fa-check-circle"></i>
                        Property Features
                    </h2>
                    <div class="feature-list">
                        <div class="feature-item">
                            <i class="fas fa-<?php echo ($property['utilities_included'] ?? 0) ? 'check-circle' : 'times-circle'; ?>" 
                               style="color: <?php echo ($property['utilities_included'] ?? 0) ? '#10b981' : '#9ca3af'; ?>"></i>
                            <span>Utilities Included</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-<?php echo ($property['parking_available'] ?? 0) ? 'check-circle' : 'times-circle'; ?>" 
                               style="color: <?php echo ($property['parking_available'] ?? 0) ? '#10b981' : '#9ca3af'; ?>"></i>
                            <span>Parking Available</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-<?php echo ($property['pet_friendly'] ?? 0) ? 'check-circle' : 'times-circle'; ?>" 
                               style="color: <?php echo ($property['pet_friendly'] ?? 0) ? '#10b981' : '#9ca3af'; ?>"></i>
                            <span>Pet Friendly</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-<?php echo ($property['furnished'] ?? 0) ? 'check-circle' : 'times-circle'; ?>" 
                               style="color: <?php echo ($property['furnished'] ?? 0) ? '#10b981' : '#9ca3af'; ?>"></i>
                            <span>Furnished</span>
                        </div>
                    </div>
                </div>

                <!-- Amenities -->
                <?php if (!empty($amenities)): ?>
                <div class="content-card">
                    <h2 class="card-title">
                        <i class="fas fa-star"></i>
                        Amenities & Features
                    </h2>
                    <div class="amenities-grid">
                        <?php foreach ($amenities as $amenity): ?>
                            <div class="amenity-item">
                                <i class="fas fa-<?php echo htmlspecialchars($amenity['icon'] ?? 'check-circle'); ?>"></i>
                                <span><?php echo htmlspecialchars($amenity['name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Sidebar Info -->
            <div>
                <!-- Landlord Contact -->
                <div class="content-card">
                    <h3 class="card-title">
                        <i class="fas fa-user"></i>
                        Landlord Contact
                    </h3>
                    <div style="space-y: 1rem;">
                        <div class="info-group">
                            <i class="fas fa-user"></i>
                            <div>
                                <div class="info-label">Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($property['landlord_name']); ?></div>
                            </div>
                        </div>
                        <div class="info-group">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($property['landlord_email']); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($property['landlord_phone'])): ?>
                        <div class="info-group">
                            <i class="fas fa-phone"></i>
                            <div>
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($property['landlord_phone']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pricing Information -->
                <div class="content-card">
                    <h3 class="card-title">
                        <i class="fas fa-money-bill-wave"></i>
                        Pricing Information
                    </h3>
                    <div style="padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b;">Monthly Rent:</span>
                            <span style="font-weight: bold; color: #3b82f6; font-size: 1.25rem;">R<?php echo number_format($property['rent_amount'] ?? 0, 2); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($property['deposit_amount'])): ?>
                    <div style="padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b;">Security Deposit:</span>
                            <span style="font-weight: 600;">R<?php echo number_format($property['deposit_amount'], 2); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div style="padding-top: 0.5rem;">
                        <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.5rem;">Total First Payment:</div>
                        <div style="font-size: 1.75rem; font-weight: bold; color: #10b981;">
                            R<?php echo number_format(($property['rent_amount'] ?? 0) + ($property['deposit_amount'] ?? 0), 2); ?>
                        </div>
                    </div>
                </div>

                <!-- Property Information -->
                <div class="content-card">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        Property Information
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Property ID:</span>
                            <span style="font-weight: 600;">#<?php echo str_pad($property['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Listed on:</span>
                            <span style="font-weight: 600;"><?php echo date('M d, Y', strtotime($property['created_at'])); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Property Type:</span>
                            <span style="font-weight: 600;"><?php echo ucfirst(htmlspecialchars($property['property_type'] ?? 'N/A')); ?></span>
                        </div>
                        <?php if (!empty($property['lease_duration_months'])): ?>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Lease Duration:</span>
                            <span style="font-weight: 600;">
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
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Available From:</span>
                            <span style="font-weight: 600;"><?php echo date('M d, Y', strtotime($property['available_from'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>

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
                return;
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
            
            currentImageIndex += direction;
            if (currentImageIndex >= images.length) currentImageIndex = 0;
            if (currentImageIndex < 0) currentImageIndex = images.length - 1;
            
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

        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            const sidebar = document.getElementById('sidebar');
            const mobileBtn = document.querySelector('.mobile-menu-btn');
            
            if (window.innerWidth < 900 &&
                sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                !mobileBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Logout confirmation
        document.getElementById('logoutLink').addEventListener('click', function(e) {
            e.preventDefault();

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
        });
    </script>
</body>
</html>