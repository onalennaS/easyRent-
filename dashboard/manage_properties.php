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

$success_message = '';
$error_message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $property_id = (int)($_POST['property_id'] ?? 0);
    
    switch ($action) {
        case 'approve':
            $update_query = "UPDATE properties SET admin_approved = 1, updated_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "i", $property_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Property approved successfully!";
            } else {
                $error_message = "Error approving property.";
            }
            mysqli_stmt_close($stmt);
            break;
            
        case 'disapprove':
            $update_query = "UPDATE properties SET admin_approved = 0, updated_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "i", $property_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Property disapproved successfully!";
            } else {
                $error_message = "Error disapproving property.";
            }
            mysqli_stmt_close($stmt);
            break;
            
        case 'toggle_availability':
            $update_query = "UPDATE properties SET is_available = NOT is_available, updated_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "i", $property_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Property availability updated successfully!";
            } else {
                $error_message = "Error updating property availability.";
            }
            mysqli_stmt_close($stmt);
            break;
            
        case 'toggle_featured':
            $update_query = "UPDATE properties SET is_featured = NOT is_featured, updated_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "i", $property_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Property featured status updated successfully!";
            } else {
                $error_message = "Error updating featured status.";
            }
            mysqli_stmt_close($stmt);
            break;
            
        case 'delete':
            $delete_query = "DELETE FROM properties WHERE id = ?";
            $stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($stmt, "i", $property_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Property deleted successfully!";
            } else {
                $error_message = "Error deleting property.";
            }
            mysqli_stmt_close($stmt);
            break;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$approval_filter = $_GET['approval'] ?? 'all';
$property_type_filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Build query
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "p.is_available = ?";
    $params[] = ($status_filter === 'available') ? 1 : 0;
    $types .= 'i';
}

if ($approval_filter !== 'all') {
    $where_conditions[] = "p.admin_approved = ?";
    $params[] = ($approval_filter === 'approved') ? 1 : 0;
    $types .= 'i';
}

