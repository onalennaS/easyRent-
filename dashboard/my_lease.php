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

// Get tenant ID from session
$tenant_id = (int)($_SESSION['user_id'] ?? 0);
$tenant_name = $_SESSION['user_name'] ?? 'Tenant';

// Initialize lease data
$lease_data = null;

// Get lease information for this tenant
$lease_query = "
    SELECT 
        l.id,
        l.property_id,
        l.landlord_id,
        l.tenant_id,
        l.application_id,
        l.lease_start_date AS start_date,
        l.lease_end_date AS end_date,
        l.monthly_rent,
        l.security_deposit,
        l.status,
        p.title AS property_title,
        p.address AS property_address,
        CONCAT(u.first_name, ' ', u.last_name) AS landlord_name,
        u.email AS landlord_email,
        u.phone AS landlord_phone
    FROM leases l
    JOIN properties p ON l.property_id = p.id
    JOIN users u ON l.landlord_id = u.id
    WHERE l.tenant_id = $tenant_id
    ORDER BY l.lease_end_date DESC
    LIMIT 1
";

$lease_result = mysqli_query($conn, $lease_query);

// Check if query executed successfully
if (!$lease_result) {
    die("Database query failed: " . mysqli_error($conn));
}

// Check if we have lease data
if (mysqli_num_rows($lease_result) > 0) {
    $lease_data = mysqli_fetch_assoc($lease_result);
    
    // Fix lease end date if it's the default 1970-01-01
    if ($lease_data['end_date'] == '1970-01-01' && !empty($lease_data['start_date'])) {
        $start = new DateTime($lease_data['start_date']);
        $start->add(new DateInterval('P1Y')); // Add 1 year
        $lease_data['end_date'] = $start->format('Y-m-d');
    }
}

// Handle lease signing
$sign_success = false;
$sign_error = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sign_lease'])) {
    $lease_id = (int)$_POST['lease_id'];
    
    // Use prepared statement to prevent SQL injection
    $sign_query = "
        UPDATE leases 
        SET signed_at = NOW(), 
            status = 'active'
        WHERE id = ? 
          AND tenant_id = ? 
          AND status = 'pending'
    ";
    
    $stmt = mysqli_prepare($conn, $sign_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $lease_id, $tenant_id);
        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $sign_success = true;
                // Refresh lease data
                $lease_result = mysqli_query($conn, $lease_query);
                $lease_data = mysqli_fetch_assoc($lease_result);
            } else {
                $sign_error = "Lease not found or already signed.";
            }
        } else {
            $sign_error = "Execution failed: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    } else {
        $sign_error = "Prepare failed: " . mysqli_error($conn);
    }
}

