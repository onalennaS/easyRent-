<?php
// my_lease.php - My Lease Page

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

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the WHERE clause based on filters
$where_conditions = ["l.tenant_id = $tenant_id"];

if ($status_filter !== 'all') {
    $where_conditions[] = "l.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

if (!empty($search_query)) {
    $search_escaped = mysqli_real_escape_string($conn, $search_query);
    $where_conditions[] = "(p.title LIKE '%$search_escaped%' OR p.address LIKE '%$search_escaped%' OR u.first_name LIKE '%$search_escaped%' OR u.last_name LIKE '%$search_escaped%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch lease information for the tenant
$lease_query = "
    SELECT
        l.*,
        p.title AS property_title,
        p.address AS property_address,
        p.description AS property_description,
        p.rent_amount,
        u.first_name AS landlord_first_name,
        u.last_name AS landlord_last_name,
        u.email AS landlord_email,
        u.phone AS landlord_phone
    FROM leases l
    JOIN properties p ON l.property_id = p.id
    JOIN users u ON l.landlord_id = u.id
    WHERE $where_clause
    ORDER BY l.lease_start_date DESC
";

$lease_result = mysqli_query($conn, $lease_query);
$leases = [];
if ($lease_result) {
    while ($row = mysqli_fetch_assoc($lease_result)) {
        $leases[] = $row;
    }
}

// Get count of leases by status
$status_counts = [
    'all' => 0,
    'active' => 0,
    'pending' => 0,
    'expired' => 0,
    'terminated' => 0
];

$count_query = "SELECT status, COUNT(*) as count FROM leases WHERE tenant_id = $tenant_id GROUP BY status";
$count_result = mysqli_query($conn, $count_query);
if ($count_result) {
    while ($row = mysqli_fetch_assoc($count_result)) {
        $status_counts[$row['status']] = $row['count'];
        $status_counts['all'] += $row['count'];
    }
}

// Handle lease signing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sign_lease'])) {
    $lease_id = intval($_POST['lease_id']);
    $signature_data = $_POST['signature'];
    
    // Process signature data
    if (!empty($signature_data)) {
        list($type, $signature_data) = explode(';', $signature_data);
        list(, $signature_data) = explode(',', $signature_data);
        $signature_data = base64_decode($signature_data);
        
        // Create signatures directory if it doesn't exist
        if (!file_exists('signatures')) {
            mkdir('signatures', 0777, true);
        }
        
        // Save signature to file
        $signature_filename = "signatures/tenant_signature_$lease_id.png";
        file_put_contents($signature_filename, $signature_data);
        
        // Update lease in database
        $update_query = "UPDATE leases SET 
                        tenant_signed = 1, 
                        tenant_signature_path = '$signature_filename', 
                        tenant_signed_date = NOW() 
                        WHERE id = $lease_id AND tenant_id = $tenant_id";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success_message'] = "Lease agreement signed successfully!";
            // Refresh lease data
            header("Location: my_lease.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error signing lease: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error_message'] = "Please provide a valid signature";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Lease - EasyRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        .lease-count {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 14px;
        }

        /* Table Styles */
        .lease-table {
            width: 100%;
            border-collapse: collapse;
        }

        .lease-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .lease-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .lease-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .lease-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .lease-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .lease-table tbody tr:last-child td {
            border-bottom: none;
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
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-expired {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-terminated {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .signature-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }

        .signature-status i {
            font-size: 16px;
        }

        .signed {
            color: #28a745;
        }

        .unsigned {
            color: #dc3545;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 12px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background-color: #138496;
            transform: translateY(-2px);
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
            z-index: 1040;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 600px;
            max-width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #000;
        }

        /* Signature pad styles */
        .signature-pad-container {
            margin: 20px 0;
            text-align: center;
            position: relative;
        }

        #signature-pad {
            border: 2px solid #ddd;
            background-color: white;
            width: 100%;
            height: 200px;
            touch-action: none;
            border-radius: 5px;
        }

        .signature-instructions {
            margin-bottom: 15px;
            color: #666;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .signature-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .lease-table {
                font-size: 13px;
            }

            .action-buttons {
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

            .detail-grid {
                grid-template-columns: 1fr;
            }

            /* Make table scrollable on mobile */
            .table-container {
                overflow-x: auto;
            }

            .lease-table {
                min-width: 800px;
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
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
            <li><a href="browse_properties.php"><i class="fas fa-search"></i> Browse Properties</a></li>
            <li><a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a></li>
            <li><a href="my_lease.php" class="active"><i class="fas fa-file-contract"></i> My Lease</a></li>
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
                <i class="fas fa-file-contract"></i>
                My Lease Agreements
            </h1>
            <div class="tenant-info">
                <span>Hello, <?php echo htmlspecialchars($tenant_name); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($tenant_name ?? '', 0, 1)); ?></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" id="successAlert">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error" id="errorAlert">
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <h3><i class="fas fa-filter"></i> Filter Leases</h3>
            </div>

            <!-- Status Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=all<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All Leases
                    <span class="badge"><?php echo $status_counts['all']; ?></span>
                </a>
                <a href="?status=active<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> Active
                    <span class="badge"><?php echo $status_counts['active']; ?></span>
                </a>
                <a href="?status=pending<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Pending
                    <span class="badge"><?php echo $status_counts['pending']; ?></span>
                </a>
                <a href="?status=expired<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'expired' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-times"></i> Expired
                    <span class="badge"><?php echo $status_counts['expired']; ?></span>
                </a>
                <a href="?status=terminated<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                   class="filter-tab <?php echo $status_filter === 'terminated' ? 'active' : ''; ?>">
                    <i class="fas fa-ban"></i> Terminated
                    <span class="badge"><?php echo $status_counts['terminated']; ?></span>
                </a>
            </div>

            <!-- Search Box -->
            <form method="GET" action="" class="search-box">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <input type="text" 
                       name="search" 
                       placeholder="Search by property, address, or landlord..." 
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

        <!-- Leases Table -->
        <?php if (!empty($leases)): ?>
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        Lease Agreements
                    </h3>
                    <span class="lease-count"><?php echo count($leases); ?> Lease(s) Found</span>
                </div>
                <table class="lease-table">
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Landlord</th>
                            <th>Lease Period</th>
                            <th>Rent Amount</th>
                            <th>Status</th>
                            <th>Signatures</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leases as $lease): ?>
                            <tr>
                                <td>
                                    <div class="property-info">
                                        <div class="property-title">
                                            <?php echo htmlspecialchars($lease['property_title']); ?>
                                        </div>
                                        <div class="property-address">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($lease['property_address']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($lease['landlord_first_name'] . ' ' . $lease['landlord_last_name']); ?></strong>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo htmlspecialchars($lease['landlord_email']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <div><strong>Start:</strong> <?php echo date('M j, Y', strtotime($lease['lease_start_date'])); ?></div>
                                        <div><strong>End:</strong> <?php echo date('M j, Y', strtotime($lease['lease_end_date'])); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: #28a745; font-size: 15px;">
                                        R<?php echo number_format($lease['rent_amount'], 2); ?>
                                    </strong>
                                    <div style="font-size: 11px; color: #666;">per month</div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($lease['status']); ?>">
                                        <?php echo ucfirst($lease['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="signature-status <?php echo !empty($lease['signature_path']) ? 'signed' : 'unsigned'; ?>">
                                        <i class="fas fa-<?php echo !empty($lease['signature_path']) ? 'check-circle' : 'times-circle'; ?>"></i>
                                        Landlord
                                    </div>
                                    <div class="signature-status <?php echo !empty($lease['tenant_signature_path']) ? 'signed' : 'unsigned'; ?>">
                                        <i class="fas fa-<?php echo !empty($lease['tenant_signature_path']) ? 'check-circle' : 'times-circle'; ?>"></i>
                                        Tenant
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_lease_tenant.php?id=<?php echo $lease['id']; ?>" 
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="download_lease.php?id=<?php echo $lease['id']; ?>" 
                                           class="btn btn-secondary btn-sm">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <?php if (empty($lease['tenant_signature_path']) && !empty($lease['signature_path'])): ?>
                                            <button class="btn btn-success btn-sm sign-lease-btn" 
                                                    data-lease-id="<?php echo $lease['id']; ?>">
                                                <i class="fas fa-signature"></i> Sign
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="empty-state">
                    <i class="fas fa-file-contract"></i>
                    <h3>No Lease Agreements Found</h3>
                    <?php if (!empty($search_query) || $status_filter !== 'all'): ?>
                        <p>No leases match your current filters. Try adjusting your search or filter criteria.</p>
                        <a href="my_lease.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-redo"></i> Clear All Filters
                        </a>
                    <?php else: ?>
                        <p>You don't have any lease agreements yet. Once your application is approved, your lease will appear here.</p>
                        <a href="my_applications.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-file-alt"></i> View My Applications
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sign Lease Modal -->
    <div id="signLeaseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-signature"></i> Sign Lease Agreement</h3>
                <span class="close" onclick="closeSignModal()">&times;</span>
            </div>
            <form id="signLeaseForm" method="POST">
                <input type="hidden" name="sign_lease" value="1">
                <input type="hidden" id="lease_id" name="lease_id" value="">
                <input type="hidden" id="signature" name="signature" value="">
                
                <div class="signature-instructions">
                    <i class="fas fa-info-circle"></i>
                    <strong>Please sign your name in the box below using your mouse or finger</strong>
                </div>
                
                <div class="signature-pad-container">
                    <canvas id="signature-pad"></canvas>
                </div>
                
                <div class="signature-actions">
                    <button type="button" id="clearSignature" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                    <button type="button" id="saveSignature" class="btn btn-info">
                        <i class="fas fa-save"></i> Save Signature
                    </button>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="width: 100%;" disabled id="signLeaseBtn">
                        <i class="fas fa-check-circle"></i> Confirm and Sign Lease
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize signature pad
        let signaturePad;
        function openSignModal() {
            document.getElementById('signLeaseModal').style.display = 'block';
            setTimeout(() => {
                signaturePad = initSignaturePad();
                signaturePad.clear();
                document.getElementById('signLeaseBtn').disabled = true;
                
                // Hide any alerts when modal opens
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.style.display = 'none', 300);
                });
            }, 100);
        }

        function closeSignModal() {
            document.getElementById('signLeaseModal').style.display = 'none';
        }

        // Clear signature
        document.getElementById('clearSignature').addEventListener('click', function() {
            if (signaturePad) {
                signaturePad.clear();
                document.getElementById('signLeaseBtn').disabled = true;
            }
        });

        // Save signature
        document.getElementById('saveSignature').addEventListener('click', function() {
            if (!signaturePad || signaturePad.isEmpty()) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please provide a signature first',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            const signatureData = signaturePad.toDataURL();
            document.getElementById('signature').value = signatureData;
            document.getElementById('signLeaseBtn').disabled = false;
            
            Swal.fire({
                title: 'Signature Saved',
                text: 'Your signature has been saved. Review and confirm to sign the lease.',
                icon: 'success',
                confirmButtonText: 'OK'
            });
        });

        // Handle form submission
        document.getElementById('signLeaseForm').addEventListener('submit', function(e) {
            if (!signaturePad || signaturePad.isEmpty()) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error',
                    text: 'Please provide a signature before submitting',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            return true;
        });

        // Sign lease button click handler
        document.querySelectorAll('.sign-lease-btn').forEach(button => {
            button.addEventListener('click', function() {
                const leaseId = this.getAttribute('data-lease-id');
                document.getElementById('lease_id').value = leaseId;
                openSignModal();
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('signLeaseModal')) {
                closeSignModal();
            }
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
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>
