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

// Fetch terminated leases
$terminated_query = "
    SELECT 
        l.id AS lease_id,
        l.lease_start_date,
        l.lease_end_date,
        l.termination_date,
        l.termination_reason,
        l.monthly_rent,
        l.security_deposit,
        CONCAT(u.first_name, ' ', u.last_name) AS tenant_name,
        u.email AS tenant_email,
        u.phone AS tenant_phone,
        p.title AS property_title,
        p.address AS property_address
    FROM leases l
    JOIN users u ON l.tenant_id = u.id
    JOIN properties p ON l.property_id = p.id
    WHERE l.landlord_id = $landlord_id
    AND l.status = 'terminated'
    ORDER BY l.termination_date DESC
";

$terminated_result = mysqli_query($conn, $terminated_query);
$terminated_leases = [];
if ($terminated_result) {
    while ($row = mysqli_fetch_assoc($terminated_result)) {
        $terminated_leases[] = $row;
    }
} else {
    $error_message = "Database error: " . mysqli_error($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminated Leases - EasyRent</title>
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
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

        /* Terminated Leases Specific Styles */
        .terminated-lease-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }

        .terminated-badge {
            background-color: #ef4444;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .tenant-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .tenant-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: 1rem;
        }

        .tenant-info h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .tenant-info p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .tenant-details {
            margin-bottom: 1.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .detail-label {
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            color: #1e293b;
            font-weight: 500;
            text-align: right;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .empty-state p {
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Header buttons */
        .header-buttons {
            display: flex;
            gap: 1rem;
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
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-file-contract"></i>
                Terminated Leases
            </h1>
            <div class="header-buttons">
                <a href="tenants.php" class="btn btn-secondary">
                    <i class="fas fa-users"></i>
                    Back to Tenants
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Terminated Leases List -->
        <div class="terminated-leases">
            <?php if (!empty($terminated_leases)): ?>
                <?php foreach ($terminated_leases as $lease): ?>
                    <div class="terminated-lease-card">
                        <div class="tenant-header">
                            <div class="tenant-avatar">
                                <?php echo strtoupper(substr($lease['tenant_name'], 0, 1)); ?>
                            </div>
                            <div class="tenant-info">
                                <h3><?php echo htmlspecialchars($lease['tenant_name']); ?></h3>
                                <p><?php echo htmlspecialchars($lease['property_title']); ?></p>
                                <span class="terminated-badge">Terminated</span>
                            </div>
                        </div>
                        
                        <div class="tenant-details">
                            <div class="detail-row">
                                <span class="detail-label">Lease Period:</span>
                                <span class="detail-value">
                                    <?php echo date('M j, Y', strtotime($lease['lease_start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($lease['lease_end_date'])); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Termination Date:</span>
                                <span class="detail-value">
                                    <?php echo date('M j, Y', strtotime($lease['termination_date'])); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Termination Reason:</span>
                                <span class="detail-value">
                                    <?php echo htmlspecialchars($lease['termination_reason']); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Rent Amount:</span>
                                <span class="detail-value">R<?php echo number_format($lease['monthly_rent']); ?>/month</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Security Deposit:</span>
                                <span class="detail-value">R<?php echo number_format($lease['security_deposit']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-contract"></i>
                    <h3>No Terminated Leases</h3>
                    <p>You don't have any terminated leases yet.</p>
                    <a href="tenants.php" class="btn btn-primary">
                        <i class="fas fa-users"></i>
                        View Active Tenants
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Show success/error messages
        <?php if (isset($success_message)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo addslashes($success_message); ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo addslashes($error_message); ?>'
            });
        <?php endif; ?>
    </script>
</body>
</html>