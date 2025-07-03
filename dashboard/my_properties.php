<?php
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
if (!$isLoggedIn) {
    header("Location: auth/login.php");
    exit();
}

$username = $_SESSION['username'];
$userRole = $_SESSION['user_type'];
$userId = $_SESSION['user_id'];

// Database connection
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "easyrent_db";

try {
    $conn = new mysqli($servername, $username_db, $password_db, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Fetch properties based on user role
    if ($userRole === 'landlord') {
        // Fetch properties owned by the landlord
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM rental_applications ra WHERE ra.property_id = p.id) as application_count,
                (SELECT COUNT(*) FROM leases l WHERE l.property_id = p.id AND l.status = 'active') as active_lease
                FROM properties p 
                WHERE p.landlord_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $properties = $result->fetch_all(MYSQLI_ASSOC);
        
        // Calculate stats
        $totalProperties = count($properties);
        $occupiedProperties = 0;
        $totalApplications = 0;
        $monthlyRevenue = 0;
        
        foreach ($properties as $property) {
            if ($property['active_lease']) {
                $occupiedProperties++;
                $monthlyRevenue += $property['rent_amount'];
            }
            $totalApplications += $property['application_count'];
        }
    } else {
        // Fetch applications for the tenant
        $sql = "SELECT ra.*, p.title, p.address, p.rent_amount, p.bedrooms, p.bathrooms, ra.status as application_status
                FROM rental_applications ra
                JOIN properties p ON ra.property_id = p.id
                WHERE ra.tenant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $applications = $result->fetch_all(MYSQLI_ASSOC);
        
        // Calculate stats
        $totalApplications = count($applications);
        $pendingApplications = 0;
        $approvedApplications = 0;
        
        foreach ($applications as $app) {
            if ($app['application_status'] === 'pending') $pendingApplications++;
            if ($app['application_status'] === 'approved') $approvedApplications++;
        }
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Properties - EasyRent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .stat-card.applications { --accent-color: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .stat-card.occupied { --accent-color: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

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

        .status-available {
            background: #dcfce7;
            color: #166534;
        }

        .status-occupied {
            background: #fef3c7;
            color: #92400e;
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

        .property-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .property-detail {
            text-align: center;
        }

        .detail-value {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        .property-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Application Cards */
        .application-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .application-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .application-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .application-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .application-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .application-detail {
            display: flex;
            flex-direction: column;
        }

        .detail-value {
            font-weight: bold;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
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

        /* Responsive Design */
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

            .property-actions {
                flex-direction: column;
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
                <li><a href="applications.php">Applications</a></li>
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
                <h1 class="hero-title">My Properties</h1>
                <p class="hero-subtitle">
                    <?php if($userRole === 'landlord'): ?>
                        Manage your rental properties and track applications
                    <?php else: ?>
                        Track your rental applications and property status
                    <?php endif; ?>
                </p>
                
                <div class="quick-actions">
                    <?php if($userRole === 'landlord'): ?>
                        <a href="add_property.php" class="quick-action-btn">
                            <i class="fas fa-plus"></i>
                            Add New Property
                        </a>
                    <?php endif; ?>
                    <a href="search.php" class="quick-action-btn">
                        <i class="fas fa-search"></i>
                        Find Properties
                    </a>
                    <a href="settings.php" class="quick-action-btn">
                        <i class="fas fa-cog"></i>
                        Account Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <?php if($userRole === 'landlord'): ?>
                <div class="stat-card properties">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $totalProperties; ?></div>
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
                            <div class="stat-value">$<?php echo number_format($monthlyRevenue); ?></div>
                            <div class="stat-label">Monthly Revenue</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card applications">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $totalApplications; ?></div>
                            <div class="stat-label">Total Applications</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card occupied">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $occupiedProperties; ?></div>
                            <div class="stat-label">Occupied Properties</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="stat-card properties">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $totalApplications; ?></div>
                            <div class="stat-label">Applications Sent</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card income">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $approvedApplications; ?></div>
                            <div class="stat-label">Approved Applications</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card applications">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $pendingApplications; ?></div>
                            <div class="stat-label">Pending Applications</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card occupied">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo ($totalApplications - $pendingApplications - $approvedApplications); ?></div>
                            <div class="stat-label">Other Status</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Property/Application Listings -->
        <div>
            <h2 class="card-title" style="font-size: 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-list"></i>
                <?php if($userRole === 'landlord'): ?>
                    Your Property Listings
                <?php else: ?>
                    Your Rental Applications
                <?php endif; ?>
            </h2>

            <?php if($userRole === 'landlord'): ?>
                <?php if(!empty($properties)): ?>
                    <div class="property-grid">
                        <?php foreach($properties as $property): ?>
                            <div class="property-card">
                                <div class="property-image">
                                    <i class="fas fa-home"></i>
                                    <span class="property-status <?php echo $property['active_lease'] ? 'status-occupied' : 'status-available'; ?>">
                                        <?php echo $property['active_lease'] ? 'Occupied' : 'Available'; ?>
                                    </span>
                                </div>
                                <div class="property-content">
                                    <h3 class="property-title"><?php echo htmlspecialchars($property['title']); ?></h3>
                                    <p class="property-address">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($property['address']); ?>
                                    </p>
                                    <div class="property-price">$<?php echo number_format($property['rent_amount']); ?>/month</div>
                                    
                                    <div class="property-details">
                                        <div class="property-detail">
                                            <div class="detail-value"><?php echo $property['bedrooms']; ?></div>
                                            <div class="detail-label">Bedrooms</div>
                                        </div>
                                        <div class="property-detail">
                                            <div class="detail-value"><?php echo $property['bathrooms']; ?></div>
                                            <div class="detail-label">Bathrooms</div>
                                        </div>
                                        <div class="property-detail">
                                            <div class="detail-value"><?php echo $property['application_count']; ?></div>
                                            <div class="detail-label">Applications</div>
                                        </div>
                                    </div>
                                    
                                    <div class="property-actions">
                                        <a href="view_property.php?id=<?php echo $property['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                            View
                                        </a>
                                        <a href="edit_property.php?id=<?php echo $property['id']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                        <a href="applications.php?property_id=<?php echo $property['id']; ?>" class="btn btn-success">
                                            <i class="fas fa-file-alt"></i>
                                            Applications
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <h3>No Properties Listed</h3>
                        <p>You haven't listed any properties yet</p>
                        <a href="add_property.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i>
                            Add Your First Property
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if(!empty($applications)): ?>
                    <div>
                        <?php foreach($applications as $app): ?>
                            <div class="application-card">
                                <div class="application-header">
                                    <div class="application-title">
                                        <?php echo htmlspecialchars($app['title']); ?>
                                    </div>
                                    <div class="application-status status-<?php echo $app['application_status']; ?>">
                                        <?php echo ucfirst($app['application_status']); ?>
                                    </div>
                                </div>
                                
                                <div class="property-address">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($app['address']); ?>
                                </div>
                                
                                <div class="application-details">
                                    <div class="application-detail">
                                        <span class="detail-value">$<?php echo number_format($app['rent_amount']); ?></span>
                                        <span class="detail-label">Rent Amount</span>
                                    </div>
                                    <div class="application-detail">
                                        <span class="detail-value"><?php echo $app['bedrooms']; ?> bd</span>
                                        <span class="detail-label">Bedrooms</span>
                                    </div>
                                    <div class="application-detail">
                                        <span class="detail-value"><?php echo $app['bathrooms']; ?> ba</span>
                                        <span class="detail-label">Bathrooms</span>
                                    </div>
                                    <div class="application-detail">
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($app['application_date'])); ?></span>
                                        <span class="detail-label">Applied On</span>
                                    </div>
                                </div>
                                
                                <div class="property-actions">
                                    <a href="view_property.php?id=<?php echo $app['property_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i>
                                        View Property
                                    </a>
                                    <button class="btn btn-secondary">
                                        <i class="fas fa-envelope"></i>
                                        Contact Landlord
                                    </button>
                                    <?php if($app['application_status'] === 'approved'): ?>
                                        <button class="btn btn-success">
                                            <i class="fas fa-file-contract"></i>
                                            Sign Lease
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>No Applications Found</h3>
                        <p>You haven't applied to any properties yet</p>
                        <a href="search.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-search"></i>
                            Find Properties to Apply
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Simple animation for property cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.property-card, .application-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>