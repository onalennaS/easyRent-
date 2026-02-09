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
    
    // Fetch properties based on user role
    if ($userRole === 'landlord') {
        // Filter parameters
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $approval_filter = $_GET['approval'] ?? 'all';
        $availability_filter = $_GET['availability'] ?? 'all';
        $property_type_filter = $_GET['type'] ?? 'all';
        $bedrooms_filter = isset($_GET['bedrooms']) && $_GET['bedrooms'] !== '' ? (int)$_GET['bedrooms'] : 0;
        $min_rent = isset($_GET['min_rent']) && $_GET['min_rent'] !== '' ? (float)$_GET['min_rent'] : 0;
        $max_rent = isset($_GET['max_rent']) && $_GET['max_rent'] !== '' ? (float)$_GET['max_rent'] : 0;

        // Build base query with subqueries
        $baseSql = "SELECT p.*, 
                (SELECT COUNT(*) FROM rental_applications ra WHERE ra.property_id = p.id) as application_count,
                (SELECT COUNT(*) FROM leases l WHERE l.property_id = p.id AND l.status = 'active') as active_lease,
                (SELECT image_url FROM property_images pi WHERE pi.property_id = p.id ORDER BY pi.id LIMIT 1) as primary_image,
                CASE 
                    WHEN p.admin_approved = 1 THEN 'Approved'
                    ELSE 'Awaiting Approval'
                END as approval_status
                FROM properties p 
                WHERE p.landlord_id = ?";
        
        $params = [$userId];
        $types = 'i';
        
        // Add filter conditions
        if (!empty($search)) {
            $baseSql .= " AND (p.title LIKE ? OR p.address LIKE ? OR p.description LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
            $types .= 'sss';
        }
        if ($approval_filter === 'approved') {
            $baseSql .= " AND p.admin_approved = 1";
        } elseif ($approval_filter === 'pending') {
            $baseSql .= " AND (p.admin_approved = 0 OR p.admin_approved IS NULL)";
        }
        if ($property_type_filter !== 'all' && !empty($property_type_filter)) {
            $baseSql .= " AND p.property_type = ?";
            $params[] = $property_type_filter;
            $types .= 's';
        }
        if ($bedrooms_filter > 0) {
            $baseSql .= " AND p.bedrooms >= ?";
            $params[] = $bedrooms_filter;
            $types .= 'i';
        }
        if ($min_rent > 0) {
            $baseSql .= " AND p.rent_amount >= ?";
            $params[] = $min_rent;
            $types .= 'd';
        }
        if ($max_rent > 0) {
            $baseSql .= " AND p.rent_amount <= ?";
            $params[] = $max_rent;
            $types .= 'd';
        }
        
        // Availability filter requires subquery - wrap in derived table
        if ($availability_filter === 'available') {
            $baseSql .= " AND (SELECT COUNT(*) FROM leases l WHERE l.property_id = p.id AND l.status = 'active') = 0";
        } elseif ($availability_filter === 'occupied') {
            $baseSql .= " AND (SELECT COUNT(*) FROM leases l WHERE l.property_id = p.id AND l.status = 'active') > 0";
        }
        
        $stmt = $conn->prepare($baseSql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $properties = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get property types for filter dropdown (landlord's properties only)
        $typesSql = "SELECT DISTINCT property_type FROM properties WHERE landlord_id = ? AND property_type != '' AND property_type IS NOT NULL ORDER BY property_type";
        $typesStmt = $conn->prepare($typesSql);
        $typesStmt->bind_param("i", $userId);
        $typesStmt->execute();
        $typesResult = $typesStmt->get_result();
        $property_types = [];
        while ($row = $typesResult->fetch_assoc()) {
            $property_types[] = $row['property_type'];
        }
        if (empty($property_types)) {
            $property_types = ['apartment', 'house', 'condo', 'townhouse', 'studio', 'duplex', 'villa', 'other'];
        }
            
        // Calculate stats
        $totalProperties = count($properties);
        $occupiedProperties = 0;
        $totalApplications = 0;
        $monthlyRevenue = 0;
        $approvedProperties = 0;
        $pendingProperties = 0;
        
        foreach ($properties as $property) {
            if ($property['active_lease']) {
                $occupiedProperties++;
                $monthlyRevenue += $property['rent_amount'];
            }
            $totalApplications += $property['application_count'];
            
            // Count approved vs pending properties
            if ($property['admin_approved'] == 1) {
                $approvedProperties++;
            } else {
                $pendingProperties++;
            }
        }
    } else {
        // Fetch applications for the tenant
        $sql = "SELECT ra.*, p.title, p.address, p.rent_amount, p.bedrooms, p.bathrooms, ra.status as application_status
                FROM rental_applications ra
                JOIN properties p ON ra.property_id = p.id
                WHERE ra.tenant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $applications = $result->fetch_all(MYSQLI_ASSOC);
        
        // Calculate stats
        $totalApplications = count($applications);
        $pendingApplications = 0;
        $approvedApplications = 0;
        
        foreach ($applications as $app) {
            if ($app['application_status'] === 'pending') $pendingApplications++;
            if ($app['application_status'] === 'approved') $approvedApplications++;
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
    <title>My Properties - EasyRent</title>
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
}

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
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
    border-bottom: 1px solid var(--border);
}

.landlord-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.landlord-info .avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.mobile-menu-btn {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #64748b;
    cursor: pointer;
}

/* Stats Grid – Colorful & Unique Style */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--accent-gradient);
    border-radius: 20px;
    padding: 1.75rem;
    box-shadow: 0 10px 30px var(--shadow-color);
    border: none;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    transition: all 0.6s ease;
}

