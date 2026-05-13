<?php
// tenant_dashboard.php - Tenant Dashboard Page

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

// Get tenant name from session or database
$tenant_name = '';
if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
    $tenant_name = $_SESSION['user_name'];
} elseif (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    $tenant_name = $_SESSION['username'];
} elseif (isset($_SESSION['name']) && !empty($_SESSION['name'])) {
    $tenant_name = $_SESSION['name'];
} else {
    // Fetch name from database if not in session
    $user_query = "SELECT name, username, email FROM users WHERE id = ? AND user_type = 'tenant' LIMIT 1";
    $stmt = mysqli_prepare($conn, $user_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $tenant_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $tenant_name = $row['name'] ?: $row['username'] ?: $row['email'];
            $_SESSION['user_name'] = $tenant_name;
        }
        mysqli_stmt_close($stmt);
    }
    
    if (empty($tenant_name)) {
        $tenant_name = 'Tenant';
    }
}

// Check what columns exist in properties table
$check_properties_query = "SHOW COLUMNS FROM properties";
$properties_columns_result = mysqli_query($conn, $check_properties_query);
$properties_columns = [];
if ($properties_columns_result) {
    while ($column = mysqli_fetch_assoc($properties_columns_result)) {
        $properties_columns[] = $column['Field'];
    }
}

// Check what columns exist in users table
$check_users_query = "SHOW COLUMNS FROM users";
$users_columns_result = mysqli_query($conn, $check_users_query);
$users_columns = [];
if ($users_columns_result) {
    while ($column = mysqli_fetch_assoc($users_columns_result)) {
        $users_columns[] = $column['Field'];
    }
}

$has_status = in_array('status', $properties_columns);
$has_rent_amount = in_array('rent_amount', $properties_columns);

// Build COALESCE for user name based on available columns
$name_fields = [];
if (in_array('full_name', $users_columns)) $name_fields[] = 'u.full_name';
if (in_array('name', $users_columns)) $name_fields[] = 'u.name';
if (in_array('first_name', $users_columns)) $name_fields[] = 'u.first_name';
if (in_array('username', $users_columns)) $name_fields[] = 'u.username';
if (in_array('email', $users_columns)) $name_fields[] = 'u.email';

$name_coalesce = !empty($name_fields) ? 
    "COALESCE(" . implode(', ', $name_fields) . ", 'Unknown')" : 
    "'Unknown'";

// Check if applications table exists
$check_applications_table = "SHOW TABLES LIKE 'rental_applications'";
$applications_table_result = mysqli_query($conn, $check_applications_table);
$has_applications_table = mysqli_num_rows($applications_table_result) > 0;

// Check if maintenance_requests table exists
$check_maintenance_table = "SHOW TABLES LIKE 'maintenance_requests'";
$maintenance_table_result = mysqli_query($conn, $check_maintenance_table);
$has_maintenance_table = mysqli_num_rows($maintenance_table_result) > 0;

// Tenant statistics
$tenant_stats_query = "";
if ($has_applications_table) {
    $tenant_stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM rental_applications WHERE tenant_id = $tenant_id) as total_applications,
            (SELECT COUNT(*) FROM rental_applications WHERE tenant_id = $tenant_id AND status = 'pending') as pending_applications,
            (SELECT COUNT(*) FROM rental_applications WHERE tenant_id = $tenant_id AND status = 'approved') as approved_applications,
            " . ($has_maintenance_table ? "(SELECT COUNT(*) FROM maintenance_requests WHERE tenant_id = $tenant_id AND status = 'open')" : "0") . " as open_maintenance
    ";
} else {
    $tenant_stats_query = "
        SELECT 
            0 as total_applications,
            0 as pending_applications,
            0 as approved_applications,
            " . ($has_maintenance_table ? "(SELECT COUNT(*) FROM maintenance_requests WHERE tenant_id = $tenant_id AND status = 'open')" : "0") . " as open_maintenance
    ";
}

$tenant_stats_result = mysqli_query($conn, $tenant_stats_query);
if (!$tenant_stats_result) {
    $tenant_stats = [
        'total_applications' => 0,
        'pending_applications' => 0,
        'approved_applications' => 0,
        'open_maintenance' => 0
    ];
} else {
    $tenant_stats = mysqli_fetch_assoc($tenant_stats_result);
}

// Available properties query
$available_properties_query = "";
if ($has_status) {
    $available_properties_query = "
        SELECT p.*, 
               $name_coalesce as landlord_name
        FROM properties p 
        JOIN users u ON p.landlord_id = u.id 
        WHERE p.status = 'approved' 
        ORDER BY p.created_at DESC 
        LIMIT 6
    ";
} else {
    $available_properties_query = "
        SELECT p.*, 
               $name_coalesce as landlord_name
        FROM properties p 
        JOIN users u ON p.landlord_id = u.id 
        ORDER BY p.created_at DESC 
        LIMIT 6
    ";
}

