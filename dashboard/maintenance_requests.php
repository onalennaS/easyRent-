<?php
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

// Handle new maintenance request submission
if (isset($_POST['submit_maintenance'])) {
    $property_id = intval($_POST['property_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    // Get landlord ID for the property
    $landlord_query = "SELECT landlord_id FROM properties WHERE id = $property_id";
    $landlord_result = mysqli_query($conn, $landlord_query);
    
    if ($landlord_result && mysqli_num_rows($landlord_result)) {
        $landlord_data = mysqli_fetch_assoc($landlord_result);
        $landlord_id = $landlord_data['landlord_id'];
    } else {
        $landlord_id = 0;
    }
    
    $insert_query = "INSERT INTO maintenance_requests (tenant_id, property_id, landlord_id, title, description, priority, category, status, reported_date, created_at) 
                     VALUES ($tenant_id, $property_id, $landlord_id, '$title', '$description', '$priority', '$category', 'open', NOW(), NOW())";
    
    if (mysqli_query($conn, $insert_query)) {
        $request_id = mysqli_insert_id($conn);
        $_SESSION['success_message'] = "Maintenance request submitted successfully!";
        
        // Handle image uploads
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = "../uploads/maintenance/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $image_count = count($_FILES['images']['name']);
            for ($i = 0; $i < $image_count; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['images']['tmp_name'][$i];
                    $name = basename($_FILES['images']['name'][$i]);
                    $upload_path = $upload_dir . uniqid() . "_" . $name;
                    
                    if (move_uploaded_file($tmp_name, $upload_path)) {
                        $insert_image = "INSERT INTO maintenance_images (maintenance_request_id, image_path, uploaded_by, created_at)
                                        VALUES ($request_id, '$upload_path', $tenant_id, NOW())";
                        mysqli_query($conn, $insert_image);
                    }
                }
            }
        }
    } else {
        $_SESSION['error_message'] = "Error submitting maintenance request: " . mysqli_error($conn);
    }
    header("Location: maintenance_requests.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = ["mr.tenant_id = $tenant_id"];

if ($status_filter !== 'all') {
    $where_conditions[] = "mr.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "mr.priority = '" . mysqli_real_escape_string($conn, $priority_filter) . "'";
}

if (!empty($search_query)) {
    $search_escaped = mysqli_real_escape_string($conn, $search_query);
    $where_conditions[] = "(mr.title LIKE '%$search_escaped%' OR mr.description LIKE '%$search_escaped%' OR p.title LIKE '%$search_escaped%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Get tenant's maintenance requests
$requests_query = "SELECT mr.*, p.title AS property_title, p.address,
                  CONCAT(u.first_name, ' ', u.last_name) AS landlord_name
                  FROM maintenance_requests mr
                  JOIN properties p ON mr.property_id = p.id
                  JOIN users u ON mr.landlord_id = u.id
                  WHERE $where_clause
                  ORDER BY mr.reported_date DESC";
$requests_result = mysqli_query($conn, $requests_query);

// Get count of requests by status
$status_counts = [
    'all' => 0,
    'open' => 0,
    'in-progress' => 0,
    'completed' => 0
];

$count_query = "SELECT status, COUNT(*) as count FROM maintenance_requests WHERE tenant_id = $tenant_id GROUP BY status";
$count_result = mysqli_query($conn, $count_query);
if ($count_result) {
    while ($row = mysqli_fetch_assoc($count_result)) {
        $status_counts[$row['status']] = $row['count'];
        $status_counts['all'] += $row['count'];
    }
}

// Get tenant's properties (for new request form)
$properties_query = "SELECT p.id, p.title, p.address 
                    FROM properties p
                    JOIN rental_applications ra ON p.id = ra.property_id
                    WHERE ra.tenant_id = $tenant_id AND ra.status = 'approved'
                    ORDER BY p.title";
$properties_result = mysqli_query($conn, $properties_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests - EasyRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
        }

        /* Alert styling */
        .alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            max-width: 600px;
            z-index: 1050;
            opacity: 1;
            transition: opacity 0.5s ease;
            padding: 15px;
            border-radius: 5px;
            font-weight: 500;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            text-align: center;
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

        /* Sidebar styles */
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

        /* Main content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #ddd;
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

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-header h3 {
            font-size: 1.2rem;
            color: #333;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filter-tab {
            padding: 8px 16px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #666;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-tab:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #764ba2;
        }

        .filter-tab .badge {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .filter-tab.active .badge {
            background: rgba(255,255,255,0.3);
        }

        .search-box {
            display: flex;
            gap: 10px;
            max-width: 500px;
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box button {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .search-box button:hover {
            transform: translateY(-2px);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .requests-count {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 14px;
        }

        /* Table Styles */
        .maintenance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .maintenance-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .maintenance-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .maintenance-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .maintenance-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .maintenance-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .maintenance-table tbody tr:last-child td {
            border-bottom: none;
        }

        .request-info {
            display: flex;
            flex-direction: column;
        }

        .request-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .request-category {
            font-size: 13px;
            color: #666;
        }

        .property-info {
            display: flex;
            flex-direction: column;
        }

        .property-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .property-address {
            font-size: 13px;
            color: #666;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-open {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-in-progress {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .priority-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
        }

        .priority-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .priority-high .priority-indicator {
            background: #dc3545;
        }

        .priority-medium .priority-indicator {
            background: #ffc107;
        }

        .priority-low .priority-indicator {
            background: #28a745;
        }

        .request-images {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .request-image {
            width: 60px;
            height: 50px;
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .request-image:hover {
            transform: scale(1.05);
        }

        .request-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .request-actions {
            display: flex;
            gap: 5px;
            flex-direction: column;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background-color: #138496;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            color: #ccc;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #333;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
            border-radius: 10px 10px 0 0;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .close-modal:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .image-preview {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .preview-item {
            position: relative;
            width: 80px;
            height: 60px;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-remove {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            color: #dc3545;
            transition: all 0.3s;
        }

        .preview-remove:hover {
            background: #fff;
            transform: scale(1.1);
        }

        .image-upload {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .file-label {
            padding: 8px 15px;
            background: #e9ecef;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
        }

        .file-label:hover {
            background: #dee2e6;
        }

        .file-input {
            display: none;
        }

        /* View Details Modal Styles */
        .detail-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .detail-label {
            width: 150px;
            font-weight: 600;
            color: #2c3e50;
        }

        .detail-value {
            flex: 1;
            color: #495057;
        }

        .detail-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .detail-image {
            border-radius: 8px;
            overflow: hidden;
            height: 120px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .detail-image:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .detail-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Image modal */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1200;
            align-items: center;
            justify-content: center;
        }
        
        .image-modal-content {
            max-width: 90%;
            max-height: 90%;
        }
        
        .image-modal-content img {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 5px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
        }
        
        .close-image-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .close-image-modal:hover {
            color: #667eea;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .maintenance-table {
                font-size: 13px;
            }

            .request-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }

            .filter-tabs {
                justify-content: center;
            }

            .search-box {
                max-width: 100%;
            }

            /* Make table scrollable on mobile */
            .table-container {
                overflow-x: auto;
            }

            .maintenance-table {
                min-width: 900px;
            }

            .request-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .detail-label {
                width: 100%;
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
                padding: 15px;
            }

            .page-title {
                font-size: 1.3rem;
            }

            .filter-section {
                padding: 15px;
            }

            .table-header {
                padding: 15px;
            }

            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .modal-content {
                max-height: 85vh;
            }
        }

        .mobile-menu-toggle {
            display: none;
        }

        @media (max-width: 600px) {
            .mobile-menu-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: #667eea;
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
        }
    </style>
</head>
<body>
    <div class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2><i class="fas fa-home"></i> EasyRent</h2>
            <p>Tenant Portal</p>
        </div>
        <ul class="nav-links">
            <li><a href="../index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
            <li><a href="tenant_profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
            <li><a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
            <li><a href="browse_properties.php"><i class="fas fa-search"></i> <span>Browse Properties</span></a></li>
            <li><a href="my_applications.php"><i class="fas fa-file-alt"></i> <span>My Applications</span></a></li>
            <li><a href="my_lease.php"><i class="fas fa-file-contract"></i> <span>My Lease</span></a></li>
            <li><a href="maintenance_requests.php" class="active"><i class="fas fa-tools"></i> <span>Maintenance</span></a></li>
            <li><a href="payment_history.php"><i class="fas fa-credit-card"></i> <span>Payments</span></a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-tools"></i>
                My Maintenance Requests
            </h1>
            <div class="tenant-info">
                <span>Hello, <?php echo htmlspecialchars($tenant_name); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($tenant_name ?? 'T', 0, 1)); ?></div>
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

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <h3><i class="fas fa-filter"></i> Filter Requests</h3>
                <button class="btn btn-primary" onclick="openRequestModal()">
                    <i class="fas fa-plus"></i> New Request
                </button>
            </div>

            <!-- Status Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All Requests
                    <span class="badge"><?php echo $status_counts['all']; ?></span>
                </a>
                <a href="?status=open<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                    <i class="fas fa-folder-open"></i> Open
                    <span class="badge"><?php echo $status_counts['open']; ?></span>
                </a>
                <a href="?status=in-progress<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'in-progress' ? 'active' : ''; ?>">
                    <i class="fas fa-spinner"></i> In Progress
                    <span class="badge"><?php echo $status_counts['in-progress']; ?></span>
                </a>
                <a href="?status=completed<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Completed
                    <span class="badge"><?php echo $status_counts['completed']; ?></span>
                </a>
            </div>

            <!-- Search Box -->
            <form method="GET" action="" class="search-box">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <input type="text" 
                       name="search" 
                       placeholder="Search by title, description, or property..." 
                       value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search_query)): ?>
                    <a href="?status=<?php echo htmlspecialchars($status_filter); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Requests Container -->
        <?php if ($requests_result && mysqli_num_rows($requests_result) > 0): ?>
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        Maintenance Requests
                    </h3>
                    <span class="requests-count">
                        <?php echo mysqli_num_rows($requests_result); ?> Request(s) Found
                    </span>
                </div>
                <table class="maintenance-table">
                    <thead>
                        <tr>
                            <th>Request Details</th>
                            <th>Property</th>
                            <th>Landlord</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported Date</th>
                            <th>Images</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = mysqli_fetch_assoc($requests_result)): 
                            // Get images for this request
                            $images_query = "SELECT * FROM maintenance_images 
                                            WHERE maintenance_request_id = {$request['id']}";
                            $images_result = mysqli_query($conn, $images_query);
                            $images = [];
                            if ($images_result) {
                                while ($image = mysqli_fetch_assoc($images_result)) {
                                    $images[] = $image;
                                }
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="request-info">
                                        <div class="request-title"><?php echo htmlspecialchars($request['title']); ?></div>
                                        <div class="request-category">
                                            <i class="fas fa-tag"></i>
                                            <?php echo ucfirst($request['category']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="property-info">
                                        <div class="property-title"><?php echo htmlspecialchars($request['property_title']); ?></div>
                                        <div class="property-address">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($request['address']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['landlord_name']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="priority-<?php echo strtolower($request['priority']); ?>">
                                        <span class="priority-indicator"></span>
                                        <?php echo ucfirst($request['priority']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($request['status']); ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $request['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <?php echo date('M j, Y', strtotime($request['reported_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($images)): ?>
                                        <div class="request-images">
                                            <?php foreach ($images as $index => $image): ?>
                                                <div class="request-image" onclick="expandImage('<?php echo htmlspecialchars($image['image_path']); ?>')">
                                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" alt="Maintenance image">
                                                </div>
                                                <?php if ($index === 1) break; ?>
                                            <?php endforeach; ?>
                                            <?php if (count($images) > 2): ?>
                                                <div class="request-image" style="background: #e9ecef; display: flex; align-items: center; justify-content: center; color: #6c757d; font-weight: bold; font-size: 11px;">
                                                    +<?php echo count($images) - 2; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">No images</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="request-actions">
                                        <button class="btn btn-sm btn-info view-details-btn" data-id="<?php echo $request['id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="empty-state">
                    <i class="fas fa-tools"></i>
                    <h3>No Maintenance Requests Found</h3>
                    <?php if (!empty($search_query) || $status_filter !== 'all'): ?>
                        <p>No requests match your current filters. Try adjusting your search or filter criteria.</p>
                        <a href="maintenance_requests.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-redo"></i> Clear All Filters
                        </a>
                    <?php else: ?>
                        <p>You haven't submitted any maintenance requests yet.</p>
                        <button class="btn btn-primary" onclick="openRequestModal()" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> Submit Your First Request
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- New Request Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-tools"></i> Submit New Maintenance Request</h3>
                <button class="close-modal" onclick="closeRequestModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="property_id">Property</label>
                        <select id="property_id" name="property_id" class="form-control" required>
                            <option value="">Select Property</option>
                            <?php 
                            // Reset the pointer for properties result
                            if ($properties_result) {
                                mysqli_data_seek($properties_result, 0);
                                if (mysqli_num_rows($properties_result) > 0): 
                            ?>
                                <?php while ($property = mysqli_fetch_assoc($properties_result)): ?>
                                    <option value="<?php echo $property['id']; ?>">
                                        <?php echo htmlspecialchars($property['title'] . ' - ' . $property['address']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="" disabled>No properties available</option>
                            <?php endif; 
                            } ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Issue Title</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               placeholder="Briefly describe the issue" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Detailed Description</label>
                        <textarea id="description" name="description" class="form-control form-textarea" 
                                  placeholder="Please describe the issue in detail..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="plumbing">Plumbing</option>
                            <option value="electrical">Electrical</option>
                            <option value="appliance">Appliance</option>
                            <option value="heating">Heating/Cooling</option>
                            <option value="structural">Structural</option>
                            <option value="pest">Pest Control</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control" required>
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Images (Optional)</label>
                        <div class="image-upload">
                            <label class="file-label">
                                <i class="fas fa-cloud-upload-alt"></i> Choose Files
                                <input type="file" name="images[]" class="file-input" multiple accept="image/*" onchange="previewImages(event)">
                            </label>
                            <span id="file-count">No files selected</span>
                        </div>
                        <div class="image-preview" id="image-preview"></div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-outline" onclick="closeRequestModal()">
                            Cancel
                        </button>
                        <button type="submit" name="submit_maintenance" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-tools"></i> Maintenance Request Details</h3>
                <button class="close-modal" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be loaded here via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close-image-modal">&times;</span>
        <div class="image-modal-content">
            <img id="expandedImg" src="" alt="Expanded Image">
        </div>
    </div>

    <script>
        // Modal functions
        function openRequestModal() {
            document.getElementById('requestModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeRequestModal() {
            document.getElementById('requestModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('image-preview').innerHTML = '';
            document.getElementById('file-count').textContent = 'No files selected';
        }
        
        function openDetailsModal() {
            document.getElementById('detailsModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Image preview functionality
        function previewImages(event) {
            const previewContainer = document.getElementById('image-preview');
            const fileCount = document.getElementById('file-count');
            const files = event.target.files;
            
            previewContainer.innerHTML = '';
            
            if (files.length > 0) {
                fileCount.textContent = files.length + ' file(s) selected';
                
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'preview-item';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Preview';
                        
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'preview-remove';
                        removeBtn.innerHTML = '&times;';
                        removeBtn.onclick = function() {
                            previewContainer.removeChild(previewItem);
                            const dt = new DataTransfer();
                            const input = event.target;
                            
                            for (let j = 0; j < input.files.length; j++) {
                                if (j !== i) {
                                    dt.items.add(input.files[j]);
                                }
                            }
                            
                            input.files = dt.files;
                            fileCount.textContent = input.files.length + ' file(s) selected';
                        };
                        
                        previewItem.appendChild(img);
                        previewItem.appendChild(removeBtn);
                        previewContainer.appendChild(previewItem);
                    };
                    
                    reader.readAsDataURL(file);
                }
            } else {
                fileCount.textContent = 'No files selected';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const requestModal = document.getElementById('requestModal');
            if (event.target === requestModal) {
                closeRequestModal();
            }
            
            const detailsModal = document.getElementById('detailsModal');
            if (event.target === detailsModal) {
                closeDetailsModal();
            }
            
            const imageModal = document.getElementById('imageModal');
            if (event.target === imageModal) {
                closeImageModal();
            }
        };

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
            
            // Mobile menu toggle
            const menuToggle = document.querySelector('.mobile-menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            // View details button click
            document.querySelectorAll('.view-details-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const requestId = this.getAttribute('data-id');
                    loadRequestDetails(requestId);
                });
            });
        });
        
        // Load request details
        function loadRequestDetails(requestId) {
            fetch(`get_request_details.php?id=${requestId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    const detailsContent = document.getElementById('detailsContent');
                    let html = `
                        <div class="detail-row">
                            <div class="detail-label">Title:</div>
                            <div class="detail-value">${escapeHTML(data.title)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Property:</div>
                            <div class="detail-value">${escapeHTML(data.property_title)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Address:</div>
                            <div class="detail-value">${escapeHTML(data.address)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Landlord:</div>
                            <div class="detail-value">${escapeHTML(data.landlord_name)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value"><span class="status-badge status-${data.status.toLowerCase()}">${data.status}</span></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Priority:</div>
                            <div class="detail-value"><span class="priority-${data.priority.toLowerCase()}">${data.priority} Priority</span></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Reported Date:</div>
                            <div class="detail-value">${escapeHTML(data.reported_date)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Created At:</div>
                            <div class="detail-value">${escapeHTML(data.created_at)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Last Updated:</div>
                            <div class="detail-value">${escapeHTML(data.updated_at)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Category:</div>
                            <div class="detail-value">${escapeHTML(data.category)}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Description:</div>
                            <div class="detail-value">${escapeHTML(data.description)}</div>
                        </div>
                    `;
                    
                    if (data.images && data.images.length > 0) {
                        html += `<div class="detail-row">
                            <div class="detail-label">Images:</div>
                            <div class="detail-value">
                                <div class="detail-images">`;
                            
                        data.images.forEach(image => {
                            html += `<div class="detail-image" onclick="expandImage('${escapeHTML(image.image_path)}')">
                                <img src="${escapeHTML(image.image_path)}" alt="Maintenance image">
                            </div>`;
                        });
                        
                        html += `</div></div></div>`;
                    }
                    
                    detailsContent.innerHTML = html;
                    openDetailsModal();
                })
                .catch(error => {
                    console.error('Error loading request details:', error);
                    alert('Failed to load request details. Please try again.');
                });
        }

        // Helper function to escape HTML
        function escapeHTML(str) {
            if (!str) return '';
            return str.toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
                .replace(/\n/g, '<br>');
        }
        
        // Image expansion functions
        function expandImage(src) {
            document.getElementById('expandedImg').src = src;
            document.getElementById('imageModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close image modal with escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeImageModal();
            }
        });
        
        // Close image modal with X button
        document.querySelector('.close-image-modal').addEventListener('click', closeImageModal);
    </script>
</body>
</html>

<?php
mysqli_close($conn); 
?>