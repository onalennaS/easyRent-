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

// Get lease ID from URL parameter
$lease_id = isset($_GET['lease_id']) ? (int)$_GET['lease_id'] : 0;

// Get landlord ID from session
$landlord_id = (int)$_SESSION['user_id'];

// Initialize lease data
$lease_data = null;

// Get lease information with verification that it belongs to this landlord
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
        l.signed_at,
        p.title AS property_title,
        p.address AS property_address,
        CONCAT(u_landlord.first_name, ' ', u_landlord.last_name) AS landlord_name,
        u_landlord.email AS landlord_email,
        u_landlord.phone AS landlord_phone,
        CONCAT(u_tenant.first_name, ' ', u_tenant.last_name) AS tenant_name,
        u_tenant.email AS tenant_email,
        u_tenant.phone AS tenant_phone
    FROM leases l
    JOIN properties p ON l.property_id = p.id
    JOIN users u_landlord ON l.landlord_id = u_landlord.id
    JOIN users u_tenant ON l.tenant_id = u_tenant.id
    WHERE l.id = $lease_id
    AND p.landlord_id = $landlord_id
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
    
    // Calculate remaining lease days
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
} else {
    $error_message = "Lease not found or you don't have permission to view it";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lease Agreement - EasyRent</title>
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
            line-height: 1.6;
        }

        .top-nav {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-actions a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .nav-actions a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
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

        .main-content {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #1e293b;
            font-size: 2.2rem;
            font-weight: 700;
        }

        .view-type {
            background: #eff6ff;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            color: #3b82f6;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
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

        .signature-display {
            height: 140px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-style: italic;
            color: #64748b;
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

        .landlord-view-notice {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 0 8px 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
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
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .header {
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
            
            .nav-actions {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="logo">
            <i class="fas fa-home"></i>
            <span>EasyRent</span>
        </div>
        
        <div class="nav-actions">
            <a href="landlord_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="my_properties.php"><i class="fas fa-building"></i> My Properties</a>
            <a href="tenants.php"><i class="fas fa-users"></i> Tenants</a>
            
            <div class="user-profile">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?>
                </div>
                <span>Landlord: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Landlord'); ?></span>
                <a href="../auth/logout.php" style="color: white; margin-left: 1rem;" id="logoutLink">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Lease Agreement #<?php echo $lease_id; ?></h1>
            <div class="view-type">
                <i class="fas fa-eye"></i> Landlord View
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($lease_data): ?>
            <div class="landlord-view-notice">
                <i class="fas fa-info-circle" style="color: #3b82f6; font-size: 1.2rem;"></i>
                <div>
                    <strong>Landlord View</strong> - You're seeing this lease exactly as it appears to your tenant.
                    This is a read-only view of the lease agreement.
                </div>
            </div>
            
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
                                        <p><?php echo $lease_data['tenant_name']; ?></p>
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
                                    <div class="col-md-6">
                                        <h3 class="h6 text-muted">Security Deposit</h3>
                                        <p>R<?php echo number_format($lease_data['security_deposit'], 2); ?></p>
                                    </div>
                                </div>
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
                                        <li class="d-flex justify-content-between py-2 border-bottom">
                                            <span>Utilities Responsibility</span>
                                            <span>Tenant pays all</span>
                                        </li>
                                        <li class="d-flex justify-content-between py-2">
                                            <span>Renewal Terms</span>
                                            <span>Automatic 1 year renewal</span>
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

                        <!-- Tenant Contact -->
                        <div class="card contact-card">
                            <div class="card-header">Tenant Contact</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h3 class="h6 text-muted">Name</h3>
                                    <p><?php echo $lease_data['tenant_name']; ?></p>
                                </div>
                                <div class="mb-3">
                                    <h3 class="h6 text-muted">Email</h3>
                                    <p><?php echo $lease_data['tenant_email']; ?></p>
                                </div>
                                <div>
                                    <h3 class="h6 text-muted">Phone</h3>
                                    <p><?php echo $lease_data['tenant_phone']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signature Section -->
                <div class="signature-area">
                    <h3 class="h5 mb-3">Lease Agreement Signatures</h3>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="signature-display">
                                <?php if ($lease_data['status'] == 'pending'): ?>
                                    <span class="text-muted">Signature Pending</span>
                                <?php else: ?>
                                    <span class="text-muted">Signed on <?php echo date('M j, Y', strtotime($lease_data['signed_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="signature-line">
                                <div class="signature-label">Tenant Signature</div>
                                <div class="signature-name"><?php echo $lease_data['tenant_name']; ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="signature-display">
                                <span class="text-muted">Signed on <?php echo date('M j, Y', strtotime($lease_data['signed_at'])); ?></span>
                            </div>
                            
                            <div class="signature-line">
                                <div class="signature-label">Landlord Signature</div>
                                <div class="signature-name"><?php echo $lease_data['landlord_name']; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <a href="generate_lease_pdf.php?lease_id=<?php echo $lease_data['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-file-pdf"></i> Download Lease PDF
                        </a>
                        <a href="tenants.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Tenants
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
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
    </script>
</body>
</html>
<?php
// Close database connection
mysqli_close($conn);
?>