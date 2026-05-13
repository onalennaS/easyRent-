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

// Check for session success message
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Fetch tenants for this landlord, including:
// - All applications that are approved
// - All tenants with signed/active leases (regardless of application status)
// - Any tenants that already have a lease (any non-terminated status)
$approved_tenants_query = "
    SELECT
        u.id AS tenant_id,
        COALESCE(tp.full_name, CONCAT(u.first_name, ' ', u.last_name)) AS tenant_name,
        u.email AS tenant_email,
        u.phone AS tenant_phone,
        p.title AS property_title,
        p.address AS property_address,
        p.id AS property_id,
        a.id AS application_id,
        a.application_date,
        a.status AS application_status,
        l.id AS lease_id,
        l.lease_start_date,
        l.lease_end_date,
        l.monthly_rent,
        l.security_deposit,
        l.status AS lease_status,
        l.signed_date,
        l.signature_path,
        (SELECT COUNT(*) FROM rental_applications a2
         WHERE a2.tenant_id = u.id AND a2.status = 'approved') AS approved_app_count
    FROM rental_applications a
    JOIN properties p ON a.property_id = p.id
    JOIN users u ON a.tenant_id = u.id
    LEFT JOIN tenant_profiles tp ON u.id = tp.tenant_id
    LEFT JOIN leases l ON a.id = l.application_id
    WHERE p.landlord_id = $landlord_id
    AND (
        a.status = 'approved'
        OR (l.id IS NOT NULL AND l.status != 'terminated')
    )

    UNION

    SELECT
        u.id AS tenant_id,
        COALESCE(tp.full_name, CONCAT(u.first_name, ' ', u.last_name)) AS tenant_name,
        u.email AS tenant_email,
        u.phone AS tenant_phone,
        p.title AS property_title,
        p.address AS property_address,
        p.id AS property_id,
        l.application_id AS application_id,
        NULL AS application_date,
        NULL AS application_status,
        l.id AS lease_id,
        l.lease_start_date,
        l.lease_end_date,
        l.monthly_rent,
        l.security_deposit,
        l.status AS lease_status,
        l.signed_date,
        l.signature_path,
        (SELECT COUNT(*) FROM rental_applications a2
         WHERE a2.tenant_id = u.id AND a2.status = 'approved') AS approved_app_count
    FROM leases l
    JOIN properties p ON l.property_id = p.id
    JOIN users u ON l.tenant_id = u.id
    LEFT JOIN tenant_profiles tp ON u.id = tp.tenant_id
    LEFT JOIN rental_applications a ON l.application_id = a.id
    WHERE p.landlord_id = $landlord_id
    AND l.status = 'active'
    AND (a.id IS NULL OR a.status != 'approved')

    ORDER BY
        tenant_name ASC,
        application_date DESC,
        CASE WHEN lease_status = 'active' THEN 0 ELSE 1 END ASC
";

$approved_tenants_result = mysqli_query($conn, $approved_tenants_query);
$approved_tenants = [];
if ($approved_tenants_result) {
    while ($row = mysqli_fetch_assoc($approved_tenants_result)) {
        $approved_tenants[] = $row;
    }
} else {
    $error_message = "Database error: " . mysqli_error($conn);
}

// Group applications by tenant
$grouped_tenants = [];
foreach ($approved_tenants as $tenant) {
    $tenant_id = $tenant['tenant_id'];
    if (!isset($grouped_tenants[$tenant_id])) {
        $grouped_tenants[$tenant_id] = [
            'tenant_info' => [
                'id' => $tenant['tenant_id'],
                'name' => $tenant['tenant_name'],
                'email' => $tenant['tenant_email'],
                'phone' => $tenant['tenant_phone'],
                'approved_app_count' => $tenant['approved_app_count']
            ],
            'applications' => []
        ];
    }
    $grouped_tenants[$tenant_id]['applications'][] = $tenant;
}

// Fetch lease templates
$templates_query = "
    SELECT id, template_name, created_at 
    FROM lease_templates 
    WHERE landlord_id = $landlord_id
    ORDER BY created_at DESC
";

$templates_result = mysqli_query($conn, $templates_query);
$templates = [];
if ($templates_result) {
    while ($row = mysqli_fetch_assoc($templates_result)) {
        $templates[] = $row;
    }
}

