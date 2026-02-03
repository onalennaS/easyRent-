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

// Get landlord ID from session
$landlord_id = $_SESSION['user_id'] ?? 1; // Fallback for testing

// Check what columns exist in the properties table
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

// Check what columns exist in users table
$check_users_query = "SHOW COLUMNS FROM users";
$users_columns_result = mysqli_query($conn, $check_users_query);
$users_columns = [];
if ($users_columns_result) {
    while ($column = mysqli_fetch_assoc($users_columns_result)) {
        $users_columns[] = $column['Field'];
    }
}

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

// Check if maintenance_requests table exists
$check_maintenance_table = "SHOW TABLES LIKE 'maintenance_requests'";
$maintenance_table_result = mysqli_query($conn, $check_maintenance_table);
$has_maintenance_table = mysqli_num_rows($maintenance_table_result) > 0;

// Check if inquiries table exists
$check_inquiries_table = "SHOW TABLES LIKE 'inquiries'";
$inquiries_table_result = mysqli_query($conn, $check_inquiries_table);
$has_inquiries_table = mysqli_num_rows($inquiries_table_result) > 0;

// Get landlord statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM properties WHERE landlord_id = $landlord_id) as total_properties,
        " . ($has_status ? "(SELECT COUNT(*) FROM properties WHERE landlord_id = $landlord_id AND status = 'approved')" : "(SELECT COUNT(*) FROM properties WHERE landlord_id = $landlord_id)") . " as active_properties,
        " . ($has_status ? "(SELECT COUNT(*) FROM properties WHERE landlord_id = $landlord_id AND status = 'pending')" : "0") . " as pending_properties,
        " . ($has_rent_amount ? "(SELECT COALESCE(SUM(rent_amount), 0) FROM properties WHERE landlord_id = $landlord_id" . ($has_status ? " AND status = 'approved'" : "") . ")" : "0") . " as monthly_income,
        " . ($has_maintenance_table ? "(SELECT COUNT(*) FROM maintenance_requests mr JOIN properties p ON mr.property_id = p.id WHERE p.landlord_id = $landlord_id AND mr.status = 'open')" : "0") . " as open_maintenance
";

$stats_result = mysqli_query($conn, $stats_query);
if ($stats_result) {
    $stats = mysqli_fetch_assoc($stats_result);
} else {
    $stats = [
        'total_properties' => 0,
        'active_properties' => 0,
        'pending_properties' => 0,
        'monthly_income' => 0,
        'open_maintenance' => 0
    ];
}

// Get recent properties
$properties_query = "
    SELECT p.*, 
           (SELECT image_url FROM property_images WHERE property_id = p.id ORDER BY created_at LIMIT 1) as primary_image
    FROM properties p 
    WHERE p.landlord_id = $landlord_id 
    ORDER BY p.created_at DESC 
    LIMIT 6
";
$properties_result = mysqli_query($conn, $properties_query);

// Get recent maintenance requests
$maintenance_result = null;
if ($has_maintenance_table) {
    $maintenance_query = "
        SELECT mr.*, p.title as property_title, p.address as property_address
        FROM maintenance_requests mr 
        JOIN properties p ON mr.property_id = p.id 
        WHERE p.landlord_id = $landlord_id 
        ORDER BY mr.created_at DESC 
        LIMIT 5
    ";
    $maintenance_result = mysqli_query($conn, $maintenance_query);
}

// Get recent inquiries (only if table exists)
$inquiries_result = null;
if ($has_inquiries_table) {
    $inquiries_query = "
        SELECT i.*, p.title as property_title, $name_coalesce as full_name, u.email
        FROM inquiries i
        JOIN properties p ON i.property_id = p.id
        JOIN users u ON i.tenant_id = u.id
        WHERE p.landlord_id = $landlord_id
        ORDER BY i.created_at DESC
        LIMIT 5
    ";
    $inquiries_result = mysqli_query($conn, $inquiries_query);
}

// Get pending applications for notification
$pending_applications_query = "
    SELECT ra.*, p.title as property_title, u.first_name, u.last_name
    FROM rental_applications ra
    JOIN properties p ON ra.property_id = p.id
    JOIN users u ON ra.tenant_id = u.id
    WHERE p.landlord_id = $landlord_id AND ra.status = 'pending'
    ORDER BY ra.application_date DESC
