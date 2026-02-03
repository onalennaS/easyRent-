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
            // Store in session for future use
            $_SESSION['user_name'] = $tenant_name;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Fallback if still no name found
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
        LIMIT 8
    ";
} else {
    $available_properties_query = "
        SELECT p.*, 
               $name_coalesce as landlord_name
        FROM properties p 
        JOIN users u ON p.landlord_id = u.id 
        ORDER BY p.created_at DESC 
        LIMIT 8
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

// Handle maintenance request submission
if ($has_maintenance_table && isset($_POST['submit_maintenance'])) {
    $property_id = intval($_POST['property_id']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    
    $insert_query = "INSERT INTO maintenance_requests (tenant_id, property_id, description, priority, status, created_at) 
                     VALUES ($tenant_id, $property_id, '$description', '$priority', 'open', NOW())";
    
    if (mysqli_query($conn, $insert_query)) {
        $_SESSION['success_message'] = "Maintenance request submitted successfully!";
    } else {
        $_SESSION['error_message'] = "Error submitting maintenance request: " . mysqli_error($conn);
    }
    header("Location: tenant_dashboard.php");
    exit();
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
    <title>Tenant Dashboard - Easy Rent</title>
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
    z-index: 1000;
}
#sidebar-overlay {
    display: none;
}

@media (max-width: 600px) {
    #sidebar-overlay {
        display: block;
    }
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

        .tenant-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .tenant-info .avatar {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card.applications .icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.pending .icon { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }
        .stat-card.approved .icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card.maintenance .icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

        .stat-card h3 {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #666;
            font-size: 14px;
        }

        .dashboard-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .property-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s ease;
            background: white;
        }

        .property-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .property-card h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .property-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .property-card .price {
            color: #28a745;
            font-weight: bold;
            font-size: 18px;
            margin: 10px 0;
        }

        .property-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-view {
            background-color: #6c757d;
            color: white;
        }

        .btn-view:hover {
            background-color: #5a6268;
        }

        .application-item, .maintenance-item {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .application-item h5, .maintenance-item h5 {
            color: #333;
            margin-bottom: 5px;
        }

        .application-item p, .maintenance-item p {
            color: #666;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-open {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-closed {
            background-color: #d4edda;
            color: #155724;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 500px;
            max-width: 90%;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            
            .property-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            }
        }
        .sidebar-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 24px;
    color: #333;
    cursor: pointer;
}

