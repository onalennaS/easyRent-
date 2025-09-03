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
        // Updated query to include admin approval status
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM rental_applications ra WHERE ra.property_id = p.id) as application_count,
                (SELECT COUNT(*) FROM leases l WHERE l.property_id = p.id AND l.status = 'active') as active_lease,
                (SELECT image_url FROM property_images pi WHERE pi.property_id = p.id ORDER BY pi.id LIMIT 1) as primary_image,
                CASE 
                    WHEN p.admin_approved = 1 THEN 'Approved'
                    ELSE 'Awaiting Approval'
                END as approval_status
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
        $approvedProperties = 0;
        $pendingProperties = 0;
        
        foreach ($properties as $property) {
            if ($property['active_lease']) {
                $occupiedProperties++;
                $monthlyRevenue += $property['rent_amount'];
            }
            $totalApplications += $property['application_count'];
            
            // Count approved vs pending properties
            if ($property['admin_approved'] == 1) {
                $approvedProperties++;
            } else {
                $pendingProperties++;
            }
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
.stat-card.approved { --accent-color: linear-gradient(135deg, #10b981 0%, #047857 100%); }
.stat-card.pending { --accent-color: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }

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

/* Properties Table */
.properties-table-container {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.table-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
}

.properties-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.properties-table th {
    background-color: #f8fafc;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.properties-table td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.properties-table tbody tr {
    transition: background-color 0.2s ease;
}

.properties-table tbody tr:hover {
    background-color: #f8fafc;
}

.property-image {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}

.image-placeholder {
    width: 80px;
    height: 60px;
    background: #f1f5f9;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge.approved {
    background-color: #dcfce7;
    color: #166534;
}

.status-badge.pending {
    background-color: #fef3c7;
    color: #92400e;
}

.status-badge.available {
    background-color: #dbeafe;
    color: #1e40af;
}

.status-badge.occupied {
    background-color: #fee2e2;
    color: #991b1b;
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

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
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
    .main-content {
        padding: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
    
    .properties-table {
        display: block;
        overflow-x: auto;
    }
    
    .action-buttons {
        flex-direction: column;
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
                <a href="profile_landlord.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="landlord_dashboard.php" class="nav-link">
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
                <p class="hero-subtitle">Manage your rental properties and track applications</p>
                
                <div class="quick-actions">
                    <a href="add_property.php" class="quick-action-btn">
                        <i class="fas fa-plus"></i>
                        Add New Property
                    </a>
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
            
            <div class="stat-card approved">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $approvedProperties; ?></div>
                        <div class="stat-label">Approved Properties</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $pendingProperties; ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Property Listings -->
        <div class="properties-table-container">
            <div class="table-header">
                <h2 class="table-title">Your Property Listings</h2>
            </div>
            
            <?php if(!empty($properties)): ?>
                <table class="properties-table">
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Address</th>
                            <th>Rent</th>
                            <th>Bed/Bath</th>
                            <th>Availability</th>
                            <th>Applications</th>
                            <th>Admin Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($properties as $property): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <?php if(!empty($property['primary_image'])): ?>
                                            <img src="../uploads/properties/<?php echo htmlspecialchars($property['primary_image']); ?>" 
                                                 class="property-image" 
                                                 alt="<?php echo htmlspecialchars($property['title']); ?>"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="image-placeholder" style="display: none;">
                                                <i class="fas fa-home"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <i class="fas fa-home"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($property['title']); ?></div>
                                            <div style="font-size: 0.875rem; color: #64748b;"><?php echo ucfirst(htmlspecialchars($property['property_type'])); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($property['address']); ?></td>
                                <td style="font-weight: 600; color: #059669;">R<?php echo number_format($property['rent_amount']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 1rem;">
                                        <div>
                                            <div style="font-weight: 600;"><?php echo $property['bedrooms']; ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;">Bedrooms</div>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo $property['bathrooms']; ?></div>
                                            <div style="font-size: 0.75rem; color: #64748b;">Bathrooms</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($property['active_lease']): ?>
                                        <span class="status-badge occupied">Occupied</span>
                                    <?php else: ?>
                                        <span class="status-badge available">Available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="text-align: center;">
                                        <div style="font-weight: 600;"><?php echo $property['application_count']; ?></div>
                                        <div style="font-size: 0.75rem; color: #64748b;">Applications</div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($property['admin_approved'] == 1): ?>
                                        <span class="status-badge approved">
                                            <i class="fas fa-check-circle" style="margin-right: 0.25rem;"></i>
                                            Approved
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge pending">
                                            <i class="fas fa-clock" style="margin-right: 0.25rem;"></i>
                                            Awaiting Approval
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_property.php?id=<?php echo $property['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_property.php?id=<?php echo $property['id']; ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="applications.php?property_id=<?php echo $property['id']; ?>" class="btn btn-success">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
        document.addEventListener('DOMContentLoaded', function() {
            const logoutLink = document.getElementById('logoutLink');
            
            if (logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    e.preventDefault(); // prevent default link behavior
                    
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'You will be logged out from your account.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, log out',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            popup: 'sweetalert-custom'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Perform logout action
                            window.location.href = '../auth/logout.php';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>