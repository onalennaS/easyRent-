<?php
session_start();

// Redirect if not logged in
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

// Get landlord ID from session
$landlord_id = (int)$_SESSION['user_id'];

// Fixed SQL query - removed PHP-style comment
$tenants_query = "
    SELECT 
        u.id AS tenant_id,
        CONCAT(u.first_name, ' ', u.last_name) AS tenant_name,
        u.email AS tenant_email,
        u.phone AS tenant_phone,
        p.title AS property_title,
        p.address AS property_address,
        l.id AS lease_id,
        l.lease_start_date,
        l.lease_end_date,
        l.monthly_rent,
        l.security_deposit,
        l.status AS lease_status,
        l.signed_at
    FROM leases l
    JOIN properties p ON l.property_id = p.id
    JOIN users u ON l.tenant_id = u.id
    WHERE p.landlord_id = $landlord_id
    AND l.status IN ('active', 'pending')
    ORDER BY l.lease_end_date DESC
";

$tenants_result = mysqli_query($conn, $tenants_query);
$tenants = [];
if ($tenants_result) {
    while ($row = mysqli_fetch_assoc($tenants_result)) {
        $tenants[] = $row;
    }
} else {
    $error_message = "Database error: " . mysqli_error($conn);
}

// ... rest of the code remains the same ...

// Handle lease activation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['activate_lease'])) {
    $lease_id = intval($_POST['lease_id']);
    
    $update_query = "UPDATE leases SET status = 'active' WHERE id = $lease_id";
    
    if (mysqli_query($conn, $update_query)) {
        $success_message = "Lease activated successfully!";
        // Refresh tenants data
        $tenants_result = mysqli_query($conn, $tenants_query);
        $tenants = [];
        if ($tenants_result) {
            while ($row = mysqli_fetch_assoc($tenants_result)) {
                $tenants[] = $row;
            }
        }
    } else {
        $error_message = "Error activating lease: " . mysqli_error($conn);
    }
}

// Handle lease termination
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['terminate_lease'])) {
    $lease_id = intval($_POST['lease_id']);
    
    $update_query = "UPDATE leases SET status = 'terminated' WHERE id = $lease_id";
    
    if (mysqli_query($conn, $update_query)) {
        $success_message = "Lease terminated successfully!";
        // Refresh tenants data
        $tenants_result = mysqli_query($conn, $tenants_query);
        $tenants = [];
        if ($tenants_result) {
            while ($row = mysqli_fetch_assoc($tenants_result)) {
                $tenants[] = $row;
            }
        }
    } else {
        $error_message = "Error terminating lease: " . mysqli_error($conn);
    }
}

