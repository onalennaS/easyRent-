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

// Fetch approved tenants with lease information - updated to include all approved applications
// Fetch approved tenants with lease information - updated to include all approved applications
$approved_tenants_query = "
    SELECT 
        u.id AS tenant_id,
        CONCAT(u.first_name, ' ', u.last_name) AS tenant_name,
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
        l.signed_at,
        l.signature_path,
        (SELECT COUNT(*) FROM rental_applications a2 
         WHERE a2.tenant_id = u.id AND a2.status = 'approved') AS approved_app_count
    FROM rental_applications a
    JOIN properties p ON a.property_id = p.id
    JOIN users u ON a.tenant_id = u.id
    LEFT JOIN leases l ON a.id = l.application_id
    WHERE p.landlord_id = $landlord_id
    AND a.status = 'approved'
    AND (l.id IS NULL OR l.status != 'terminated')
    ORDER BY 
        u.last_name ASC,
        u.first_name ASC,
        a.application_date DESC,
        CASE WHEN l.status IS NULL THEN 0 ELSE 1 END DESC
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

// Handle lease creation form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_lease'])) {
    $application_id = intval($_POST['application_id']);
    $template_id = intval($_POST['template_id']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $rent_amount = floatval($_POST['rent_amount']);
    $deposit = floatval($_POST['deposit']);
    $terms = mysqli_real_escape_string($conn, $_POST['terms']);
    
    // Get tenant_id from the application
    $tenant_id_query = "SELECT tenant_id FROM rental_applications WHERE id = $application_id";
    $tenant_id_result = mysqli_query($conn, $tenant_id_query);
    
    if ($tenant_id_result && mysqli_num_rows($tenant_id_result) > 0) {
        $tenant_data = mysqli_fetch_assoc($tenant_id_result);
        $tenant_id = $tenant_data['tenant_id'];
        
        // Check if this tenant already has any draft leases
        $check_draft_query = "SELECT id FROM leases WHERE tenant_id = $tenant_id AND status = 'draft'";
        $check_draft_result = mysqli_query($conn, $check_draft_query);
        
        if ($check_draft_result && mysqli_num_rows($check_draft_result) > 0) {
            $error_message = "This tenant already has a draft lease agreement. Please sign or cancel the existing lease before creating a new one.";
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
                            'draft'
                        )
                    ";
                    
                    if (mysqli_query($conn, $insert_query)) {
                        $success_message = "Lease agreement created successfully!";
                        // Refresh tenants data
                        $approved_tenants_result = mysqli_query($conn, $approved_tenants_query);
                        $approved_tenants = [];
                        if ($approved_tenants_result) {
                            while ($row = mysqli_fetch_assoc($approved_tenants_result)) {
                                $approved_tenants[] = $row;
                            }
                        }
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
        $success_message = "Lease template created successfully!";
        // Refresh templates data
        $templates_result = mysqli_query($conn, $templates_query);
        $templates = [];
        if ($templates_result) {
            while ($row = mysqli_fetch_assoc($templates_result)) {
                $templates[] = $row;
            }
        }
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
            $success_message = "Lease agreement signed and activated successfully!";
            // Refresh tenants data
            $approved_tenants_result = mysqli_query($conn, $approved_tenants_query);
            $approved_tenants = [];
            if ($approved_tenants_result) {
                while ($row = mysqli_fetch_assoc($approved_tenants_result)) {
                    $approved_tenants[] = $row;
                }
            }
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
        $success_message = "Lease agreement terminated successfully!";
        // Refresh tenants data
        $approved_tenants_result = mysqli_query($conn, $approved_tenants_query);
        $approved_tenants = [];
        if ($approved_tenants_result) {
            while ($row = mysqli_fetch_assoc($approved_tenants_result)) {
                $approved_tenants[] = $row;
            }
        }
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
    <title>Approved Tenants - EasyRent</title>
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
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
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
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
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

.btn-terminated {
    background-color: #6b7280;
    color: white;
}

.btn-terminated:hover {
    background-color: #4b5563;
}

/* Table Styles */
.tenant-table-container {
    overflow-x: auto;
    margin-bottom: 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.tenant-table {
    width: 100%;
    border-collapse: collapse;
}

.tenant-table th {
    background-color: #f1f5f9;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
}

.tenant-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
}

.tenant-table tr:last-child td {
    border-bottom: none;
}

.tenant-table tr:hover {
    background-color: #f8fafc;
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

.table-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.table-actions .btn {
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
}

/* Status styles */
.lease-status {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-draft {
    background: #fef3c7;
    color: #92400e;
}

.status-signed,
.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-pending {
    background: #dbeafe;
    color: #1e40af;
}

/* Modals */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 50px rgba(0,0,0,0.2);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.25rem;
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

.modal-body {
    padding: 2rem;
    line-height: 1.8;
}

.form-group {
    margin-bottom: 1.8rem;
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

/* Template Styles */
.template-section {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f1f5f9;
}

.template-section h5 {
    color: #1e293b;
    font-weight: 600;
}

.placeholder-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
}

.placeholder-item {
    background: #f8fafc;
    padding: 0.75rem;
    border-radius: 6px;
    border-left: 3px solid #3b82f6;
}

.placeholder-name {
    display: block;
    font-weight: 600;
    color: #1e40af;
    margin-bottom: 0.25rem;
}

.placeholder-desc {
    display: block;
    font-size: 0.85rem;
    color: #64748b;
}

.template-preview {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    font-size: 0.9rem;
    line-height: 1.8;
    max-height: 300px;
    overflow-y: auto;
}

.template-preview h4 {
    color: #1e293b;
    margin-top: 1.5rem;
    margin-bottom: 0.8rem;
    font-size: 1.1rem;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.5rem;
}

.template-preview ul {
    margin-bottom: 1.5rem;
    padding-left: 1.5rem;
}

.template-preview li {
    margin-bottom: 0.5rem;
}

.template-preview p {
    margin-bottom: 1.2rem;
}

/* Signature Pad Styles */
.signature-container {
    margin: 1.5rem 0;
}

.signature-pad-wrapper {
    position: relative;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    margin-bottom: 1rem;
}

#signature-pad {
    width: 100%;
    height: 200px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.1);
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

/* Summernote editor styling */
.note-editor {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    overflow: hidden;
}

.note-editor .note-toolbar {
    background: #f1f5f9;
    border-bottom: 1px solid #cbd5e1;
}

.note-editor .note-editable p {
    margin-bottom: 1.2rem;
    line-height: 1.8;
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

.alert i {
    font-size: 1.25rem;
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

/* Badge styles */
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: #3b82f6;
    color: white;
    font-size: 0.75rem;
    border-radius: 12px;
    font-weight: 500;
    margin-top: 0.25rem;
}

/* View lease button */
.view-lease-btn {
    background-color: #4CAF50;
    color: white;
}

.view-lease-btn:hover {
    background-color: #45a049;
}

/* Termination modal styles */
.termination-reason {
    margin-top: 1rem;
}

.termination-reason textarea {
    min-height: 100px;
}

/* Detail row spacing */
.detail-row {
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #f1f5f9;
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

/* Tenant property section */
.tenant-property {
    background: #f1f5f9;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.tenant-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}

.signature-space {
    height: 60px;
    border-bottom: 1px solid #94a3b8;
    margin-bottom: 0.5rem;
}

.signature-line {
    margin-top: 3rem;
    padding-top: 1.5rem;
    margin-bottom: 1.5rem;
}

.signature-block {
    margin-bottom: 1rem;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .tenant-table th:nth-child(5),
    .tenant-table td:nth-child(5),
    .tenant-table th:nth-child(6),
    .tenant-table td:nth-child(6) {
        display: none;
    }

    .placeholder-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 992px) {
    .tenant-table th:nth-child(4),
    .tenant-table td:nth-child(4),
    .tenant-table th:nth-child(7),
    .tenant-table td:nth-child(7) {
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
        width: 100%;
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
}

@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }

    .tenant-table th:nth-child(3),
    .tenant-table td:nth-child(3) {
        display: none;
    }
    
    .tenant-info-cell {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .table-actions {
        flex-direction: column;
    }
    
    .table-actions .btn {
        width: 100%;
    }

    .modal-body {
        padding: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .tenant-property {
        padding: 1.2rem;
    }

    .signature-line {
        flex-direction: column;
    }
    
    .signature-block {
        width: 100%;
        margin-bottom: 1.5rem;
    }
}

@media (max-width: 480px) {
    .modal-body {
        padding: 1rem;
    }
    
    .form-group {
        margin-bottom: 1.2rem;
    }
    
    .template-preview {
        padding: 1rem;
    }

    .page-title {
        font-size: 1.5rem;
    }

    .header-buttons {
        flex-direction: column;
        width: 100%;
    }

    .tenant-actions {
        flex-direction: column;
    }

    .signature-actions {
        flex-direction: column;
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
            <a href="tenants.php" class="nav-link active">
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
        <h1 class="page-title">Approved Tenants</h1>
        <div></div>
    </div>
        <!-- Page Header -->
        <div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users"></i>
        Approved Tenants
    </h1>
    <div class="header-buttons">
        <a href="terminated_leases.php" class="btn btn-secondary">
            <i class="fas fa-file-contract"></i>
            View Terminated Leases
        </a>
        <button class="btn btn-primary" id="createTemplateBtn">
            <i class="fas fa-file-contract"></i>
            Create Lease Template
        </button>
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

<!-- Replace the entire tenant-cards div with this table -->
<div class="tenant-table-container">
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
            <?php if (!empty($grouped_tenants)): ?>
                <?php foreach ($grouped_tenants as $tenant_id => $tenant_data): ?>
                    <?php 
                    $tenant_info = $tenant_data['tenant_info'];
                    $applications = $tenant_data['applications'];
                    ?>
                    
                    <?php foreach ($applications as $application): ?>
                        <tr>
                            <!-- Tenant Column -->
                            <td>
                                <div class="tenant-info-cell">
                                    <div class="tenant-avatar-small">
                                        <?php echo strtoupper(substr($tenant_info['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($tenant_info['name']); ?></strong>
                                        <div class="tenant-id">ID: #<?php echo htmlspecialchars($tenant_info['id']); ?></div>
                                        <?php if ($tenant_info['approved_app_count'] > 1): ?>
                                            <span class="badge"><?php echo $tenant_info['approved_app_count']; ?> approved</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Contact Column -->
                            <td>
                                <div><?php echo htmlspecialchars($tenant_info['email']); ?></div>
                                <div><?php echo htmlspecialchars($tenant_info['phone']); ?></div>
                            </td>
                            
                            <!-- Property Column -->
                            <td>
                                <div class="property-title"><?php echo htmlspecialchars($application['property_title']); ?></div>
                                <div class="property-address">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($application['property_address']); ?>
                                </div>
                            </td>
                            
                            <!-- Application Date Column -->
                            <td>
                                <?php echo date('M j, Y', strtotime($application['application_date'])); ?>
                            </td>
                            
                            <!-- Lease Status Column -->
                            <td>
                                <?php if ($application['lease_id']): ?>
                                    <span class="lease-status status-<?php echo $application['lease_status']; ?>">
                                        <?php echo ucfirst($application['lease_status']); ?>
                                    </span>
                                <?php else: ?>
                                    No lease created
                                <?php endif; ?>
                            </td>
                            
                            <!-- Lease Period Column -->
                            <td>
                                <?php if ($application['lease_id']): ?>
                                    <?php echo date('M j, Y', strtotime($application['lease_start_date'])); ?> - 
                                    <?php echo date('M j, Y', strtotime($application['lease_end_date'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            
                            <!-- Rent Amount Column -->
                            <td>
                                <?php if ($application['lease_id']): ?>
                                    R<?php echo number_format($application['monthly_rent']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            
                            <!-- Actions Column -->
                            <td>
                                <div class="table-actions">
                                    <?php if ($application['lease_id']): ?>
                                        <?php if ($application['lease_status'] == 'draft'): ?>
                                            <button class="btn btn-sm btn-success sign-lease-btn" data-lease-id="<?php echo $application['lease_id']; ?>">
                                                <i class="fas fa-signature"></i> Sign
                                            </button>
                                        <?php elseif ($application['lease_status'] == 'active'): ?>
                                            <a href="view_lease.php?id=<?php echo $application['lease_id']; ?>" class="btn btn-sm btn-secondary">
                                                <i class="fas fa-file-alt"></i> View
                                            </a>
                                            <a href="download_lease.php?id=<?php echo $application['lease_id']; ?>" class="btn btn-sm btn-secondary">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button class="btn btn-sm btn-danger terminate-lease-btn" 
                                                    data-lease-id="<?php echo $application['lease_id']; ?>"
                                                    data-tenant-name="<?php echo htmlspecialchars($tenant_info['name']); ?>"
                                                    data-property-name="<?php echo htmlspecialchars($application['property_title']); ?>">
                                                <i class="fas fa-times-circle"></i> Terminate
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-primary create-lease-btn" 
                                                data-tenant-name="<?php echo htmlspecialchars($tenant_info['name']); ?>" 
                                                data-property-name="<?php echo htmlspecialchars($application['property_title']); ?>"
                                                data-application-id="<?php echo $application['application_id']; ?>"
                                                data-tenant-id="<?php echo $tenant_info['id']; ?>">
                                            <i class="fas fa-file-contract"></i> Create
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-user-friends"></i>
                        <h3>No Approved Tenants</h3>
                        <?php if (isset($error_message)): ?>
                            <p style="color: red;">Database error occurred. Please check your database connection and table structure.</p>
                        <?php else: ?>
                            <p>You don't have any approved tenants yet. Once tenants apply and get approved, they'll appear here.</p>
                        <?php endif; ?>
                        <a href="applications.php" class="btn btn-primary">
                            <i class="fas fa-list"></i>
                            View Applications
                        </a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
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
                        <label class="form-label">Tenant</label>
                        <input type="text" id="tenantName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Property</label>
                        <input type="text" id="propertyName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Lease Template</label>
                        <select name="template_id" class="form-control" required id="templateSelect">
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
                        <p>Please sign your name in the box below using your mouse or finger</p>
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
                    
                    <div class="form-group termination-reason">
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
    <!-- Update the template content in the createTemplateModal -->
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
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(255, 255, 255, 0)',
            penColor: 'rgb(0, 0, 0)'
        });

        // Handle modal functionality
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
                
                // Check if tenant already has a draft lease via AJAX
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
                            document.getElementById('tenantName').value = tenantName;
                            document.getElementById('propertyName').value = propertyName;
                            document.getElementById('application_id').value = applicationId;
                            
                            openModal('createLeaseModal');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // If there's an error with the check, still allow opening the modal
                        document.getElementById('tenantName').value = tenantName;
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
        
        // Sign lease buttons
        document.querySelectorAll('.sign-lease-btn').forEach(button => {
            button.addEventListener('click', function() {
                const leaseId = this.getAttribute('data-lease-id');
                document.getElementById('lease_id').value = leaseId;
                openModal('signLeaseModal');
            });
        });
        
        // Close modals when clicking close button or outside modal
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
        
        // Create template button
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
            
            // Show preview
            document.getElementById('previewImage').src = signatureData;
            document.getElementById('signaturePreview').style.display = 'block';
            
            Swal.fire({
                title: 'Signature Saved',
                text: 'Your signature has been saved. Review and confirm to activate the lease.',
                icon: 'success',
                confirmButtonText: 'OK'
            });
        });
        
        // Initialize Summernote editor for template content
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

        // ENHANCED FORM SUBMISSION HANDLERS WITH CONFIRMATIONS

        // Handle lease template creation with confirmation
        document.getElementById('templateForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default first
            
            const templateName = this.elements['template_name'].value.trim();
            const content = this.elements['content'].value.trim();
            
            // Validation
            if (!templateName) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please enter a template name',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            if (!content) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please enter template content',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Confirmation dialog
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
                    // Show loading
                    Swal.fire({
                        title: 'Creating Template',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the form
                    this.submit();
                }
            });
        });

        // Handle lease agreement creation with confirmation
        document.getElementById('leaseForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default first
            
            const templateId = this.elements['template_id'].value;
            const tenantName = document.getElementById('tenantName').value;
            const propertyName = document.getElementById('propertyName').value;
            const startDate = this.elements['start_date'].value;
            const endDate = this.elements['end_date'].value;
            const rentAmount = this.elements['rent_amount'].value;
            
            // Validation
            if (!templateId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please select a lease template',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Confirmation dialog
            Swal.fire({
                title: 'Create Lease Agreement?',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <p><strong>Tenant:</strong> ${tenantName}</p>
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
                    // Show loading
                    Swal.fire({
                        title: 'Creating Lease Agreement',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the form
                    this.submit();
                }
            });
        });

        // Handle lease signing with double confirmation
        document.getElementById('signLeaseForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default first
            
            if (signaturePad.isEmpty()) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please provide a signature before submitting',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // First confirmation - Review
            Swal.fire({
                title: 'Review Lease Details',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <h4 style="margin-bottom: 1rem; color: #1e293b;">Please review the lease details:</h4>
                        <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <p style="margin: 0.5rem 0;"><strong>Action:</strong> Sign and activate lease agreement</p>
                            <p style="margin: 0.5rem 0;"><strong>Status Change:</strong> Draft → Active</p>
                            <p style="margin: 0.5rem 0;"><strong>Effect:</strong> Lease becomes legally binding</p>
                        </div>
                        <div style="margin: 1rem 0; text-align: center;">
                            <img src="${signaturePad.toDataURL()}" style="max-width: 200px; border: 1px solid #e5e7eb; border-radius: 4px;" alt="Your signature">
                            <p style="font-size: 0.9rem; color: #64748b; margin-top: 0.5rem;">Your signature</p>
                        </div>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Continue to final step',
                cancelButtonText: 'Cancel',
                customClass: {
                    htmlContainer: 'text-left'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Second confirmation - Final confirmation
                    Swal.fire({
                        title: 'Final Confirmation',
                        html: `
                            <div style="text-align: center; margin: 1rem 0;">
                                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1rem; text-align: left;">
                                    <strong>⚠️ Important:</strong> Once you confirm, this lease agreement will be:
                                    <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                                        <li>Legally binding and active</li>
                                        <li>Digitally signed with your signature</li>
                                        <li>Available for tenant access</li>
                                        <li>Cannot be easily reversed</li>
                                    </ul>
                                </div>
                                <p><strong>Are you absolutely sure you want to sign and activate this lease agreement?</strong></p>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ef4444',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Yes, sign and activate',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            htmlContainer: 'text-left'
                        }
                    }).then((finalResult) => {
                        if (finalResult.isConfirmed) {
                            // Show loading
                            Swal.fire({
                                title: 'Signing Lease Agreement',
                                text: 'Processing your signature and activating lease...',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            // Submit the form
                            this.submit();
                        }
                    });
                }
            });
        });

        // Handle terminate lease form submission
        document.getElementById('terminateLeaseForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default first
            
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
                    // Show loading
                    Swal.fire({
                        title: 'Terminating Lease',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the form
                    this.submit();
                }
            });
        });

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
    
    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const sidebar = document.querySelector('.sidebar');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 900 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !mobileMenuBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Logout confirmation
        document.getElementById('logoutLink')?.addEventListener('click', function(e) {
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
                    window.location.href = '../auth/logout.php';
                }
            });
        });
    </script>

</body>
</html>