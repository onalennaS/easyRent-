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

// First, let's check what columns exist in the properties table
$check_properties_query = "SHOW COLUMNS FROM properties";
$properties_columns_result = mysqli_query($conn, $check_properties_query);
$properties_columns = [];
if ($properties_columns_result) {
    while ($column = mysqli_fetch_assoc($properties_columns_result)) {
        $properties_columns[] = $column['Field'];
    }
}

// Check if status column exists in properties table
$has_status = in_array('status', $properties_columns);
$has_rent_amount = in_array('rent_amount', $properties_columns);

// Build stats query based on available columns
if ($has_status) {
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM properties) as total_properties,
            (SELECT COUNT(*) FROM properties WHERE status = 'pending') as pending_properties,
            (SELECT COUNT(*) FROM properties WHERE status = 'approved') as approved_properties,
            (SELECT COUNT(*) FROM properties WHERE status = 'rejected') as rejected_properties,
            (SELECT COUNT(*) FROM users WHERE user_type = 'landlord') as total_landlords,
            (SELECT COUNT(*) FROM users WHERE user_type = 'tenant') as total_tenants,
            (SELECT COUNT(*) FROM maintenance_requests WHERE status = 'open') as open_maintenance,
            " . ($has_rent_amount ? "(SELECT COALESCE(SUM(rent_amount), 0) FROM properties WHERE status = 'approved')" : "0") . " as total_rent_value
    ";
} else {
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM properties) as total_properties,
            0 as pending_properties,
            (SELECT COUNT(*) FROM properties) as approved_properties,
            0 as rejected_properties,
            (SELECT COUNT(*) FROM users WHERE user_type = 'landlord') as total_landlords,
            (SELECT COUNT(*) FROM users WHERE user_type = 'tenant') as total_tenants,
            0 as open_maintenance,
            " . ($has_rent_amount ? "(SELECT COALESCE(SUM(rent_amount), 0) FROM properties)" : "0") . " as total_rent_value
    ";
}

$stats_result = mysqli_query($conn, $stats_query);

// Check if query was successful
if (!$stats_result) {
    // Log the error and provide fallback values
    error_log("Database query failed: " . mysqli_error($conn));
    
    // Provide default stats if query fails
    $stats = [
        'total_properties' => 0,
        'pending_properties' => 0,
        'approved_properties' => 0,
        'rejected_properties' => 0,
        'total_landlords' => 0,
        'total_tenants' => 0,
        'open_maintenance' => 0,
        'total_rent_value' => 0
    ];
    
    // Display error message
    $_SESSION['error_message'] = "Database error occurred. Some statistics may not be accurate. Error: " . mysqli_error($conn);
} else {
    $stats = mysqli_fetch_assoc($stats_result);
}

// Fixed query - using common column names that typically exist in users table
// Try different variations of name columns that might exist
$recent_properties_query = "
    SELECT p.*, 
           COALESCE(u.full_name, u.name, u.first_name, u.username, 'Unknown') as landlord_name, 
           u.email as landlord_email 
    FROM properties p 
    JOIN users u ON p.landlord_id = u.id 
    WHERE p.status = 'pending' 
    ORDER BY p.created_at DESC 
    LIMIT 10
";

// Alternative approach - let's first check what columns exist in the users table
$check_columns_query = "SHOW COLUMNS FROM users";
$columns_result = mysqli_query($conn, $check_columns_query);
$user_columns = [];
if ($columns_result) {
    while ($column = mysqli_fetch_assoc($columns_result)) {
        $user_columns[] = $column['Field'];
    }
}

// Determine the correct name column to use
$name_column = 'username'; // default fallback
if (in_array('full_name', $user_columns)) {
    $name_column = 'full_name';
} elseif (in_array('name', $user_columns)) {
    $name_column = 'name';
} elseif (in_array('first_name', $user_columns)) {
    $name_column = 'first_name';
}

