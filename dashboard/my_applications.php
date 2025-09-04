<?php
// my_applications.php - Tenant Applications Page

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
    SELECT ra.*, p.title, p.address, p.rent_amount, 
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
    <title>My Applications - Easy Rent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        

        

       

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }

        .header h1 {
            color: var(--dark);
            font-size: 28px;
            display: flex;
            align-items: center;
        }

        .header h1 i {
            margin-right: 12px;
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-greeting {
            margin-right: 15px;
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
        }

        .user-role {
            font-size: 14px;
            color: var(--gray);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
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
        .section-title {
            font-size: 22px;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }

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
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-info {
                margin-top: 15px;
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
            
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-file-alt"></i> My Rental Applications</h1>
                <div class="user-info">
                    <div class="user-greeting">
                        <div class="user-name">Hello, <?php echo htmlspecialchars($tenant_name); ?></div>
                        <div class="user-role">Tenant Account</div>
                    </div>
                    <div class="user-avatar"><?php echo strtoupper(substr($tenant_name, 0, 1)); ?></div>
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
                            <div class="application-item" data-status="<?php echo $status_class; ?>">
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
                                    <p class="price">$<?php echo number_format($app['rent_amount']); ?>/month</p>
                                </div>
                                
                                <div class="application-meta">
                                    <p><strong>Applied:</strong> <?php echo $app_date; ?></p>
                                    <p><strong>Status:</strong> <span class="status <?php echo $status_class; ?>"><?php echo ucfirst($app['status']); ?></span></p>
                                    <p><strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($app['updated_at'] ?? $app['created_at'])); ?></p>
                                </div>
                                
                                <div class="application-actions">
                                    <button class="btn btn-view">
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
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const statusFilter = document.getElementById('status-filter');
            const dateFilter = document.getElementById('date-filter');
            const applicationItems = document.querySelectorAll('.application-item');
            
            function filterApplications() {
                const statusValue = statusFilter.value;
                const dateValue = dateFilter.value;
                
                applicationItems.forEach(item => {
                    const itemStatus = item.getAttribute('data-status');
                    
                    // Status filtering
                    if (statusValue !== 'all' && statusValue !== itemStatus) {
                        item.style.display = 'none';
                        return;
                    }
                    
                    // Date sorting (simulated by CSS order)
                    item.style.display = 'flex';
                });
            }
            
            // Add event listeners
            statusFilter.addEventListener('change', filterApplications);
            dateFilter.addEventListener('change', filterApplications);
            
            // Withdraw button functionality
            document.querySelectorAll('.btn-withdraw').forEach(button => {
                button.addEventListener('click', function() {
                    const applicationItem = this.closest('.application-item');
                    const propertyTitle = applicationItem.querySelector('h3').textContent;
                    
                    if (confirm(`Are you sure you want to withdraw your application for "${propertyTitle}"?`)) {
                        // In a real application, this would send an AJAX request
                        applicationItem.style.opacity = '0.6';
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Withdrawing...';
                        
                        // Simulate API call
                        setTimeout(() => {
                            this.innerHTML = '<i class="fas fa-check"></i> Withdrawn';
                            this.classList.remove('btn-withdraw');
                            this.classList.add('btn-view');
                            applicationItem.querySelector('.status').textContent = 'Withdrawn';
                            applicationItem.querySelector('.status').className = 'status rejected';
                        }, 1500);
                    }
                });
            });
            
            // View button functionality
            document.querySelectorAll('.btn-view').forEach(button => {
                button.addEventListener('click', function() {
                    const applicationItem = this.closest('.application-item');
                    const propertyTitle = applicationItem.querySelector('h3').textContent;
                    alert(`Viewing details for: ${propertyTitle}\n\nIn a real application, this would open a detailed view.`);
                });
            });
        });
    </script>
</body>
</html>