// Calculate statistics
$stats_query = "SELECT 
    COUNT(DISTINCT u.id) AS total_tenants,
    SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) AS pending_leases,
    SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) AS active_leases,
    SUM(CASE WHEN a.status = 'approved' AND l.id IS NULL THEN 1 ELSE 0 END) AS approved_no_lease
    FROM rental_applications a
    JOIN properties p ON a.property_id = p.id
    JOIN users u ON a.tenant_id = u.id
    LEFT JOIN leases l ON a.id = l.application_id
    WHERE p.landlord_id = $landlord_id
    AND (a.status = 'approved' OR (l.id IS NOT NULL AND l.status != 'terminated'))";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle lease creation form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_lease'])) {
    $application_id = intval($_POST['application_id']);
    $template_id = intval($_POST['template_id']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $rent_amount = floatval($_POST['rent_amount']);
    $deposit = floatval($_POST['deposit']);
    $additional_terms = $_POST['terms'];
    $terms = mysqli_real_escape_string($conn, $additional_terms);
    
    // Get tenant_id from the application
    $tenant_id_query = "SELECT tenant_id FROM rental_applications WHERE id = $application_id";
    $tenant_id_result = mysqli_query($conn, $tenant_id_query);
    
    if ($tenant_id_result && mysqli_num_rows($tenant_id_result) > 0) {
        $tenant_data = mysqli_fetch_assoc($tenant_id_result);
        $tenant_id = $tenant_data['tenant_id'];
        
        // Check if this tenant already has any draft or pending leases
        $check_draft_query = "SELECT id FROM leases WHERE tenant_id = $tenant_id AND status IN ('draft', 'pending')";
        $check_draft_result = mysqli_query($conn, $check_draft_query);
        
        if ($check_draft_result && mysqli_num_rows($check_draft_result) > 0) {
            $error_message = "This tenant already has a pending lease agreement. Please sign or cancel the existing lease before creating a new one.";
        } else {
            // Check if this application already has an active lease
            $check_lease_query = "SELECT id FROM leases WHERE application_id = $application_id AND status != 'terminated'";
            $check_lease_result = mysqli_query($conn, $check_lease_query);
            
            if ($check_lease_result && mysqli_num_rows($check_lease_result) > 0) {
                $error_message = "This application already has an active lease agreement.";
            } else {
                // Get application details
                $app_details_query = "SELECT property_id, tenant_id FROM rental_applications WHERE id = $application_id";
                $app_details_result = mysqli_query($conn, $app_details_query);
                
                if ($app_details_result && mysqli_num_rows($app_details_result) > 0) {
                    $app_details = mysqli_fetch_assoc($app_details_result);
                    $property_id = $app_details['property_id'];
                    $tenant_id = $app_details['tenant_id'];

                    // Initialize variables
                    $tenant_name = '';
                    $landlord_name = '';
                    $property_address = '';

                    // Fetch tenant name from users table
                    $tenant_query = "SELECT CONCAT(first_name, ' ', last_name) AS tenant_name FROM users WHERE id = $tenant_id";
                    $tenant_result = mysqli_query($conn, $tenant_query);
                    if ($tenant_result && mysqli_num_rows($tenant_result) > 0) {
                        $tenant_data = mysqli_fetch_assoc($tenant_result);
                        $tenant_name = mysqli_real_escape_string($conn, $tenant_data['tenant_name']);
                    } else {
                        $error_message = "Error: Could not find tenant information.";
                    }

                    // Fetch landlord name from users table
                    $landlord_query = "SELECT CONCAT(first_name, ' ', last_name) AS landlord_name FROM users WHERE id = $landlord_id";
                    $landlord_result = mysqli_query($conn, $landlord_query);
                    if ($landlord_result && mysqli_num_rows($landlord_result) > 0) {
                        $landlord_data = mysqli_fetch_assoc($landlord_result);
                        $landlord_name = mysqli_real_escape_string($conn, $landlord_data['landlord_name']);
                    }

                    // Fetch property address
                    $property_query = "SELECT address FROM properties WHERE id = $property_id";
                    $property_result = mysqli_query($conn, $property_query);
                    if ($property_result && mysqli_num_rows($property_result) > 0) {
                        $property_data = mysqli_fetch_assoc($property_result);
                        $property_address = mysqli_real_escape_string($conn, $property_data['address']);
                    }

                    // Fetch template content
                    $template_query = "SELECT content FROM lease_templates WHERE id = $template_id";
                    $template_result = mysqli_query($conn, $template_query);
                    if ($template_result && mysqli_num_rows($template_result) > 0) {
                        $template_data = mysqli_fetch_assoc($template_result);
                        $template_content = $template_data['content'];

                        // Replace placeholders
                        $lease_content = str_replace('[TENANT_NAME]', $tenant_name, $template_content);
                        $lease_content = str_replace('[LANDLORD_NAME]', $landlord_name ?? '', $lease_content);
                        $lease_content = str_replace('[PROPERTY_ADDRESS]', $property_address ?? '', $lease_content);
                        $lease_content = str_replace('[START_DATE]', $start_date, $lease_content);
                        $lease_content = str_replace('[END_DATE]', $end_date, $lease_content);
                        $lease_content = str_replace('[RENT_AMOUNT]', $rent_amount, $lease_content);
                        $lease_content = str_replace('[DEPOSIT_AMOUNT]', $deposit, $lease_content);
                        $lease_content = str_replace('[DATE]', date('Y-m-d'), $lease_content);

                        // Append additional terms
                        if (!empty($additional_terms)) {
                            $lease_content .= "\n\nAdditional Terms:\n" . $additional_terms;
                        }

                        $lease_content = mysqli_real_escape_string($conn, $lease_content);
                    } else {
                        $error_message = "Error: Could not find template.";
                    }

                    // Insert lease
                    $insert_query = "
                        INSERT INTO leases (
                            property_id, 
                            tenant_id, 
                            landlord_id,
                            application_id, 
                            template_id, 
                            lease_start_date, 
                            lease_end_date, 
                            monthly_rent, 
                            security_deposit, 
                            terms, 
                            status
                        )
                        VALUES (
                            $property_id, 
                            $tenant_id, 
                            $landlord_id,
                            $application_id, 
                            $template_id, 
                            '$start_date', 
                            '$end_date', 
                            $rent_amount, 
                            $deposit, 
                            '$terms', 
                            'pending'
                        )
                    ";
                    
                    if (mysqli_query($conn, $insert_query)) {
                        $_SESSION['success_message'] = "Lease agreement created successfully! You can now sign the lease.";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $error_message = "Error creating lease: " . mysqli_error($conn);
                    }
                } else {
                    $error_message = "Error: Could not find application details.";
                }
            }
        }
    } else {
        $error_message = "Error: Could not find tenant information.";
    }
}

