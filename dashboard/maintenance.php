<?php
session_start();

// Require authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
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

$landlord_id = $_SESSION['user_id'];

// Initialize filter variables
$property_filter = $_GET['property'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base query with parameterized filtering
$maintenance_query = "
    SELECT mr.*, 
           p.title AS property_title, 
           p.address AS property_address,
           CONCAT(u.first_name, ' ', u.last_name) AS tenant_name,
           u.email AS tenant_email,
           u.phone AS tenant_phone
    FROM maintenance_requests mr 
    JOIN properties p ON mr.property_id = p.id 
    JOIN users u ON mr.tenant_id = u.id
    WHERE p.landlord_id = ?
";

$params = [$landlord_id];
$types = "i";

// Add filters
if ($property_filter) {
    $maintenance_query .= " AND p.id = ?";
    $params[] = $property_filter;
    $types .= "i";
}

if ($status_filter) {
    $maintenance_query .= " AND mr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($priority_filter) {
    $maintenance_query .= " AND mr.priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}

if (!empty($search_query)) {
    $maintenance_query .= " AND (mr.title LIKE ? OR mr.description LIKE ? OR p.title LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$maintenance_query .= " ORDER BY mr.created_at DESC";

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $maintenance_query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$maintenance_result = mysqli_stmt_get_result($stmt);

// Get landlord properties for filtering
$properties_query = "SELECT id, title FROM properties WHERE landlord_id = ?";
$stmt_properties = mysqli_prepare($conn, $properties_query);
mysqli_stmt_bind_param($stmt_properties, "i", $landlord_id);
mysqli_stmt_execute($stmt_properties);
$properties_result = mysqli_stmt_get_result($stmt_properties);

// Handle status update
if (isset($_POST['update_status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $update_query = "UPDATE maintenance_requests SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "si", $new_status, $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Maintenance status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating status: " . mysqli_error($conn);
    }
    header("Location: maintenance.php");
    exit();
}

// Handle vendor assignment
if (isset($_POST['assign_vendor'])) {
    $request_id = intval($_POST['request_id']);
    $vendor_name = mysqli_real_escape_string($conn, $_POST['contractor_assigned']);
    $vendor_contact = mysqli_real_escape_string($conn, $_POST['contractor_contact']);
    $cost_estimate = floatval($_POST['estimated_cost']);
    
    $update_query = "UPDATE maintenance_requests 
                    SET contractor_assigned = ?, 
                        contractor_contact = ?,
                        estimated_cost = ?,
                        status = 'assigned'
                    WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ssdi", $vendor_name, $vendor_contact, $cost_estimate, $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Contractor assigned successfully!";
    } else {
        $_SESSION['error_message'] = "Error assigning contractor: " . mysqli_error($conn);
    }
    header("Location: maintenance.php");
    exit();
}

// Handle cost update
if (isset($_POST['update_cost'])) {
    $request_id = intval($_POST['request_id']);
    $actual_cost = floatval($_POST['actual_cost']);
    
    $update_query = "UPDATE maintenance_requests 
                    SET actual_cost = ?
                    WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "di", $actual_cost, $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Actual cost updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating cost: " . mysqli_error($conn);
    }
    header("Location: maintenance.php");
    exit();
}

// Handle notes update
if (isset($_POST['update_notes'])) {
    $request_id = intval($_POST['request_id']);
    $landlord_notes = mysqli_real_escape_string($conn, $_POST['landlord_notes']);
    
    $update_query = "UPDATE maintenance_requests 
                    SET landlord_notes = ?
                    WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "si", $landlord_notes, $request_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Notes updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating notes: " . mysqli_error($conn);
    }
    header("Location: maintenance.php");
    exit();
}

// Calculate statistics
$stats_query = "SELECT 
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS assigned_count,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count
    FROM maintenance_requests mr
    JOIN properties p ON mr.property_id = p.id
    WHERE p.landlord_id = ?";

$stmt_stats = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt_stats, "i", $landlord_id);
mysqli_stmt_execute($stmt_stats);
$stats_result = mysqli_stmt_get_result($stmt_stats);
$stats = mysqli_fetch_assoc($stats_result);

