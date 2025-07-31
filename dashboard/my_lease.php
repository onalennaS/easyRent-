<?php
session_start();

// Check if user is logged in and is a tenant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tenant') {
    header("Location: ../login.php");
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

$tenant_id = $_SESSION['user_id'];

// Fetch lease information for the tenant
$lease_query = "
    SELECT 
        l.*,
        p.title AS property_title,
        p.address AS property_address,
        p.description AS property_description,
        p.rent_amount,
        u.first_name AS landlord_first_name,
        u.last_name AS landlord_last_name,
        u.email AS landlord_email,
        u.phone AS landlord_phone
    FROM leases l
    JOIN properties p ON l.property_id = p.id
    JOIN users u ON l.landlord_id = u.id
    WHERE l.tenant_id = $tenant_id
    ORDER BY l.lease_start_date DESC
";

$lease_result = mysqli_query($conn, $lease_query);
$leases = [];
if ($lease_result) {
    while ($row = mysqli_fetch_assoc($lease_result)) {
        $leases[] = $row;
    }
}

// Handle lease signing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sign_lease'])) {
    $lease_id = intval($_POST['lease_id']);
    $signature_data = $_POST['signature'];
    
    // Process signature data
    if (!empty($signature_data)) {
        list($type, $signature_data) = explode(';', $signature_data);
        list(, $signature_data) = explode(',', $signature_data);
        $signature_data = base64_decode($signature_data);
        
        // Create signatures directory if it doesn't exist
        if (!file_exists('signatures')) {
            mkdir('signatures', 0777, true);
        }
        
        // Save signature to file
        $signature_filename = "signatures/tenant_signature_$lease_id.png";
        file_put_contents($signature_filename, $signature_data);
        
        // Update lease in database
        $update_query = "UPDATE leases SET 
                        tenant_signed = 1, 
                        tenant_signature_path = '$signature_filename', 
                        tenant_signed_date = NOW() 
                        WHERE id = $lease_id AND tenant_id = $tenant_id";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success_message'] = "Lease agreement signed successfully!";
            // Refresh lease data
            header("Location: my_lease.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error signing lease: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error_message'] = "Please provide a valid signature";
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
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
        }

        /* Alert styling */
        .alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            max-width: 600px;
            z-index: 1050;
            opacity: 1;
            transition: opacity 0.5s ease;
            padding: 15px;
            border-radius: 5px;
            font-weight: 500;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Sidebar styles */
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

        /* Main content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .header .tenant-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header .tenant-info .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* Lease container */
        .lease-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .lease-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .lease-title {
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }

        .lease-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-draft {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .lease-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-section h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #333;
            font-weight: 500;
        }

        .signature-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .signature-box {
            width: 48%;
            text-align: center;
        }

        .signature-img {
            max-width: 200px;
            max-height: 80px;
            margin-bottom: 10px;
            border: 1px solid #eee;
        }

        .signature-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .signature-date {
            color: #666;
            font-size: 14px;
        }

        .lease-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }
