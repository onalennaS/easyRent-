<?php
session_start();

// Redirect if not logged in or not a tenant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tenant') {
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

// Get lease ID from URL
if (!isset($_GET['id'])) {
    header("Location: my_lease.php");
    exit();
}

$lease_id = (int)$_GET['id'];
$tenant_id = (int)$_SESSION['user_id'];

// Fetch lease details - ensure it belongs to this tenant
$lease_query = "
    SELECT 
        l.*,
        p.title AS property_title,
        p.address AS property_address,
        p.description AS property_description,
        lt.template_name,
        lt.content AS template_content,
        CONCAT(landlord.first_name, ' ', landlord.last_name) AS landlord_name,
        landlord.email AS landlord_email,
        landlord.phone AS landlord_phone,
        CONCAT(tenant.first_name, ' ', tenant.last_name) AS tenant_name,
        tenant.email AS tenant_email,
        tenant.phone AS tenant_phone,
        l.signed_date AS landlord_signed_date,
        l.tenant_signed_date
    FROM leases l
    JOIN properties p ON l.property_id = p.id
    LEFT JOIN lease_templates lt ON l.template_id = lt.id
    JOIN users landlord ON l.landlord_id = landlord.id
    JOIN users tenant ON l.tenant_id = tenant.id
    WHERE l.id = $lease_id
    AND l.tenant_id = $tenant_id
";

$lease_result = mysqli_query($conn, $lease_query);

if (!$lease_result || mysqli_num_rows($lease_result) === 0) {
    $_SESSION['error_message'] = "Lease not found or you don't have permission to view it.";
    header("Location: my_lease.php");
    exit();
}

$lease = mysqli_fetch_assoc($lease_result);

// Replace placeholders in template content
$content = $lease['template_content'] ?? '';
$replacements = [
    '[DATE]' => date('F j, Y', strtotime($lease['created_at'])),
    '[LANDLORD_NAME]' => $lease['landlord_name'],
    '[LANDLORD_ADDRESS]' => 'Not specified',
    '[TENANT_NAME]' => $lease['tenant_name'],
    '[TENANT_ADDRESS]' => 'Not specified',
    '[PROPERTY_ADDRESS]' => $lease['property_address'],
    '[START_DATE]' => date('F j, Y', strtotime($lease['lease_start_date'])),
    '[END_DATE]' => date('F j, Y', strtotime($lease['lease_end_date'])),
    '[RENT_AMOUNT]' => number_format($lease['monthly_rent'] ?? $lease['rent_amount'] ?? 0, 2),
    '[DEPOSIT_AMOUNT]' => number_format($lease['security_deposit'], 2)
];

foreach ($replacements as $placeholder => $value) {
    $content = str_replace($placeholder, $value, $content);
}

// Add additional terms if they exist
if (!empty($lease['terms'])) {
    $content .= "\n\n<h4>Additional Terms</h4>\n<p>" . nl2br(htmlspecialchars($lease['terms'])) . "</p>";
}

