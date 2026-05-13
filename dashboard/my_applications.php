<?php
// my_applications.php - Tenant Applications Page

// Handle AJAX withdrawal request first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_application'])) {
    // Start session before any output
    session_start();
    
    // Clean any existing output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffer
    ob_start();

    // Suppress errors to prevent output corruption
    error_reporting(0);
    ini_set('display_errors', 0);

    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "easyrent_db";

    $conn = mysqli_connect($servername, $username, $password, $dbname);

    // Check connection
    if (!$conn) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit();
    }

    $tenant_id = $_SESSION['user_id'] ?? 0;
    $response = ['success' => false, 'message' => ''];
    $application_id = intval($_POST['application_id'] ?? 0);

    if ($application_id > 0 && $tenant_id > 0) {
        // Check if application belongs to tenant and is pending
        $check_query = "SELECT status FROM rental_applications WHERE id = ? AND tenant_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $check_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $application_id, $tenant_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                if (strtolower($row['status']) === 'pending') {
                    // Update status to withdrawn
                    $update_query = "UPDATE rental_applications SET status = 'withdrawn', updated_at = NOW() WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, "i", $application_id);
                        if (mysqli_stmt_execute($update_stmt)) {
                            $response['success'] = true;
                            $response['message'] = 'Application withdrawn successfully.';
                        } else {
                            $response['message'] = 'Failed to update application status.';
                        }
                        mysqli_stmt_close($update_stmt);
                    } else {
                        $response['message'] = 'Database error.';
                    }
                } else {
                    $response['message'] = 'Only pending applications can be withdrawn.';
                }
            } else {
                $response['message'] = 'Application not found.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = 'Database error.';
        }
    } else {
        $response['message'] = 'Invalid application ID or session expired.';
    }

    mysqli_close($conn);
    
    // Clear the buffer and send clean JSON
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit();
}

// Handle AJAX request for property details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_property_details'])) {
    session_start();
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    ob_start();
    error_reporting(0);
    ini_set('display_errors', 0);

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "easyrent_db";

    $conn = mysqli_connect($servername, $username, $password, $dbname);

    if (!$conn) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit();
    }

    $property_id = intval($_POST['property_id'] ?? 0);
    $response = ['success' => false, 'message' => ''];

    if ($property_id > 0) {
        // Fetch property details
        $property_query = "
            SELECT p.*, u.username as landlord_name, u.email as landlord_email, u.phone as landlord_phone
            FROM properties p
            LEFT JOIN users u ON p.landlord_id = u.id
            WHERE p.id = ?
            LIMIT 1
        ";
        
        $stmt = mysqli_prepare($conn, $property_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $property_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($property = mysqli_fetch_assoc($result)) {
                // Fetch property images
                $images_query = "SELECT image_url FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC";
                $images_stmt = mysqli_prepare($conn, $images_query);
                mysqli_stmt_bind_param($images_stmt, "i", $property_id);
                mysqli_stmt_execute($images_stmt);
                $images_result = mysqli_stmt_get_result($images_stmt);
                
                $images = [];
                while ($img = mysqli_fetch_assoc($images_result)) {
                    $images[] = ltrim($img['image_url'], '/');
                }
                mysqli_stmt_close($images_stmt);
                
                // Fetch amenities
                $amenities_query = "
                    SELECT a.name, a.icon
                    FROM amenities a
                    INNER JOIN property_amenities pa ON a.id = pa.amenity_id
                    WHERE pa.property_id = ?
                ";
                $amenities_stmt = mysqli_prepare($conn, $amenities_query);
                $amenities = [];
                if ($amenities_stmt) {
                    mysqli_stmt_bind_param($amenities_stmt, "i", $property_id);
                    mysqli_stmt_execute($amenities_stmt);
                    $amenities_result = mysqli_stmt_get_result($amenities_stmt);
                    while ($amenity = mysqli_fetch_assoc($amenities_result)) {
                        $amenities[] = $amenity;
                    }
                    mysqli_stmt_close($amenities_stmt);
                }
                
                $response['success'] = true;
                $response['property'] = $property;
                $response['images'] = $images;
                $response['amenities'] = $amenities;
            } else {
                $response['message'] = 'Property not found.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $response['message'] = 'Database error.';
        }
    } else {
        $response['message'] = 'Invalid property ID.';
    }

    mysqli_close($conn);
    
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit();
}

