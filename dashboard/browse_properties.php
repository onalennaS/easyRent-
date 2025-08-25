<?php
// browse_properties.php - FIXED IMAGE DISPLAY

session_start();

// Check if user is logged in and is a tenant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tenant') {
    header("Location: ../login.php");
    exit();
}

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

$tenant_id = $_SESSION['user_id'];

// Pre-fetch applied property IDs
$applied_property_ids = [];
$applied_query = "SELECT property_id FROM rental_applications WHERE tenant_id = $tenant_id";
$applied_result = mysqli_query($conn, $applied_query);
if ($applied_result && mysqli_num_rows($applied_result) > 0) {
    while ($row = mysqli_fetch_assoc($applied_result)) {
        $applied_property_ids[] = $row['property_id'];
    }
}

// Check what columns exist in tables
$check_properties_query = "SHOW COLUMNS FROM properties";
$properties_columns_result = mysqli_query($conn, $check_properties_query);
$properties_columns = [];
if ($properties_columns_result) {
    while ($column = mysqli_fetch_assoc($properties_columns_result)) {
        $properties_columns[] = $column['Field'];
    }
}

$has_status = in_array('status', $properties_columns);
$has_rent_amount = in_array('rent_amount', $properties_columns);

// Check if applications table exists
$check_applications_table = "SHOW TABLES LIKE 'rental_applications'";
$applications_table_result = mysqli_query($conn, $check_applications_table);
$has_applications_table = mysqli_num_rows($applications_table_result) > 0;

// Check if property_images table exists
$check_images_table = "SHOW TABLES LIKE 'property_images'";
$images_table_result = mysqli_query($conn, $check_images_table);
$has_images_table = mysqli_num_rows($images_table_result) > 0;

// Initialize search parameters with proper defaults
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : 0;
$bedrooms = isset($_GET['bedrooms']) && $_GET['bedrooms'] !== '' ? intval($_GET['bedrooms']) : 0;
$property_type = isset($_GET['property_type']) ? mysqli_real_escape_string($conn, $_GET['property_type']) : '';

// Build properties query with filters - FIXED LOGIC
$where_clauses = [];

// Only add status filter if the column exists
if ($has_status) {
    $where_clauses[] = "p.status = 'approved'";
}

// Search filter
if (!empty($search)) {
    $where_clauses[] = "(p.title LIKE '%$search%' OR p.description LIKE '%$search%' OR p.address LIKE '%$search%')";
}

// FIXED: Price filters now handle all cases correctly
if ($has_rent_amount) {
    if ($min_price > 0) {
        $where_clauses[] = "p.rent_amount >= $min_price";
    }
    if ($max_price > 0) {
        $where_clauses[] = "p.rent_amount <= $max_price";
    }
}

// Bedrooms filter - only apply if column exists
if (in_array('bedrooms', $properties_columns) && $bedrooms > 0) {
    $where_clauses[] = "p.bedrooms = $bedrooms";
}

// Property type filter
if (!empty($property_type)) {
    $where_clauses[] = "p.property_type = '$property_type'";
}

$where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Debug: Log the query being built
error_log("WHERE SQL: " . $where_sql);

// Get property types for dropdown
$property_types_query = "SELECT DISTINCT property_type FROM properties WHERE property_type != '' AND property_type IS NOT NULL";
$property_types_result = mysqli_query($conn, $property_types_query);
$property_types = [];
if ($property_types_result) {
    while ($row = mysqli_fetch_assoc($property_types_result)) {
        $property_types[] = $row['property_type'];
    }
}

// Available properties query with pagination
$per_page = 12;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Get total properties for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM properties p 
    $where_sql
";

error_log("Count Query: " . $count_query);

$count_result = mysqli_query($conn, $count_query);
if (!$count_result) {
    error_log("Count query failed: " . mysqli_error($conn));
    $total_properties = 0;
} else {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_properties = $count_row ? intval($count_row['total']) : 0;
}

$total_pages = $total_properties > 0 ? ceil($total_properties / $per_page) : 1;