// Build properties query based on available columns
if ($has_status) {
   $recent_properties_query = "
    SELECT p.*, 
           COALESCE(u.full_name, u.name, u.first_name, u.username, 'Unknown') as landlord_name, 
           u.username as landlord_username,
           (SELECT COUNT(*) FROM landlord_documents WHERE landlord_id = p.landlord_id AND (status = 'pending' OR status IS NULL)) as pending_documents_count
    FROM properties p 
    JOIN users u ON p.landlord_id = u.id 
    WHERE p.status = 'pending' 
    ORDER BY p.created_at DESC 
    LIMIT 10
";
} else {
    // If no status column, show all recent properties
    $recent_properties_query = "
        SELECT p.*, 
               CONCAT(u.first_name, ' ', u.last_name) as landlord_name, 
               u.email as landlord_email,
               (SELECT COUNT(*) FROM landlord_documents WHERE landlord_id = p.landlord_id AND (status = 'pending' OR status IS NULL)) as pending_documents_count
        FROM properties p 
        JOIN users u ON p.landlord_id = u.id 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ";
}

$recent_properties = mysqli_query($conn, $recent_properties_query);

// Check if properties query was successful
if (!$recent_properties) {
    error_log("Properties query failed: " . mysqli_error($conn));
    $_SESSION['error_message'] = "Error loading properties: " . mysqli_error($conn);
    // Create an empty result set for the display logic
    $recent_properties = null;
}

// Fetch recent landlord registrations with error handling
$recent_landlords_query = "
    SELECT *, CONCAT(first_name, ' ', last_name) as display_name FROM users 
    WHERE user_type = 'landlord' 
    ORDER BY created_at DESC 
    LIMIT 5
";
$recent_landlords = mysqli_query($conn, $recent_landlords_query);

// Check if landlords query was successful
if (!$recent_landlords) {
    error_log("Landlords query failed: " . mysqli_error($conn));
    $_SESSION['error_message'] = "Error loading landlords: " . mysqli_error($conn);
    $recent_landlords = null;
}

// Function to check if landlord documents are approved
function areLandlordDocumentsApproved($conn, $landlord_id) {
    // Check if landlord_documents table exists
    $check_table = "SHOW TABLES LIKE 'landlord_documents'";
    $table_result = mysqli_query($conn, $check_table);
    if (!$table_result || mysqli_num_rows($table_result) == 0) {
        // If table doesn't exist, allow approval (backward compatibility)
        return true;
    }
    
    // Check if landlord has any documents
    $doc_count_query = "SELECT COUNT(*) as doc_count FROM landlord_documents WHERE landlord_id = ?";
    $doc_count_stmt = mysqli_prepare($conn, $doc_count_query);
    mysqli_stmt_bind_param($doc_count_stmt, "i", $landlord_id);
    mysqli_stmt_execute($doc_count_stmt);
    $doc_count_result = mysqli_stmt_get_result($doc_count_stmt);
    $doc_count = mysqli_fetch_assoc($doc_count_result)['doc_count'];
    mysqli_stmt_close($doc_count_stmt);
    
    // If no documents uploaded, allow approval (landlord may not have uploaded yet)
    if ($doc_count == 0) {
        return true;
    }
    
    // Check if all documents are approved (no pending documents)
    $pending_query = "SELECT COUNT(*) as pending_count FROM landlord_documents 
                      WHERE landlord_id = ? AND (status = 'pending' OR status IS NULL)";
    $pending_stmt = mysqli_prepare($conn, $pending_query);
    mysqli_stmt_bind_param($pending_stmt, "i", $landlord_id);
    mysqli_stmt_execute($pending_stmt);
    $pending_result = mysqli_stmt_get_result($pending_stmt);
    $pending_count = mysqli_fetch_assoc($pending_result)['pending_count'];
    mysqli_stmt_close($pending_stmt);
    
    // If there are pending documents, don't allow approval
    return $pending_count == 0;
}

// Handle property approval/rejection (only if status column exists)
if ($has_status && isset($_POST['approve_property'])) {
    $property_id = intval($_POST['property_id']);
    
    // Get landlord_id from property
    $get_landlord_query = "SELECT landlord_id FROM properties WHERE id = ?";
    $get_landlord_stmt = mysqli_prepare($conn, $get_landlord_query);
    mysqli_stmt_bind_param($get_landlord_stmt, "i", $property_id);
    mysqli_stmt_execute($get_landlord_stmt);
    $landlord_result = mysqli_stmt_get_result($get_landlord_stmt);
    
    if ($landlord_result && mysqli_num_rows($landlord_result) > 0) {
        $property_data = mysqli_fetch_assoc($landlord_result);
        $landlord_id = $property_data['landlord_id'];
        mysqli_stmt_close($get_landlord_stmt);
        
        // Check if landlord documents are approved
        if (!areLandlordDocumentsApproved($conn, $landlord_id)) {
            $_SESSION['error_message'] = "Cannot approve property: The landlord has pending documents that need to be reviewed and approved first. Please review and approve/reject all landlord documents before approving this property.";
        } else {
            // Proceed with approval
            $update_query = "UPDATE properties SET status = 'approved', approved_at = NOW() WHERE id = $property_id";
            if (mysqli_query($conn, $update_query)) {
                $_SESSION['success_message'] = "Property approved successfully!";
            } else {
                $_SESSION['error_message'] = "Error approving property: " . mysqli_error($conn);
            }
        }
    } else {
        $_SESSION['error_message'] = "Property not found.";
    }
    
    header("Location: admin_dashboard.php");
    exit();
}