.stat-card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 150px;
    height: 150px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 50%;
    transition: all 0.6s ease;
}

.stat-card:hover {
    transform: translateY(-10px) scale(1.03);
    box-shadow: 0 20px 40px var(--shadow-color);
}

.stat-card:hover::before {
    transform: scale(1.3) rotate(45deg);
    top: -60%;
    right: -30%;
}

.stat-card:hover::after {
    transform: scale(1.4) rotate(-45deg);
}

.stat-card.properties { 
    --accent-gradient: linear-gradient(135deg, #8b7bce 0%, #6b5bb0 100%);
    --shadow-color: rgba(139, 123, 206, 0.4);
    --icon-bg: rgba(255, 255, 255, 0.2);
}

.stat-card.income { 
    --accent-gradient: linear-gradient(135deg, #f4a79d 0%, #e8907f 100%);
    --shadow-color: rgba(244, 167, 157, 0.4);
    --icon-bg: rgba(255, 255, 255, 0.2);
}

.stat-card.applications { 
    --accent-gradient: linear-gradient(135deg, #7ec8c3 0%, #5fb3ad 100%);
    --shadow-color: rgba(126, 200, 195, 0.4);
    --icon-bg: rgba(255, 255, 255, 0.2);
}

.stat-card.occupied { 
    --accent-gradient: linear-gradient(135deg, #6bcf9d 0%, #4fb883 100%);
    --shadow-color: rgba(107, 207, 157, 0.4);
    --icon-bg: rgba(255, 255, 255, 0.2);
}

.stat-card.approved { 
    --accent-gradient: linear-gradient(135deg, #b69ce8 0%, #9d7fd6 100%);
    --shadow-color: rgba(182, 156, 232, 0.4);
    --icon-bg: rgba(255, 255, 255, 0.2);
}

.stat-card.pending { 
    --accent-gradient: linear-gradient(135deg, #f5b5a8 0%, #e89b8a 100%);
    --shadow-color: rgba(245, 181, 168, 0.4);
    --icon-bg: rgba(255, 255, 255, 0.2);
}

/* Filters */
.filters-container {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.filter-select,
.filter-input {
    padding: 0.6rem 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 0;
    position: relative;
    z-index: 1;
}

.stat-icon {
    width: 50px;
    height: 50px;
    min-width: 50px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: white;
    background: var(--icon-bg);
    backdrop-filter: blur(10px);
    flex-shrink: 0;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.1) rotate(5deg);
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 0.3rem;
    line-height: 1.2;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.stat-label {
    color: rgba(255, 255, 255, 0.95);
    font-weight: 600;
    font-size: 0.85rem;
    line-height: 1.3;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Card Title */
.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Properties Table */
.properties-table-container {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.table-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
}

.properties-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.properties-table th {
    background-color: #f8fafc;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.properties-table td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.properties-table tbody tr {
    transition: background-color 0.2s ease;
}

.properties-table tbody tr:hover {
    background-color: #f8fafc;
}

.property-image {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}

.image-placeholder {
    width: 80px;
    height: 60px;
    background: #f1f5f9;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge.approved {
    background-color: #dcfce7;
    color: #166534;
}

.status-badge.pending {
    background-color: #fef3c7;
    color: #92400e;
}

.status-badge.available {
    background-color: #dbeafe;
    color: #1e40af;
}

.status-badge.occupied {
    background-color: #fee2e2;
    color: #991b1b;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #f1f5f9;
    color: #475569;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #64748b;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #cbd5e1;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: #475569;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .main-content {
        padding: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

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

    .stats-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    
    .properties-table {
        display: block;
        overflow-x: auto;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}

@media (max-width: 640px) {
    .stat-card {
        padding: 1.25rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .stat-label {
        font-size: 0.75rem;
    }
    
    .stat-icon {
        width: 42px;
        height: 42px;
        font-size: 1.1rem;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>Easy Rent</h2>
            <p>Landlord Portal</p>
        </div>
        <ul>
            <li><a href="../index.php" class="home-button"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="profile_landlord.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="landlord_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="my_properties.php" class="active"><i class="fas fa-building"></i> My Properties</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="add_property.php"><i class="fas fa-plus-circle"></i> Add Property</a></li>
            <li><a href="maintenance.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="tenants.php"><i class="fas fa-users"></i> Tenants</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-building"></i>
                My Properties
            </h1>
            <div class="landlord-info">
                <span>Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Landlord'); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?></div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card properties">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $totalProperties; ?></div>
                        <div class="stat-label">Total Properties</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card income">
                <div class="stat-header">
                    <div>
                        <div class="stat-value">R<?php echo number_format($monthlyRevenue); ?></div>
                        <div class="stat-label">Monthly Revenue</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card applications">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $totalApplications; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card occupied">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $occupiedProperties; ?></div>
                        <div class="stat-label">Occupied Properties</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-key"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card approved">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $approvedProperties; ?></div>
                        <div class="stat-label">Approved Properties</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $pendingProperties; ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Property Listings -->
        <?php if ($userRole === 'landlord'): ?>
        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" action="my_properties.php" id="filter-form">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Title, address..." 
                               value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Admin Status</label>
                        <select name="approval" class="filter-select">
                            <option value="all" <?php echo ($approval_filter ?? 'all') === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="approved" <?php echo ($approval_filter ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="pending" <?php echo ($approval_filter ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Availability</label>
                        <select name="availability" class="filter-select">
                            <option value="all" <?php echo ($availability_filter ?? 'all') === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="available" <?php echo ($availability_filter ?? '') === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="occupied" <?php echo ($availability_filter ?? '') === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Property Type</label>
                        <select name="type" class="filter-select">
                            <option value="all" <?php echo ($property_type_filter ?? 'all') === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach (($property_types ?? []) as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                    <?php echo ($property_type_filter ?? '') === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-bed"></i> Min Bedrooms</label>
                        <select name="bedrooms" class="filter-select">
                            <option value="">Any</option>
                            <option value="1" <?php echo ($bedrooms_filter ?? 0) == 1 ? 'selected' : ''; ?>>1+</option>
                            <option value="2" <?php echo ($bedrooms_filter ?? 0) == 2 ? 'selected' : ''; ?>>2+</option>
                            <option value="3" <?php echo ($bedrooms_filter ?? 0) == 3 ? 'selected' : ''; ?>>3+</option>
                            <option value="4" <?php echo ($bedrooms_filter ?? 0) == 4 ? 'selected' : ''; ?>>4+</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Min Rent (R)</label>
                        <input type="number" name="min_rent" class="filter-input" placeholder="Min" 
                               value="<?php echo ($min_rent ?? 0) > 0 ? (int)$min_rent : ''; ?>" min="0" step="100">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Max Rent (R)</label>
                        <input type="number" name="max_rent" class="filter-input" placeholder="Max" 
                               value="<?php echo ($max_rent ?? 0) > 0 ? (int)$max_rent : ''; ?>" min="0" step="100">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="my_properties.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="properties-table-container">
            <div class="table-header">
                <h2 class="table-title">Your Property Listings</h2>
                <?php if ($userRole === 'landlord' && isset($properties)): ?>
                <span style="color: #64748b; font-size: 0.9rem;"><?php echo count($properties); ?> properties</span>
                <?php endif; ?>
            </div>
            
            <?php if(!empty($properties)): ?>
                <table class="properties-table">
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Address</th>
                            <th>Rent</th>
                            <th>Bed/Bath</th>
                            <th>Availability</th>
                            <th>Applications</th>
                            <th>Admin Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($properties as $property): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <?php if(!empty($property['primary_image'])): ?>
                                            <img src="../uploads/properties/<?php echo htmlspecialchars($property['primary_image']); ?>" 
                                                 class="property-image" 
                                                 alt="<?php echo htmlspecialchars($property['title']); ?>"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="image-placeholder" style="display: none;">
                                                <i class="fas fa-home"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <i class="fas fa-home"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($property['title']); ?></div>
                                            <div style="font-size: 0.875rem; color: #64748b;"><?php echo ucfirst(htmlspecialchars($property['property_type'])); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($property['address']); ?></td>
                                <td style="font-weight: 600; color: #059669;">R<?php echo number_format($property['rent_amount']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 1rem;">
                                        <div>
                                            <div style="font-weight: 600;"><?php echo $property['bedrooms']; ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;">Bedrooms</div>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo $property['bathrooms']; ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;">Bathrooms</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($property['active_lease']): ?>
                                        <span class="status-badge occupied">Occupied</span>
                                    <?php else: ?>
                                        <span class="status-badge available">Available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="text-align: center;">
                                        <div style="font-weight: 600;"><?php echo $property['application_count']; ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b;">Applications</div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($property['admin_approved'] == 1): ?>
                                        <span class="status-badge approved">
                                            <i class="fas fa-check-circle" style="margin-right: 0.25rem;"></i>
                                            Approved
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge pending">
                                            <i class="fas fa-clock" style="margin-right: 0.25rem;"></i>
                                            Awaiting Approval
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_property.php?id=<?php echo $property['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_property.php?id=<?php echo $property['id']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="applications.php?property_id=<?php echo $property['id']; ?>" class="btn btn-success">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-building"></i>
                    <h3>No Properties Listed</h3>
                    <p>You haven't listed any properties yet</p>
                    <a href="add_property.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i>
                        Add Your First Property
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const sidebar = document.querySelector('.sidebar');
        
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 900 && 
                    sidebar.classList.contains('active') && 
                    !sidebar.contains(e.target) && 
                    !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }

        // Logout confirmation
        function confirmLogout(event) {
            event.preventDefault();
            
            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out of your account",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, logout!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../auth/logout.php';
                }
            });
        }
    </script>
</body>
</html>