";
$pending_applications_result = mysqli_query($conn, $pending_applications_query);
$pending_applications = [];
if ($pending_applications_result) {
    while ($row = mysqli_fetch_assoc($pending_applications_result)) {
        $pending_applications[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Dashboard - Easy Rent</title>
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
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

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #06b6d4 100%);
            border-radius: 20px;
            padding: 3rem 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="70" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.625rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .quick-action-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            border-color: rgba(255,255,255,0.5);
        }

        .quick-action-btn i {
            font-size: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .stat-card.properties { --accent-color: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
        .stat-card.income { --accent-color: linear-gradient(135deg, #10b981 0%, #047857 100%); }
        .stat-card.maintenance { --accent-color: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-card.pending { --accent-color: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: var(--accent-color);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-all-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .view-all-link:hover {
            color: #1d4ed8;
        }

/* Property Cards - Reduced Size */
.property-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); /* Reduced from 300px */
    gap: 1rem; /* Reduced from 1.5rem */
}

.property-card {
    background: white;
    border-radius: 12px; /* Reduced from 16px */
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06); /* Reduced shadow */
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    max-width: 280px; /* Added max-width constraint */
}

.property-card:hover {
    transform: translateY(-3px); /* Reduced from -5px */
    box-shadow: 0 4px 20px rgba(0,0,0,0.12); /* Reduced shadow */
}

.property-image {
    height: 140px; /* Reduced from 200px */
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
}

.property-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.property-card:hover .property-image img {
    transform: scale(1.03); /* Reduced from 1.05 */
}

.property-image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6366f1;
    font-size: 2rem; /* Reduced from 3rem */
}

.property-status {
    position: absolute;
    top: 0.75rem; /* Reduced from 1rem */
    right: 0.75rem; /* Reduced from 1rem */
    padding: 0.25rem 0.5rem; /* Reduced horizontal padding */
    border-radius: 16px; /* Reduced from 20px */
    font-size: 0.7rem; /* Reduced from 0.75rem */
    font-weight: 600;
    text-transform: uppercase;
}

.property-content {
    padding: 1rem; /* Reduced from 1.5rem */
}

.property-title {
    font-size: 1rem; /* Reduced from 1.1rem */
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    /* Limit to 2 lines and add ellipsis for long titles */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.property-address {
    color: #64748b;
    font-size: 0.85rem; /* Reduced from 0.9rem */
    margin-bottom: 0.75rem; /* Reduced from 1rem */
    display: flex;
    align-items: center;
    gap: 0.25rem;
    /* Limit to 2 lines for long addresses */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.property-price {
    font-size: 1.1rem; /* Reduced from 1.25rem */
    font-weight: bold;
    color: #059669;
    margin-bottom: 0.75rem; /* Reduced from 1rem */
}

.property-actions {
    display: flex;
    gap: 0.375rem; /* Reduced from 0.5rem */
}

/* Button Styles */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    color: white;
}

.btn-secondary {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #475569 0%, #334155 100%);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #047857 100%);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669 0%, #065f46 100%);
    color: white;
}

.property-actions .btn {
    padding: 0.4rem 0.75rem; /* Reduced padding */
    border-radius: 6px; /* Reduced from 8px */
    text-decoration: none;
    font-size: 0.8rem; /* Reduced from 0.875rem */
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    flex: 1; /* Make buttons equal width */
    justify-content: center;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
}

.property-actions .btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
}

.property-actions .btn-secondary {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    color: white;
}

.property-actions .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .property-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Even smaller on mobile */
        gap: 0.75rem;
    }
    
    .property-card {
        max-width: none; /* Remove max-width constraint on mobile */
    }
    
    .property-image {
        height: 120px; /* Further reduced on mobile */
    }
    
    .property-content {
        padding: 0.75rem; /* Further reduced padding on mobile */
    }
}

@media (max-width: 640px) {
    .property-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Smallest size for very small screens */
    }
    
    .property-actions .btn {
        padding: 0.35rem 0.5rem;
        font-size: 0.75rem;
    }
}

        /* List Items */
        .list-item {
            display: flex;
            align-items: start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .item-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }

        .maintenance-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .inquiry-icon {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .item-content {
            flex: 1;
        }

        .item-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .item-subtitle {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            color: #9ca3af;
            font-size: 0.75rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            font-size: 1rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        .empty-state .btn {
            margin-top: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
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

            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }

            .property-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .hero-section {
                padding: 2rem 1.5rem;
            }

            .quick-actions {
                justify-content: center;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .content-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-home"></i>
                Easy Rent
            </div>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo $_SESSION['user_name'] ?? 'Landlord'; ?></div>
                <div class="user-role">Landlord</div>
            </div>
        </div>
        
        <ul class="sidebar-nav">
            <ul class="sidebar-nav">
    <li class="nav-item">
        <a href="profile_landlord.php" class="nav-link">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </li>
            <li class="nav-item">
                <a href="landlord_dashboard.php" class="nav-link active">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="my_properties.php" class="nav-link">
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
        <!-- Top Bar -->
        <div class="top-bar">
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">Dashboard</h1>
            <div></div> <!-- Empty div for spacing -->
        </div>

        <!-- Hero Section -->
        <div class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">Good <?php echo date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening'); ?>!</h1>
                <p class="hero-subtitle">Manage your properties efficiently and grow your rental business</p>
                
                <div class="quick-actions">
                    <a href="add_property.php" class="quick-action-btn">
                        <i class="fas fa-plus"></i>
                        Add New Property
                    </a>
                    <a href="maintenance.php" class="quick-action-btn">
                        <i class="fas fa-tools"></i>
                        Maintenance Requests
                    </a>
                    <a href="reports.php" class="quick-action-btn">
                        <i class="fas fa-chart-line"></i>
                        View Reports
                    </a>
                </div>
            </div>
        </div>

      
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Properties -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-building"></i>
                        Recent Properties
                    </h2>
                    <a href="my_properties.php" class="view-all-link">View All</a>
                </div>
                
                <?php if ($properties_result && mysqli_num_rows($properties_result) > 0): ?>
                    <div class="property-grid">
                        <?php while ($property = mysqli_fetch_assoc($properties_result)): 
                            // Determine the image path
                            $image_path = !empty($property['primary_image']) ? 
                                '../uploads/properties/' . $property['primary_image'] : 
                                null;
                        ?>
                            <div class="property-card">
                                <div class="property-image">
                                    <?php if ($image_path): ?>
                                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($property['title'] ?? 'Property'); ?>">
                                    <?php else: ?>
                                        <div class="property-image-placeholder">
                                            <i class="fas fa-home"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_status): ?>
                                        <span class="property-status status-<?php echo $property['status'] ?? 'approved'; ?>">
                                            <?php echo ucfirst($property['status'] ?? 'approved'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="property-content">
                                    <h3 class="property-title"><?php echo htmlspecialchars($property['title'] ?? 'Property'); ?></h3>
                                    <p class="property-address">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($property['address'] ?? 'Address not available'); ?>
                                    </p>
                                    <?php if ($has_rent_amount && isset($property['rent_amount'])): ?>
                                        <div class="property-price">R<?php echo number_format($property['rent_amount']); ?>/month</div>
                                    <?php endif; ?>
                                    <div class="property-actions">
                                        <a href="view_property.php?id=<?php echo $property['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                            View
                                        </a>
                                        <a href="edit_property.php?id=<?php echo $property['id']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <h3>No Properties Yet</h3>
                        <p>Start by adding your first property to the platform</p>
                        <a href="add_property.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i>
                            Add Property
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Recent Maintenance -->
                <div class="content-card" style="margin-bottom: 2rem;">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-tools"></i>
                            Recent Maintenance
                        </h3>
                    </div>
                    
                    <?php if ($maintenance_result && mysqli_num_rows($maintenance_result) > 0): ?>
                        <?php while ($maintenance = mysqli_fetch_assoc($maintenance_result)): ?>
                            <div class="list-item">
                                <div class="item-icon maintenance-icon">
                                    <i class="fas fa-wrench"></i>
                                </div>
                                <div class="item-content">
                                    <div class="item-title"><?php echo htmlspecialchars($maintenance['title'] ?? 'Maintenance Request'); ?></div>
                                    <div class="item-subtitle"><?php echo htmlspecialchars($maintenance['property_title'] ?? 'Property'); ?></div>
                                    <div class="item-meta"><?php echo date('M j, Y', strtotime($maintenance['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No recent maintenance requests</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Inquiries -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-envelope"></i>
                            Recent Inquiries
                        </h3>
                    </div>
                    
                    <?php if ($inquiries_result && mysqli_num_rows($inquiries_result) > 0): ?>
                        <?php while ($inquiry = mysqli_fetch_assoc($inquiries_result)): ?>
                            <div class="list-item">
                                <div class="item-icon inquiry-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="item-content">
                                    <div class="item-title"><?php echo htmlspecialchars($inquiry['first_name'] . ' ' . $inquiry['last_name']); ?></div>
                                    <div class="item-subtitle"><?php echo htmlspecialchars($inquiry['property_title']); ?></div>
                                    <div class="item-meta"><?php echo date('M j, Y', strtotime($inquiry['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope-open"></i>
                            <p>No recent inquiries</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const sidebar = document.querySelector('.sidebar');
        
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        // Logout confirmation
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

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 900 &&
                sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                !mobileMenuBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Show pending applications notification
        <?php if (!empty($pending_applications)): ?>
            const pendingCount = <?php echo count($pending_applications); ?>;
            const propertyNames = <?php echo json_encode(array_column($pending_applications, 'property_title')); ?>;

            let propertyList = '';
            if (pendingCount === 1) {
                propertyList = `<strong>${propertyNames[0]}</strong>`;
            } else if (pendingCount === 2) {
                propertyList = `<strong>${propertyNames[0]}</strong> and <strong>${propertyNames[1]}</strong>`;
            } else {
                propertyList = `<strong>${propertyNames[0]}</strong> and ${pendingCount - 1} other${pendingCount > 2 ? 's' : ''}`;
            }

            Swal.fire({
                title: 'New Rental Applications!',
                html: `You have ${pendingCount} new rental application${pendingCount > 1 ? 's' : ''} for ${propertyList}.<br><br>Check your applications to review them.`,
                icon: 'info',
                confirmButtonColor: '#3b82f6',
                confirmButtonText: 'View Applications',
                showCancelButton: true,
                cancelButtonText: 'Later',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'applications.php';
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>