// Main properties query - FIXED: Added image handling
$available_properties_query = "
    SELECT p.*, 
           (SELECT image_url FROM property_images 
            WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS main_image
    FROM properties p 
    $where_sql
    ORDER BY p.created_at DESC 
    LIMIT $offset, $per_page
";

error_log("Main Query: " . $available_properties_query);

$available_properties = mysqli_query($conn, $available_properties_query);
if (!$available_properties) {
    error_log("Properties query failed: " . mysqli_error($conn));
}

// Handle rental application
if ($has_applications_table && isset($_POST['apply_property'])) {
    $property_id = intval($_POST['property_id']);
    
    // Check if already applied
    $check_existing = "SELECT id FROM rental_applications WHERE tenant_id = $tenant_id AND property_id = $property_id";
    $existing_result = mysqli_query($conn, $check_existing);
    
    if ($existing_result && mysqli_num_rows($existing_result) > 0) {
        $_SESSION['error_message'] = "You have already applied for this property!";
    } else {
        $insert_application = "INSERT INTO rental_applications (tenant_id, property_id, status, created_at) 
                              VALUES ($tenant_id, $property_id, 'pending', NOW())";
        
        if (mysqli_query($conn, $insert_application)) {
            $_SESSION['success_message'] = "Application submitted successfully!";
            // Update applied properties array
            $applied_property_ids[] = $property_id;
        } else {
            $_SESSION['error_message'] = "Error submitting application: " . mysqli_error($conn);
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: browse_properties.php?" . http_build_query($_GET));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Properties - Easy Rent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS styles remain unchanged */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
            color: white;
            padding: 20px 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }

        .sidebar .logo h2 {
            font-size: 24px;
            font-weight: bold;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar ul li {
            margin: 5px 0;
        }

        .sidebar ul li a {
            display: block;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background-color: rgba(255,255,255,0.1);
            border-left-color: #fff;
        }

        .sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: opacity 0.3s ease;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .filters-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            margin-bottom: 15px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.2);
        }

        .filter-actions {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 10px;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4a90e2 0%, #2a6fc9 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #3a80d2 0%, #1a5fb9 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(42, 111, 201, 0.25);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #4a90e2;
            color: #4a90e2;
        }

        .btn-outline:hover {
            background-color: rgba(74, 144, 226, 0.1);
        }

        .btn-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #33d96b 0%, #28e9c7 100%);
        }
        
        .btn-disabled {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            cursor: not-allowed;
            opacity: 0.9;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section h2 {
            color: #333;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .results-summary {
            color: #666;
            font-size: 16px;
        }

        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .property-card {
            border: 1px solid #eee;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }

        .property-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-5px);
        }

        .property-image {
            height: 200px;
            width: 100%;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            flex-direction: column;
            gap: 10px;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-content {
            padding: 20px;
        }

        .property-card h3 {
            color: #333;
            margin-bottom: 12px;
            font-size: 20px;
        }

        .property-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            color: #666;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }

        .property-card p {
            color: #666;
            font-size: 15px;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .property-address {
            margin-bottom: 15px;
        }

        .property-price {
            color: #28a745;
            font-weight: bold;
            font-size: 22px;
            margin: 15px 0;
        }

        .property-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .landlord-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }

        .landlord-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }

        .property-actions {
            display: flex;
            gap: 10px;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-results i {
            font-size: 72px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .no-results h3 {
            margin-bottom: 10px;
            color: #555;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 8px;
        }

        .page-item {
            display: inline-block;
        }

        .page-link {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            color: #4a90e2;
            text-decoration: none;
            transition: all 0.3s;
        }

        .page-link:hover {
            background-color: #f0f7ff;
            border-color: #4a90e2;
        }

        .page-item.active .page-link {
            background-color: #4a90e2;
            color: white;
            border-color: #4a90e2;
        }

        .page-item.disabled .page-link {
            color: #aaa;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .property-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 600px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 70px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .property-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>Easy Rent</h2>
            <p>Tenant Portal</p>
        </div>
        <ul>
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="browse_properties.php" class="active"><i class="fas fa-search"></i> Browse Properties</a></li>
            <li><a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a></li>
            <li><a href="my_lease.php"><i class="fas fa-file-contract"></i> My Lease</a></li>
            <li><a href="maintenance_requests.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="payment_history.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="tenant_profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Find Your Perfect Home</h1>
            <div class="tenant-info">
                <span>Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Tenant'); ?></span>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Search Filters -->
        <div class="filters-container">
            <form method="GET" action="browse_properties.php" id="filter-form">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="search"><i class="fas fa-search"></i> Search</label>
                        <input type="text" id="search" name="search" placeholder="Location, keywords..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
    <label for="min_price"><span class="text-gray-700 font-medium">R</span> Min Price</label>
    <input type="number" id="min_price" name="min_price" placeholder="Min (R)" 
           value="<?php echo $min_price > 0 ? $min_price : ''; ?>" min="0" step="50">
</div>

<div class="filter-group">
    <label for="max_price"><span class="text-gray-700 font-medium">R</span> Max Price</label>
    <input type="number" id="max_price" name="max_price" placeholder="Max (R)" 
           value="<?php echo $max_price > 0 ? $max_price : ''; ?>" min="0" step="50">
</div>

                    
                    <div class="filter-group">
                        <label for="bedrooms"><i class="fas fa-bed"></i> Bedrooms</label>
                        <select id="bedrooms" name="bedrooms">
                            <option value="">Any</option>
                            <option value="1" <?php echo $bedrooms == 1 ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo $bedrooms == 2 ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo $bedrooms == 3 ? 'selected' : ''; ?>>3</option>
                            <option value="4" <?php echo $bedrooms == 4 ? 'selected' : ''; ?>>4+</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="property_type"><i class="fas fa-home"></i> Property Type</label>
                        <select id="property_type" name="property_type">
                            <option value="">Any Type</option>
                            <?php foreach ($property_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                    <?php echo $property_type === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="browse_properties.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Properties Section -->
        <div class="section">
            <div class="section-header">
                <h2>
                    <i class="fas fa-home"></i>
                    Available Properties
                </h2>
                <div class="results-summary">
                    <?php echo $total_properties; ?> properties found
                </div>
            </div>
            
            <?php if ($available_properties && mysqli_num_rows($available_properties) > 0): ?>
                <div class="property-grid">
                    <?php while ($property = mysqli_fetch_assoc($available_properties)): 
                        // Handle image path - FIXED
                        $image_path = '';
                        $image_alt = htmlspecialchars($property['title']);
                        
                        if (!empty($property['main_image'])) {
                            // Clean the image path by removing any leading slash
                            $image_url = ltrim($property['main_image'], '/');
                            $image_path = '../uploads/properties/' . $image_url;
                        }
                    ?>
                        <div class="property-card">
                            <div class="property-image">
                                <?php if (!empty($image_path)): ?>
                                    <img src="<?php echo $image_path; ?>"
                                         alt="<?php echo $image_alt; ?>">
                                <?php else: ?>
                                    <i class="fas fa-home" style="font-size:48px;"></i>
                                    <span>No Image Available</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="property-content">
                                <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                                
                                <div class="property-meta">
                                    <?php if (in_array('bedrooms', $properties_columns) && isset($property['bedrooms'])): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-bed"></i>
                                        <?php echo $property['bedrooms']; ?> Beds
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array('bathrooms', $properties_columns) && isset($property['bathrooms'])): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-bath"></i>
                                        <?php echo $property['bathrooms']; ?> Baths
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array('square_feet', $properties_columns) && isset($property['square_feet']) && $property['square_feet']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-ruler-combined"></i>
                                        <?php echo number_format($property['square_feet']); ?> sqft
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="property-address">
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['address']); ?></p>
                                </div>
                                
                                <?php if ($has_rent_amount && isset($property['rent_amount']) && $property['rent_amount'] > 0): ?>
                                    <div class="property-price">R<?php echo number_format($property['rent_amount']); ?>/month</div>
                                <?php endif; ?>
                                
                                <p><?php echo htmlspecialchars(substr($property['description'] ?? '', 0, 100)); ?>
                                   <?php echo strlen($property['description'] ?? '') > 100 ? '...' : ''; ?></p>
                                
                                <div class="property-footer">
                                    <div class="landlord-info">
                                        
                                        <span></span>
                                    </div>
                                    
                                    <div class="property-actions">
                                        <a href="property_details.php?id=<?php echo $property['id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($has_applications_table): ?>
                                            <?php if (in_array($property['id'], $applied_property_ids)): ?>
                                                <span class="btn btn-disabled">
                                                    <i class="fas fa-check-circle"></i> Applied
                                                </span>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                                    <button type="submit" name="apply_property" class="btn btn-success">
                                                        <i class="fas fa-paper-plane"></i> Apply
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <div class="page-item">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="page-item disabled">
                                <span class="page-link">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </span>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Calculate page range to show
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        // Show first page if not in range
                        if ($start_page > 1): ?>
                            <div class="page-item">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-link">1</a>
                            </div>
                            <?php if ($start_page > 2): ?>
                                <div class="page-item disabled">
                                    <span class="page-link">...</span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <div class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-link">
                                    <?php echo $i; ?>
                                </a>
                            </div>
                        <?php endfor; ?>

                        <?php 
                        // Show last page if not in range
                        if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <div class="page-item disabled">
                                    <span class="page-link">...</span>
                                </div>
                            <?php endif; ?>
                            <div class="page-item">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-link">
                                    <?php echo $total_pages; ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <div class="page-item">
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="page-item disabled">
                                <span class="page-link">
                                    Next <i class="fas fa-chevron-right"></i>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- No Results -->
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No Properties Found</h3>
                    <p>Try adjusting your search criteria or browse all available properties.</p>
                    <a href="browse_properties.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-home"></i> View All Properties
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Price validation
            const minPriceInput = document.getElementById('min_price');
            const maxPriceInput = document.getElementById('max_price');
            
            function validatePriceRange() {
                const minPrice = parseFloat(minPriceInput.value) || 0;
                const maxPrice = parseFloat(maxPriceInput.value) || 0;
                
                if (minPrice > 0 && maxPrice > 0 && minPrice > maxPrice) {
                    maxPriceInput.setCustomValidity('Maximum price must be greater than minimum price');
                } else {
                    maxPriceInput.setCustomValidity('');
                }
            }
            
            minPriceInput.addEventListener('input', validatePriceRange);
            maxPriceInput.addEventListener('input', validatePriceRange);
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>

    
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>