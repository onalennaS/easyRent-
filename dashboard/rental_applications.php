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
    $landlord_notes = $_POST['landlord_notes'] ?? '';
    
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
    <title>Rental Applications - L&T Connect</title>
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: bold;
            color: #1e293b;
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

        /* Responsive */
        @media (max-width: 1024px) {
            .applications-table {
                display: block;
                overflow-x: auto;
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
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="logo">
                <img src="../logo.png" alt="L&T Connect" style="max-height: 36px; width: auto;">
            </div>
            
            <ul class="nav-menu">
                <li><a href="landlord_dashboard.php">Dashboard</a></li>
                <li><a href="my_properties.php">My Properties</a></li>
                <li><a href="applications.php" class="active">Applications</a></li>
                <li><a href="maintenance.php">Maintenance</a></li>
                <li><a href="tenants.php">Tenants</a></li>
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
        <div class="page-header">
            <h1 class="page-title">Rental Applications</h1>
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
                        <th>Application ID</th>
                        <th>Property</th>
                        <th>Tenant</th>
                        <th>Application Date</th>
                        <th>Move-in Date</th>
                        <th>Lease Duration</th>
                        <th>Rent Offer</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>#<?php echo $app['id']; ?></td>
                            <td><?php echo htmlspecialchars($app['property_title']); ?></td>
                            <td class="tenant-info">
                                <span class="tenant-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></span>
                                <span class="tenant-contact"><?php echo htmlspecialchars($app['email']); ?></span>
                                <span class="tenant-contact"><?php echo htmlspecialchars($app['phone']); ?></span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['move_in_date'])); ?></td>
                            <td><?php echo $app['lease_duration_months']; ?> months</td>
                            <td>R<?php echo number_format($app['monthly_rent'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </td>
                            <td class="application-actions">
                                <button class="btn btn-secondary view-details" data-id="<?php echo $app['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-warning update-status" data-id="<?php echo $app['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
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
    
    <!-- Application Details Modal -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Application Details</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div id="modalDetailsContent">
                <!-- Content will be loaded via JavaScript -->
            </div>
        </div>
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
                    <select name="status" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
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
    
    <script>
        // Modal functionality
        const modals = document.querySelectorAll('.modal');
        const closeButtons = document.querySelectorAll('.close-modal');
        const viewButtons = document.querySelectorAll('.view-details');
        const updateButtons = document.querySelectorAll('.update-status');
        const logoutLink = document.getElementById('logoutLink');
        
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
        
        // View details button click
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const appId = this.getAttribute('data-id');
                // In a real implementation, we would fetch application details via AJAX
                // For this example, we'll just show static content
                document.getElementById('modalDetailsContent').innerHTML = `
                    <div class="application-details">
                        <div class="form-group">
                            <label class="form-label">Application ID</label>
                            <p>#${appId}</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Property</label>
                            <p>Downtown Apartment</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tenant</label>
                            <p>John Doe</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Application Date</label>
                            <p>May 15, 2023</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Move-in Date</label>
                            <p>June 1, 2023</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Lease Duration</label>
                            <p>12 months</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Rent Offer</label>
                            <p>R12,500/month</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Security Deposit</label>
                            <p>R12,500</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tenant Notes</label>
                            <p>I'm looking for a quiet apartment close to downtown. I work remotely so a good internet connection is important.</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Landlord Notes</label>
                            <p>Good candidate - employed, good references. Scheduled viewing for May 20.</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Last Reviewed</label>
                            <p>May 16, 2023 by Landlord</p>
                        </div>
                    </div>
                `;
                openModal('detailsModal');
            });
        });
        
        // Update status button click
        updateButtons.forEach(button => {
            button.addEventListener('click', function() {
                const appId = this.getAttribute('data-id');
                document.getElementById('statusApplicationId').value = appId;
                openModal('statusModal');
            });
        });
        
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
        
        // Show success/error messages
        <?php if (isset($success_message)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo $success_message; ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php elseif (isset($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?php echo $error_message; ?>'
            });
        <?php endif; ?>
    </script>
</body>
</html>