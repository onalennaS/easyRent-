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
    // Updated query to include property images
    $sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM rental_applications ra WHERE ra.property_id = p.id) as application_count,
            (SELECT COUNT(*) FROM leases l WHERE l.property_id = p.id AND l.status = 'active') as active_lease,
            (SELECT image_url FROM property_images pi WHERE pi.property_id = p.id ORDER BY pi.id LIMIT 1) as primary_image
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

/* Card Title */
.card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
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
    transform: scale(1.03);
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
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.property-address i {
    margin-right: 0.25rem;
    flex-shrink: 0;
}

.property-price {
    font-size: 1.1rem; /* Reduced from 1.25rem */
    font-weight: bold;
    color: #059669;
    margin-bottom: 0.75rem; /* Reduced from 1rem */
}

.property-details {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem; /* Reduced from 1rem */
}

.property-detail {
    text-align: center;
}

.detail-value {
    font-weight: bold;
    font-size: 1rem; /* Reduced from 1.1rem */
}

.detail-label {
    font-size: 0.75rem; /* Reduced from 0.8rem */
    color: #64748b;
}

.property-actions {
    display: flex;
    gap: 0.375rem; /* Reduced from 0.5rem */
    margin-top: 0.75rem; /* Reduced from 1rem */
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
    
    .property-actions {
        flex-direction: column; /* Stack buttons on mobile */
        gap: 0.5rem;
    }
    
    .property-actions .btn {
        flex: none; /* Remove flex on mobile */
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

    .property-actions {
        flex-direction: column;
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
            <li class="nav-item">
                <a href="landlord_dashboard.php" class="nav-link active">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
               <a href="my_properties.php" class="nav-link active">
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
    <h1 class="page-title">My Properties</h1>
    <div></div> <!-- Empty div for spacing -->
</div>
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
                            <div class="stat-value">R<?php echo number_format($monthlyRevenue); ?></div>
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
                        <?php if(!empty($property['primary_image'])): ?>
                            <img src="../uploads/properties/<?php echo htmlspecialchars($property['primary_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($property['title']); ?>"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="property-image-placeholder" style="display: none;">
                                <i class="fas fa-home"></i>
                            </div>
                        <?php else: ?>
                            <div class="property-image-placeholder">
                                <i class="fas fa-home"></i>
                            </div>
                        <?php endif; ?>
                        
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
                        <div class="property-price">R<?php echo number_format($property['rent_amount']); ?>/month</div>
                        
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
                                Apps
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
    <!-- Add these to your head section -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- Add this script at the end of your body -->
<script>
  // Mobile menu toggle
  const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
  const sidebar = document.querySelector('.sidebar');
  
  if (mobileMenuBtn && sidebar) {
      mobileMenuBtn.addEventListener('click', () => {
          sidebar.classList.toggle('active');
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
  }

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
</script>
</body>
</html>