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

// Get applications for this landlord's properties
$applications_query = "
    SELECT ra.*, p.title AS property_title, u.first_name, u.last_name, u.email, u.phone
    FROM rental_applications ra
    JOIN properties p ON ra.property_id = p.id
    JOIN users u ON ra.tenant_id = u.id
    WHERE p.landlord_id = $landlord_id
    ORDER BY ra.application_date DESC
";

$applications_result = mysqli_query($conn, $applications_query);
$applications = [];
if ($applications_result) {
    while ($row = mysqli_fetch_assoc($applications_result)) {
        $applications[] = $row;
    }
}

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $application_id = $_POST['application_id'];
    $new_status = $_POST['status'];
    
    // Check if application is already approved
    $check_query = "SELECT status FROM rental_applications WHERE id = $application_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if ($check_result) {
        $current_app = mysqli_fetch_assoc($check_result);
        if ($current_app['status'] === 'approved') {
            $error_message = "This application has already been approved and cannot be modified.";
        } else {
            // Continue with existing logic
            $landlord_notes = mysqli_real_escape_string($conn, $_POST['landlord_notes'] ?? '');
            
            // For non-approved statuses
            if ($new_status != 'approved') {
                $update_query = "
                    UPDATE rental_applications 
                    SET status = '$new_status', 
                        landlord_notes = '$landlord_notes',
                        reviewed_at = NOW(),
                        reviewed_by = $landlord_id
                    WHERE id = $application_id
                ";
                
                if (mysqli_query($conn, $update_query)) {
                    $success_message = "Application status updated successfully!";
                } else {
                    $error_message = "Error updating application: " . mysqli_error($conn);
                }
            } 
            // For approved status
            else {
                // Get application details
                $app_query = "SELECT * FROM rental_applications WHERE id = $application_id";
                $app_result = mysqli_query($conn, $app_query);
                
                if (!$app_result) {
                    $error_message = "Error fetching application: " . mysqli_error($conn);
                } else {
                    $application = mysqli_fetch_assoc($app_result);
                    
                    // Get monthly rent from form submission
                    $monthly_rent = $_POST['monthly_rent'];
                    
                    // Validate monthly rent
                    if (empty($monthly_rent) || !is_numeric($monthly_rent) || $monthly_rent <= 0) {
                        $error_message = "Invalid monthly rent value. Please enter a valid rent amount.";
                    } else {
                        // Update application status first
                        $update_query = "
                            UPDATE rental_applications 
                            SET status = '$new_status', 
                                landlord_notes = '$landlord_notes',
                                reviewed_at = NOW(),
                                reviewed_by = $landlord_id
                            WHERE id = $application_id
                        ";
                        
                        if (mysqli_query($conn, $update_query)) {
                            // Validate and format move_in_date
                            $move_in_date = $application['move_in_date'] ?? null;
                            
                            if ($move_in_date && strtotime($move_in_date)) {
                                $move_in_date = date('Y-m-d', strtotime($move_in_date));
                            } else {
                                $move_in_date = date('Y-m-d', strtotime('+7 days'));
                                $error_message = "Invalid move-in date detected. Using fallback date: " . date('M d, Y', strtotime($move_in_date));
                            }
                            
                            // Calculate end date
                            $end_date = date('Y-m-d', strtotime($move_in_date . " + {$application['lease_duration_months']} months"));
                            
                            // Create lease
                            $stmt = $conn->prepare("
                                INSERT INTO leases (
                                    property_id, 
                                    tenant_id, 
                                    landlord_id,
                                    application_id,
                                    lease_start_date,
                                    lease_end_date,
                                    monthly_rent,
                                    security_deposit,
                                    status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                            ");
                            
                            // Bind parameters
                            $stmt->bind_param(
                                "iiiissdd", 
                                $application['property_id'],
                                $application['tenant_id'],
                                $landlord_id,
                                $application_id,
                                $move_in_date,
                                $end_date,
                                $monthly_rent,  // Use rent from form
                                $monthly_rent   // Security deposit = 1 month rent
                            );
                            
                            if ($stmt->execute()) {
                                $lease_id = $stmt->insert_id;
                                $success_message = "Application approved and lease #$lease_id created!";
                                
                                // Send notification to tenant
                                $tenant_id = $application['tenant_id'];
                                $property_id = $application['property_id'];
                                $message = "Your application for property #$property_id has been approved! Please sign your lease agreement.";
                                
                                $notif_query = "
                                    INSERT INTO notifications (user_id, message, type, is_read, created_at)
                                    VALUES ($tenant_id, '$message', 'application', 0, NOW())
                                ";
                                mysqli_query($conn, $notif_query);
                            } else {
                                $error_message = "Lease creation failed: " . $stmt->error;
                            }
                            $stmt->close();
                        } else {
                            $error_message = "Error updating application: " . mysqli_error($conn);
                        }
                    }
                }
            }
        }
    }
    
    // Refresh applications data
    $applications_result = mysqli_query($conn, $applications_query);
    $applications = [];
    if ($applications_result) {
        while ($row = mysqli_fetch_assoc($applications_result)) {
            $applications[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Applications - Easy Rent</title>
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

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.page-actions {
    display: flex;
    gap: 1rem;
}

/* Filters */
.filters-section {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.filters-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
}

.filter-reset {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    cursor: pointer;
}

.filter-options {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}

.filter-label {
    font-size: 0.875rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.filter-select {
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: white;
    font-size: 0.9rem;
}

/* Applications Table */
.applications-table {
    width: 100%;
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    border-collapse: collapse;
}

.applications-table thead {
    background: #f1f5f9;
}

.applications-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 1px solid #e2e8f0;
}

.applications-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.applications-table tr:last-child td {
    border-bottom: none;
}

.applications-table tr:hover {
    background-color: #f8fafc;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    display: inline-block;
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

.status-reviewed {
    background: #dbeafe;
    color: #1e40af;
}

.tenant-info {
    display: flex;
    flex-direction: column;
}

.tenant-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.tenant-contact {
    font-size: 0.875rem;
    color: #64748b;
}

.application-actions {
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

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-info {
    background: #0ea5e9;
    color: white;
}

.btn-info:hover {
    background: #0284c7;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 2rem;
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
}

.close-modal:hover {
    color: #64748b;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #1e293b;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1rem;
}

/* Tenant Profile Modal */
.tenant-profile-modal .modal-content {
    max-width: 800px;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
    font-weight: bold;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.profile-info h3 {
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
    color: #1e293b;
}

.profile-info p {
    color: #64748b;
}

.profile-sections {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.profile-section {
    margin-bottom: 1.5rem;
}

.profile-section h4 {
    font-size: 1.1rem;
    margin-bottom: 1rem;
    color: #1e293b;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-grid {
    display: grid;
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 0.875rem;
    color: #64748b;
    margin-bottom: 0.25rem;
}

.info-value {
    font-weight: 500;
    color: #1e293b;
}

.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.document-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    background: #f8fafc;
    transition: all 0.3s ease;
}

.document-card:hover {
    border-color: #3b82f6;
    background: white;
    transform: translateY(-2px);
}

.document-icon {
    font-size: 2rem;
    color: #3b82f6;
    margin-bottom: 0.5rem;
    text-align: center;
}

.document-name {
    font-weight: 500;
    text-align: center;
    color: #1e293b;
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
    .applications-table {
        display: block;
        overflow-x: auto;
    }
    
    .profile-sections {
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
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .filter-options {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
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
            <a href="my_properties.php" class="nav-link">
                <i class="fas fa-building"></i>
                <span>My Properties</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="applications.php" class="nav-link active">
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
        <h1 class="page-title">Rental Applications</h1>
        <div></div> <!-- Empty div for spacing -->
    </div>

    <div class="page-header">
        <div class="page-actions">
                <button class="btn btn-primary">
                    <i class="fas fa-download"></i>
                    Export
                </button>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-header">
                <h3 class="filters-title">Filter Applications</h3>
                <span class="filter-reset">Reset Filters</span>
            </div>
            <div class="filter-options">
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select class="filter-select">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="reviewed">Reviewed</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Property</label>
                    <select class="filter-select">
                        <option value="all">All Properties</option>
                        <option value="1">Downtown Apartment</option>
                        <option value="2">Suburban House</option>
                        <option value="3">Luxury Condo</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Date Range</label>
                    <select class="filter-select">
                        <option value="all">All Time</option>
                        <option value="week">Last 7 Days</option>
                        <option value="month">Last 30 Days</option>
                        <option value="quarter">Last 90 Days</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Applications Table -->
        <?php if (!empty($applications)): ?>
            <table class="applications-table">
                <thead>
                    <tr>
                        
                        <th>Property</th>
                        <th>Tenant</th>
                        <th>Application Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                           
                            <td><?php echo htmlspecialchars($app['property_title']); ?></td>
                            <td class="tenant-info">
                                <span class="tenant-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></span>
                                <span class="tenant-contact"><?php echo htmlspecialchars($app['email']); ?></span>
                                <span class="tenant-contact"><?php echo htmlspecialchars($app['phone']); ?></span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td class="application-actions">
                                <button class="btn btn-info view-tenant-profile" 
                                        data-id="<?php echo $app['tenant_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>">
                                    <i class="fas fa-user"></i>
                                </button>
                                <?php if ($app['status'] === 'approved'): ?>
                                    <button class="btn btn-secondary" disabled title="Application already approved">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-warning update-status" data-id="<?php echo $app['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No Rental Applications Found</h3>
                <p>You currently have no rental applications. When tenants apply to your properties, they'll appear here.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Application Status</h3>
                <button class="close-modal">&times;</button>
            </div>
            <form method="POST" action="applications.php">
                <input type="hidden" name="application_id" id="statusApplicationId">
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" id="statusSelect" required>
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="form-group" id="rentInputGroup" style="display: none;">
                    <label class="form-label">Monthly Rent for Lease*</label>
                    <input type="number" step="0.01" min="0" name="monthly_rent" class="form-control" placeholder="Enter monthly rent amount">
                    <small class="form-text text-muted">This rent will be used for the new lease</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Landlord Notes</label>
                    <textarea name="landlord_notes" class="form-control" placeholder="Add any notes for the tenant..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tenant Profile Modal -->
    <div class="modal tenant-profile-modal" id="tenantProfileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Tenant Profile</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="profile-header">
                    <div class="profile-avatar" id="tenantAvatar">
                        <!-- Avatar will be populated by JavaScript -->
                    </div>
                    <div class="profile-info">
                        <h3 id="tenantName"></h3>
                        <p id="tenantContact"></p>
                    </div>
                </div>
                
                <div class="profile-sections">
                    <div class="left-section">
                        <div class="profile-section">
                            <h4><i class="fas fa-info-circle"></i> Personal Information</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">ID Number</span>
                                    <span class="info-value" id="tenantIdNumber"></span>
                                </div>
                               
                                <div class="info-item">
                                    <span class="info-label">Address</span>
                                    <span class="info-value" id="tenantAddress"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h4><i class="fas fa-briefcase"></i> Employment Information</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Employment Status</span>
                                    <span class="info-value" id="tenantEmployment"></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Employer</span>
                                    <span class="info-value" id="tenantEmployer"></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Job Title</span>
                                    <span class="info-value" id="tenantJobTitle"></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Monthly Income</span>
                                    <span class="info-value" id="tenantIncome"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="right-section">
                        <div class="profile-section">
                            <h4><i class="fas fa-phone"></i> Emergency Contact</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Name</span>
                                    <span class="info-value" id="emergencyName"></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Phone</span>
                                    <span class="info-value" id="emergencyPhone"></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Relationship</span>
                                    <span class="info-value" id="emergencyRelationship"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h4><i class="fas fa-file-alt"></i> Rental History</h4>
                            <div class="info-item">
                                <span class="info-value" id="rentalHistory"></span>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h4><i class="fas fa-file-upload"></i> Documents</h4>
                            <div class="documents-grid" id="tenantDocuments">
                                <!-- Documents will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary close-modal">Close</button>
            </div>
        </div>
    </div>
   
<script>
// Modal functionality
const modals = document.querySelectorAll('.modal');
const closeButtons = document.querySelectorAll('.close-modal');
const updateButtons = document.querySelectorAll('.update-status');
const viewProfileButtons = document.querySelectorAll('.view-tenant-profile');
const logoutLink = document.getElementById('logoutLink');
const statusSelect = document.getElementById('statusSelect');
const rentInputGroup = document.getElementById('rentInputGroup');

// Show modal
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

// Close modal
function closeModal() {
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
}

// Update status button click
updateButtons.forEach(button => {
    button.addEventListener('click', function() {
        const appId = this.getAttribute('data-id');
        
        // Check if button is disabled (for approved applications)
        if (this.disabled) {
            Swal.fire({
                icon: 'warning',
                title: 'Action Restricted',
                text: 'This application has already been approved and cannot be modified.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        document.getElementById('statusApplicationId').value = appId;
        
        // Reset form state
        rentInputGroup.style.display = 'none';
        statusSelect.value = 'pending';
        
        openModal('statusModal');
    });
});

// View tenant profile button click
viewProfileButtons.forEach(button => {
    button.addEventListener('click', function() {
        const tenantId = this.getAttribute('data-id');
        const tenantName = this.getAttribute('data-name');
        
        // Show loading state
        Swal.fire({
            title: 'Loading Profile',
            text: 'Please wait while we fetch the tenant profile...',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Fetch tenant profile data via AJAX
        fetch(`get_tenant_profile.php?tenant_id=${tenantId}`)
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    // Populate the modal with tenant data
                    document.getElementById('tenantName').textContent = tenantName;
                    document.getElementById('tenantContact').textContent = `${data.profile.email} • ${data.profile.phone}`;
                    
                    // Set avatar
                    const avatarElement = document.getElementById('tenantAvatar');
                    if (data.profile.profile_image) {
                        avatarElement.innerHTML = `<img src="../uploads/tenant_profiles/${data.profile.profile_image}" alt="${tenantName}">`;
                    } else {
                        avatarElement.textContent = tenantName.charAt(0).toUpperCase();
                    }
                    
                    // Personal info
                    document.getElementById('tenantIdNumber').textContent = data.profile.id_number || 'Not provided';
                    
                    document.getElementById('tenantAddress').textContent = 
                        `${data.profile.address || ''}, ${data.profile.city || ''}, ${data.profile.province || ''}, ${data.profile.postal_code || ''}`.trim();
                    
                    // Employment info
                    document.getElementById('tenantEmployment').textContent = formatEmploymentStatus(data.profile.employment_status) || 'Not provided';
                    document.getElementById('tenantEmployer').textContent = data.profile.employer_name || 'Not provided';
                    document.getElementById('tenantJobTitle').textContent = data.profile.job_title || 'Not provided';
                    document.getElementById('tenantIncome').textContent = data.profile.monthly_income ? 
                        `R ${parseFloat(data.profile.monthly_income).toLocaleString()}` : 'Not provided';
                    
                    // Emergency contact
                    document.getElementById('emergencyName').textContent = data.profile.emergency_contact_name || 'Not provided';
                    document.getElementById('emergencyPhone').textContent = data.profile.emergency_contact_phone || 'Not provided';
                    document.getElementById('emergencyRelationship').textContent = data.profile.emergency_contact_relationship || 'Not provided';
                    
                    // Rental history
                    document.getElementById('rentalHistory').textContent = data.profile.rental_history || 'No rental history provided';
                    
                    // Documents
                    const documentsContainer = document.getElementById('tenantDocuments');
                    documentsContainer.innerHTML = '';
                    
                    if (data.documents && data.documents.length > 0) {
                        data.documents.forEach(doc => {
                            const docCard = document.createElement('div');
                            docCard.className = 'document-card';
                            docCard.innerHTML = `
                                <div class="document-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="document-name">${doc.document_name}</div>
                            `;
                            // Add click to view functionality
                            docCard.addEventListener('click', () => {
                                window.open(`../uploads/tenant_documents/${doc.file_path}`, '_blank');
                            });
                            documentsContainer.appendChild(docCard);
                        });
                    } else {
                        documentsContainer.innerHTML = '<p>No documents uploaded</p>';
                    }
                    
                    // Show the modal
                    openModal('tenantProfileModal');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to load tenant profile',
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to fetch tenant profile. Please try again.',
                    confirmButtonColor: '#ef4444'
                });
                console.error('Error:', error);
            });
    });
});

// Helper function to format employment status
function formatEmploymentStatus(status) {
    if (!status) return '';
    
    const statusMap = {
        'employed': 'Employed',
        'self_employed': 'Self Employed',
        'student': 'Student',
        'unemployed': 'Unemployed',
        'retired': 'Retired'
    };
    
    return statusMap[status] || status;
}

// Close modals when clicking close button or outside modal
closeButtons.forEach(button => {
    button.addEventListener('click', closeModal);
});

window.addEventListener('click', function(event) {
    modals.forEach(modal => {
        if (event.target === modal) {
            closeModal();
        }
    });
});

// Show/hide rent input based on status selection
statusSelect.addEventListener('change', function() {
    if (this.value === 'approved') {
        rentInputGroup.style.display = 'block';
    } else {
        rentInputGroup.style.display = 'none';
    }
});

// Enhanced form submission with SweetAlert confirmation
document.addEventListener('DOMContentLoaded', function() {
    // Intercept form submission for status updates
    const statusForm = document.querySelector('#statusModal form');
    
    if (statusForm) {
        statusForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            const formData = new FormData(this);
            const status = formData.get('status');
            const monthlyRent = formData.get('monthly_rent');
            
            // Determine confirmation message based on status
            let title, text, confirmButtonText, icon, confirmButtonColor;
            
            switch(status) {
                case 'approved':
                    if (!monthlyRent || monthlyRent <= 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Missing Information',
                            text: 'Please enter a valid monthly rent amount for approval.',
                            confirmButtonColor: '#3085d6'
                        });
                        return;
                    }
                    
                    // Format rent in South African Rand
                    const formattedRent = new Intl.NumberFormat('en-ZA', {
                        style: 'currency',
                        currency: 'ZAR',
                        minimumFractionDigits: 2
                    }).format(monthlyRent);
                    
                    title = 'Approve Application?';
                    text = `Are you sure you want to approve this application with a monthly rent of ${formattedRent}? This will create a new lease agreement.`;
                    confirmButtonText = 'Yes, Approve';
                    icon = 'success';
                    confirmButtonColor = '#10b981';
                    break;
                    
                case 'rejected':
                    title = 'Reject Application?';
                    text = 'Are you sure you want to reject this application? This action cannot be undone.';
                    confirmButtonText = 'Yes, Reject';
                    icon = 'warning';
                    confirmButtonColor = '#ef4444';
                    break;
                    
                case 'reviewed':
                    title = 'Mark as Reviewed?';
                    text = 'Are you sure you want to mark this application as reviewed?';
                    confirmButtonText = 'Yes, Mark Reviewed';
                    icon = 'info';
                    confirmButtonColor = '#3b82f6';
                    break;
                    
                default: // pending
                    title = 'Update Status?';
                    text = 'Are you sure you want to change this application status to pending?';
                    confirmButtonText = 'Yes, Update';
                    icon = 'question';
                    confirmButtonColor = '#f59e0b';
            }
            
            // Show confirmation dialog
            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#6b7280',
                confirmButtonText: confirmButtonText,
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Updating application status',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the form normally (not via JavaScript)
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'applications.php';
                    
                    // Add all form data as hidden inputs
                    for (let [key, value] of formData.entries()) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }
                    
                    // Add update_status field
                    const updateInput = document.createElement('input');
                    updateInput.type = 'hidden';
                    updateInput.name = 'update_status';
                    updateInput.value = '1';
                    form.appendChild(updateInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    }
});

// Logout confirmation
logoutLink.addEventListener('click', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: 'You will be logged out from your account.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log out',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = this.href;
        }
    });
});

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

// Show success/error messages
<?php if (isset($success_message)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: <?= json_encode($success_message) ?>,
        timer: 3000,
        showConfirmButton: false
    });
<?php elseif (isset($error_message)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: <?= json_encode($error_message) ?>
    });
<?php endif; ?>
</script>
</body>
</html>