#signature-pad {
    background-color: #fff;
    border: 1px solid #ddd;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.1);
}
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1040;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #000;
        }

        /* Signature pad styles */
        .signature-pad-container {
            margin: 20px 0;
            text-align: center;
            position: relative;
        }

        #signature-pad {
            border: 1px solid #ddd;
            background-color: white;
            width: 100%;
            height: 200px;
            touch-action: none;
            background-image: linear-gradient(45deg, #f0f0f0 25%, transparent 25%),
                            linear-gradient(-45deg, #f0f0f0 25%, transparent 25%),
                            linear-gradient(45deg, transparent 75%, #f0f0f0 75%),
                            linear-gradient(-45deg, transparent 75%, #f0f0f0 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }

        .signature-instructions {
            margin-bottom: 15px;
            color: #666;
            text-align: center;
        }

        .signature-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 50px;
            margin-bottom: 20px;
            color: #ccc;
        }

        /* Sidebar toggle for mobile */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: #333;
            cursor: pointer;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .signature-row {
                flex-direction: column;
            }
            
            .signature-box {
                width: 100%;
                margin-bottom: 20px;
            }
        }

        @media (max-width: 600px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding-top: 70px;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .lease-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .modal-content {
                padding: 20px;
            }
            
            #signature-pad {
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h2>Easy Rent</h2>
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
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h1>My Lease Agreement</h1>
            <div class="tenant-info">
                <span>Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Tenant'); ?></span>
                <div class="avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'T', 0, 1)); ?>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" id="successAlert">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error" id="errorAlert">
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($leases)): ?>
            <?php foreach ($leases as $lease): ?>
                <div class="lease-container">
                    <div class="lease-header">
                        <div class="lease-title">
                            Lease Agreement for <?php echo htmlspecialchars($lease['property_title']); ?>
                        </div>
                        <div class="lease-status status-<?php echo strtolower($lease['status']); ?>">
                            <?php echo ucfirst($lease['status']); ?>
                        </div>
                    </div>

                    <div class="lease-details">
                        <div class="detail-section">
                            <h3>Property Details</h3>
                            <div class="detail-row">
                                <span class="detail-label">Property:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($lease['property_title']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($lease['property_address']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Monthly Rent:</span>
                                <span class="detail-value">R<?php echo number_format($lease['rent_amount'], 2); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3>Lease Terms</h3>
                            <div class="detail-row">
                                <span class="detail-label">Lease Start:</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($lease['lease_start_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Lease End:</span>
                                <span class="detail-value"><?php echo date('M j, Y', strtotime($lease['lease_end_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Security Deposit:</span>
                                <span class="detail-value">R<?php echo number_format($lease['security_deposit'], 2); ?></span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h3>Landlord Information</h3>
                            <div class="detail-row">
                                <span class="detail-label">Name:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($lease['landlord_first_name'] . ' ' . $lease['landlord_last_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($lease['landlord_email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($lease['landlord_phone']); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="signature-section">
                        <h3>Signatures</h3>
                        <div class="signature-row">
                            <div class="signature-box">
                                <div class="signature-label">Landlord Signature</div>
                                <?php if (!empty($lease['signature_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($lease['signature_path']); ?>" alt="Landlord Signature" class="signature-img">
                                    <div class="signature-date">
                                        Signed on <?php echo date('M j, Y', strtotime($lease['signed_date'])); ?>
                                    </div>
                                <?php else: ?>
                                    <div style="height: 80px; display: flex; align-items: center; justify-content: center; color: #999;">
                                        Not signed yet
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="signature-box">
                                <div class="signature-label">Tenant Signature</div>
                                <?php if (!empty($lease['tenant_signature_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($lease['tenant_signature_path']); ?>" alt="Tenant Signature" class="signature-img">
                                    <div class="signature-date">
                                        Signed on <?php echo date('M j, Y', strtotime($lease['tenant_signed_date'])); ?>
                                    </div>
                                <?php else: ?>
                                    <div style="height: 80px; display: flex; align-items: center; justify-content: center; color: #999;">
                                        Not signed yet
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="lease-actions">
                        <a href="download_lease.php?id=<?php echo $lease['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Download Lease
                        </a>
                        <a href="view_lease.php?id=<?php echo $lease['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-file-alt"></i> View Full Lease
                        </a>
                        <?php if (empty($lease['tenant_signature_path']) && !empty($lease['signature_path'])): ?>
                            <button class="btn btn-success sign-lease-btn" data-lease-id="<?php echo $lease['id']; ?>">
                                <i class="fas fa-signature"></i> Sign Lease
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-contract"></i>
                <h3>No Lease Agreements Found</h3>
                <p>You don't have any active lease agreements yet. Once your application is approved, your lease will appear here.</p>
                <a href="my_applications.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-file-alt"></i> View My Applications
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sign Lease Modal -->
    <div id="signLeaseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Sign Lease Agreement</h3>
                <span class="close" onclick="closeSignModal()">&times;</span>
            </div>
            <form id="signLeaseForm" method="POST">
                <input type="hidden" name="sign_lease" value="1">
                <input type="hidden" id="lease_id" name="lease_id" value="">
                <input type="hidden" id="signature" name="signature" value="">
                
                <div class="signature-instructions">
                    <p>Please sign your name in the box below using your mouse or finger</p>
                </div>
                
                <div class="signature-pad-container">
                    <canvas id="signature-pad"></canvas>
                </div>
                
                <div class="signature-actions">
                    <button type="button" id="clearSignature" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                    <button type="button" id="saveSignature" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Signature
                    </button>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;" disabled id="signLeaseBtn">
                        <i class="fas fa-check-circle"></i> Confirm and Sign Lease
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize signature pad
        let signaturePad;
        
        function initSignaturePad() {
            const canvas = document.getElementById('signature-pad');
            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)',
                minWidth: 1.5,
                maxWidth: 3,
                velocityFilterWeight: 0.7,
                throttle: 16
            });

            // Handle signature pad resizing
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                signaturePad.clear();
            }

            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            // Enable touch support
            canvas.addEventListener('touchstart', function(e) {
                e.preventDefault();
            });

            return signaturePad;
        }

        // Initialize when modal opens
        function openSignModal() {
            document.getElementById('signLeaseModal').style.display = 'block';
            setTimeout(() => {
                signaturePad = initSignaturePad();
                signaturePad.clear();
                document.getElementById('signLeaseBtn').disabled = true;
                
                // Hide any alerts when modal opens
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.style.display = 'none', 300);
                });
            }, 100);
        }

        // Clear signature
        document.getElementById('clearSignature').addEventListener('click', function() {
            if (signaturePad) {
                signaturePad.clear();
                document.getElementById('signLeaseBtn').disabled = true;
            }
        });

        // Save signature
        document.getElementById('saveSignature').addEventListener('click', function() {
            if (!signaturePad || signaturePad.isEmpty()) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please provide a signature first',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            const signatureData = signaturePad.toDataURL();
            document.getElementById('signature').value = signatureData;
            document.getElementById('signLeaseBtn').disabled = false;
            
            Swal.fire({
                title: 'Signature Saved',
                text: 'Your signature has been saved. Review and confirm to sign the lease.',
                icon: 'success',
                confirmButtonText: 'OK'
            });
        });

        // Handle form submission
        document.getElementById('signLeaseForm').addEventListener('submit', function(e) {
            if (!signaturePad || signaturePad.isEmpty()) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error',
                    text: 'Please provide a signature before submitting',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            return true;
        });

        // Sign lease button click handler
        document.querySelectorAll('.sign-lease-btn').forEach(button => {
            button.addEventListener('click', function() {
                const leaseId = this.getAttribute('data-lease-id');
                document.getElementById('lease_id').value = leaseId;
                openSignModal();
            });
        });

        // Modal functions
        function closeSignModal() {
            document.getElementById('signLeaseModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('signLeaseModal')) {
                closeSignModal();
            }
        }

        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>