$available_properties = mysqli_query($conn, $available_properties_query);
if (!$available_properties) {
    error_log("Properties query failed: " . mysqli_error($conn));
    $available_properties = null;
}

// Recent applications
$recent_applications = null;
if ($has_applications_table) {
    $recent_applications_query = "
        SELECT ra.*, p.title, p.address, 
               $name_coalesce as landlord_name
        FROM rental_applications ra
        JOIN properties p ON ra.property_id = p.id
        JOIN users u ON p.landlord_id = u.id
        WHERE ra.tenant_id = $tenant_id
        ORDER BY ra.created_at DESC
        LIMIT 5
    ";
    $recent_applications = mysqli_query($conn, $recent_applications_query);
}

// Recent maintenance requests
$recent_maintenance = null;
if ($has_maintenance_table) {
    $recent_maintenance_query = "
        SELECT mr.*, p.title, p.address
        FROM maintenance_requests mr
        JOIN properties p ON mr.property_id = p.id
        WHERE mr.tenant_id = $tenant_id
        ORDER BY mr.created_at DESC
        LIMIT 5
    ";
    $recent_maintenance = mysqli_query($conn, $recent_maintenance_query);
}

// Handle rental application
if ($has_applications_table && isset($_POST['apply_property'])) {
    $property_id = intval($_POST['property_id']);
    
    // Check if already applied
    $check_existing = "SELECT id FROM rental_applications WHERE tenant_id = $tenant_id AND property_id = $property_id";
    $existing_result = mysqli_query($conn, $check_existing);
    
    if (mysqli_num_rows($existing_result) > 0) {
        $_SESSION['error_message'] = "You have already applied for this property!";
    } else {
        $insert_application = "INSERT INTO rental_applications (tenant_id, property_id, status, created_at) 
                              VALUES ($tenant_id, $property_id, 'pending', NOW())";
        
        if (mysqli_query($conn, $insert_application)) {
            $_SESSION['success_message'] = "Application submitted successfully!";
        } else {
            $_SESSION['error_message'] = "Error submitting application: " . mysqli_error($conn);
        }
    }
    header("Location: tenant_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard - L&T Connect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
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

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .tenant-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .tenant-info .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--accent-gradient);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px var(--shadow-color);
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

        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px var(--shadow-color);
        }

        .stat-card:hover::before {
            transform: scale(1.3) rotate(45deg);
        }

        .stat-card.applications { 
            --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --shadow-color: rgba(102, 126, 234, 0.4);
        }

        .stat-card.pending { 
            --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --shadow-color: rgba(240, 147, 251, 0.4);
        }

        .stat-card.approved { 
            --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --shadow-color: rgba(79, 172, 254, 0.4);
        }

        .stat-card.maintenance { 
            --accent-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --shadow-color: rgba(67, 233, 123, 0.4);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 1;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ffffff;
            margin: 1rem 0 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.95);
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Dashboard Sections */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f3f4f6;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Property Cards */
        .property-grid {
            display: grid;
            gap: 1.5rem;
        }

        .property-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            background: white;
        }

        .property-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transform: translateY(-3px);
            border-color: #667eea;
        }

        .property-card h4 {
            color: #1e293b;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .property-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .property-info p {
            color: #64748b;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .property-price {
            color: #10b981;
            font-weight: bold;
            font-size: 1.5rem;
            margin: 0.75rem 0;
        }

        .property-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-view {
            background: #f3f4f6;
            color: #4b5563;
        }

        .btn-view:hover {
            background: #e5e7eb;
        }

        /* List Items */
        .application-item, .maintenance-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .application-item:hover, .maintenance-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #cbd5e1;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
        }

        .item-title {
            color: #1e293b;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .item-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .item-details p {
            color: #64748b;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-open {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-closed, .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        /* Full Width Section */
        .full-width {
            grid-column: 1 / -1;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 600px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .top-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .property-actions {
                flex-direction: column;
            }
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: #667eea;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        @media (max-width: 600px) {
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        #sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        @media (max-width: 600px) {
            #sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="../logo.png" alt="L&T Connect" style="max-height: 42px; width: auto; display: block; margin-bottom: 0.75rem;">
            <p>Tenant Portal</p>
        </div>
        <ul>
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="tenant_profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="tenant_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="browse_properties.php"><i class="fas fa-search"></i> Browse Properties</a></li>
            <li><a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a></li>
            <li><a href="my_lease.php"><i class="fas fa-file-contract"></i> My Lease</a></li>
            <li><a href="maintenance_requests.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="payment_history.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-tachometer-alt"></i>
                Welcome Back, <?php echo htmlspecialchars($tenant_name); ?>!
            </h1>
            <div class="tenant-info">
                <span>Good to see you</span>
                <div class="avatar"><?php echo strtoupper(substr($tenant_name ?? 'T', 0, 1)); ?></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card applications">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo number_format($tenant_stats['total_applications']); ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo number_format($tenant_stats['pending_applications']); ?></div>
                        <div class="stat-label">Pending Applications</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card approved">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo number_format($tenant_stats['approved_applications']); ?></div>
                        <div class="stat-label">Approved Applications</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card maintenance">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo number_format($tenant_stats['open_maintenance']); ?></div>
                        <div class="stat-label">Open Maintenance</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Sections -->
        <div class="dashboard-grid">
            <!-- Available Properties -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-building"></i>
                        Available Properties
                    </h2>
                    <a href="browse_properties.php" class="btn btn-primary btn-sm">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if ($available_properties && mysqli_num_rows($available_properties) > 0): ?>
                    <div class="property-grid">
                        <?php 
                        $count = 0;
                        while ($property = mysqli_fetch_assoc($available_properties)): 
                            if ($count >= 3) break;
                            $count++;
                        ?>
                            <div class="property-card">
                                <h4><?php echo htmlspecialchars($property['title']); ?></h4>
                                <div class="property-info">
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['address']); ?></p>
                                    <p><i class="fas fa-user-tie"></i> Landlord: <?php echo htmlspecialchars($property['landlord_name']); ?></p>
                                </div>
                                <?php if ($has_rent_amount): ?>
                                    <div class="property-price">R<?php echo number_format($property['rent_amount'], 2); ?>/month</div>
                                <?php endif; ?>
                                <div class="property-actions">
                                    <a href="property_details.php?id=<?php echo $property['id']; ?>" class="btn btn-view" style="flex: 1;">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($has_applications_table): ?>
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                            <button type="submit" name="apply_property" class="btn btn-primary" style="width: 100%;">
                                                <i class="fas fa-paper-plane"></i> Apply
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <h3>No Properties Available</h3>
                        <p>Check back later for new listings</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Applications -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-file-alt"></i>
                        Recent Applications
                    </h2>
                    <a href="my_applications.php" class="btn btn-primary btn-sm">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <?php if ($recent_applications && mysqli_num_rows($recent_applications) > 0): ?>
                    <?php 
                    $count = 0;
                    while ($application = mysqli_fetch_assoc($recent_applications)): 
                        if ($count >= 3) break;
                        $count++;
                    ?>
                        <div class="application-item">
                            <div class="item-header">
                                <div>
                                    <h5 class="item-title"><?php echo htmlspecialchars($application['title']); ?></h5>
                                    <div class="item-details">
                                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($application['address']); ?></p>
                                        <p><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($application['created_at'])); ?></p>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $application['status']; ?>">
                                    <?php echo ucfirst($application['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>No Applications Yet</h3>
                        <p>Start applying to properties</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Maintenance Requests -->
            <div class="section full-width">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tools"></i>
                        Recent Maintenance Requests
                    </h2>
                    <div style="display: flex; gap: 0.75rem;">
                        <?php if ($has_maintenance_table): ?>
                            <a href="maintenance_requests.php" class="btn btn-success">
                                <i class="fas fa-plus"></i> New Request
                            </a>
                        <?php endif; ?>
                        <a href="maintenance_requests.php" class="btn btn-primary">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                
                <?php if ($recent_maintenance && mysqli_num_rows($recent_maintenance) > 0): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                        <?php 
                        $count = 0;
                        while ($maintenance = mysqli_fetch_assoc($recent_maintenance)): 
                            if ($count >= 4) break;
                            $count++;
                        ?>
                            <div class="maintenance-item">
                                <div class="item-header">
                                    <div>
                                        <h5 class="item-title"><?php echo htmlspecialchars($maintenance['title']); ?></h5>
                                        <div class="item-details">
                                            <p><i class="fas fa-home"></i> <?php echo htmlspecialchars($maintenance['title']); ?></p>
                                            <p><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($maintenance['created_at'])); ?></p>
                                            <p><i class="fas fa-exclamation-circle"></i> <?php echo ucfirst($maintenance['priority']); ?> Priority</p>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo $maintenance['status']; ?>">
                                        <?php echo ucfirst($maintenance['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tools"></i>
                        <h3>No Maintenance Requests</h3>
                        <p>You haven't submitted any maintenance requests yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
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

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.3s ease';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });

        // Application confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const applyButtons = document.querySelectorAll('button[name="apply_property"]');
            applyButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to apply for this property?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>