if ($property_type_filter !== 'all') {
    $where_conditions[] = "p.property_type = ?";
    $params[] = $property_type_filter;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.address LIKE ? OR p.city LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Validate sort column
$allowed_sorts = ['created_at', 'title', 'rent_amount', 'city', 'property_type', 'view_count'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'created_at';
}

$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Get properties with landlord information
$query = "
    SELECT p.*, 
           CONCAT(u.first_name, ' ', u.last_name) as landlord_name,
           u.email as landlord_email,
           u.phone as landlord_phone,
           (SELECT image_url FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as main_image
    FROM properties p
    LEFT JOIN users u ON p.landlord_id = u.id
    $where_clause
    ORDER BY p.$sort $order
";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$properties = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_properties,
        SUM(CASE WHEN admin_approved = 1 THEN 1 ELSE 0 END) as approved_properties,
        SUM(CASE WHEN admin_approved = 0 THEN 1 ELSE 0 END) as pending_properties,
        SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_properties,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured_properties,
        AVG(rent_amount) as avg_rent
    FROM properties
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Properties - Easy Rent Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .search-bar {
            display: flex;
            gap: 0.75rem;
        }

        .search-bar input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            min-width: 300px;
        }

        .search-bar button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0 1.5rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-bar button:hover {
            background: var(--primary-dark);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.primary { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
        .stat-icon.success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .stat-icon.warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark-text);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--gray-text);
            font-size: 0.9rem;
        }

        /* Filters */
        .filters-container {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
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
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
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

        /* Properties Table */
        .properties-container {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .properties-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .table-container {
            overflow-x: auto;
        }

        .properties-table {
            width: 100%;
            border-collapse: collapse;
        }

        .properties-table th,
        .properties-table td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .properties-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .properties-table th a {
            color: #374151;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .properties-table th a:hover {
            color: var(--primary-color);
        }

        .properties-table tbody tr:hover {
            background: #f8fafc;
        }

        /* Property Card (for mobile) */
        .property-card {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .property-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .property-title {
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 0.25rem;
        }

        .property-location {
            color: var(--gray-text);
            font-size: 0.9rem;
        }

        .property-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .property-detail {
            font-size: 0.9rem;
        }

        .property-detail strong {
            color: var(--dark-text);
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.available {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.unavailable {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.featured {
            background: #f3e8ff;
            color: #7c2d12;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

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
            gap: 0.25rem;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #9b4af9 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-secondary {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
            color: #475569;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .properties-table {
                display: none;
            }
            
            .property-card {
                display: block;
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
            
            .sidebar .user-profile {
                justify-content: center;
                padding: 1rem;
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
            
            .search-bar {
                width: 100%;
            }
            
            .search-bar input {
                min-width: 0;
                flex: 1;
            }
            
            .filters-container {
                padding: 1.5rem;
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
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-text);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: var(--primary-color);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }

        /* Modal styles for confirmation */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-text);
        }

        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-text);
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        .toggle-sidebar {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .toggle-sidebar {
                display: flex;
            }
        }

        .property-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .image-placeholder {
            width: 80px;
            height: 60px;
            background: #f8fafc;
            border: 1px dashed #e5e7eb;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
        }
    </style>
    
</head>
<body>
<!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>Easy Rent</h2>
            <p>Admin Panel</p>
        </div>
        <ul>
            
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_properties.php"><i class="fas fa-building"></i> Properties</a></li>
            <li><a href="manage_landlords.php"><i class="fas fa-users"></i> Landlords</a></li>
            <li><a href="manage_tenants.php"><i class="fas fa-user-friends"></i> Tenants</a></li>
            <li><a href="maintenance_requests.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="financial_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="system_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>


    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-building"></i>
                Manage Properties
            </h1>
            <div class="search-bar">
                <input type="text" placeholder="Search properties...">
                <button>Search</button>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_properties']); ?></div>
                <div class="stat-label">Total Properties</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['approved_properties']); ?></div>
                <div class="stat-label">Approved Properties</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['pending_properties']); ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['featured_properties']); ?></div>
                <div class="stat-label">Featured Properties</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" action="manage_properties.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="unavailable" <?php echo $status_filter === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Approval</label>
                        <select name="approval" class="filter-select">
                            <option value="all" <?php echo $approval_filter === 'all' ? 'selected' : ''; ?>>All Approvals</option>
                            <option value="approved" <?php echo $approval_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="pending" <?php echo $approval_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Property Type</label>
                        <select name="type" class="filter-select">
                            <option value="all" <?php echo $property_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="apartment" <?php echo $property_type_filter === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                            <option value="house" <?php echo $property_type_filter === 'house' ? 'selected' : ''; ?>>House</option>
                            <option value="condo" <?php echo $property_type_filter === 'condo' ? 'selected' : ''; ?>>Condo</option>
                            <option value="townhouse" <?php echo $property_type_filter === 'townhouse' ? 'selected' : ''; ?>>Townhouse</option>
                            <option value="studio" <?php echo $property_type_filter === 'studio' ? 'selected' : ''; ?>>Studio</option>
                            <option value="duplex" <?php echo $property_type_filter === 'duplex' ? 'selected' : ''; ?>>Duplex</option>
                            <option value="villa" <?php echo $property_type_filter === 'villa' ? 'selected' : ''; ?>>Villa</option>
                            <option value="other" <?php echo $property_type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search properties..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <select name="sort" class="filter-select">
                            <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                            <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                            <option value="rent_amount" <?php echo $sort === 'rent_amount' ? 'selected' : ''; ?>>Rent Amount</option>
                            <option value="city" <?php echo $sort === 'city' ? 'selected' : ''; ?>>City</option>
                            <option value="property_type" <?php echo $sort === 'property_type' ? 'selected' : ''; ?>>Property Type</option>
                            <option value="view_count" <?php echo $sort === 'view_count' ? 'selected' : ''; ?>>View Count</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Order</label>
                        <select name="order" class="filter-select">
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Apply Filters
                    </button>
                    <a href="manage_properties.php" class="btn btn-secondary">
                        <i class="fas fa-sync"></i>
                        Reset Filters
                    </a>
                </div>
            </form>
        </div>

<!-- Properties Table -->
        <div class="properties-container">
            <div class="properties-header">
                <div class="properties-title">Property Listings</div>
                <div class="properties-count"><?php echo count($properties); ?> properties found</div>
            </div>
            
            <?php if (count($properties) > 0): ?>
                <!-- Desktop Table -->
                <div class="table-container">
                    <table class="properties-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th><a href="?sort=title&order=<?php echo $sort === 'title' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Title</a></th>
                                <th>Landlord</th>
                                <th>Location</th>
                                <th><a href="?sort=property_type&order=<?php echo $sort === 'property_type' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Type</a></th>
                                <th><a href="?sort=rent_amount&order=<?php echo $sort === 'rent_amount' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>">Rent</a></th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($properties as $property): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($property['main_image'])): ?>
                                            <img src="../uploads/properties/<?php echo htmlspecialchars($property['main_image']); ?>" 
                                                 class="property-image" 
                                                 alt="<?php echo htmlspecialchars($property['title']); ?>">
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <i class="fas fa-home"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="property-title"><?php echo htmlspecialchars($property['title']); ?></div>
                                        <div class="property-location">
                                            <?php echo htmlspecialchars($property['city'] . ', ' . $property['state']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($property['landlord_name']); ?></div>
                                        <div class="property-location"><?php echo htmlspecialchars($property['landlord_email']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($property['address']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($property['property_type'])); ?></td>
                                    <td>R<?php echo number_format($property['rent_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($property['is_available']): ?>
                                            <span class="status-badge available">Available</span>
                                        <?php else: ?>
                                            <span class="status-badge unavailable">Unavailable</span>
                                        <?php endif; ?>
                                        <?php if ($property['is_featured']): ?>
                                            <span class="status-badge featured">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($property['admin_approved']): ?>
                                            <span class="status-badge approved">Approved</span>
                                        <?php else: ?>
                                            <span class="status-badge pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- Approve/Disapprove -->
                                            <?php if (!$property['admin_approved']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                                    <input type="hidden" name="action" value="disapprove">
                                                    <button type="submit" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-ban"></i> Disapprove
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Toggle Availability -->
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_availability">
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    <i class="fas fa-sync"></i> 
                                                    <?php echo $property['is_available'] ? 'Make Unavailable' : 'Make Available'; ?>
                                                </button>
                                            </form>
                                            
                                            <!-- Toggle Featured -->
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_featured">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-star"></i> 
                                                    <?php echo $property['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                                                </button>
                                            </form>
                                            
                                            <!-- Delete -->
                                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete();">
                                                <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                            <a href="property_images.php?id=<?php echo $property['id']; ?>" 
                                               class="btn btn-secondary btn-sm"
                                               target="_blank">
                                                <i class="fas fa-images"></i> View Images
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile Cards -->
                <?php foreach ($properties as $property): ?>
                    <div class="property-card">
                        <div class="property-card-header">
                            <div>
                                <div class="property-title"><?php echo htmlspecialchars($property['title']); ?></div>
                                <div class="property-location">
                                    <?php echo htmlspecialchars($property['city'] . ', ' . $property['state']); ?>
                                </div>
                            </div>
                            <div>
                                <span class="status-badge <?php echo $property['admin_approved'] ? 'approved' : 'pending'; ?>">
                                    <?php echo $property['admin_approved'] ? 'Approved' : 'Pending'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="property-details">
                            <div class="property-detail">
                                <strong>Type:</strong> <?php echo ucfirst($property['property_type']); ?>
                            </div>
                            <div class="property-detail">
                                <strong>Rent:</strong> $<?php echo number_format($property['rent_amount'], 2); ?>
                            </div>
                            <div class="property-detail">
                                <strong>Landlord:</strong> <?php echo htmlspecialchars($property['landlord_name']); ?>
                            </div>
                            <div class="property-detail">
                                <strong>Status:</strong>
                                <span class="status-badge <?php echo $property['is_available'] ? 'available' : 'unavailable'; ?>">
                                    <?php echo $property['is_available'] ? 'Available' : 'Unavailable'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <?php if (!$property['admin_approved']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                    <input type="hidden" name="action" value="disapprove">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="fas fa-ban"></i> Disapprove
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                <input type="hidden" name="action" value="toggle_availability">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-sync"></i> 
                                    <?php echo $property['is_available'] ? 'Unavailable' : 'Available'; ?>
                                </button>
                            </form>
                            
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                <input type="hidden" name="action" value="toggle_featured">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-star"></i> 
                                    <?php echo $property['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                                </button>
                            </form>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete();">
                                <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-building"></i>
                    <h3>No properties found</h3>
                    <p>Try adjusting your filters or search criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Script -->
    <script>
        function confirmDelete() {
            return confirm("Are you sure you want to delete this property? This action cannot be undone.");
        }
        
        // Toggle sidebar on mobile
        document.querySelector('.toggle-sidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
        });
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>