if ($has_status && isset($_POST['reject_property'])) {
    $property_id = intval($_POST['property_id']);
    $rejection_reason = mysqli_real_escape_string($conn, $_POST['rejection_reason']);
    
    $update_query = "UPDATE properties SET status = 'rejected', rejection_reason = '$rejection_reason' WHERE id = $property_id";
    if (mysqli_query($conn, $update_query)) {
        $_SESSION['success_message'] = "Property rejected successfully!";
    } else {
        $_SESSION['error_message'] = "Error rejecting property: " . mysqli_error($conn);
    }
    header("Location: admin_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Easy Rent</title>
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

        .header .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header .admin-info .avatar {
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

        .stat-card.properties .icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.landlords .icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card.tenants .icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.maintenance .icon { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }

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

        .property-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .property-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .property-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .property-info h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 18px;
        }

        .property-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .property-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
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
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background-color: #218838;
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background-color: #c82333;
        }

        .btn-view {
            background-color: #007bff;
            color: white;
        }

        .btn-view:hover {
            background-color: #0056b3;
        }

        .landlord-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .landlord-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 15px;
        }

        .landlord-info h5 {
            color: #333;
            margin-bottom: 3px;
        }

        .landlord-info p {
            color: #666;
            font-size: 13px;
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
            margin: 15% auto;
            padding: 30px;
            border-radius: 10px;
            width: 400px;
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

        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 15px;
        }

        /* Debug info */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #666;
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
            <li><a href="#" id="logoutLink"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Admin Dashboard</h1>
            <div class="admin-info">
                <span>Welcome, <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span>
                <div class="avatar">
                    <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                </div>
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
            <div class="stat-card properties">
                <div class="icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3><?php echo number_format($stats['total_properties']); ?></h3>
                <p>Total Properties</p>
                <small style="color: #666;">
                    <?php echo $stats['pending_properties']; ?> pending approval
                </small>
            </div>

            <div class="stat-card landlords">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo number_format($stats['total_landlords']); ?></h3>
                <p>Registered Landlords</p>
            </div>

            <div class="stat-card tenants">
                <div class="icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <h3><?php echo number_format($stats['total_tenants']); ?></h3>
                <p>Active Tenants</p>
            </div>

            <div class="stat-card maintenance">
                <div class="icon">
                    <i class="fas fa-tools"></i>
                </div>
                <h3><?php echo number_format($stats['open_maintenance']); ?></h3>
                <p>Open Maintenance</p>
            </div>
        </div>

        <!-- Dashboard Sections -->
        <div class="dashboard-sections">
            <!-- Pending Properties -->
            <div class="section">
                <h2>
                    <i class="fas fa-clock"></i>
                    <?php echo $has_status ? 'Pending Property Approvals' : 'Recent Properties'; ?>
                </h2>
                
                <?php if ($recent_properties && mysqli_num_rows($recent_properties) > 0): ?>
                    <?php while ($property = mysqli_fetch_assoc($recent_properties)): ?>
                        <div class="property-card">
                            <div class="property-header">
                                <div class="property-info">
                                    <h4><?php echo htmlspecialchars($property['title'] ?? 'Property'); ?></h4>
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['address'] ?? 'Address not available'); ?></p>
                                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($property['landlord_name']); ?></p>
                                    <?php if ($has_rent_amount && isset($property['rent_amount'])): ?>
                                        <p><i class="fas fa-dollar-sign"></i> $<?php echo number_format($property['rent_amount']); ?>/month</p>
                                    <?php endif; ?>
                                    <p><i class="fas fa-calendar"></i> Added: <?php echo date('M j, Y', strtotime($property['created_at'])); ?></p>
                                </div>
                                <?php if ($has_status): ?>
                                    <span class="property-status status-pending">
                                        <?php echo ucfirst($property['status'] ?? 'pending'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($has_status && ($property['status'] ?? 'pending') == 'pending'): ?>
                                <div class="property-actions">
                                    <?php 
                                    $has_pending_docs = isset($property['pending_documents_count']) && $property['pending_documents_count'] > 0;
                                    if ($has_pending_docs): ?>
                                        <button type="button" class="btn btn-warning" onclick="showDocumentWarning(<?php echo $property['id']; ?>, <?php echo $property['pending_documents_count']; ?>)">
                                            <i class="fas fa-exclamation-triangle"></i> Documents Pending (<?php echo $property['pending_documents_count']; ?>)
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="property_id" value="<?php echo $property['id']; ?>">
                                            <button type="submit" name="approve_property" class="btn btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-reject" onclick="openRejectModal(<?php echo $property['id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    
                                    <a href="view_property.php?id=<?php echo $property['id']; ?>" class="btn btn-view">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="property-actions">
                                    <a href="view_property.php?id=<?php echo $property['id']; ?>" class="btn btn-view">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 10px; display: block;"></i>
                        <?php echo $has_status ? 'No pending properties to review' : 'No properties found'; ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Recent Landlords -->
            <div class="section">
                <h2>
                    <i class="fas fa-user-plus"></i>
                    Recent Landlord Registrations
                </h2>
                
                <?php if ($recent_landlords && mysqli_num_rows($recent_landlords) > 0): ?>
                    <?php while ($landlord = mysqli_fetch_assoc($recent_landlords)): ?>
                        <div class="landlord-item">
                            <div class="landlord-avatar">
                                <?php echo strtoupper(substr($landlord['display_name'], 0, 1)); ?>
                            </div>
                            <div class="landlord-info">
                                <h5><?php echo htmlspecialchars($landlord['display_name']); ?></h5>
                                <p><?php echo htmlspecialchars($landlord['email']); ?></p>
                                <p>Joined: <?php echo date('M j, Y', strtotime($landlord['created_at'])); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">No recent registrations</p>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="manage_landlords.php" class="btn btn-view">View All Landlords</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Property</h3>
                <span class="close" onclick="closeRejectModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" id="reject_property_id" name="property_id">
                <label for="rejection_reason">Reason for rejection:</label>
                <textarea id="rejection_reason" name="rejection_reason" placeholder="Please provide a reason for rejecting this property..." required></textarea>
                <div style="text-align: right;">
                    <button type="button" class="btn" onclick="closeRejectModal()" style="background-color: #6c757d; color: white; margin-right: 10px;">Cancel</button>
                    <button type="submit" name="reject_property" class="btn btn-reject">Reject Property</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showDocumentWarning(propertyId, pendingCount) {
            Swal.fire({
                title: 'Cannot Approve Property',
                html: `
                    <div style="text-align: left;">
                        <p style="margin-bottom: 15px; color: #666;">This property cannot be approved because the landlord has <strong>${pendingCount}</strong> pending document(s) that need to be reviewed first.</p>
                        <p style="margin-bottom: 15px; color: #e74c3c; font-weight: 600;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Please review and approve/reject all landlord documents before approving this property.
                        </p>
                        <p style="color: #666; font-size: 14px;">
                            <i class="fas fa-info-circle"></i> 
                            This ensures the legitimacy of landlord documents before property approval.
                        </p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonColor: '#3b82f6',
                confirmButtonText: 'OK',
                width: '600px'
            });
        }
        
        function openRejectModal(propertyId) {
            document.getElementById('reject_property_id').value = propertyId;
            document.getElementById('rejectModal').style.display = 'block';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('rejection_reason').value = '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target == modal) {
                closeRejectModal();
            }
        }
    </script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  <script>
  document.getElementById('logoutLink').addEventListener('click', function(e) {
    e.preventDefault(); // prevent default link behavior

    Swal.fire({
      title: 'Are you sure?',
      text: 'You will be logged out from your account.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6', // blue
      cancelButtonColor: '#d33',     // red
      confirmButtonText: 'Yes, log out',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        // ✅ Perform your logout action here
        window.location.href = '../auth/logout.php'; // Replace with your logout URL
      }
    });
  });
</script>
</body>
</html>