// Now start the normal page processing
ob_start();
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

// Fetch applications with property details using prepared statement
$applications_query = "
    SELECT ra.*, p.id as property_id, p.title, p.address, p.rent_amount, 
           (SELECT image_url FROM property_images 
            WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS main_image
    FROM rental_applications ra
    JOIN properties p ON ra.property_id = p.id
    WHERE ra.tenant_id = ?
    ORDER BY ra.created_at DESC
";

$stmt = mysqli_prepare($conn, $applications_query);
$applications = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $tenant_id);
    mysqli_stmt_execute($stmt);
    $applications_result = mysqli_stmt_get_result($stmt);
    
    if ($applications_result && mysqli_num_rows($applications_result) > 0) {
        while ($row = mysqli_fetch_assoc($applications_result)) {
            $applications[] = $row;
            $stats['total']++;
            
            switch (strtolower($row['status'])) {
                case 'pending': $stats['pending']++; break;
                case 'approved': $stats['approved']++; break;
                case 'rejected': $stats['rejected']++; break;
            }
        }
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - L&T Connect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #4a90e2;
            --primary-dark: #2a6fc9;
            --secondary: #43e97b;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border: #dee2e6;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
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
            background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* Stats Section */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            display: flex;
            align-items: center;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 24px;
        }

        .stat-icon.total { background-color: rgba(74, 144, 226, 0.15); color: var(--primary); }
        .stat-icon.pending { background-color: rgba(255, 193, 7, 0.15); color: var(--warning); }
        .stat-icon.approved { background-color: rgba(40, 167, 69, 0.15); color: var(--success); }
        .stat-icon.rejected { background-color: rgba(220, 53, 69, 0.15); color: var(--danger); }

        .stat-content h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-content p {
            color: var(--gray);
            font-size: 16px;
        }

        /* Applications Section */
        .applications-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .filters {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .filter-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            font-size: 15px;
        }

        .applications-list {
            padding: 0;
        }

        .application-item {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            transition: background-color 0.2s;
        }

        .application-item:hover {
            background-color: var(--light);
        }

        .property-image {
            width: 120px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 20px;
            background-color: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-image i {
            font-size: 36px;
            color: var(--gray);
        }

        .property-details {
            flex: 1;
            min-width: 250px;
        }

        .property-details h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .property-details p {
            color: var(--gray);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .property-details .price {
            color: var(--success);
            font-weight: 600;
            font-size: 18px;
        }

        .application-meta {
            min-width: 200px;
            padding: 10px 0;
        }

        .application-meta p {
            margin-bottom: 8px;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .status.pending { background-color: rgba(255, 193, 7, 0.15); color: var(--warning); }
        .status.approved { background-color: rgba(40, 167, 69, 0.15); color: var(--success); }
        .status.rejected { background-color: rgba(220, 53, 69, 0.15); color: var(--danger); }
        .status.withdrawn { background-color: rgba(108, 117, 125, 0.15); color: var(--gray); }

        .application-actions {
            display: flex;
            gap: 10px;
            padding: 10px 0;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            border: none;
        }

        .btn i {
            margin-right: 5px;
        }

        .btn-view {
            background-color: rgba(74, 144, 226, 0.1);
            color: var(--primary);
        }

        .btn-view:hover {
            background-color: rgba(74, 144, 226, 0.2);
        }

        .btn-withdraw {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .btn-withdraw:hover {
            background-color: rgba(220, 53, 69, 0.2);
        }

        .no-applications {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }

        .no-applications i {
            font-size: 72px;
            color: var(--light-gray);
            margin-bottom: 20px;
        }

        .no-applications h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }

        .no-applications p {
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .browse-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s;
        }

        .browse-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.3);
        }

        .browse-btn i {
            margin-right: 8px;
        }

        /* Property Details Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            line-height: 1;
        }

        .close:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        .property-gallery {
            margin-bottom: 30px;
        }

        .main-property-image {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 15px;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-thumbnails {
            display: flex;
            gap: 10px;
            overflow-x: auto;
        }

        .property-thumbnail {
            width: 100px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .property-thumbnail:hover,
        .property-thumbnail.active {
            border-color: var(--primary);
        }

        .property-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: var(--light);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .info-card i {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .info-card h4 {
            margin-bottom: 5px;
            font-size: 18px;
        }

        .info-card p {
            color: var(--gray);
            font-size: 14px;
        }

        .property-section {
            margin-bottom: 30px;
        }

        .property-section h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: var(--dark);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }

        .property-description {
            color: var(--gray);
            line-height: 1.8;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .amenity-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background: var(--light);
            border-radius: 8px;
        }

        .amenity-item i {
            color: var(--primary);
            margin-right: 10px;
            font-size: 18px;
        }

        .landlord-info {
            background: var(--light);
            padding: 20px;
            border-radius: 10px;
        }

        .landlord-info h4 {
            margin-bottom: 15px;
            color: var(--dark);
        }

        .landlord-info p {
            margin-bottom: 10px;
            color: var(--gray);
        }

        .landlord-info i {
            color: var(--primary);
            margin-right: 10px;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .logo span, .nav-link span {
                display: none;
            }
            
            .logo i, .nav-link i {
                margin-right: 0;
                font-size: 24px;
            }
            
            .nav-link {
                justify-content: center;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 70px;
                max-width: calc(100% - 70px);
            }
        }

        @media (max-width: 768px) {
            .application-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .property-image {
                margin-bottom: 15px;
            }
            
            .application-meta {
                margin: 15px 0;
            }
            
            .application-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .main-property-image {
                height: 250px;
            }

            .property-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 20px 15px;
            }
            
            .filters {
                flex-direction: column;
                gap: 15px;
            }

            .property-info-grid {
                grid-template-columns: 1fr;
            }

            .amenities-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="../logo.png" alt="L&T Connect" style="max-height: 42px; width: auto; display: block; margin-bottom: 0.75rem;">
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
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-file-alt"></i>
                My Rental Applications
            </h1>
            <div class="tenant-info">
                <span>Hello, <?php echo htmlspecialchars($tenant_name); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($tenant_name ?? '', 0, 1)); ?></div>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved Applications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected Applications</p>
                </div>
            </div>
        </div>

        <!-- Applications Section -->
        <div class="applications-container">
            <div class="filters">
                <div class="filter-group">
                    <label for="status-filter">Filter by Status</label>
                    <select id="status-filter">
                        <option value="all">All Applications</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date-filter">Sort by Date</label>
                    <select id="date-filter">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                    </select>
                </div>
            </div>
            
            <div class="applications-list">
                <?php if (!empty($applications)): ?>
                    <?php foreach ($applications as $app): 
                        $image_path = !empty($app['main_image']) ? '../uploads/properties/' . ltrim($app['main_image'], '/') : '';
                        $status_class = strtolower($app['status']);
                        $app_date = date('M d, Y', strtotime($app['created_at']));
                    ?>
                        <div class="application-item" data-status="<?php echo $status_class; ?>" data-application-id="<?php echo $app['id']; ?>" data-property-id="<?php echo $app['property_id']; ?>">
                            <div class="property-image">
                                <?php if (!empty($image_path)): ?>
                                    <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($app['title']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-home"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="property-details">
                                <h3><?php echo htmlspecialchars($app['title']); ?></h3>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($app['address']); ?></p>
                                <p class="price">R<?php echo number_format($app['rent_amount']); ?>/month</p>
                            </div>
                            
                            <div class="application-meta">
                                <p><strong>Applied:</strong> <?php echo $app_date; ?></p>
                                <p><strong>Status:</strong> <span class="status <?php echo $status_class; ?>"><?php echo ucfirst($app['status']); ?></span></p>
                                <p><strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($app['updated_at'] ?? $app['created_at'])); ?></p>
                            </div>
                            
                            <div class="application-actions">
                                <button class="btn btn-view" data-property-id="<?php echo $app['property_id']; ?>">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <?php if ($status_class === 'pending'): ?>
                                    <button class="btn btn-withdraw">
                                        <i class="fas fa-times"></i> Withdraw
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-applications">
                        <i class="fas fa-file-alt"></i>
                        <h3>No Applications Found</h3>
                        <p>You haven't applied to any properties yet. Start browsing our available properties and submit your first application.</p>
                        <a href="browse_properties.php" class="browse-btn">
                            <i class="fas fa-search"></i> Browse Properties
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Property Details Modal -->
    <div id="propertyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalPropertyTitle">Property Details</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="property-gallery">
                    <div class="main-property-image" id="mainPropertyImage">
                        <i class="fas fa-home" style="font-size: 72px; color: var(--gray);"></i>
                    </div>
                    <div class="property-thumbnails" id="propertyThumbnails">
                        <!-- Thumbnails will be inserted here -->
                    </div>
                </div>

                <div class="property-info-grid" id="propertyInfoGrid">
                    <!-- Property info cards will be inserted here -->
                </div>

                <div class="property-section">
                    <h3>Description</h3>
                    <p class="property-description" id="propertyDescription">Loading...</p>
                </div>

                <div class="property-section">
                    <h3>Location</h3>
                    <div id="propertyLocation">Loading...</div>
                </div>

                <div class="property-section" id="amenitiesSection" style="display: none;">
                    <h3>Amenities & Features</h3>
                    <div class="amenities-grid" id="propertyAmenities">
                        <!-- Amenities will be inserted here -->
                    </div>
                </div>

                <div class="property-section">
                    <h3>Landlord Information</h3>
                    <div class="landlord-info" id="landlordInfo">
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const statusFilter = document.getElementById('status-filter');
            const dateFilter = document.getElementById('date-filter');
            const applicationItems = document.querySelectorAll('.application-item');
            
            function filterApplications() {
                const statusValue = statusFilter.value;
                
                applicationItems.forEach(item => {
                    const itemStatus = item.getAttribute('data-status');
                    
                    // Status filtering
                    if (statusValue !== 'all' && statusValue !== itemStatus) {
                        item.style.display = 'none';
                        return;
                    }
                    
                    item.style.display = 'flex';
                });
            }
            
            // Add event listeners
            statusFilter.addEventListener('change', filterApplications);
            dateFilter.addEventListener('change', filterApplications);
            
            // Modal functionality
            const modal = document.getElementById('propertyModal');
            const closeBtn = document.querySelector('.close');

            // Close modal when clicking X
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }

            // View Details button functionality
            document.querySelectorAll('.btn-view').forEach(button => {
                button.addEventListener('click', function() {
                    const propertyId = this.getAttribute('data-property-id');
                    
                    // Show loading state
                    Swal.fire({
                        title: 'Loading Property Details',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Fetch property details
                    const formData = new FormData();
                    formData.append('get_property_details', '1');
                    formData.append('property_id', propertyId);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        
                        if (data.success) {
                            const property = data.property;
                            const images = data.images;
                            const amenities = data.amenities;

                            // Update modal title
                            document.getElementById('modalPropertyTitle').textContent = property.title;

                            // Update main image
                            const mainImageDiv = document.getElementById('mainPropertyImage');
                            if (images && images.length > 0) {
                                mainImageDiv.innerHTML = `<img src="../uploads/properties/${images[0]}" alt="${property.title}">`;
                                
                                // Update thumbnails
                                const thumbnailsDiv = document.getElementById('propertyThumbnails');
                                thumbnailsDiv.innerHTML = '';
                                images.forEach((img, index) => {
                                    const thumb = document.createElement('div');
                                    thumb.className = 'property-thumbnail' + (index === 0 ? ' active' : '');
                                    thumb.innerHTML = `<img src="../uploads/properties/${img}" alt="Thumbnail ${index + 1}">`;
                                    thumb.onclick = function() {
                                        mainImageDiv.innerHTML = `<img src="../uploads/properties/${img}" alt="${property.title}">`;
                                        document.querySelectorAll('.property-thumbnail').forEach(t => t.classList.remove('active'));
                                        this.classList.add('active');
                                    };
                                    thumbnailsDiv.appendChild(thumb);
                                });
                            } else {
                                mainImageDiv.innerHTML = '<i class="fas fa-home" style="font-size: 72px; color: var(--gray);"></i>';
                                document.getElementById('propertyThumbnails').innerHTML = '';
                            }

                            // Update property info grid
                            const infoGrid = document.getElementById('propertyInfoGrid');
                            infoGrid.innerHTML = `
                                <div class="info-card">
                                    <i class="fas fa-bed"></i>
                                    <h4>${property.bedrooms || 'N/A'}</h4>
                                    <p>Bedrooms</p>
                                </div>
                                <div class="info-card">
                                    <i class="fas fa-bath"></i>
                                    <h4>${property.bathrooms || 'N/A'}</h4>
                                    <p>Bathrooms</p>
                                </div>
                                <div class="info-card">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                    <h4>${property.square_meters || 'N/A'} m²</h4>
                                    <p>Square Meters</p>
                                </div>
                                <div class="info-card">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <h4>R${Number(property.rent_amount).toLocaleString()}</h4>
                                    <p>Monthly Rent</p>
                                </div>
                            `;

                            // Update description
                            document.getElementById('propertyDescription').textContent = property.description || 'No description available.';

                            // Update location
                            document.getElementById('propertyLocation').innerHTML = `
                                <p><i class="fas fa-map-marker-alt"></i> ${property.address || 'N/A'}</p>
                                <p><i class="fas fa-city"></i> ${property.city || 'N/A'}, ${property.state || 'N/A'}</p>
                                <p><i class="fas fa-mail-bulk"></i> ${property.postal_code || 'N/A'}</p>
                            `;

                            // Update amenities
                            if (amenities && amenities.length > 0) {
                                document.getElementById('amenitiesSection').style.display = 'block';
                                const amenitiesDiv = document.getElementById('propertyAmenities');
                                amenitiesDiv.innerHTML = '';
                                amenities.forEach(amenity => {
                                    const amenityItem = document.createElement('div');
                                    amenityItem.className = 'amenity-item';
                                    amenityItem.innerHTML = `
                                        <i class="fas fa-${amenity.icon || 'check-circle'}"></i>
                                        <span>${amenity.name}</span>
                                    `;
                                    amenitiesDiv.appendChild(amenityItem);
                                });
                            } else {
                                document.getElementById('amenitiesSection').style.display = 'none';
                            }

                            // Update landlord info
                            document.getElementById('landlordInfo').innerHTML = `
                                <h4>Contact Information</h4>
                                <p><i class="fas fa-user"></i> ${property.landlord_name || 'N/A'}</p>
                                <p><i class="fas fa-envelope"></i> ${property.landlord_email || 'N/A'}</p>
                                ${property.landlord_phone ? `<p><i class="fas fa-phone"></i> ${property.landlord_phone}</p>` : ''}
                            `;

                            // Show modal
                            modal.style.display = 'block';
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to load property details.'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while loading property details.'
                        });
                    });
                });
            });
            
            // Withdraw button functionality
            document.querySelectorAll('.btn-withdraw').forEach(button => {
                button.addEventListener('click', function() {
                    const applicationItem = this.closest('.application-item');
                    const propertyTitle = applicationItem.querySelector('h3').textContent;
                    const applicationId = applicationItem.dataset.applicationId;

                    Swal.fire({
                        title: 'Withdraw Application',
                        text: `Are you sure you want to withdraw your application for "${propertyTitle}"?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, withdraw it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Disable button and show loading state
                            this.disabled = true;
                            const originalHTML = this.innerHTML;
                            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Withdrawing...';

                            // Send AJAX request
                            const formData = new FormData();
                            formData.append('withdraw_application', '1');
                            formData.append('application_id', applicationId);

                            fetch(window.location.href, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                const contentType = response.headers.get('content-type');
                                if (!contentType || !contentType.includes('application/json')) {
                                    throw new Error('Invalid response format');
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    // Update UI on success
                                    this.remove();
                                    applicationItem.querySelector('.status').textContent = 'Withdrawn';
                                    applicationItem.querySelector('.status').className = 'status withdrawn';
                                    applicationItem.setAttribute('data-status', 'withdrawn');

                                    // Show success message
                                    Swal.fire({
                                        title: 'Withdrawn!',
                                        text: 'Your application has been withdrawn successfully.',
                                        icon: 'success',
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: data.message || 'Failed to withdraw application.',
                                        icon: 'error'
                                    });
                                    this.disabled = false;
                                    this.innerHTML = originalHTML;
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'An error occurred while withdrawing the application. Please try again.',
                                    icon: 'error'
                                });
                                this.disabled = false;
                                this.innerHTML = originalHTML;
                            });
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>