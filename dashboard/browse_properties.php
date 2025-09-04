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

// Check if tenant has completed their profile
$profile_complete = false;
$profile_check_query = "SELECT full_name, email, phone, address, city, province, postal_code, id_number, employment_status, monthly_income, emergency_contact_name, emergency_contact_phone FROM tenant_profiles WHERE tenant_id = $tenant_id";
$profile_result = mysqli_query($conn, $profile_check_query);

if ($profile_result && mysqli_num_rows($profile_result) > 0) {
    $profile_data = mysqli_fetch_assoc($profile_result);
    // Check if all required fields are filled
    $required_fields = ['full_name', 'email', 'phone', 'address', 'city', 'province', 'postal_code', 'id_number', 'employment_status', 'monthly_income', 'emergency_contact_name', 'emergency_contact_phone'];
    
    $profile_complete = true;
    foreach ($required_fields as $field) {
        if (empty($profile_data[$field])) {
            $profile_complete = false;
            break;
        }
    }
}
// Check tenant documents completeness
$documents_complete = false;
$uploaded_docs_count = 0;
$total_possible_docs = 7; // Total document types available

if ($profile_complete) {
    $docs_query = "SELECT COUNT(*) as doc_count FROM tenant_documents WHERE tenant_id = $tenant_id";
    $docs_result = mysqli_query($conn, $docs_query);
    
    if ($docs_result) {
        $docs_data = mysqli_fetch_assoc($docs_result);
        $uploaded_docs_count = $docs_data['doc_count'];
        // Consider documents complete if they have at least 3 key documents
        $documents_complete = $uploaded_docs_count >= 3;
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
$where_clauses = ["p.admin_approved = 1"];

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
    
    // Check if profile is complete before allowing application
    if (!$profile_complete) {
        $_SESSION['error_message'] = "Please complete your profile before applying for properties.";
        header("Location: browse_properties.php?" . http_build_query($_GET));
        exit();
    }
    
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #7c3aed;
            --primary-dark: #6d28d9;
            --secondary-color: #a855f7;
            --accent-color: #ec4899;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --gray-text: #64748b;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --sidebar-bg: #1e293b;
            --sidebar-active: #334155;
            --sidebar-text: #cbd5e1;
        }

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

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 250px;
            max-width: calc(100% - 250px);
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Filters */
        .filters-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-select,
        .filter-input {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Properties Grid */
        .properties-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .properties-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .properties-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-text);
        }

        .properties-count {
            color: var(--gray-text);
            font-size: 0.9rem;
        }

        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .property-card {
            border: 1px solid var(--border-color);
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
            overflow: hidden;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-content {
            padding: 1.25rem;
        }

        .property-card h3 {
            color: var(--dark-text);
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .property-meta {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: var(--gray-text);
            font-size: 0.85rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .property-description {
            color: var(--gray-text);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .property-address {
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--gray-text);
        }

        .property-price {
            color: var(--success-color);
            font-weight: bold;
            font-size: 1.25rem;
            margin: 0.75rem 0;
        }

        .property-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .property-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            justify-content: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #9b4af9 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }
        
        .btn-disabled {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            cursor: not-allowed;
            opacity: 0.9;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: rgba(124, 58, 237, 0.1);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-text);
            grid-column: 1 / -1;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
            opacity: 0.5;
        }

        .no-results h3 {
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .page-item {
            display: inline-block;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .page-link:hover {
            background-color: #f0f7ff;
            border-color: var(--primary-color);
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-item.disabled .page-link {
            color: #aaa;
            pointer-events: none;
        }

        @media (max-width: 1024px) {
            .property-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar .logo span,
            .sidebar .nav-menu a span,
            .sidebar .profile-info {
                display: none;
            }
            
            .sidebar .logo {
                justify-content: center;
                padding: 1rem;
            }
            
            .sidebar .nav-menu a {
                justify-content: center;
            }
            
            .main-content {
                margin-left: 70px;
                max-width: calc(100% - 70px);
                padding: 1rem;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .property-grid {
                grid-template-columns: 1fr;
            }
            
            .properties-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
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
            <li><a href="tenant_profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="browse_properties.php" class="active"><i class="fas fa-search"></i> Browse Properties</a></li>
            <li><a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a></li>
            <li><a href="my_lease.php"><i class="fas fa-file-contract"></i> My Lease</a></li>
            <li><a href="maintenance_requests.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="payment_history.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-building"></i>
                Browse Properties
            </h1>
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
                        <label class="filter-label"><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Location, keywords..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label"><span>R</span> Min Price</label>
                        <input type="number" name="min_price" class="filter-input" placeholder="Min (R)" 
                               value="<?php echo $min_price > 0 ? $min_price : ''; ?>" min="0" step="50">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label"><span>R</span> Max Price</label>
                        <input type="number" name="max_price" class="filter-input" placeholder="Max (R)" 
                               value="<?php echo $max_price > 0 ? $max_price : ''; ?>" min="0" step="50">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-bed"></i> Bedrooms</label>
                        <select name="bedrooms" class="filter-select">
                            <option value="">Any</option>
                            <option value="1" <?php echo $bedrooms == 1 ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo $bedrooms == 2 ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo $bedrooms == 3 ? 'selected' : ''; ?>>3</option>
                            <option value="4" <?php echo $bedrooms == 4 ? 'selected' : ''; ?>>4+</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-home"></i> Property Type</label>
                        <select name="property_type" class="filter-select">
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
        <div class="properties-container">
            <div class="properties-header">
                <div class="properties-title">
                    <i class="fas fa-home"></i>
                    Available Properties
                </div>
                <div class="properties-count"><?php echo $total_properties; ?> properties found</div>
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
                                
                                <p class="property-description"><?php echo htmlspecialchars(substr($property['description'] ?? '', 0, 100)); ?>
                                   <?php echo strlen($property['description'] ?? '') > 100 ? '...' : ''; ?></p>
                                
                                <div class="property-footer">
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
<?php if ($profile_complete): ?>
    <form method="POST" style="display: inline;" id="applyForm_<?php echo $property['id']; ?>">
        <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
        <button type="button" class="btn btn-success" onclick="confirmApplication(<?php echo $property['id']; ?>, '<?php echo htmlspecialchars(addslashes($property['title'])); ?>')">
            <i class="fas fa-paper-plane"></i> Apply
        </button>
    </form>
<?php else: ?>
    <button type="button" class="btn btn-success" onclick="showProfileIncompleteAlert()">
        <i class="fas fa-paper-plane"></i> Apply
    </button>
<?php endif; ?>
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
            const minPriceInput = document.querySelector('input[name="min_price"]');
            const maxPriceInput = document.querySelector('input[name="max_price"]');
            
            function validatePriceRange() {
                const minPrice = parseFloat(minPriceInput.value) || 0;
                const maxPrice = parseFloat(maxPriceInput.value) || 0;
                
                if (minPrice > 0 && maxPrice > 0 && minPrice > maxPrice) {
                    maxPriceInput.setCustomValidity('Maximum price must be greater than minimum price');
                } else {
                    maxPriceInput.setCustomValidity('');
                }
            }
            
            if (minPriceInput && maxPriceInput) {
                minPriceInput.addEventListener('input', validatePriceRange);
                maxPriceInput.addEventListener('input', validatePriceRange);
            }
            
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
<script>
function showProfileIncompleteAlert() {
    Swal.fire({
        title: 'Profile Incomplete',
        html: 'You need to complete your tenant profile before applying for properties.<br><br>A complete profile increases your chances of approval!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-user-edit"></i> Complete Profile',
        cancelButtonText: 'Later'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'tenant_profile.php';
        }
    });
}

function confirmApplication(propertyId, propertyTitle) {
    Swal.fire({
        title: 'Confirm Application',
        html: `Are you sure you want to apply for:<br><br><strong>${propertyTitle}</strong><br><br>This will submit your rental application to the property owner.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Yes, Apply',
        cancelButtonText: 'Cancel',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Submitting Application',
                text: 'Please wait...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Create and submit a hidden form with the apply_property field
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            // Add property_id field
            const propertyIdInput = document.createElement('input');
            propertyIdInput.type = 'hidden';
            propertyIdInput.name = 'property_id';
            propertyIdInput.value = propertyId;
            form.appendChild(propertyIdInput);
            
            // Add apply_property field (this is what the PHP looks for)
            const applyInput = document.createElement('input');
            applyInput.type = 'hidden';
            applyInput.name = 'apply_property';
            applyInput.value = '1';
            form.appendChild(applyInput);
            
            // Add to page and submit
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>