// Calculate remaining lease days
if ($lease_data) {
    $today = new DateTime();
    $start_date = new DateTime($lease_data['start_date']);
    $end_date = new DateTime($lease_data['end_date']);

    // Calculate the total days of the lease
    $totalDays = $start_date->diff($end_date)->days;

    // Calculate the remaining days and progress
    if ($today < $start_date) {
        // Lease hasn't started
        $daysPassed = 0;
        $remaining = $today->diff($end_date)->format('%a days');
        $percentage = 0;
    } elseif ($today > $end_date) {
        // Lease has expired
        $daysPassed = $totalDays;
        $remaining = 'Lease expired';
        $percentage = 100;
    } else {
        // Lease is active
        $daysPassed = $start_date->diff($today)->days;
        $remaining = $today->diff($end_date)->format('%a days');
        $percentage = ($daysPassed / $totalDays) * 100;
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        
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

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #1e293b;
            font-size: 28px;
            font-weight: 700;
        }

        .tenant-info {
            font-size: 16px;
            color: #475569;
        }

        .tenant-info span {
            font-weight: 500;
            color: #1e40af;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            font-weight: 500;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background-color: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.04);
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section h2 {
            color: #1e293b;
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .results-summary {
            background-color: #f1f5f9;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            color: #475569;
        }

        .lease-container {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }

        .lease-header {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
            color: white;
            border-radius: 12px;
            padding: 1.8rem;
            margin-bottom: 2rem;
        }

        .status-badge {
            font-size: 0.9rem;
            padding: 6px 16px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
        }

        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-expired {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: none;
            overflow: hidden;
        }

        .card-header {
            padding: 1.2rem 1.5rem;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 18px;
            color: #1e293b;
        }

        .card-body {
            padding: 1.5rem;
        }

        .key-dates {
            background-color: #f8fafc;
        }

        .contact-card {
            background-color: #eff6ff;
        }

        .progress {
            height: 10px;
            margin-top: 10px;
            border-radius: 5px;
            background-color: #e2e8f0;
            overflow: visible;
        }

        .progress-bar {
            background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 5px;
        }

        .signature-area {
            margin-top: 2.5rem;
            padding: 2rem;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
        }

        .signature-pad {
            height: 140px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            cursor: crosshair;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }

        .signature-line {
            border-top: 1px solid #cbd5e1;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .signature-label {
            color: #64748b;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .signature-name {
            font-weight: 600;
            margin-top: 0.25rem;
            color: #1e293b;
            font-size: 18px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #3b82f6;
            color: #3b82f6;
        }

        .btn-outline:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(245, 158, 11, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 3.5rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
            max-width: 600px;
            margin: 0 auto;
        }

        .empty-state-icon {
            font-size: 5rem;
            color: #cbd5e1;
            margin-bottom: 1.8rem;
        }

        .empty-state-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.2rem;
        }

        .empty-state-text {
            color: #64748b;
            margin-bottom: 1.8rem;
            font-size: 1.1rem;
            line-height: 1.7;
        }

        .btn-apply {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .btn-apply:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(245, 158, 11, 0.3);
            color: white;
        }

        .h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.2rem;
        }

        .h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .h6 {
            font-size: 1rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .text-muted {
            color: #64748b;
        }

        .d-flex {
            display: flex;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .align-items-center {
            align-items: center;
        }

        .w-100 {
            width: 100%;
        }

        .py-3 {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .mt-4 {
            margin-top: 1.5rem;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        .mb-4 {
            margin-bottom: 1.5rem;
        }

        .gap-3 {
            gap: 1rem;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }

        .col-md-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
            padding: 0 15px;
        }

        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            padding: 0 15px;
        }

        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
        }

        .list-unstyled {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .border-bottom {
            border-bottom: 1px solid #e2e8f0;
        }

        .py-2 {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        @media (max-width: 992px) {
            .col-md-8, .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .main-content {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
            }
            
            .lease-container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
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
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .signature-area {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>EasyRent</h2>
            <p>Tenant Portal</p>
        </div>
        <ul>
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="browse_properties.php"><i class="fas fa-search"></i> Browse Properties</a></li>
            <li><a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a></li>
            <li><a href="my_lease.php" class="active"><i class="fas fa-file-contract"></i> My Lease</a></li>
            <li><a href="maintenance_requests.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="payment_history.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="tenant_profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Lease Agreement</h1>
            <div class="tenant-info">
                Tenant: <span><?php echo htmlspecialchars($tenant_name); ?></span>
            </div>
        </div>

        <?php if ($sign_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Your lease has been signed successfully!
            </div>
        <?php endif; ?>
        
        <?php if ($sign_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $sign_error; ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <div class="section-header">
                <h2>
                    <i class="fas fa-file-contract"></i>
                    Lease Details
                </h2>
                <div class="results-summary">
                    <?php echo $lease_data ? 'Active lease' : 'No active lease'; ?>
                </div>
            </div>
            
            <?php if ($lease_data): ?>
                <div class="lease-container">
                    <div class="lease-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h3 mb-1">Lease Agreement #<?php echo $lease_data['id']; ?></h1>
                                <p class="mb-0">Property: <?php echo $lease_data['property_title']; ?></p>
                            </div>
                            <span class="status-badge status-<?php echo $lease_data['status']; ?>">
                                <?php echo ucfirst($lease_data['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Main Lease Details -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">Agreement Details</div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h3 class="h6 text-muted">Tenant</h3>
                                            <p><?php echo $tenant_name; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h3 class="h6 text-muted">Property</h3>
                                            <p><?php echo $lease_data['property_address']; ?></p>
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h3 class="h6 text-muted">Landlord</h3>
                                            <p><?php echo $lease_data['landlord_name']; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h3 class="h6 text-muted">Monthly Rent</h3>
                                            <p>R<?php echo number_format($lease_data['monthly_rent'], 2); ?></p>
                                        </div>
                                    </div>

                                    <div class="row">
        <div class="col-md-6">
            <h3 class="h6 text-muted">Lease Status</h3>
            <span class="status-badge status-<?php echo $lease_data['status']; ?>">
                <?php echo ucfirst($lease_data['status']); ?>
            </span>
        </div>
       
    </div>
                            <!-- Terms and Conditions -->
                            <div class="card">
                                <div class="card-header">Lease Terms</div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h3 class="h6 text-muted">Lease Start Date</h3>
                                            <p><?php echo date('M j, Y', strtotime($lease_data['start_date'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h3 class="h6 text-muted">Lease End Date</h3>
                                            <p><?php echo date('M j, Y', strtotime($lease_data['end_date'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h3 class="h6 text-muted">Key Terms</h3>
                                        <ul class="list-unstyled">
                                            <li class="d-flex justify-content-between py-2 border-bottom">
                                                <span>Late Payment Fee</span>
                                                <span>R150.00</span>
                                            </li>
                                            <li class="d-flex justify-content-between py-2 border-bottom">
                                                <span>Pet Deposit (if applicable)</span>
                                                <span>R500.00</span>
                                            </li>
                                            <li class="d-flex justify-content-between py-2 border-bottom">
                                                <span>Maintenance Responsibility</span>
                                                <span>Tenant minor repairs</span>
                                            </li>
                                            <li class="d-flex justify-content-between py-2 border-bottom">
                                                <span>Notice Period</span>
                                                <span>60 days</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Key Dates -->
                        <div class="col-md-4">
                            <div class="card key-dates">
                                <div class="card-header">Lease Timeline</div>
                                <div class="card-body">
                                    <div class="mb-4">
                                        <h3 class="h6 text-muted">Lease Start</h3>
                                        <p><?php echo date('M j, Y', strtotime($lease_data['start_date'])); ?></p>
                                    </div>
                                    <div class="mb-4">
                                        <h3 class="h6 text-muted">Lease End</h3>
                                        <p><?php echo date('M j, Y', strtotime($lease_data['end_date'])); ?></p>
                                    </div>
                                    <div>
                                        <h3 class="h6 text-muted">Time Remaining</h3>
                                        <p><?php echo $remaining; ?></p>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                style="width: <?= $percentage ?>%" 
                                                aria-valuenow="<?= $percentage ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Landlord Contact -->
                            <div class="card contact-card">
                                <div class="card-header">Landlord Contact</div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h3 class="h6 text-muted">Name</h3>
                                        <p><?php echo $lease_data['landlord_name']; ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <h3 class="h6 text-muted">Email</h3>
                                        <p><?php echo $lease_data['landlord_email']; ?></p>
                                    </div>
                                    <div>
                                        <h3 class="h6 text-muted">Phone</h3>
                                        <p><?php echo $lease_data['landlord_phone']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Signature Section -->
                    <?php if ($lease_data['status'] == 'pending'): ?>
                        <div class="signature-area">
                            <h3 class="h5 mb-3">Sign Your Lease Agreement</h3>
                            <p class="text-muted">Please review the lease terms above and sign below to accept the agreement.</p>
                            
                            <div class="signature-pad" id="signaturePad"></div>
                            
                            <div class="d-flex justify-content-between">
                                <button class="btn btn-outline" id="clearSignature">
                                    <i class="fas fa-eraser"></i> Clear Signature
                                </button>
                                <button class="btn btn-primary" id="saveSignature">
                                    <i class="fas fa-save"></i> Save Signature
                                </button>
                            </div>
                            
                            <div class="signature-line">
                                <div class="signature-label">Tenant Signature</div>
                                <div class="signature-name"><?php echo $tenant_name; ?></div>
                            </div>
                            
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="lease_id" value="<?php echo $lease_data['id']; ?>">
                                <button type="submit" name="sign_lease" class="btn btn-success w-100 py-3">
                                    <i class="fas fa-signature"></i> Sign Lease Agreement
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
    <div class="d-flex justify-content-center gap-3 mt-4">
        <a href="generate_lease_pdf.php?lease_id=<?php echo $lease_data['id']; ?>" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Download Lease PDF
        </a>
        <?php if ($lease_data['status'] == 'active'): ?>
            <button class="btn btn-warning" id="renewLease">
                <i class="fas fa-sync-alt"></i> Request Renewal
            </button>
        <?php endif; ?>
        
        
<?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h2 class="empty-state-title">No Active Lease Found</h2>
                    <p class="empty-state-text">
                        You don't currently have an active lease agreement. Once you apply for a property and get approved, 
                        your lease will appear here for signing.
                    </p>
                    <a href="browse_properties.php" class="btn btn-apply">
                        <i class="fas fa-search"></i> Browse Available Properties
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Signature pad functionality
        document.addEventListener('DOMContentLoaded', function() {
            const signaturePad = document.getElementById('signaturePad');
            if (signaturePad) {
                const canvas = document.createElement('canvas');
                signaturePad.appendChild(canvas);
                
                const ctx = canvas.getContext('2d');
                canvas.width = signaturePad.offsetWidth;
                canvas.height = signaturePad.offsetHeight;
                ctx.strokeStyle = '#1e3a8a';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                
                let drawing = false;
                let lastX = 0;
                let lastY = 0;
                
                // Resize canvas on window resize
                window.addEventListener('resize', () => {
                    canvas.width = signaturePad.offsetWidth;
                    canvas.height = signaturePad.offsetHeight;
                    ctx.strokeStyle = '#1e3a8a';
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                });
                
                // Start drawing
                canvas.addEventListener('mousedown', (e) => {
                    drawing = true;
                    [lastX, lastY] = [e.offsetX, e.offsetY];
                });
                
                // Draw
                canvas.addEventListener('mousemove', (e) => {
                    if (!drawing) return;
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(e.offsetX, e.offsetY);
                    ctx.stroke();
                    [lastX, lastY] = [e.offsetX, e.offsetY];
                });
                
                // Stop drawing
                canvas.addEventListener('mouseup', () => drawing = false);
                canvas.addEventListener('mouseout', () => drawing = false);
                
                // Touch support
                canvas.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    const rect = canvas.getBoundingClientRect();
                    const touch = e.touches[0];
                    drawing = true;
                    [lastX, lastY] = [touch.clientX - rect.left, touch.clientY - rect.top];
                });
                
                canvas.addEventListener('touchmove', (e) => {
                    e.preventDefault();
                    if (!drawing) return;
                    const rect = canvas.getBoundingClientRect();
                    const touch = e.touches[0];
                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
                    ctx.stroke();
                    [lastX, lastY] = [touch.clientX - rect.left, touch.clientY - rect.top];
                });
                
                canvas.addEventListener('touchend', () => drawing = false);
                
                // Clear signature
                document.getElementById('clearSignature').addEventListener('click', (e) => {
                    e.preventDefault();
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                });
                
                // Save signature
                document.getElementById('saveSignature').addEventListener('click', (e) => {
                    e.preventDefault();
                    // In a real implementation, this would save the signature to the server
                    Swal.fire({
                        icon: 'success',
                        title: 'Signature Saved!',
                        text: 'Your signature has been saved successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            }
            
            // Renew lease button
            const renewBtn = document.getElementById('renewLease');
            if (renewBtn) {
                renewBtn.addEventListener('click', () => {
                    Swal.fire({
                        title: 'Request Lease Renewal?',
                        text: 'This will send a request to your landlord to renew your lease agreement.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Send Request',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#10b981',
                        cancelButtonColor: '#64748b',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Request Sent!',
                                text: 'Your renewal request has been sent to the landlord.',
                                timer: 3000,
                                showConfirmButton: false
                            });
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
        });
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>