// Handle lease template creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_template'])) {
    $template_name = mysqli_real_escape_string($conn, $_POST['template_name']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    
    $insert_query = "INSERT INTO lease_templates (landlord_id, template_name, content) 
                     VALUES ($landlord_id, '$template_name', '$content')";
    
    if (mysqli_query($conn, $insert_query)) {
        $_SESSION['success_message'] = "Lease template created successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error_message = "Error creating template: " . mysqli_error($conn);
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
        $signature_filename = "signatures/landlord_signature_$lease_id.png";
        file_put_contents($signature_filename, $signature_data);
        
        // Update lease in database
        $update_query = "UPDATE leases SET
                        status = 'active',
                        signature_path = '$signature_filename',
                        signed_date = NOW()
                        WHERE id = $lease_id";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success_message'] = "Lease agreement signed and activated successfully!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error_message = "Error signing lease: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Please provide a valid signature";
    }
}

// Handle lease termination
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['terminate_lease'])) {
    $lease_id = intval($_POST['lease_id']);
    $termination_reason = mysqli_real_escape_string($conn, $_POST['termination_reason']);
    $termination_date = mysqli_real_escape_string($conn, $_POST['termination_date']);
    
    // Update lease status to terminated
    $update_query = "UPDATE leases SET 
                    status = 'terminated',
                    termination_reason = '$termination_reason',
                    termination_date = '$termination_date'
                    WHERE id = $lease_id AND landlord_id = $landlord_id";
    
    if (mysqli_query($conn, $update_query)) {
        $_SESSION['success_message'] = "Lease agreement terminated successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error_message = "Error terminating lease: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenants & Approved Applications - L&T Connect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
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

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
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

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 250px;
            max-width: calc(100% - 250px);
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .landlord-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .landlord-info .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-buttons {
            display: flex;
            gap: 1rem;
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--accent-gradient);
            border-radius: 20px;
            padding: 1.75rem;
            box-shadow: 0 10px 30px var(--shadow-color);
            border: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            transition: all 0.6s ease;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            transition: all 0.6s ease;
        }

        .stat-card:hover {
            transform: translateY(-10px) scale(1.03);
            box-shadow: 0 20px 40px var(--shadow-color);
        }

        .stat-card:hover::before {
            transform: scale(1.3) rotate(45deg);
            top: -60%;
            right: -30%;
        }

        .stat-card:hover::after {
            transform: scale(1.4) rotate(-45deg);
        }

        .stat-card.total { 
            --accent-gradient: linear-gradient(135deg, #8b7bce 0%, #6b5bb0 100%);
            --shadow-color: rgba(139, 123, 206, 0.4);
            --icon-bg: rgba(255, 255, 255, 0.2);
        }

        .stat-card.pending { 
            --accent-gradient: linear-gradient(135deg, #7ec8c3 0%, #5fb3ad 100%);
            --shadow-color: rgba(126, 200, 195, 0.4);
            --icon-bg: rgba(255, 255, 255, 0.2);
        }

        .stat-card.active { 
            --accent-gradient: linear-gradient(135deg, #6bcf9d 0%, #4fb883 100%);
            --shadow-color: rgba(107, 207, 157, 0.4);
            --icon-bg: rgba(255, 255, 255, 0.2);
        }

        .stat-card.approved { 
            --accent-gradient: linear-gradient(135deg, #f4a79d 0%, #e8907f 100%);
            --shadow-color: rgba(244, 167, 157, 0.4);
            --icon-bg: rgba(255, 255, 255, 0.2);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            background: var(--icon-bg);
            backdrop-filter: blur(10px);
            flex-shrink: 0;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.3rem;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.95);
            font-weight: 600;
            font-size: 0.85rem;
            line-height: 1.3;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filters */
        .filters {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
        }

        .filter-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            transition: border-color 0.3s ease;
        }

        .filter-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #3b82f6;
            color: #3b82f6;
        }

        .btn-outline:hover {
            background: rgba(59, 130, 246, 0.1);
        }

        /* Tenants List */
        .tenants-list {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .tenant-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tenant-table thead {
            background: #f8fafc;
            border-bottom: 2px solid #e5e7eb;
        }

        .tenant-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tenant-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
        }

        .tenant-table tbody tr:hover {
            background: #f8fafc;
        }

        .tenant-table td {
            padding: 0.5rem;
            vertical-align: top;
            font-size: 0.8rem;
        }

        .tenant-info-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .tenant-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8b5cf6 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }

        .tenant-id {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .property-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .property-address {
            font-size: 0.85rem;
            color: #64748b;
        }

        .property-address i {
            margin-right: 0.25rem;
        }

        .status-badge {
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .status-draft { background: #fef3c7; color: #92400e; }
        .status-pending { background: #dbeafe; color: #1e40af; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-signed { background: #dcfce7; color: #166534; }

        .table-actions {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dropdown-btn:hover {
            background: #1d4ed8;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            bottom: 100%;
            right: 0;
            margin-bottom: 2px;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-item {
            padding: 0.25rem 0.5rem;
            text-decoration: none;
            display: block;
            color: #374151;
            font-size: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s ease;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background-color: #f8fafc;
        }

        .dropdown-item.danger {
            color: #dc2626;
        }

        .dropdown-item.danger:hover {
            background-color: #fef2f2;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
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

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #475569;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #3b82f6;
            color: white;
            font-size: 0.75rem;
            border-radius: 12px;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1001;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            box-shadow: 0 10px 50px rgba(0,0,0,0.3);
            overflow: hidden;
            margin: auto;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.75rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            transition: opacity 0.2s ease;
        }

        .close-modal:hover {
            opacity: 0.7;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1e293b;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }

        /* Signature Pad */
        .signature-container {
            margin: 1.5rem 0;
        }

        .signature-pad-wrapper {
            position: relative;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            margin-bottom: 1rem;
            touch-action: none;
        }

        #signature-pad {
            width: 100%;
            height: 200px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: inset 0 0 5px rgba(0,0,0,0.1);
            touch-action: none;
            cursor: crosshair;
        }

        .signature-guide {
            position: absolute;
            bottom: 30%;
            width: 100%;
            border-top: 1px dashed #ccc;
            pointer-events: none;
            color: #999;
            text-align: center;
            font-size: 12px;
        }

        .signature-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .signature-preview {
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8fafc;
        }

        .signature-preview img {
            max-width: 200px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }

        .signature-instructions {
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            border-radius: 4px;
        }

        /* Summernote */
        .note-editor {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            overflow: hidden;
        }

        .note-editor .note-toolbar {
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .tenant-table th:nth-child(4),
            .tenant-table td:nth-child(4) {
                display: none;
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
                max-width: 100%;
            }
            
            .mobile-menu-btn {
                display: block;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header-buttons {
                width: 100%;
                justify-content: flex-start;
            }

            .tenant-table th:nth-child(6),
            .tenant-table td:nth-child(6),
            .tenant-table th:nth-child(7),
            .tenant-table td:nth-child(7) {
                display: none;
            }

            .filter-row {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .tenant-table th:nth-child(3),
            .tenant-table td:nth-child(3) {
                display: none;
            }

            .modal-body {
                padding: 1rem;
            }

            .table-actions {
                flex-direction: column;
            }

            .table-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .stat-card {
                padding: 1.25rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
            
            .stat-icon {
                width: 42px;
                height: 42px;
                font-size: 1.1rem;
            }

            .tenant-table {
                font-size: 0.875rem;
            }

            .tenant-table th,
            .tenant-table td {
                padding: 0.75rem 0.5rem;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="../logo.png" alt="L&T Connect" style="max-height: 42px; width: auto; display: block; margin-bottom: 0.75rem;">
            <p>Landlord Portal</p>
        </div>
        <ul>
            <li><a href="../index.php" class="home-button"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="profile_landlord.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="landlord_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="my_properties.php"><i class="fas fa-building"></i> My Properties</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="add_property.php"><i class="fas fa-plus-circle"></i> Add Property</a></li>
            <li><a href="maintenance.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="tenants.php" class="active"><i class="fas fa-users"></i> Tenants</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-users"></i>
                Tenants & Leases
            </h1>
            <div class="landlord-info">
                <span>Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Landlord'); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?></div>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div></div>
            <div class="header-buttons">
                <a href="terminated_leases.php" class="btn btn-secondary">
                    <i class="fas fa-file-contract"></i>
                    Terminated Leases
                </a>
                <button class="btn btn-primary" id="createTemplateBtn">
                    <i class="fas fa-file-contract"></i>
                    Create Template
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['total_tenants'] ?? 0; ?></div>
                        <div class="stat-label">Total Tenants</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card pending">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['pending_leases'] ?? 0; ?></div>
                        <div class="stat-label">Pending Leases</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card active">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['active_leases'] ?? 0; ?></div>
                        <div class="stat-label">Active Leases</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card approved">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['approved_no_lease'] ?? 0; ?></div>
                        <div class="stat-label">Awaiting Lease</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters" id="filterForm">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="property_filter">Property</label>
                    <select id="property_filter" name="property_filter" class="filter-control">
                        <option value="">All Properties</option>
                        <?php
                        // Get unique properties from approved tenants
                        $properties_seen = [];
                        foreach ($approved_tenants as $tenant) {
                            if (!in_array($tenant['property_id'], $properties_seen)) {
                                $properties_seen[] = $tenant['property_id'];
                                $selected = (isset($_GET['property_filter']) && $_GET['property_filter'] == $tenant['property_id']) ? 'selected' : '';
                                echo '<option value="' . $tenant['property_id'] . '" ' . $selected . '>' . htmlspecialchars($tenant['property_title']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="lease_status_filter">Lease Status</label>
                    <select id="lease_status_filter" name="lease_status_filter" class="filter-control">
                        <option value="">All Statuses</option>
                        <option value="no_lease" <?php echo (isset($_GET['lease_status_filter']) && $_GET['lease_status_filter'] === 'no_lease') ? 'selected' : ''; ?>>No Lease</option>
                        <option value="draft" <?php echo (isset($_GET['lease_status_filter']) && $_GET['lease_status_filter'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="pending" <?php echo (isset($_GET['lease_status_filter']) && $_GET['lease_status_filter'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="active" <?php echo (isset($_GET['lease_status_filter']) && $_GET['lease_status_filter'] === 'active') ? 'selected' : ''; ?>>Active</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="search">Search Tenant</label>
                    <input type="text" id="search" name="search" class="filter-control" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn btn-outline" onclick="resetFilters()">
                    <i class="fas fa-sync"></i>
                    Reset Filters
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Apply Filters
                </button>
            </div>
        </form>

        <!-- Tenants Table -->
        <div class="tenants-list">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-users"></i>
                    Tenant Management
                </h2>
            </div>

            <?php if (!empty($grouped_tenants)): ?>
                <div class="table-container">
                    <table class="tenant-table">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Contact</th>
                                <th>Property</th>
                                <th>Application Date</th>
                                <th>Lease Status</th>
                                <th>Lease Period</th>
                                <th>Rent Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $displayCount = 0;
                            foreach ($grouped_tenants as $tenant_id => $tenant_data): 
                                $tenant_info = $tenant_data['tenant_info'];
                                $applications = $tenant_data['applications'];
                                
                                foreach ($applications as $application): 
                                    // Apply filters
                                    $shouldDisplay = true;
                                    
                                    // Property filter
                                    if (isset($_GET['property_filter']) && !empty($_GET['property_filter'])) {
                                        if ($application['property_id'] != $_GET['property_filter']) {
                                            $shouldDisplay = false;
                                        }
                                    }
                                    
                                    // Lease status filter
                                    if (isset($_GET['lease_status_filter']) && !empty($_GET['lease_status_filter'])) {
                                        if ($_GET['lease_status_filter'] === 'no_lease') {
                                            if ($application['lease_id'] && !empty($application['lease_id'])) {
                                                $shouldDisplay = false;
                                            }
                                        } else {
                                            if ($application['lease_status'] != $_GET['lease_status_filter']) {
                                                $shouldDisplay = false;
                                            }
                                        }
                                    }
                                    
                                    // Search filter
                                    if (isset($_GET['search']) && !empty($_GET['search'])) {
                                        $search = strtolower($_GET['search']);
                                        $name = strtolower($tenant_info['name']);
                                        $email = strtolower($tenant_info['email']);
                                        if (strpos($name, $search) === false && strpos($email, $search) === false) {
                                            $shouldDisplay = false;
                                        }
                                    }
                                    
                                    if (!$shouldDisplay) continue;
                                    $displayCount++;
                            ?>
                                <tr class="tenant-row">
                                    <td>
                                        <div class="tenant-info-cell">
                                            <div>
                                                <strong><?php echo htmlspecialchars($tenant_info['name']); ?></strong>
                                                <div class="tenant-id" style="display: none;">ID: #<?php echo htmlspecialchars($tenant_info['id']); ?></div>
                                                <?php if ($tenant_info['approved_app_count'] > 1): ?>
                                                    <span class="badge"><?php echo $tenant_info['approved_app_count']; ?> properties</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($tenant_info['email']); ?></div>
                                        <div style="color: #64748b; font-size: 0.875rem;"><?php echo htmlspecialchars($tenant_info['phone']); ?></div>
                                    </td>
                                    <td>
                                        <div class="property-title"><?php echo htmlspecialchars($application['property_title']); ?></div>
                                        <div class="property-address">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($application['property_address']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $application['application_date'] ? date('M j, Y', strtotime($application['application_date'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <?php if ($application['lease_id'] && !empty($application['lease_id'])): ?>
                                            <span class="status-badge status-<?php echo $application['lease_status']; ?>">
                                                <?php echo ucfirst($application['lease_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge" style="background: #fef3c7; color: #92400e;">
                                                No Lease
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($application['lease_id'] && !empty($application['lease_id'])): ?>
                                            <div><?php echo date('M j, Y', strtotime($application['lease_start_date'])); ?></div>
                                            <div style="color: #64748b; font-size: 0.875rem;">to <?php echo date('M j, Y', strtotime($application['lease_end_date'])); ?></div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($application['lease_id'] && !empty($application['lease_id'])): ?>
                                            <strong>R<?php echo number_format($application['monthly_rent']); ?></strong>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="dropdown-btn" onclick="toggleDropdown(this)">
                                                <i class="fas fa-ellipsis-v"></i>
                                                Actions
                                            </button>
                                            <div class="dropdown-content">
                                                <?php if ($application['lease_id'] && !empty($application['lease_id'])): ?>
                                                    <?php if ($application['lease_status'] == 'draft' || $application['lease_status'] == 'pending'): ?>
                                                        <a href="view_lease.php?id=<?php echo $application['lease_id']; ?>" class="dropdown-item" target="_blank">
                                                            <i class="fas fa-eye"></i> Preview
                                                        </a>
                                                        <button class="dropdown-item sign-lease-btn"
                                                                data-lease-id="<?php echo $application['lease_id']; ?>"
                                                                data-tenant-name="<?php echo htmlspecialchars($tenant_info['name']); ?>"
                                                                data-property-name="<?php echo htmlspecialchars($application['property_title']); ?>">
                                                            <i class="fas fa-signature"></i> Sign
                                                        </button>
                                                    <?php elseif ($application['lease_status'] == 'active'): ?>
                                                        <a href="view_lease.php?id=<?php echo $application['lease_id']; ?>" class="dropdown-item" target="_blank">
                                                            <i class="fas fa-file-alt"></i> View
                                                        </a>
                                                        <a href="download_lease.php?id=<?php echo $application['lease_id']; ?>" class="dropdown-item">
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                        <button class="dropdown-item danger terminate-lease-btn"
                                                                data-lease-id="<?php echo $application['lease_id']; ?>"
                                                                data-tenant-name="<?php echo htmlspecialchars($tenant_info['name']); ?>"
                                                                data-property-name="<?php echo htmlspecialchars($application['property_title']); ?>">
                                                            <i class="fas fa-times-circle"></i> Terminate
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button class="dropdown-item create-lease-btn"
                                                            data-tenant-name="<?php echo htmlspecialchars($tenant_info['name']); ?>"
                                                            data-property-name="<?php echo htmlspecialchars($application['property_title']); ?>"
                                                            data-application-id="<?php echo $application['application_id']; ?>"
                                                            data-tenant-id="<?php echo $tenant_info['id']; ?>">
                                                        <i class="fas fa-file-contract"></i> Create Lease
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                            <?php if ($displayCount === 0): ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <i class="fas fa-filter"></i>
                                        <h3>No Results Found</h3>
                                        <p>No tenants match your current filters. Try adjusting your search criteria.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-friends"></i>
                    <h3>No Approved Tenants</h3>
                    <p>You don't have any approved tenants yet. Once tenants apply and get approved, they'll appear here.</p>
                    <a href="applications.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-list"></i>
                        View Applications
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Lease Modal -->
    <div class="modal" id="createLeaseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create Lease Agreement</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="leaseForm" method="POST">
                    <input type="hidden" name="create_lease" value="1">
                    <input type="hidden" id="application_id" name="application_id" value="">

                    <div class="form-group">
                        <label class="form-label">Property</label>
                        <input type="text" id="propertyName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Lease Template</label>
                        <select name="template_id" class="form-control" <?php echo !empty($templates) ? 'required' : 'disabled'; ?> id="templateSelect">
                            <option value="">Select a template</option>
                            <?php if (!empty($templates)): ?>
                                <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>">
                                        <?php echo htmlspecialchars($template['template_name']); ?>
                                        (<?php echo date('M j, Y', strtotime($template['created_at'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No templates available - Create one first</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Lease Start Date</label>
                        <input type="text" name="start_date" class="form-control datepicker" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Lease End Date</label>
                        <input type="text" name="end_date" class="form-control datepicker" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Monthly Rent (R)</label>
                        <input type="number" name="rent_amount" class="form-control" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Security Deposit (R)</label>
                        <input type="number" name="deposit" class="form-control" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Additional Terms</label>
                        <textarea name="terms" class="form-control" rows="4" placeholder="Enter any additional terms or conditions..."></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i>
                            Create Lease Agreement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sign Lease Modal -->
    <div class="modal" id="signLeaseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Sign Lease Agreement</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="signLeaseForm" method="POST">
                    <input type="hidden" name="sign_lease" value="1">
                    <input type="hidden" id="lease_id" name="lease_id" value="">
                    <input type="hidden" id="signature" name="signature" value="">
                    
                    <div class="signature-instructions">
                        <p><strong>Lease Details:</strong></p>
                        <p id="signatureLeaseInfo"></p>
                        <p style="margin-top: 1rem;">Please sign your name in the box below using your mouse or finger</p>
                    </div>
                    
                    <div class="signature-container">
                        <div class="signature-pad-wrapper">
                            <canvas id="signature-pad"></canvas>
                            <div class="signature-center-guide"></div>
                        </div>
                        <div class="signature-actions">
                            <button type="button" id="clearSignature" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Clear Signature
                            </button>
                            <button type="button" id="saveSignature" class="btn btn-success">
                                <i class="fas fa-save"></i> Save Signature
                            </button>
                        </div>
                        <div class="signature-preview" id="signaturePreview" style="display: none;">
                            <p>Your signature:</p>
                            <img id="previewImage" src="" alt="Your signature">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;" disabled id="signLeaseBtn">
                            <i class="fas fa-check-circle"></i> Confirm and Activate Lease
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Terminate Lease Modal -->
    <div class="modal" id="terminateLeaseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Terminate Lease Agreement</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="terminateLeaseForm" method="POST">
                    <input type="hidden" name="terminate_lease" value="1">
                    <input type="hidden" id="terminate_lease_id" name="lease_id" value="">
                    
                    <div class="form-group">
                        <label class="form-label">Tenant</label>
                        <input type="text" id="terminate_tenant_name" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Property</label>
                        <input type="text" id="terminate_property_name" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Termination Date</label>
                        <input type="text" name="termination_date" class="form-control datepicker" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Termination Reason</label>
                        <textarea name="termination_reason" class="form-control" rows="4" required placeholder="Enter the reason for terminating this lease..."></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-danger" style="width: 100%;">
                            <i class="fas fa-times-circle"></i>
                            Confirm Termination
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Template Modal -->
    <div class="modal" id="createTemplateModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create Lease Template</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="templateForm" method="POST">
                    <input type="hidden" name="create_template" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Template Name</label>
                        <input type="text" name="template_name" class="form-control" required placeholder="e.g., Standard 12-Month Lease">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Template Content</label>
                        <textarea id="template-content" name="content" class="form-control" rows="10" required>
<h4 style="text-align: center; margin-bottom: 1.5rem;">RESIDENTIAL LEASE AGREEMENT</h4>

<div class="template-section">
    <p>This Residential Lease Agreement (the "Agreement") is made and entered into on <strong>[DATE]</strong>, by and between:</p>
    
    <div class="signature-block" style="margin: 1.5rem 0;">
        <h5>LANDLORD:</h5>
        <p>[LANDLORD_NAME]<br>
        [LANDLORD_ADDRESS]</p>
    </div>
    
    <div class="signature-block" style="margin: 1.5rem 0;">
        <h5>TENANT:</h5>
        <p>[TENANT_NAME]<br>
        [TENANT_ADDRESS]</p>
    </div>
    
    <div class="signature-block" style="margin: 1.5rem 0;">
        <h5>PROPERTY:</h5>
        <p>[PROPERTY_ADDRESS]</p>
    </div>
</div>

<div class="template-section">
    <h5 style="margin-bottom: 0.5rem;">1. TERM</h5>
    <p>The lease term will begin on <strong>[START_DATE]</strong> and end on <strong>[END_DATE]</strong>.</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">2. RENT</h5>
    <p>The monthly rent for the Property is <strong>R[RENT_AMOUNT]</strong>, payable in advance on the first day of each calendar month.</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">3. SECURITY DEPOSIT</h5>
    <p>Upon execution of this Agreement, Tenant shall deposit with Landlord the sum of <strong>R[DEPOSIT_AMOUNT]</strong> as security.</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">4. UTILITIES</h5>
    <p>Tenant shall be responsible for all utilities including water, electricity, gas, and internet.</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">5. MAINTENANCE</h5>
    <p>Tenant shall keep the premises in clean, sanitary, and good condition.</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">6. OCCUPANTS</h5>
    <p>The premises shall not be occupied by any person other than the Tenant and the following individuals: [LIST OCCUPANTS].</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">7. PETS</h5>
    <p>No pets shall be allowed on the premises without Landlord's prior written consent.</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">8. SUBLETTING</h5>
    <p>Tenant shall not sublet any portion of the Property without Landlord's prior written consent.</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">9. DEFAULT</h5>
    <p>If Tenant fails to pay rent when due, Landlord may terminate this Agreement upon providing proper notice.</p>
    
    <h5 style="margin: 1rem 0 0.5rem;">10. GOVERNING LAW</h5>
    <p>This Agreement shall be governed by the laws of the Republic of South Africa.</p>
</div>
                        </textarea>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
                            <i class="fas fa-save"></i>
                            Save Lease Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize date pickers
        flatpickr('.datepicker', {
            dateFormat: "Y-m-d",
            minDate: "today"
        });

        // Initialize signature pad
        const canvas = document.getElementById('signature-pad');

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            signaturePad.clear();
        }

        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 1,
            maxWidth: 3,
            throttle: 16,
            minDistance: 1,
            velocityFilterWeight: 0.7,
            dotSize: function () {
                return (this.minWidth + this.maxWidth) / 2;
            }
        });

        document.querySelectorAll('.sign-lease-btn').forEach(button => {
            button.addEventListener('click', function() {
                const leaseId = this.getAttribute('data-lease-id');
                const tenantName = this.getAttribute('data-tenant-name');
                const propertyName = this.getAttribute('data-property-name');
                
                document.getElementById('lease_id').value = leaseId;
                document.getElementById('signatureLeaseInfo').innerHTML = `
                    <strong>Tenant:</strong> ${tenantName}<br>
                    <strong>Property:</strong> ${propertyName}
                `;
                
                openModal('signLeaseModal');
                
                setTimeout(() => {
                    resizeCanvas();
                }, 100);
            });
        });

        window.addEventListener("resize", resizeCanvas);

        // Modal functionality
        const modals = document.querySelectorAll('.modal');
        const closeButtons = document.querySelectorAll('.close-modal');
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Create lease buttons
        document.querySelectorAll('.create-lease-btn').forEach(button => {
            button.addEventListener('click', function() {
                const tenantName = this.getAttribute('data-tenant-name');
                const propertyName = this.getAttribute('data-property-name');
                const applicationId = this.getAttribute('data-application-id');
                const tenantId = this.getAttribute('data-tenant-id');
                
                <?php if (empty($templates)): ?>
                    Swal.fire({
                        title: 'No Lease Templates Available',
                        text: 'You have no available leases. Please create a lease template first.',
                        icon: 'warning',
                        confirmButtonText: 'Create Template',
                        showCancelButton: true,
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            openModal('createTemplateModal');
                        }
                    });
                    return;
                <?php endif; ?>
                
                fetch('check_draft_lease.php?tenant_id=' + tenantId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_draft) {
                            Swal.fire({
                                title: 'Draft Exists',
                                text: 'This tenant already has a draft lease. Please sign or cancel the existing lease before creating a new one.',
                                icon: 'warning',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            document.getElementById('propertyName').value = propertyName;
                            document.getElementById('application_id').value = applicationId;
                            openModal('createLeaseModal');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('propertyName').value = propertyName;
                        document.getElementById('application_id').value = applicationId;
                        openModal('createLeaseModal');
                    });
            });
        });

        // Terminate lease buttons
        document.querySelectorAll('.terminate-lease-btn').forEach(button => {
            button.addEventListener('click', function() {
                const leaseId = this.getAttribute('data-lease-id');
                const tenantName = this.getAttribute('data-tenant-name');
                const propertyName = this.getAttribute('data-property-name');
                
                document.getElementById('terminate_lease_id').value = leaseId;
                document.getElementById('terminate_tenant_name').value = tenantName;
                document.getElementById('terminate_property_name').value = propertyName;
                
                openModal('terminateLeaseModal');
            });
        });
        
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                closeModal(modal.id);
            });
        });
        
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal(modal.id);
                }
            });
        });
        
        document.getElementById('createTemplateBtn').addEventListener('click', function() {
            openModal('createTemplateModal');
        });
        
        // Signature pad functionality
        document.getElementById('clearSignature').addEventListener('click', function() {
            signaturePad.clear();
            document.getElementById('signaturePreview').style.display = 'none';
            document.getElementById('signLeaseBtn').disabled = true;
        });
        
        document.getElementById('saveSignature').addEventListener('click', function() {
            if (signaturePad.isEmpty()) {
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
            
            document.getElementById('previewImage').src = signatureData;
            document.getElementById('signaturePreview').style.display = 'block';
            
            Swal.fire({
                title: 'Signature Saved',
                text: 'Your signature has been saved. Review and confirm to activate the lease.',
                icon: 'success',
                confirmButtonText: 'OK'
            });
        });
        
        // Initialize Summernote
        $(document).ready(function() {
            $('#template-content').summernote({
                height: 300,
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['height', ['height']],
                    ['insert', ['link', 'table', 'hr']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                placeholder: 'Enter your lease agreement template here...'
            });
        });

        // Form submissions with confirmations
        document.getElementById('templateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const templateName = this.elements['template_name'].value.trim();
            const content = this.elements['content'].value.trim();
            
            if (!templateName || !content) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please fill in all required fields',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Create Lease Template?',
                text: `Are you sure you want to create the template "${templateName}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, create template',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Creating Template',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    this.submit();
                }
            });
        });

        document.getElementById('leaseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const templateId = this.elements['template_id'].value;
            const propertyName = document.getElementById('propertyName').value;
            const startDate = this.elements['start_date'].value;
            const endDate = this.elements['end_date'].value;
            const rentAmount = this.elements['rent_amount'].value;

            if (!templateId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please select a lease template',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Create Lease Agreement?',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <p><strong>Property:</strong> ${propertyName}</p>
                        <p><strong>Lease Period:</strong> ${startDate} to ${endDate}</p>
                        <p><strong>Monthly Rent:</strong> R${parseFloat(rentAmount).toLocaleString()}</p>
                    </div>
                    <p style="margin-top: 1rem;">Are you sure you want to create this lease agreement?</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, create lease',
                cancelButtonText: 'Cancel',
                customClass: {
                    htmlContainer: 'text-left'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Creating Lease Agreement',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    this.submit();
                }
            });
        });

        document.getElementById('terminateLeaseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const tenantName = document.getElementById('terminate_tenant_name').value;
            const propertyName = document.getElementById('terminate_property_name').value;
            const terminationDate = this.elements['termination_date'].value;
            const reason = this.elements['termination_reason'].value.trim();
            
            if (!reason) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please provide a termination reason',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Terminate Lease Agreement?',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 1rem;">
                            <strong>⚠️ Warning:</strong> This action cannot be undone.
                        </div>
                        <p><strong>Tenant:</strong> ${tenantName}</p>
                        <p><strong>Property:</strong> ${propertyName}</p>
                        <p><strong>Termination Date:</strong> ${terminationDate}</p>
                        <p><strong>Reason:</strong> ${reason}</p>
                    </div>
                    <p style="margin-top: 1rem;"><strong>Are you sure you want to terminate this lease?</strong></p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, terminate lease',
                cancelButtonText: 'Cancel',
                customClass: {
                    htmlContainer: 'text-left'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Terminating Lease',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    this.submit();
                }
            });
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const sidebar = document.querySelector('.sidebar');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }

        document.addEventListener('click', (e) => {
            if (window.innerWidth < 900 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !mobileMenuBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        function resetFilters() {
            document.getElementById('property_filter').value = '';
            document.getElementById('lease_status_filter').value = '';
            document.getElementById('search').value = '';
            document.getElementById('filterForm').submit();
        }

        function toggleDropdown(button) {
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                if (dropdown !== button.nextElementSibling) {
                    dropdown.classList.remove('show');
                }
            });

            // Toggle the clicked dropdown
            const dropdown = button.nextElementSibling;
            dropdown.classList.toggle('show');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.matches('.dropdown-btn') && !event.target.closest('.dropdown-btn')) {
                document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });

        function confirmLogout(event) {
            event.preventDefault();

            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out of your account",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, logout!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../auth/logout.php';
                }
            });
        }

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