// Function to format date
function formatDate($date) {
    return $date ? date('M j, Y H:i', strtotime($date)) : 'N/A';
}

// Function to format currency
function formatCurrency($amount) {
    return $amount ? 'R' . number_format($amount, 2) : 'N/A';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - L&T Connect</title>
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

        /* Sidebar Styles */
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
            border-bottom: 1px solid #e5e7eb;
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

        /* Alerts */
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

        .stat-card.open { 
            --accent-gradient: linear-gradient(135deg, #8b7bce 0%, #6b5bb0 100%);
            --shadow-color: rgba(139, 123, 206, 0.4);
            --icon-bg: rgba(255, 255, 255, 0.2);
        }

        .stat-card.assigned { 
            --accent-gradient: linear-gradient(135deg, #7ec8c3 0%, #5fb3ad 100%);
            --shadow-color: rgba(126, 200, 195, 0.4);
            --icon-bg: rgba(255, 255, 255, 0.2);
        }

        .stat-card.progress { 
            --accent-gradient: linear-gradient(135deg, #f4a79d 0%, #e8907f 100%);
            --shadow-color: rgba(244, 167, 157, 0.4);
            --icon-bg: rgba(255, 255, 255, 0.2);
        }

        .stat-card.completed { 
            --accent-gradient: linear-gradient(135deg, #6bcf9d 0%, #4fb883 100%);
            --shadow-color: rgba(107, 207, 157, 0.4);
            --icon-bg: rgba(255, 255, 255, 0.2);
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

        /* Filters */
        .filters {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
        }

        .filter-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #3b82f6;
            color: #3b82f6;
        }

        .btn-outline:hover {
            background: rgba(59, 130, 246, 0.1);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .status-open { background: #ede9fe; color: #6b5bb0; }
        .status-assigned { background: #ccf5f2; color: #5fb3ad; }
        .status-in_progress { background: #ffe8e0; color: #e8907f; }
        .status-completed { background: #d1f4e0; color: #4fb883; }

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

        /* Empty State */
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
            margin-bottom: 0.5rem;
            color: #475569;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            overflow: hidden;
            margin: auto;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.75rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            transition: opacity 0.2s ease;
        }

        .close-modal:hover {
            opacity: 0.7;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-section {
            margin-bottom: 30px;
        }

        .modal-section:last-child {
            margin-bottom: 0;
        }

        .modal-section h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
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
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .notes-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }

        .notes-textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .action-form {
            margin-bottom: 20px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* Image Modal Specific Styles */
        #imageModal .modal-content {
            max-width: 80vw;
            max-height: 80vh;
        }

        #imageModal .modal-body {
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #modalImage {
            width: 100%;
            height: auto;
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 8px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .maintenance-table {
                font-size: 13px;
            }

            .request-actions {
                flex-direction: column;
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
                max-width: 100%;
            }
            
            .mobile-menu-btn {
                display: block;
            }

            /* Make table scrollable on tablet */
            .table-container {
                overflow-x: auto;
            }

            .maintenance-table {
                min-width: 1000px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .filter-row {
                flex-direction: column;
            }

            .modal-content {
                max-width: 95vw;
                margin: 10px;
            }

            .modal-header {
                padding: 1rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .action-form {
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                justify-content: center;
            }

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
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="../logo.png" alt="L&T Connect" style="max-height: 42px; width: auto; display: block; margin-bottom: 0.75rem;">
            <p>Landlord Portal</p>
        </div>
        <ul>
            <li><a href="../index.php" class="home-button"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="profile_landlord.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="landlord_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="my_properties.php"><i class="fas fa-building"></i> My Properties</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="add_property.php"><i class="fas fa-plus-circle"></i> Add Property</a></li>
            <li><a href="maintenance.php" class="active"><i class="fas fa-tools"></i> Maintenance</a></li>
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
                <i class="fas fa-tools"></i>
                Maintenance Requests
            </h1>
            <div class="landlord-info">
                <span>Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Landlord'); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?></div>
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

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card open">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['open_count'] ?? 0; ?></div>
                        <div class="stat-label">Open Requests</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card assigned">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['assigned_count'] ?? 0; ?></div>
                        <div class="stat-label">Assigned</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card progress">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['in_progress_count'] ?? 0; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card completed">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['completed_count'] ?? 0; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="property">Property</label>
                    <select id="property" name="property" class="filter-control">
                        <option value="">All Properties</option>
                        <?php 
                        if ($properties_result) {
                            mysqli_data_seek($properties_result, 0);
                            while ($property = mysqli_fetch_assoc($properties_result)): 
                        ?>
                            <?php $selected = $property['id'] == $property_filter ? 'selected' : ''; ?>
                            <option value="<?php echo $property['id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($property['title']); ?>
                            </option>
                        <?php endwhile; } ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="filter-control">
                        <option value="">All Statuses</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" class="filter-control">
                        <option value="">All Priorities</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" class="filter-control" 
                           placeholder="Search by title, description..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn btn-outline" onclick="resetFilters()">
                    <i class="fas fa-sync"></i>
                    Reset Filters
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Apply Filters
                </button>
            </div>
        </form>

        <!-- Requests Table -->
        <?php if ($maintenance_result && mysqli_num_rows($maintenance_result) > 0): ?>
            <div class="table-container">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-tools"></i>
                        Maintenance Requests
                    </h2>
                    <span class="requests-count">
                        <?php echo mysqli_num_rows($maintenance_result); ?> Request(s) Found
                    </span>
                </div>
                <table class="maintenance-table">
                    <thead>
                        <tr>
                            <th>Request Details</th>
                            <th>Property</th>
                            <th>Tenant</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Reported Date</th>
                            <th>Images</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = mysqli_fetch_assoc($maintenance_result)): 
                            // Get images for this request
                            $images_query = "SELECT * FROM maintenance_images 
                                            WHERE maintenance_request_id = {$request['id']}";
                            $images_result = mysqli_query($conn, $images_query);
                            $images = [];
                            if ($images_result) {
                                while ($image = mysqli_fetch_assoc($images_result)) {
                                    $images[] = $image['image_path'];
                                }
                            }
                        ?>
                            <tr>
                                <td>
                                    <div class="request-info">
                                        <div class="request-title"><?php echo htmlspecialchars($request['title']); ?></div>
                                        <div class="request-category">
                                            <i class="fas fa-tag"></i>
                                            <?php echo ucfirst($request['category'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="property-info">
                                        <div class="property-title"><?php echo htmlspecialchars($request['property_title']); ?></div>
                                        <div class="property-address">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($request['property_address']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['tenant_name']); ?></strong>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo htmlspecialchars($request['tenant_email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="priority-<?php echo strtolower($request['priority']); ?>">
                                        <span class="priority-indicator"></span>
                                        <?php echo ucfirst($request['priority']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
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
                                                <div class="request-image" onclick="openImageModal('<?php echo htmlspecialchars($image); ?>')">
                                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Maintenance image">
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
                                        <button class="btn btn-primary btn-sm" onclick="openDetailsModal(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="openManageModal(<?php echo $request['id']; ?>)">
                                            <i class="fas fa-cog"></i> Manage
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
                    <i class="fas fa-check-circle"></i>
                    <h3>No Maintenance Requests</h3>
                    <p>You don't have any maintenance requests at this time.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modals -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="detailsModalTitle">Request Details</h3>
                <button class="close-modal" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="detailsModalBody"></div>
        </div>
    </div>

    <div id="manageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="manageModalTitle">Manage Request</h3>
                <button class="close-modal" onclick="closeManageModal()">&times;</button>
            </div>
            <div class="modal-body" id="manageModalBody"></div>
        </div>
    </div>

    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Maintenance Photo</h3>
                <button class="close-modal" onclick="closeImageModal()">&times;</button>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="Maintenance Photo" style="width: 100%; border-radius: 8px;">
            </div>
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

            document.addEventListener('click', (e) => {
                if (window.innerWidth < 900 && 
                    sidebar.classList.contains('active') && 
                    !sidebar.contains(e.target) && 
                    !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }

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

        const maintenanceRequests = <?php 
            $requests = [];
            if ($maintenance_result && mysqli_num_rows($maintenance_result) > 0) {
                mysqli_data_seek($maintenance_result, 0);
                while ($request = mysqli_fetch_assoc($maintenance_result)) {
                    $images_query = "SELECT image_path FROM maintenance_images 
                                    WHERE maintenance_request_id = {$request['id']}";
                    $images_result = mysqli_query($conn, $images_query);
                    $images = [];
                    if ($images_result) {
                        while ($image = mysqli_fetch_assoc($images_result)) {
                            $images[] = $image['image_path'];
                        }
                    }
                    
                    $requests[$request['id']] = [
                        'id' => $request['id'],
                        'title' => $request['title'],
                        'status' => $request['status'],
                        'property' => $request['property_title'],
                        'tenant' => $request['tenant_name'],
                        'reported_date' => formatDate($request['reported_date']),
                        'priority' => $request['priority'],
                        'category' => $request['category'] ?? 'N/A',
                        'description' => $request['description'],
                        'contractor_assigned' => $request['contractor_assigned'] ?? '',
                        'contractor_contact' => $request['contractor_contact'] ?? '',
                        'estimated_cost' => $request['estimated_cost'] ?? '',
                        'actual_cost' => $request['actual_cost'] ?? '',
                        'landlord_notes' => $request['landlord_notes'] ?? '',
                        'tenant_rating' => $request['tenant_rating'] ?? '',
                        'tenant_feedback' => $request['tenant_feedback'] ?? '',
                        'acknowledged_date' => formatDate($request['acknowledged_date'] ?? ''),
                        'started_date' => formatDate($request['started_date'] ?? ''),
                        'completed_date' => formatDate($request['completed_date'] ?? ''),
                        'created_at' => formatDate($request['created_at']),
                        'updated_at' => formatDate($request['updated_at']),
                        'images' => $images
                    ];
                }
            }
            echo json_encode($requests);
        ?>;

        function openDetailsModal(requestId) {
            const request = maintenanceRequests[requestId];
            if (!request) return;

            const modal = document.getElementById('detailsModal');
            const title = document.getElementById('detailsModalTitle');
            const body = document.getElementById('detailsModalBody');

            title.textContent = request.title;
            
            let imagesHTML = '';
            if (request.images.length > 0) {
                imagesHTML = `
                    <div class="modal-section">
                        <h4>Attached Images</h4>
                        <div class="request-images" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            ${request.images.map(img => `
                                <div class="request-image" style="width: 100%; height: 100px;" onclick="openImageModal('${img}')">
                                    <img src="${img}" alt="Maintenance photo">
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            body.innerHTML = `
                <div class="modal-section">
                    <h4>Request Information</h4>
                    <div class="details-grid">
                        <div class="detail-group">
                            <span class="detail-label">Request ID</span>
                            <span class="detail-value">${request.id}</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">
                                <span class="status-badge status-${request.status}">
                                    ${request.status.charAt(0).toUpperCase() + request.status.slice(1).replace('_', ' ')}
                                </span>
                            </span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Property</span>
                            <span class="detail-value">${request.property}</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Tenant</span>
                            <span class="detail-value">${request.tenant}</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Reported Date</span>
                            <span class="detail-value">${request.reported_date}</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Priority</span>
                            <span class="detail-value">${request.priority}</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Category</span>
                            <span class="detail-value">${request.category || 'N/A'}</span>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <h4>Description</h4>
                    <div class="request-description" style="background: #f1f5f9; border-radius: 8px; padding: 1.25rem; color: #334155; line-height: 1.7;">
                        ${request.description.replace(/\n/g, '<br>')}
                    </div>
                </div>

                ${request.contractor_assigned ? `
                <div class="modal-section">
                    <h4>Contractor Information</h4>
                    <div class="details-grid">
                        <div class="detail-group">
                            <span class="detail-label">Contractor</span>
                            <span class="detail-value">${request.contractor_assigned}</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Contact</span>
                            <span class="detail-value">${request.contractor_contact}</span>
                        </div>
                        <div class="detail-group">
                            <span class="detail-label">Estimated Cost</span>
                            <span class="detail-value">R${parseFloat(request.estimated_cost).toFixed(2)}</span>
                        </div>
                        ${request.actual_cost ? `
                        <div class="detail-group">
                            <span class="detail-label">Actual Cost</span>
                            <span class="detail-value">R${parseFloat(request.actual_cost).toFixed(2)}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                ${request.landlord_notes ? `
                <div class="modal-section">
                    <h4>Landlord Notes</h4>
                    <div class="request-description" style="background: #f1f5f9; border-radius: 8px; padding: 1.25rem; color: #334155; line-height: 1.7;">
                        ${request.landlord_notes.replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}

                ${imagesHTML}
            `;

            modal.style.display = 'flex';
        }

        function openManageModal(requestId) {
            const request = maintenanceRequests[requestId];
            if (!request) return;

            const modal = document.getElementById('manageModal');
            const title = document.getElementById('manageModalTitle');
            const body = document.getElementById('manageModalBody');

            title.textContent = `Manage: ${request.title}`;
            
            body.innerHTML = `
                <div class="modal-section">
                    <h4>Update Status</h4>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="request_id" value="${request.id}">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="open" ${request.status === 'open' ? 'selected' : ''}>Open</option>
                                <option value="assigned" ${request.status === 'assigned' ? 'selected' : ''}>Assigned</option>
                                <option value="in_progress" ${request.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                <option value="completed" ${request.status === 'completed' ? 'selected' : ''}>Completed</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fas fa-sync"></i>
                                Update Status
                            </button>
                        </div>
                    </form>
                </div>

                <div class="modal-section">
                    <h4>Contractor Assignment</h4>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="request_id" value="${request.id}">
                        <div class="form-group">
                            <label for="contractor_assigned">Contractor Name</label>
                            <input type="text" name="contractor_assigned" class="form-control" 
                                   placeholder="Enter contractor name" required
                                   value="${request.contractor_assigned || ''}">
                        </div>
                        <div class="form-group">
                            <label for="contractor_contact">Contractor Contact</label>
                            <input type="text" name="contractor_contact" class="form-control" 
                                   placeholder="Enter contact info" required
                                   value="${request.contractor_contact || ''}">
                        </div>
                        <div class="form-group">
                            <label for="estimated_cost">Estimated Cost (R)</label>
                            <input type="number" name="estimated_cost" class="form-control" 
                                   placeholder="Enter estimate" step="0.01" min="0" required
                                   value="${request.estimated_cost || ''}">
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="assign_vendor" class="btn btn-warning">
                                <i class="fas fa-user-tie"></i>
                                Assign Contractor
                            </button>
                        </div>
                    </form>
                </div>

                <div class="modal-section">
                    <h4>Cost Management</h4>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="request_id" value="${request.id}">
                        <div class="form-group">
                            <label for="actual_cost">Actual Cost (R)</label>
                            <input type="number" name="actual_cost" class="form-control" 
                                   placeholder="Enter actual cost" step="0.01" min="0"
                                   value="${request.actual_cost || ''}">
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update_cost" class="btn btn-success">
                                <i class="fas fa-money-bill-wave"></i>
                                Update Cost
                            </button>
                        </div>
                    </form>
                </div>

                <div class="modal-section">
                    <h4>Landlord Notes</h4>
                    <form method="POST" class="action-form">
                        <input type="hidden" name="request_id" value="${request.id}">
                        <div class="form-group">
                            <label for="landlord_notes">Notes</label>
                            <textarea name="landlord_notes" class="notes-textarea" 
                                      placeholder="Add notes about this maintenance request">${request.landlord_notes || ''}</textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update_notes" class="btn btn-primary">
                                <i class="fas fa-edit"></i>
                                Update Notes
                            </button>
                        </div>
                    </form>
                </div>
            `;

            modal.style.display = 'flex';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        function closeManageModal() {
            document.getElementById('manageModal').style.display = 'none';
        }

        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            modal.style.display = 'flex';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        function resetFilters() {
            document.getElementById('property').value = '';
            document.getElementById('status').value = '';
            document.getElementById('priority').value = '';
            document.getElementById('search').value = '';
            document.querySelector('.filters').submit();
        }

        window.onclick = function(event) {
            const modals = ['detailsModal', 'manageModal', 'imageModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDetailsModal();
                closeManageModal();
                closeImageModal();
            }
        });

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
    </script>
</body>
</html>