@media (max-width: 600px) {
    .sidebar-toggle {
        display: block;
    }
    
    .main-content {
        padding-top: 70px;
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
            <li><a href="../index.php" class="home-button"><i class="fas fa-home"></i> Home</a></li>
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
                Welcome Back!
            </h1>
            <div class="tenant-info">
                <span>Hello, <?php echo htmlspecialchars($tenant_name); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($tenant_name ?? '', 0, 1)); ?></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card applications">
                <div class="icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3><?php echo number_format($tenant_stats['total_applications']); ?></h3>
                <p>Total Applications</p>
            </div>

            <div class="stat-card pending">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo number_format($tenant_stats['pending_applications']); ?></h3>
                <p>Pending Applications</p>
            </div>

            <div class="stat-card approved">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo number_format($tenant_stats['approved_applications']); ?></h3>
                <p>Approved Applications</p>
            </div>

            <div class="stat-card maintenance">
                <div class="icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3><?php echo number_format($tenant_stats['open_maintenance']); ?></h3>
                <p>Open Maintenance</p>
            </div>
        </div>

        

            <!-- Maintenance Requests -->
            <div class="section">
                <h2>
                    <i class="fas fa-tools"></i>
                    Maintenance Requests
                </h2>
                
                <?php if ($recent_maintenance && mysqli_num_rows($recent_maintenance) > 0): ?>
                    <?php while ($maintenance = mysqli_fetch_assoc($recent_maintenance)): ?>
                        <div class="maintenance-item">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                <div>
                                    <h5><?php echo htmlspecialchars($maintenance['title']); ?></h5>
                                    <p><?php echo htmlspecialchars(substr($maintenance['description'], 0, 50)) . '...'; ?></p>
                                    <p><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($maintenance['created_at'])); ?></p>
                                    <p><i class="fas fa-exclamation-triangle"></i> Priority: <?php echo ucfirst($maintenance['priority']); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo $maintenance['status']; ?>">
                                    <?php echo ucfirst($maintenance['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No maintenance requests</p>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 15px;">
                    <?php if ($has_maintenance_table): ?>
                        <button class="btn btn-success" onclick="openMaintenanceModal()" style="margin-right: 10px;">
                            <i class="fas fa-plus"></i> New Request
                        </button>
                    <?php endif; ?>
                    <a href="maintenance_requests.php" class="btn btn-primary">View All Requests</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Request Modal -->
    <?php if ($has_maintenance_table): ?>
    <div id="maintenanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Submit Maintenance Request</h3>
                <span class="close" onclick="closeMaintenanceModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="property_id">Property:</label>
                    <select id="property_id" name="property_id" required>
                        <option value="">Select Property</option>
                        <?php
                        // Get properties for dropdown (approved applications or all properties)
                        if ($has_applications_table) {
                            $property_options_query = "
                                SELECT DISTINCT p.id, p.title, p.address
                                FROM properties p 
                                JOIN rental_applications ra ON p.id = ra.property_id 
                                WHERE ra.tenant_id = $tenant_id AND ra.status = 'approved'
                                ORDER BY p.title
                            ";
                        } else {
                            $property_options_query = "
                                SELECT id, title, address 
                                FROM properties 
                                ORDER BY title
                            ";
                        }
                        
                        $property_options = mysqli_query($conn, $property_options_query);
                        if ($property_options) {
                            while ($prop = mysqli_fetch_assoc($property_options)) {
                                echo "<option value='" . $prop['id'] . "'>" . 
                                     htmlspecialchars($prop['title']) . " - " . 
                                     htmlspecialchars($prop['address']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority">Priority:</label>
                    <select id="priority" name="priority" required>
                        <option value="">Select Priority</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" required 
                              placeholder="Please describe the maintenance issue in detail..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="submit_maintenance" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                    <button type="button" class="btn btn-view" onclick="closeMaintenanceModal()" style="margin-left: 10px;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Modal functions
        function openMaintenanceModal() {
            document.getElementById('maintenanceModal').style.display = 'block';
        }

        function closeMaintenanceModal() {
            document.getElementById('maintenanceModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('maintenanceModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        // Mobile sidebar toggle (for responsive design)
        // Replace the existing toggleSidebar function with this:
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
    
    // Add overlay when sidebar is active
    if (sidebar.classList.contains('active')) {
        createOverlay();
    } else {
        removeOverlay();
    }
}

function createOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'sidebar-overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
    overlay.style.zIndex = '999';
    overlay.onclick = toggleSidebar;
    document.body.appendChild(overlay);
}

function removeOverlay() {
    const overlay = document.getElementById('sidebar-overlay');
    if (overlay) overlay.remove();
}

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const maintenanceForm = document.querySelector('#maintenanceModal form');
            if (maintenanceForm) {
                maintenanceForm.addEventListener('submit', function(e) {
                    const propertyId = document.getElementById('property_id').value;
                    const priority = document.getElementById('priority').value;
                    const description = document.getElementById('description').value.trim();

                    if (!propertyId) {
                        alert('Please select a property');
                        e.preventDefault();
                        return;
                    }

                    if (!priority) {
                        alert('Please select a priority level');
                        e.preventDefault();
                        return;
                    }

                    if (!description || description.length < 10) {
                        alert('Please provide a detailed description (at least 10 characters)');
                        e.preventDefault();
                        return;
                    }
                });
            }
        });

        // Property application confirmation
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

        // Add smooth scrolling for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add loading state to buttons
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                        submitBtn.disabled = true;
                        
                        // Re-enable button after 3 seconds (fallback)
                        setTimeout(function() {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 3000);
                    }
                });
            });
        });
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
<!-- Add these to your head section -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- Add this script at the end of your body -->
<script>
  // Logout confirmation function
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
        
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</body>
</html>

<?php
mysqli_close($conn);
?>