// Get tenant name for header
$tenant_name = '';
if (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
    $tenant_name = $_SESSION['user_name'];
} else {
    $tenant_name = $lease['tenant_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lease Agreement - L&T Connect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        /* Sidebar */
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
            overflow-y: auto;
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

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .action-buttons {
            display: flex;
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
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-expired {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Lease Meta Cards */
        .lease-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .meta-card {
            flex: 1;
            min-width: 200px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid #e2e8f0;
        }

        .meta-title {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .meta-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Lease Content */
        .lease-content {
            padding: 2rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            line-height: 1.8;
        }

        .lease-content h1, .lease-content h2, .lease-content h3, .lease-content h4 {
            color: #1e293b;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }

        .lease-content p {
            margin-bottom: 1rem;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }

        .signature-section h3 {
            margin-bottom: 2rem;
        }

        .signature-line {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        .signature-block {
            width: 45%;
        }

        .signature-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .signature-img {
            height: 80px;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid #94a3b8;
        }

        .signature-img img {
            max-height: 80px;
        }

        .signature-date {
            margin-top: 1rem;
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .action-buttons,
            .btn {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                padding: 0;
            }

            .container {
                box-shadow: none;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                width: 100%;
            }

            .btn {
                width: 100%;
            }

            .lease-meta {
                flex-direction: column;
            }

            .signature-line {
                flex-direction: column;
            }

            .signature-block {
                width: 100%;
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="../logo.png" alt="L&T Connect" style="max-height: 42px; width: auto; display: block; margin-bottom: 0.75rem;">
            <p>Tenant Portal</p>
        </div>
        <ul>
            <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="tenant_profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="browse_properties.php"><i class="fas fa-search"></i> Browse Properties</a></li>
            <li><a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a></li>
            <li><a href="my_lease.php" class="active"><i class="fas fa-file-contract"></i> My Lease</a></li>
            <li><a href="maintenance_requests.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="payment_history.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="header">
                <h1 class="title">Lease Agreement</h1>
                <div class="action-buttons">
                    <a href="download_lease.php?id=<?php echo $lease_id; ?>" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download Lease
                    </a>
                    <a href="my_lease.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Leases
                    </a>
                </div>
            </div>

            <div class="lease-meta">
                <div class="meta-card">
                    <i class="fas fa-building meta-icon"></i>
                    <div class="meta-title"><i class="fas fa-home"></i> Property</div>
                    <div class="meta-value"><?php echo htmlspecialchars($lease['property_title']); ?></div>
                    <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #64748b;">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($lease['property_address']); ?>
                    </div>
                </div>

                <div class="meta-card">
                    <i class="fas fa-user-tie meta-icon"></i>
                    <div class="meta-title"><i class="fas fa-user-shield"></i> Landlord</div>
                    <div class="meta-value"><?php echo htmlspecialchars($lease['landlord_name']); ?></div>
                    <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #64748b;">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($lease['landlord_email']); ?>
                    </div>
                </div>

                <div class="meta-card">
                    <i class="fas fa-calendar meta-icon"></i>
                    <div class="meta-title"><i class="fas fa-calendar-alt"></i> Lease Period</div>
                    <div class="meta-value">
                        <?php echo date('M j, Y', strtotime($lease['lease_start_date'])); ?>
                    </div>
                    <div style="margin-top: 0.25rem; font-size: 0.9rem; color: #64748b;">
                        to <?php echo date('M j, Y', strtotime($lease['lease_end_date'])); ?>
                    </div>
                </div>

                <div class="meta-card">
                    <i class="fas fa-money-bill-wave meta-icon"></i>
                    <div class="meta-title"><i class="fas fa-dollar-sign"></i> Monthly Rent</div>
                    <div class="meta-value" style="color: #10b981;">
                        R<?php echo number_format($lease['monthly_rent'] ?? $lease['rent_amount'] ?? 0, 2); ?>
                    </div>
                </div>

                <div class="meta-card">
                    <i class="fas fa-shield-alt meta-icon"></i>
                    <div class="meta-title"><i class="fas fa-lock"></i> Security Deposit</div>
                    <div class="meta-value">
                        R<?php echo number_format($lease['security_deposit'], 2); ?>
                    </div>
                </div>

                <div class="meta-card">
                    <i class="fas fa-info-circle meta-icon"></i>
                    <div class="meta-title"><i class="fas fa-flag"></i> Status</div>
                    <div class="meta-value">
                        <span class="status-badge status-<?php echo strtolower($lease['status']); ?>">
                            <?php echo ucfirst($lease['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="lease-content">
                <?php if (!empty($content)): ?>
                    <?php echo $content; ?>
                <?php else: ?>
                    <h1>RESIDENTIAL LEASE AGREEMENT</h1>
                    
                    <p><strong>This Lease Agreement</strong> is made on <?php echo date('F j, Y', strtotime($lease['created_at'])); ?></p>
                    
                    <h2>BETWEEN</h2>
                    <p><strong>Landlord:</strong> <?php echo htmlspecialchars($lease['landlord_name']); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($lease['landlord_email']); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($lease['landlord_phone'] ?? 'Not provided'); ?></p>
                    
                    <p><strong>AND</strong></p>
                    
                    <p><strong>Tenant:</strong> <?php echo htmlspecialchars($lease['tenant_name']); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($lease['tenant_email']); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($lease['tenant_phone'] ?? 'Not provided'); ?></p>
                    
                    <h2>PROPERTY DETAILS</h2>
                    <p><strong>Property Address:</strong> <?php echo htmlspecialchars($lease['property_address']); ?></p>
                    <p><strong>Property Description:</strong> <?php echo htmlspecialchars($lease['property_description'] ?? 'Not provided'); ?></p>
                    
                    <h2>LEASE TERMS</h2>
                    <p><strong>Lease Start Date:</strong> <?php echo date('F j, Y', strtotime($lease['lease_start_date'])); ?></p>
                    <p><strong>Lease End Date:</strong> <?php echo date('F j, Y', strtotime($lease['lease_end_date'])); ?></p>
                    <p><strong>Monthly Rent:</strong> R<?php echo number_format($lease['monthly_rent'] ?? $lease['rent_amount'] ?? 0, 2); ?></p>
                    <p><strong>Security Deposit:</strong> R<?php echo number_format($lease['security_deposit'], 2); ?></p>
                    
                    <?php if (!empty($lease['terms'])): ?>
                        <h2>ADDITIONAL TERMS</h2>
                        <p><?php echo nl2br(htmlspecialchars($lease['terms'])); ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="signature-section">
                    <h3><i class="fas fa-pen-fancy"></i> Signatures</h3>
                    <div class="signature-line">
                        <div class="signature-block">
                            <div class="signature-label">
                                <i class="fas fa-user-tie"></i> Landlord Signature
                            </div>
                            <div class="signature-img">
                                <?php if (!empty($lease['signature_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($lease['signature_path']); ?>" alt="Landlord Signature">
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Signature pending</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($lease['signature_path'])): ?>
                                <div class="signature-status signed">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Signed</span>
                                </div>
                                <div class="signature-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo !empty($lease['landlord_signed_date']) ? date('F j, Y \a\t g:i A', strtotime($lease['landlord_signed_date'])) : 'Date not available'; ?>
                                </div>
                            <?php else: ?>
                                <div class="signature-status unsigned">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Not signed yet</span>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                                <strong><?php echo htmlspecialchars($lease['landlord_name']); ?></strong><br>
                                <span style="font-size: 0.85rem; color: #64748b;">Landlord</span>
                            </div>
                        </div>

                        <div class="signature-block">
                            <div class="signature-label">
                                <i class="fas fa-user"></i> Tenant Signature
                            </div>
                            <div class="signature-img">
                                <?php if (!empty($lease['tenant_signature_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($lease['tenant_signature_path']); ?>" alt="Tenant Signature">
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Signature pending</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($lease['tenant_signature_path'])): ?>
                                <div class="signature-status signed">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Signed</span>
                                </div>
                                <div class="signature-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo !empty($lease['tenant_signed_date']) ? date('F j, Y \a\t g:i A', strtotime($lease['tenant_signed_date'])) : 'Date not available'; ?>
                                </div>
                            <?php else: ?>
                                <div class="signature-status unsigned">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>Awaiting your signature</span>
                                </div>
                                <div style="margin-top: 1rem;">
                                    <a href="my_lease.php" class="btn btn-success" style="width: 100%;">
                                        <i class="fas fa-signature"></i> Sign Lease Now
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                                <strong><?php echo htmlspecialchars($lease['tenant_name']); ?></strong><br>
                                <span style="font-size: 0.85rem; color: #64748b;">Tenant</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 8000);
            });
        });
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>