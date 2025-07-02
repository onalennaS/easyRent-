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

// Get landlord statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM properties WHERE landlord_id = $landlord_id) as total_properties,
        " . ($has_status ? "(SELECT COUNT(*) FROM properties WHERE landlord_id = $landlord_id AND status = 'approved')" : "(SELECT COUNT(*) FROM properties WHERE landlord_id = $landlord_id)") . " as active_properties,
        " . ($has_status ? "(SELECT COUNT(*) FROM properties WHERE landlord_id = $landlord_id AND status = 'pending')" : "0") . " as pending_properties,
        " . ($has_rent_amount ? "(SELECT COALESCE(SUM(rent_amount), 0) FROM properties WHERE landlord_id = $landlord_id" . ($has_status ? " AND status = 'approved'" : "") . ")" : "0") . " as monthly_income,
        (SELECT COUNT(*) FROM maintenance_requests mr JOIN properties p ON mr.property_id = p.id WHERE p.landlord_id = $landlord_id AND mr.status = 'open') as open_maintenance
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
    SELECT * FROM properties 
    WHERE landlord_id = $landlord_id 
    ORDER BY created_at DESC 
    LIMIT 6
";
$properties_result = mysqli_query($conn, $properties_query);

// Get recent maintenance requests
$maintenance_query = "
    SELECT mr.*, p.title as property_title, p.address as property_address
    FROM maintenance_requests mr 
    JOIN properties p ON mr.property_id = p.id 
    WHERE p.landlord_id = $landlord_id 
    ORDER BY mr.created_at DESC 
    LIMIT 5
";
$maintenance_result = mysqli_query($conn, $maintenance_query);

// Get recent inquiries (assuming there's an inquiries table)
$inquiries_query = "
    SELECT i.*, p.title as property_title, u.first_name, u.last_name, u.email
    FROM inquiries i 
    JOIN properties p ON i.property_id = p.id 
    JOIN users u ON i.tenant_id = u.id
    WHERE p.landlord_id = $landlord_id 
    ORDER BY i.created_at DESC 
    LIMIT 5
";
$inquiries_result = mysqli_query($conn, $inquiries_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Dashboard - Easy Rent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- In your HTML head -->
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
        }

        /* Top Navigation */
        .top-nav {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 0 2rem;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-avatar {
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

        /* Main Content */
        .main-content {
            margin-top: 70px;
            padding: 2rem;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
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
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-action-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
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
            justify-content: between;
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
        }

        .view-all-link:hover {
            color: #1d4ed8;
        }

        /* Property Cards */
        .property-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .property-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .property-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .property-image {
            height: 200px;
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6366f1;
            font-size: 3rem;
            position: relative;
        }

        .property-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .property-content {
            padding: 1.5rem;
        }

        .property-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .property-address {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .property-price {
            font-size: 1.25rem;
            font-weight: bold;
            color: #059669;
            margin-bottom: 1rem;
        }

        .property-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
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

            .nav-menu {
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            }

            .property-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .nav-container {
                padding: 0 1rem;
            }

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
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-home"></i>
                Easy Rent
            </div>
            
            <ul class="nav-menu">
                <li><a href="landlord_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="my_properties.php">My Properties</a></li>
                <li><a href="applications.php" class="active">Applications</a></li>
                <li><a href="add_property.php">Add Property</a></li>
                <li><a href="maintenance.php">Maintenance</a></li>
                <li><a href="tenants.php">Tenants</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
            
            <div class="user-profile">
                <span>Welcome, <?php echo $_SESSION['user_name'] ?? 'Landlord'; ?></span>
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?>
                </div>
                 <a href="../auth/logout.php" style="color: white; margin-left: 1rem;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
               
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
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

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card properties">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['total_properties']); ?></div>
                        <div class="stat-label">Total Properties</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card income">
                <div class="stat-header">
                    <div>
                        <div class="stat-value">R<?php echo number_format($stats['monthly_income']); ?></div>
                        <div class="stat-label">Monthly Income</div>
                    </div>
                   <div class="stat-icon">
    <span class="currency-symbol">R</span>
</div>

                </div>
            </div>

            <div class="stat-card maintenance">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['open_maintenance']); ?></div>
                        <div class="stat-label">Open Maintenance</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-wrench"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo number_format($stats['pending_properties']); ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
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
                        <?php while ($property = mysqli_fetch_assoc($properties_result)): ?>
                            <div class="property-card">
                                <div class="property-image">
                                    <i class="fas fa-home"></i>
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