// Handle lease view request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['view_lease'])) {
    $lease_id = intval($_POST['lease_id']);
    header("Location: view_lease.php?lease_id=$lease_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tenants - EasyRent</title>
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
            padding: 2rem;
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
            margin-bottom: 1rem;
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
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
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

        .stat-card.tenants { --accent-color: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-card.income { --accent-color: linear-gradient(135deg, #10b981 0%, #047857 100%); }
        .stat-card.active { --accent-color: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
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
            font-size: 2rem;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-weight: 500;
        }

        /* Content Section */
        .content-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-filter {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .filter-select {
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            font-size: 1rem;
        }

        /* Tenants Table */
        .tenants-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tenants-table thead {
            background: #f1f5f9;
        }

        .tenants-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #1e293b;
            border-bottom: 1px solid #e2e8f0;
        }

        .tenants-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .tenants-table tr:last-child td {
            border-bottom: none;
        }

        .tenants-table tr:hover {
            background-color: #f8fafc;
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

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-terminated {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-expired {
            background: #e0e7ff;
            color: #4338ca;
        }

        .lease-actions {
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

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .tenants-table {
                display: block;
                overflow-x: auto;
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
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .search-filter {
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
                <li><a href="landlord_dashboard.php">Dashboard</a></li>
                <li><a href="my_properties.php">My Properties</a></li>
                <li><a href="applications.php">Applications</a></li>
                <li><a href="add_property.php">Add Property</a></li>
                <li><a href="maintenance.php">Maintenance</a></li>
                <li><a href="tenants.php" class="active">Tenants</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
            
            <div class="user-profile">
                <span>Welcome, <?php echo $_SESSION['user_name'] ?? 'Landlord'; ?></span>
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?>
                </div>
                <a href="../auth/logout.php" style="color: white; margin-left: 1rem;" id="logoutLink">
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
                <h1 class="hero-title">Tenant Management</h1>
                <p class="hero-subtitle">Manage your tenants and lease agreements</p>
                
                <div class="quick-actions">
                    <a href="applications.php" class="quick-action-btn">
                        <i class="fas fa-user-plus"></i>
                        View Applications
                    </a>
                    <a href="reports.php" class="quick-action-btn">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Generate Rent Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <?php 
        $total_tenants = count($tenants);
        $active_tenants = array_filter($tenants, function($t) { return $t['lease_status'] === 'active'; });
        $pending_tenants = array_filter($tenants, function($t) { return $t['lease_status'] === 'pending'; });
        $monthly_income = array_reduce($active_tenants, function($carry, $t) { 
            return $carry + $t['monthly_rent']; 
        }, 0);
        ?>
        
        <div class="stats-grid">
            <!-- Stats content remains the same -->
        </div>

        <!-- Tenants Section -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-users"></i>
                    My Tenants
                </h2>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error" style="background-color: #fee2e2; color: #b91c1c; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success" style="background-color: #dcfce7; color: #166534; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="search-filter">
                <input type="text" class="search-input" placeholder="Search tenants..." id="searchInput">
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="terminated">Terminated</option>
                </select>
            </div>
            
            <?php if (!empty($tenants)): ?>
                <table class="tenants-table" id="tenantsTable">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Property</th>
                            <th>Lease Dates</th>
                            <th>Rent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                            <tr data-status="<?php echo $tenant['lease_status']; ?>">
                                <td>
                                    <div class="tenant-info">
                                        <span class="tenant-name"><?php echo $tenant['tenant_name']; ?></span>
                                        <span class="tenant-contact"><?php echo $tenant['tenant_email']; ?></span>
                                        <span class="tenant-contact"><?php echo $tenant['tenant_phone']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="tenant-info">
                                        <span class="tenant-name"><?php echo $tenant['property_title']; ?></span>
                                        <span class="tenant-contact"><?php echo $tenant['property_address']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="tenant-info">
                                        <span>Start: <?php echo date('M j, Y', strtotime($tenant['lease_start_date'])); ?></span>
                                        <span>End: <?php echo date('M j, Y', strtotime($tenant['lease_end_date'])); ?></span>
                                    </div>
                                </td>
                                <td>R<?php echo number_format($tenant['monthly_rent'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $tenant['lease_status']; ?>">
                                        <?php echo ucfirst($tenant['lease_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="lease-actions">
                                        <!-- View Lease Button -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="lease_id" value="<?php echo $tenant['lease_id']; ?>">
                                            <button type="submit" name="view_lease" class="btn btn-secondary">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </form>
                                        
                                        <!-- PDF Download Button -->
                                        <a href="generate_lease_pdf.php?lease_id=<?php echo $tenant['lease_id']; ?>" 
                                           class="btn btn-secondary" target="_blank">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        
                                        <?php if ($tenant['lease_status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="lease_id" value="<?php echo $tenant['lease_id']; ?>">
                                                <button type="submit" name="activate_lease" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Activate
                                                </button>
                                            </form>
                                        <?php elseif ($tenant['lease_status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="lease_id" value="<?php echo $tenant['lease_id']; ?>">
                                                <button type="submit" name="terminate_lease" class="btn btn-danger">
                                                    <i class="fas fa-times"></i> Terminate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>No Tenants Found</h3>
                    <p>You currently don't have any tenants. When tenants sign leases for your properties, they'll appear here.</p>
                    <a href="applications.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-user-plus"></i> View Applications
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search and filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const tableRows = document.querySelectorAll('#tenantsTable tbody tr');
            
            // Search functionality
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    tableRows.forEach(row => {
                        const tenantName = row.cells[0].textContent.toLowerCase();
                        const propertyName = row.cells[1].textContent.toLowerCase();
                        
                        if (tenantName.includes(searchTerm) || propertyName.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            // Filter by status
            if (statusFilter) {
                statusFilter.addEventListener('change', function() {
                    const selectedStatus = this.value;
                    
                    tableRows.forEach(row => {
                        const rowStatus = row.getAttribute('data-status');
                        
                        if (selectedStatus === 'all' || rowStatus === selectedStatus) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
            
            // Logout confirmation
            document.getElementById('logoutLink').addEventListener('click', function(e) {
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
        });
    </script>
</body>
</html>