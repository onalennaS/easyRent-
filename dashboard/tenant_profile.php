<?php
session_start();

// Check if user is logged in and is a tenant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tenant') {
    header("Location: ../login.php");
    exit();
}

// Database connection
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

// Define upload directories - ADD THIS
$upload_dir = '../uploads/tenant_profiles/';
$documents_dir = '../uploads/tenant_documents/';

$tenant_id = $_SESSION['user_id'];

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate required fields
        $required_fields = [
            'full_name' => 'Full Name',
            'email' => 'Email Address',
            'phone' => 'Phone Number',
            'address' => 'Current Address',
            'city' => 'City',
            'province' => 'Province',
            'postal_code' => 'Postal Code',
            'id_number' => 'ID Number',
            'employment_status' => 'Employment Status',
            'monthly_income' => 'Monthly Income',
            'emergency_contact_name' => 'Emergency Contact Name',
            'emergency_contact_phone' => 'Emergency Contact Phone'
        ];
        
        $missing_fields = [];
        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $label;
            }
        }
        
        // Validate ID number length
        if (!empty($_POST['id_number']) && strlen($_POST['id_number']) !== 13) {
            $error_message = "ID Number must be exactly 13 digits";
        }
        // If there are missing required fields, return error
        elseif (!empty($missing_fields)) {
            $error_message = "Missing required fields: " . implode(", ", $missing_fields);
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/tenant_profiles/';
            $documents_dir = '../uploads/tenant_documents/';
            
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create profiles upload directory");
                }
            }
            if (!file_exists($documents_dir)) {
                if (!mkdir($documents_dir, 0777, true)) {
                    throw new Exception("Failed to create documents upload directory");
                }
            }

            // Process profile data
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $address = mysqli_real_escape_string($conn, $_POST['address']);
            $city = mysqli_real_escape_string($conn, $_POST['city']);
            $province = mysqli_real_escape_string($conn, $_POST['province']);
            $postal_code = mysqli_real_escape_string($conn, $_POST['postal_code']);
            $id_number = mysqli_real_escape_string($conn, $_POST['id_number']);
            $employment_status = mysqli_real_escape_string($conn, $_POST['employment_status']);
            $employer_name = mysqli_real_escape_string($conn, $_POST['employer_name']);
            $job_title = mysqli_real_escape_string($conn, $_POST['job_title']);
            $monthly_income = (float)$_POST['monthly_income'];
            $emergency_contact_name = mysqli_real_escape_string($conn, $_POST['emergency_contact_name']);
            $emergency_contact_phone = mysqli_real_escape_string($conn, $_POST['emergency_contact_phone']);
            $emergency_contact_relationship = mysqli_real_escape_string($conn, $_POST['emergency_contact_relationship']);
            $rental_history = mysqli_real_escape_string($conn, $_POST['rental_history']);
            $additional_info = mysqli_real_escape_string($conn, $_POST['additional_info']);

            // Handle profile image upload
            $profile_image = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                if (in_array($_FILES['profile_image']['type'], $allowed_types)) {
                    $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $profile_image = 'tenant_profile_' . $tenant_id . '_' . time() . '.' . $file_extension;
                    move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $profile_image);
                }
            }

            // Check if profile exists
            $check_query = "SELECT id FROM tenant_profiles WHERE tenant_id = $tenant_id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing profile
                $query = "UPDATE tenant_profiles SET 
                            full_name = '$full_name',
                            email = '$email',
                            phone = '$phone',
                            address = '$address',
                            city = '$city',
                            province = '$province',
                            postal_code = '$postal_code',
                            id_number = '$id_number',
                            employment_status = '$employment_status',
                            employer_name = '$employer_name',
                            job_title = '$job_title',
                            monthly_income = $monthly_income,
                            emergency_contact_name = '$emergency_contact_name',
                            emergency_contact_phone = '$emergency_contact_phone',
                            emergency_contact_relationship = '$emergency_contact_relationship',
                            rental_history = '$rental_history',
                            additional_info = '$additional_info'";
                
                if ($profile_image) {
                    $query .= ", profile_image = '$profile_image'";
                }
                
                $query .= ", updated_at = NOW() WHERE tenant_id = $tenant_id";
            } else {
                // Insert new profile
                $query = "INSERT INTO tenant_profiles (
                            tenant_id, full_name, email, phone, address, city, province, postal_code,
                            id_number, employment_status, employer_name, job_title, monthly_income,
                            emergency_contact_name, emergency_contact_phone, emergency_contact_relationship,
                            rental_history, additional_info";
                
                if ($profile_image) {
                    $query .= ", profile_image";
                }
                
                $query .= ", created_at, updated_at) VALUES (
                            $tenant_id, '$full_name', '$email', '$phone', '$address', '$city', '$province', '$postal_code',
                            '$id_number', '$employment_status', '$employer_name', '$job_title', $monthly_income,
                            '$emergency_contact_name', '$emergency_contact_phone', '$emergency_contact_relationship',
                            '$rental_history', '$additional_info'";
                
                if ($profile_image) {
                    $query .= ", '$profile_image'";
                }
                
                $query .= ", NOW(), NOW())";
            }

            if (mysqli_query($conn, $query)) {
                // Handle document uploads
                $document_types = [
                    'id_document' => 'Identity Document',
                    'proof_of_income' => 'Proof of Income',
                    'bank_statement' => 'Bank Statement',
                    'employment_letter' => 'Employment Letter',
                    'credit_report' => 'Credit Report',
                    'reference_letter' => 'Reference Letter',
                    'rental_history_doc' => 'Rental History Document'
                ];

                $upload_errors = [];
                $upload_success = [];

                foreach ($document_types as $field => $doc_name) {
                    if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
                        $file = $_FILES[$field];
                        
                        if ($file['error'] !== UPLOAD_ERR_OK) {
                            $upload_errors[] = "Error uploading {$doc_name}: " . $file['error'];
                            continue;
                        }
                        
                        // Validate file type
                        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                        $file_type = mime_content_type($file['tmp_name']);
                        
                        if (!in_array($file_type, $allowed_types) && !in_array($file['type'], $allowed_types)) {
                            $upload_errors[] = "{$doc_name}: Invalid file type. Only PDF, JPG, PNG files are allowed.";
                            continue;
                        }
                        
                        if ($file['size'] > 10 * 1024 * 1024) {
                            $upload_errors[] = "{$doc_name}: File too large. Maximum size is 10MB.";
                            continue;
                        }
                        
                        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = $field . '_' . $tenant_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $file_path = $documents_dir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            // Delete old file if exists
                            $old_file_query = "SELECT file_path FROM tenant_documents WHERE tenant_id = $tenant_id AND document_type = '$field'";
                            $old_file_result = mysqli_query($conn, $old_file_query);
                            if ($old_file_result && mysqli_num_rows($old_file_result) > 0) {
                                $old_file = mysqli_fetch_assoc($old_file_result);
                                $old_file_path = $documents_dir . $old_file['file_path'];
                                if (file_exists($old_file_path)) {
                                    unlink($old_file_path);
                                }
                            }
                            
                            $doc_query = "INSERT INTO tenant_documents (tenant_id, document_type, document_name, file_path, uploaded_at)
                                         VALUES ($tenant_id, '$field', '" . mysqli_real_escape_string($conn, $doc_name) . "', '" . mysqli_real_escape_string($conn, $filename) . "', NOW())
                                         ON DUPLICATE KEY UPDATE
                                         document_name = '" . mysqli_real_escape_string($conn, $doc_name) . "',
                                         file_path = '" . mysqli_real_escape_string($conn, $filename) . "',
                                         uploaded_at = NOW()";
                            
                            if (mysqli_query($conn, $doc_query)) {
                                $upload_success[] = "{$doc_name} uploaded successfully";
                            } else {
                                $upload_errors[] = "{$doc_name}: Database error - " . mysqli_error($conn);
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                            }
                        } else {
                            $upload_errors[] = "{$doc_name}: Failed to move uploaded file";
                        }
                    }
                }
                
                // Prepare success/error messages
                $message_parts = [];
                if (!empty($upload_success)) {
                    $message_parts[] = "Profile updated successfully!";
                    $message_parts[] = "Documents uploaded: " . implode(", ", $upload_success);
                }
                if (!empty($upload_errors)) {
                    $message_parts[] = "Upload errors: " . implode("; ", $upload_errors);
                }
                
                if (!empty($upload_errors) && empty($upload_success)) {
                    $error_message = implode(" ", $message_parts);
                } else {
                    $success_message = implode(" ", $message_parts);
                }
            } else {
                $error_message = "Error updating profile: " . mysqli_error($conn);
            }
        }
    } catch (Exception $e) {
        $error_message = "An error occurred: " . $e->getMessage();
    }
}

// Create tables if they don't exist
$create_profile_table = "
    CREATE TABLE IF NOT EXISTS tenant_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        province VARCHAR(100) NOT NULL,
        postal_code VARCHAR(10) NOT NULL,
        id_number VARCHAR(20) NOT NULL,
        employment_status ENUM('employed', 'self_employed', 'unemployed', 'student', 'retired') NOT NULL,
        employer_name VARCHAR(255),
        job_title VARCHAR(255),
        monthly_income DECIMAL(10,2) NOT NULL,
        emergency_contact_name VARCHAR(255) NOT NULL,
        emergency_contact_phone VARCHAR(20) NOT NULL,
        emergency_contact_relationship VARCHAR(100),
        rental_history TEXT,
        additional_info TEXT,
        profile_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$create_documents_table = "
    CREATE TABLE IF NOT EXISTS tenant_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        document_type VARCHAR(100) NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_doc (tenant_id, document_type),
        INDEX idx_tenant_id (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

mysqli_query($conn, $create_profile_table);
mysqli_query($conn, $create_documents_table);

// Get existing profile data
$profile = [];
$profile_query = "SELECT * FROM tenant_profiles WHERE tenant_id = $tenant_id";
$profile_result = mysqli_query($conn, $profile_query);
if ($profile_result && mysqli_num_rows($profile_result) > 0) {
    $profile = mysqli_fetch_assoc($profile_result);
}

// Fetch email from users table if not set in profile
if (empty($profile['email'])) {
    $user_email_query = "SELECT email FROM users WHERE id = $tenant_id";
    $user_email_result = mysqli_query($conn, $user_email_query);
    if ($user_email_result && mysqli_num_rows($user_email_result) > 0) {
        $user_email = mysqli_fetch_assoc($user_email_result)['email'];
        $profile['email'] = $user_email;
    }
}

// Get existing documents
$existing_documents = [];
$documents_query = "SELECT * FROM tenant_documents WHERE tenant_id = $tenant_id";
$documents_result = mysqli_query($conn, $documents_query);
if ($documents_result) {
    while ($doc = mysqli_fetch_assoc($documents_result)) {
        $existing_documents[$doc['document_type']] = $doc;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Profile - Easy Rent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .image-pending {
    position: relative;
}

.image-pending::after {
    content: "Changes pending - Save to apply";
    position: absolute;
    bottom: -25px;
    left: 50%;
    transform: translateX(-50%);
    background: #f59e0b;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 10;
}

.image-preview-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.image-pending .image-preview-overlay {
    opacity: 1;
}
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
            padding: 20px;
            min-height: 100vh;
        }

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
            background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: #333;
            cursor: pointer;
        }

        /* Profile Form Styles */
        .profile-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .profile-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .profile-header p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .completion-status {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-bottom: 1px solid #e5e7eb;
            padding: 2rem;
        }

        .completion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .completion-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .completion-percentage {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .completion-percentage span {
            font-size: 1.25rem;
            font-weight: 700;
            color: #3b82f6;
        }

        .progress-bar {
            width: 200px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .form-sections {
            padding: 2rem;
        }

        .section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: " *";
            color: #ef4444;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .document-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .document-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            background: #fafafa;
            transition: all 0.3s ease;
        }

        .document-card:hover {
            border-color: #3b82f6;
            background: white;
        }

        .document-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .document-description {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }

        .existing-file {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            color: #0369a1;
        }

        .file-date {
            font-size: 0.75rem;
            color: #0284c7;
        }

        .submit-section {
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: 500;
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

        .validation-message {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #ef4444;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .validation-message.success {
            color: #10b981;
        }

        .form-input.valid {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-input.invalid {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .document-grid {
                grid-template-columns: 1fr;
            }

            .completion-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .completion-percentage {
                width: 100%;
            }
            
            .progress-bar {
                flex: 1;
            }
        }
        .profile-image-section {
    text-align: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.profile-image-container {
    position: relative;
    display: inline-block;
    margin-bottom: 1rem;
}

.profile-image-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
    font-weight: bold;
}

.profile-image-overlay {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: #667eea;
    color: white;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.profile-image-info {
    color: #6b7280;
    font-size: 0.875rem;
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
            <li><a href="tenant_profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="browse_properties.php"><i class="fas fa-search"></i> Browse Properties</a></li>
            <li><a href="my_applications.php"><i class="fas fa-file-alt"></i> My Applications</a></li>
            <li><a href="my_lease.php"><i class="fas fa-file-contract"></i> My Lease</a></li>
            <li><a href="maintenance_requests.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="payment_history.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li><a href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
<div class="header">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <h1>Complete Your Profile</h1>
    <div class="tenant-info">
        <span>Hello, <?php echo $_SESSION['user_name'] ?? 'Tenant'; ?></span>
        <div class="avatar">
            <?php if (!empty($profile['profile_image']) && file_exists($upload_dir . $profile['profile_image'])): ?>
                <img src="<?php echo $upload_dir . htmlspecialchars($profile['profile_image']); ?>" 
                     alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'T', 0, 1)); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Container -->
        <div class="profile-container">
            <div class="profile-header">
                <h2><i class="fas fa-user-circle"></i> Build Your Tenant Profile</h2>
                <p>Complete your profile to apply for properties and increase your chances of approval</p>
            </div>

            <!-- Profile Completion Status -->
            <div class="completion-status">
                <div class="completion-header">
                    <h3><i class="fas fa-tasks"></i> Profile Completion Status</h3>
                    <div class="completion-percentage">
                        <span id="completionPercent">0%</span>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <div class="form-sections">
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <!-- Personal Information Section -->
                    <!-- Personal Information Section -->
<div class="section">
    <h3 class="section-title">
        <i class="fas fa-user"></i>
        Personal Information
    </h3>
    
    <!-- Profile Image Display -->
    <div class="profile-image-section">
        <div class="profile-image-container">
            <?php if (!empty($profile['profile_image']) && file_exists($upload_dir . $profile['profile_image'])): ?>
                <img src="<?php echo $upload_dir . htmlspecialchars($profile['profile_image']); ?>" 
                     alt="Profile Image" class="profile-image-preview" id="profileImagePreview">
            <?php else: ?>
                <div class="profile-image-preview" id="profileImagePreview">
                    <?php echo strtoupper(substr($profile['full_name'] ?? $_SESSION['user_name'] ?? 'T', 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="profile-image-overlay" onclick="document.getElementById('profile_image').click()">
                <i class="fas fa-camera"></i>
            </div>
        </div>
<div class="profile-image-info">
    <p><strong>Profile Photo</strong></p>
    <p>Click the camera icon to upload or change your photo</p>
    <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.5rem;">
        Press ESC to cancel selection • Max size: 5MB • JPG, PNG only
    </p>
</div>
    <br>
    <br>
    <div class="form-grid">
        <div class="form-group">
            <label for="full_name" class="form-label required">Full Name</label>
            <input type="text" id="full_name" name="full_name" class="form-input" 
                   value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
            <div id="full_name_validation" class="validation-message"></div>
        </div>

        <div class="form-group">
            <label for="email" class="form-label required">Email Address</label>
            <input type="email" id="email" name="email" class="form-input"
                   value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required readonly>
            <div id="email_validation" class="validation-message"></div>
        </div>

        <div class="form-group">
            <label for="phone" class="form-label required">Phone Number</label>
            <input type="tel" id="phone" name="phone" class="form-input" 
                   value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required>
            <div id="phone_validation" class="validation-message"></div>
        </div>

        <div class="form-group">
            <label for="id_number" class="form-label required">ID Number</label>
            <input type="text" id="id_number" name="id_number" class="form-input" 
                   value="<?php echo htmlspecialchars($profile['id_number'] ?? ''); ?>" required maxlength="13">
            <div id="id_number_validation" class="validation-message"></div>
        </div>

        <div class="form-group">
            <label for="profile_image" class="form-label">Profile Image</label>
            <input type="file" id="profile_image" name="profile_image" class="form-input" accept="image/*" style="display: none;">
            <div id="profile_image_validation" class="validation-message"></div>
        </div>
    </div>
</div>

                    <!-- Address Information -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Current Address
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="address" class="form-label required">Street Address</label>
                                <input type="text" id="address" name="address" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>" required>
                                <div id="address_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="city" class="form-label required">City</label>
                                <input type="text" id="city" name="city" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" required>
                                <div id="city_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="province" class="form-label required">Province</label>
                                <select id="province" name="province" class="form-input" required>
                                    <option value="">Select Province</option>
                                    <option value="Gauteng" <?php echo ($profile['province'] ?? '') == 'Gauteng' ? 'selected' : ''; ?>>Gauteng</option>
                                    <option value="Western Cape" <?php echo ($profile['province'] ?? '') == 'Western Cape' ? 'selected' : ''; ?>>Western Cape</option>
                                    <option value="KwaZulu-Natal" <?php echo ($profile['province'] ?? '') == 'KwaZulu-Natal' ? 'selected' : ''; ?>>KwaZulu-Natal</option>
                                    <option value="Eastern Cape" <?php echo ($profile['province'] ?? '') == 'Eastern Cape' ? 'selected' : ''; ?>>Eastern Cape</option>
                                    <option value="Free State" <?php echo ($profile['province'] ?? '') == 'Free State' ? 'selected' : ''; ?>>Free State</option>
                                    <option value="Limpopo" <?php echo ($profile['province'] ?? '') == 'Limpopo' ? 'selected' : ''; ?>>Limpopo</option>
                                    <option value="Mpumalanga" <?php echo ($profile['province'] ?? '') == 'Mpumalanga' ? 'selected' : ''; ?>>Mpumalanga</option>
                                    <option value="Northern Cape" <?php echo ($profile['province'] ?? '') == 'Northern Cape' ? 'selected' : ''; ?>>Northern Cape</option>
                                    <option value="North West" <?php echo ($profile['province'] ?? '') == 'North West' ? 'selected' : ''; ?>>North West</option>
                                </select>
                                <div id="province_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="postal_code" class="form-label required">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['postal_code'] ?? ''); ?>" required maxlength="4">
                                <div id="postal_code_validation" class="validation-message"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Information -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-briefcase"></i>
                            Employment Information
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="employment_status" class="form-label required">Employment Status</label>
                                <select id="employment_status" name="employment_status" class="form-input" required>
                                    <option value="">Select Status</option>
                                    <option value="employed" <?php echo ($profile['employment_status'] ?? '') == 'employed' ? 'selected' : ''; ?>>Employed</option>
                                    <option value="self_employed" <?php echo ($profile['employment_status'] ?? '') == 'self_employed' ? 'selected' : ''; ?>>Self Employed</option>
                                    <option value="student" <?php echo ($profile['employment_status'] ?? '') == 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="unemployed" <?php echo ($profile['employment_status'] ?? '') == 'unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                                    <option value="retired" <?php echo ($profile['employment_status'] ?? '') == 'retired' ? 'selected' : ''; ?>>Retired</option>
                                </select>
                                <div id="employment_status_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="employer_name" class="form-label">Employer/Company Name</label>
                                <input type="text" id="employer_name" name="employer_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['employer_name'] ?? ''); ?>">
                                <div id="employer_name_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="job_title" class="form-label">Job Title/Position</label>
                                <input type="text" id="job_title" name="job_title" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['job_title'] ?? ''); ?>">
                                <div id="job_title_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="monthly_income" class="form-label required">Monthly Income (R)</label>
                                <input type="number" id="monthly_income" name="monthly_income" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['monthly_income'] ?? ''); ?>" required min="0" step="0.01">
                                <div id="monthly_income_validation" class="validation-message"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-phone"></i>
                            Emergency Contact
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="emergency_contact_name" class="form-label required">Contact Name</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['emergency_contact_name'] ?? ''); ?>" required>
                                <div id="emergency_contact_name_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="emergency_contact_phone" class="form-label required">Contact Phone</label>
                                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['emergency_contact_phone'] ?? ''); ?>" required>
                                <div id="emergency_contact_phone_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                                <select id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-input">
                                    <option value="">Select Relationship</option>
                                    <option value="parent" <?php echo ($profile['emergency_contact_relationship'] ?? '') == 'parent' ? 'selected' : ''; ?>>Parent</option>
                                    <option value="sibling" <?php echo ($profile['emergency_contact_relationship'] ?? '') == 'sibling' ? 'selected' : ''; ?>>Sibling</option>
                                    <option value="spouse" <?php echo ($profile['emergency_contact_relationship'] ?? '') == 'spouse' ? 'selected' : ''; ?>>Spouse/Partner</option>
                                    <option value="friend" <?php echo ($profile['emergency_contact_relationship'] ?? '') == 'friend' ? 'selected' : ''; ?>>Friend</option>
                                    <option value="relative" <?php echo ($profile['emergency_contact_relationship'] ?? '') == 'relative' ? 'selected' : ''; ?>>Other Relative</option>
                                    <option value="other" <?php echo ($profile['emergency_contact_relationship'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <div id="emergency_contact_relationship_validation" class="validation-message"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Additional Information
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="rental_history" class="form-label">Previous Rental History</label>
                                <textarea id="rental_history" name="rental_history" class="form-input form-textarea" 
                                          placeholder="Describe your previous rental experience, landlord references, etc..."><?php echo htmlspecialchars($profile['rental_history'] ?? ''); ?></textarea>
                                <div id="rental_history_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group full-width">
                                <label for="additional_info" class="form-label">Additional Information</label>
                                <textarea id="additional_info" name="additional_info" class="form-input form-textarea" 
                                          placeholder="Any additional information that might help with your application..."><?php echo htmlspecialchars($profile['additional_info'] ?? ''); ?></textarea>
                                <div id="additional_info_validation" class="validation-message"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Required Documents Section -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-file-upload"></i>
                            Required Documents
                        </h3>
                        
                        <p style="margin-bottom: 2rem; color: #6b7280;">Upload the following documents to strengthen your rental applications. All documents should be clear and up-to-date.</p>
                        
                        <div class="document-grid">
                            <!-- Identity Document -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-id-card"></i>
                                    Identity Document
                                </div>
                                <div class="document-description">
                                    Copy of your South African ID or passport
                                </div>
                                
                                <?php if (isset($existing_documents['id_document'])): ?>
                                    <div class="existing-file">
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($existing_documents['id_document']['document_name']); ?></div>
                                            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['id_document']['uploaded_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" id="id_document" name="id_document" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="id_document_validation" class="validation-message"></div>
                            </div>

                            <!-- Proof of Income -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-money-check-alt"></i>
                                    Proof of Income
                                </div>
                                <div class="document-description">
                                    Latest 3 months payslips or income statements
                                </div>
                                
                                <?php if (isset($existing_documents['proof_of_income'])): ?>
                                    <div class="existing-file">
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($existing_documents['proof_of_income']['document_name']); ?></div>
                                            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['proof_of_income']['uploaded_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" id="proof_of_income" name="proof_of_income" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="proof_of_income_validation" class="validation-message"></div>
                            </div>

                            <!-- Bank Statement -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-university"></i>
                                    Bank Statement
                                </div>
                                <div class="document-description">
                                    Latest 3 months bank statements
                                </div>
                                
                                <?php if (isset($existing_documents['bank_statement'])): ?>
                                    <div class="existing-file">
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($existing_documents['bank_statement']['document_name']); ?></div>
                                            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['bank_statement']['uploaded_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" id="bank_statement" name="bank_statement" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="bank_statement_validation" class="validation-message"></div>
                            </div>

                            <!-- Employment Letter -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-file-alt"></i>
                                    Employment Letter
                                </div>
                                <div class="document-description">
                                    Letter from employer confirming employment
                                </div>
                                
                                <?php if (isset($existing_documents['employment_letter'])): ?>
                                    <div class="existing-file">
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($existing_documents['employment_letter']['document_name']); ?></div>
                                            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['employment_letter']['uploaded_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" id="employment_letter" name="employment_letter" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="employment_letter_validation" class="validation-message"></div>
                            </div>

                            <!-- Credit Report -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-chart-line"></i>
                                    Credit Report
                                </div>
                                <div class="document-description">
                                    Recent credit report from approved credit bureau
                                </div>
                                
                                <?php if (isset($existing_documents['credit_report'])): ?>
                                    <div class="existing-file">
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($existing_documents['credit_report']['document_name']); ?></div>
                                            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['credit_report']['uploaded_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" id="credit_report" name="credit_report" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="credit_report_validation" class="validation-message"></div>
                            </div>

                            <!-- Reference Letter -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-user-friends"></i>
                                    Reference Letter
                                </div>
                                <div class="document-description">
                                    Character reference from previous landlord or employer
                                </div>
                                
                                <?php if (isset($existing_documents['reference_letter'])): ?>
                                    <div class="existing-file">
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($existing_documents['reference_letter']['document_name']); ?></div>
                                            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['reference_letter']['uploaded_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" id="reference_letter" name="reference_letter" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="reference_letter_validation" class="validation-message"></div>
                            </div>

                            <!-- Rental History Document -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-home"></i>
                                    Rental History Document
                                </div>
                                <div class="document-description">
                                    Previous lease agreements or rental references
                                </div>
                                
                                <?php if (isset($existing_documents['rental_history_doc'])): ?>
                                    <div class="existing-file">
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($existing_documents['rental_history_doc']['document_name']); ?></div>
                                            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['rental_history_doc']['uploaded_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <input type="file" id="rental_history_doc" name="rental_history_doc" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="rental_history_doc_validation" class="validation-message"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div class="submit-section">
                        <div>
                            <p style="color: #6b7280; margin-bottom: 0.5rem;">
                                <i class="fas fa-info-circle"></i>
                                Complete profile improves application success rate
                            </p>
                            <p style="color: #9ca3af; font-size: 0.875rem;">
                                All information is encrypted and stored securely
                            </p>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                            <i class="fas fa-save"></i>
                            Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Profile completion tracking
        function updateCompletionStatus() {
            let completedItems = 0;
            let totalItems = 6;
            
            // Check personal info completion
            const personalFields = ['full_name', 'email', 'phone', 'id_number'];
            const personalComplete = personalFields.every(field => 
                document.getElementById(field).value.trim()
            );
            if (personalComplete) completedItems++;
            
            // Check address completion
            const addressFields = ['address', 'city', 'province', 'postal_code'];
            const addressComplete = addressFields.every(field => 
                document.getElementById(field).value.trim()
            );
            if (addressComplete) completedItems++;
            
            // Check employment completion
            const employmentFields = ['employment_status', 'monthly_income'];
            const employmentComplete = employmentFields.every(field => 
                document.getElementById(field).value.trim()
            );
            if (employmentComplete) completedItems++;
            
            // Check emergency contact completion
            const emergencyFields = ['emergency_contact_name', 'emergency_contact_phone'];
            const emergencyComplete = emergencyFields.every(field => 
                document.getElementById(field).value.trim()
            );
            if (emergencyComplete) completedItems++;
            
            // Check documents (count existing + new uploads)
            const existingDocs = <?php echo count($existing_documents); ?>;
            const newUploads = Array.from(document.querySelectorAll('input[type="file"]'))
                .filter(input => input.files.length > 0).length;
            const totalDocs = existingDocs + newUploads;
            
            if (totalDocs >= 3) completedItems++;
            if (totalDocs >= 5) completedItems++;
            
            // Update progress bar and percentage
            const percentage = Math.round((completedItems / totalItems) * 100);
            document.getElementById('completionPercent').textContent = `${percentage}%`;
            document.getElementById('progressFill').style.width = `${percentage}%`;
            
            // Make progress bar green when 100%
            const progressFill = document.getElementById('progressFill');
            if (percentage === 100) {
                progressFill.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            }
        }

        // Enhanced form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const requiredFields = [
                { id: 'full_name', label: 'Full Name' },
                { id: 'email', label: 'Email Address' },
                { id: 'phone', label: 'Phone Number' },
                { id: 'address', label: 'Current Address' },
                { id: 'city', label: 'City' },
                { id: 'province', label: 'Province' },
                { id: 'postal_code', label: 'Postal Code' },
                { id: 'id_number', label: 'ID Number' },
                { id: 'employment_status', label: 'Employment Status' },
                { id: 'monthly_income', label: 'Monthly Income' },
                { id: 'emergency_contact_name', label: 'Emergency Contact Name' },
                { id: 'emergency_contact_phone', label: 'Emergency Contact Phone' }
            ];
            
            let missingFields = [];
            let isValid = true;
            
            // Reset all field styles
            requiredFields.forEach(field => {
                const element = document.getElementById(field.id);
                element.style.borderColor = '#d1d5db';
                document.getElementById(`${field.id}_validation`).textContent = '';
            });
            
            // Check each required field
            requiredFields.forEach(field => {
                const element = document.getElementById(field.id);
                if (!element.value.trim()) {
                    element.style.borderColor = '#ef4444';
                    document.getElementById(`${field.id}_validation`).textContent = `${field.label} is required`;
                    missingFields.push(field.label);
                    isValid = false;
                }
            });
            
            // Validate ID number length
            const idNumber = document.getElementById('id_number');
            if (idNumber.value && idNumber.value.length !== 13) {
                idNumber.style.borderColor = '#ef4444';
                document.getElementById('id_number_validation').textContent = 'ID number must be exactly 13 digits';
                isValid = false;
                
                Swal.fire({
                    title: 'Validation Error',
                    text: 'ID number must be exactly 13 digits',
                    icon: 'error',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            if (!isValid) {
                Swal.fire({
                    title: 'Missing Required Fields',
                    html: `<p>Please fill in the following required fields:</p>
                           <ul style="text-align: left; margin: 10px 0;">
                               ${missingFields.map(field => `<li>${field}</li>`).join('')}
                           </ul>`,
                    icon: 'warning',
                    confirmButtonColor: '#667eea',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Show saving alert
            Swal.fire({
                title: 'Saving Profile',
                text: 'Please wait while we save your profile...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            this.submit();
        });

        // File upload validation
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    if (file.size > 10 * 1024 * 1024) {
                        Swal.fire({
                            title: 'File Too Large',
                            text: 'Please select a file smaller than 10MB',
                            icon: 'error',
                            confirmButtonColor: '#667eea'
                        });
                        this.value = '';
                        return;
                    }
                    
                    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire({
                            title: 'Invalid File Type',
                            text: 'Please select a PDF, JPG, or PNG file',
                            icon: 'error',
                            confirmButtonColor: '#667eea'
                        });
                        this.value = '';
                        return;
                    }
                    
                    updateCompletionStatus();
                }
            });
        });

        // Field validations
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#ef4444';
                document.getElementById('email_validation').textContent = 'Please enter a valid email address';
            } else if (email) {
                this.style.borderColor = '#10b981';
                document.getElementById('email_validation').textContent = '';
            }
        });

        document.getElementById('phone').addEventListener('blur', function() {
            const phone = this.value.replace(/[\s\-\(\)]/g, '');
            const phoneRegex = /^(\+27|0)[1-9]\d{8}$/;
            
            if (phone && !phoneRegex.test(phone)) {
                this.style.borderColor = '#ef4444';
                document.getElementById('phone_validation').textContent = 'Please enter a valid South African phone number';
            } else if (phone) {
                this.style.borderColor = '#10b981';
                document.getElementById('phone_validation').textContent = '';
            }
        });

        document.getElementById('id_number').addEventListener('blur', function() {
            const idNumber = this.value.replace(/\s+/g, '');
            
            if (idNumber) {
                if (idNumber.length !== 13) {
                    this.style.borderColor = '#ef4444';
                    document.getElementById('id_number_validation').textContent = 'ID number must be exactly 13 digits';
                } else if (!/^\d{13}$/.test(idNumber)) {
                    this.style.borderColor = '#ef4444';
                    document.getElementById('id_number_validation').textContent = 'ID number must contain only numbers';
                } else {
                    this.style.borderColor = '#10b981';
                    document.getElementById('id_number_validation').textContent = '';
                }
            }
        });

        document.getElementById('postal_code').addEventListener('blur', function() {
            const postalCode = this.value.trim();
            
            if (postalCode && (postalCode.length !== 4 || !/^\d{4}$/.test(postalCode))) {
                this.style.borderColor = '#ef4444';
                document.getElementById('postal_code_validation').textContent = 'Postal code must be exactly 4 digits';
            } else if (postalCode) {
                this.style.borderColor = '#10b981';
                document.getElementById('postal_code_validation').textContent = '';
            }
        });

        // Emergency contact phone validation
        document.getElementById('emergency_contact_phone').addEventListener('blur', function() {
            const phone = this.value.replace(/[\s\-\(\)]/g, '');
            const phoneRegex = /^(\+27|0)[1-9]\d{8}$/;
            
            if (phone && !phoneRegex.test(phone)) {
                this.style.borderColor = '#ef4444';
                document.getElementById('emergency_contact_phone_validation').textContent = 'Please enter a valid South African phone number';
            } else if (phone) {
                this.style.borderColor = '#10b981';
                document.getElementById('emergency_contact_phone_validation').textContent = '';
            }
        });

        // Monthly income validation
        document.getElementById('monthly_income').addEventListener('blur', function() {
            const income = parseFloat(this.value);
            
            if (this.value && (isNaN(income) || income < 0)) {
                this.style.borderColor = '#ef4444';
                document.getElementById('monthly_income_validation').textContent = 'Please enter a valid income amount';
            } else if (this.value && income < 3000) {
                this.style.borderColor = '#f59e0b';
                document.getElementById('monthly_income_validation').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Low income may affect rental applications';
                document.getElementById('monthly_income_validation').style.color = '#f59e0b';
            } else if (this.value) {
                this.style.borderColor = '#10b981';
                document.getElementById('monthly_income_validation').textContent = '';
                document.getElementById('monthly_income_validation').style.color = '#ef4444';
            }
        });

        // Logout confirmation
        function confirmLogout(event) {
            event.preventDefault();
            
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
        }

        // Success/Error messages from PHP
        <?php if ($success_message): ?>
            Swal.fire({
                title: 'Success!',
                html: '<?php echo str_replace("'", "\\'", $success_message); ?>',
                icon: 'success',
                confirmButtonColor: '#10b981'
            });
        <?php endif; ?>

        <?php if ($error_message): ?>
            Swal.fire({
                title: 'Error!',
                html: '<?php echo str_replace("'", "\\'", $error_message); ?>',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        <?php endif; ?>

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth < 768 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !toggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Enhanced file drag and drop
        document.querySelectorAll('.document-card').forEach(card => {
            const fileInput = card.querySelector('input[type="file"]');
            
            if (fileInput) {
                card.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    card.style.borderColor = '#667eea';
                    card.style.background = '#f3f4f6';
                });
                
                card.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    card.style.borderColor = '#e5e7eb';
                    card.style.background = '#fafafa';
                });
                
                card.addEventListener('drop', (e) => {
                    e.preventDefault();
                    card.style.borderColor = '#e5e7eb';
                    card.style.background = '#fafafa';
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                
                card.addEventListener('click', (e) => {
                    if (e.target === card || e.target.closest('.document-title') || e.target.closest('.document-description')) {
                        fileInput.click();
                    }
                });
            }
        });

        // Document viewing functionality
        function viewDocument(fileName) {
            const documentPath = `../uploads/tenant_documents/${fileName}`;
            
            if (fileName.toLowerCase().endsWith('.pdf')) {
                window.open(documentPath, '_blank');
            } else {
                Swal.fire({
                    title: 'Document Preview',
                    html: `<img src="${documentPath}" style="max-width: 100%; max-height: 400px; object-fit: contain;" alt="Document Preview">`,
                    width: 'auto',
                    showCloseButton: true,
                    showConfirmButton: false
                });
            }
        }

        // Add click handlers to existing files
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.existing-file').forEach(fileElement => {
                fileElement.style.cursor = 'pointer';
                fileElement.title = 'Click to view document';
                
                fileElement.addEventListener('click', function() {
                    const fileName = this.querySelector('.file-name').textContent;
                    const documentType = this.closest('.document-card').querySelector('input[type="file"]').name;
                    
                    // You might want to get the actual filename from PHP data
                    // For now, we'll construct it based on the document type
                    const actualFileName = `${documentType}_<?php echo $tenant_id; ?>_*.*`;
                    
                    if (fileName) {
                        // This would need to be enhanced to get the actual file path
                        console.log('View document:', fileName);
                    }
                });
            });
            
            // Initialize completion status
            updateCompletionStatus();
        });

        // Real-time validation feedback
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('input', function() {
                updateCompletionStatus();
                
                // Real-time validation for specific fields
                if (this.hasAttribute('required') && this.value.trim()) {
                    this.style.borderColor = '#10b981';
                    const validationElement = document.getElementById(`${this.id}_validation`);
                    if (validationElement) {
                        validationElement.textContent = '';
                    }
                }
            });
            
            element.addEventListener('change', updateCompletionStatus);
        });

        // Employment status change handler
        document.getElementById('employment_status').addEventListener('change', function() {
            const employerFields = ['employer_name', 'job_title'];
            const isEmployed = this.value === 'employed' || this.value === 'self_employed';
            
            employerFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const label = field.previousElementSibling;
                
                if (isEmployed) {
                    label.classList.add('required');
                    if (!label.textContent.includes('*')) {
                        label.innerHTML += ' <span style="color: #ef4444;">*</span>';
                    }
                } else {
                    label.classList.remove('required');
                    label.innerHTML = label.innerHTML.replace(/ <span[^>]*>\*<\/span>/, '');
                }
            });
        });

        // Auto-save functionality (optional)
        let autoSaveTimer;
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    // You could implement auto-save here
                    console.log('Auto-save triggered');
                }, 5000); // Save after 5 seconds of inactivity
            });
        });

        // Form submission loading state
        document.getElementById('profileForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('saveProfileBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            // Re-enable if form submission fails (fallback)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 10000);
        });

        // Keyboard navigation improvements
        document.addEventListener('keydown', function(e) {
            // ESC to close modals
            if (e.key === 'Escape') {
                const sidebar = document.querySelector('.sidebar');
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
            
            // Ctrl+S to save form
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('profileForm').dispatchEvent(new Event('submit'));
            }
        });

        // Accessibility improvements
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.setAttribute('aria-describedby', `${this.id}_validation`);
            });
            
            input.addEventListener('blur', function() {
                this.removeAttribute('aria-describedby');
            });
        });

        // Progress tracking with localStorage (if needed for persistence)
        function saveProgress() {
            const formData = new FormData(document.getElementById('profileForm'));
            const progressData = {};
            
            for (let [key, value] of formData.entries()) {
                if (value) {
                    progressData[key] = value;
                }
            }
            
            // Note: localStorage is not available in Claude artifacts
            // This would work in a real environment
            // localStorage.setItem('tenant_profile_progress', JSON.stringify(progressData));
        }

        // Call save progress periodically
        setInterval(saveProgress, 30000); // Save every 30 seconds
        // Profile image preview functionality
// Profile image preview functionality
document.getElementById('profile_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('profileImagePreview');
    const container = document.querySelector('.profile-image-container');
    
    if (file) {
        // Validate file
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                title: 'File Too Large',
                text: 'Profile image must be smaller than 5MB',
                icon: 'error',
                confirmButtonColor: '#667eea'
            });
            this.value = '';
            return;
        }
        
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                title: 'Invalid File Type',
                text: 'Please select a JPG or PNG image',
                icon: 'error',
                confirmButtonColor: '#667eea'
            });
            this.value = '';
            return;
        }
        
        // Show preview of selected image
        const reader = new FileReader();
        reader.onload = function(e) {
            // Remove any existing preview overlay
            const existingOverlay = container.querySelector('.image-preview-overlay');
            if (existingOverlay) {
                existingOverlay.remove();
            }
            
            // Update the image preview
            preview.innerHTML = `<img src="${e.target.result}" alt="Profile Preview" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">`;
            
            // Add pending indicator
            container.classList.add('image-pending');
            
            // Add overlay to indicate pending changes
            const overlay = document.createElement('div');
            overlay.className = 'image-preview-overlay';
            overlay.innerHTML = '<i class="fas fa-clock"></i>';
            container.appendChild(overlay);
            
            // Update the info text
            const infoText = document.querySelector('.profile-image-info p:last-child');
            if (infoText) {
                infoText.innerHTML = '<strong style="color: #f59e0b;">New image selected - Save profile to apply changes</strong>';
            }
        };
        reader.readAsDataURL(file);
        
        updateCompletionStatus();
    } else {
        // Reset if no file selected
        resetImagePreview();
    }
});

// Function to reset image preview to original state
function resetImagePreview() {
    const container = document.querySelector('.profile-image-container');
    const preview = document.getElementById('profileImagePreview');
    const infoText = document.querySelector('.profile-image-info p:last-child');
    
    container.classList.remove('image-pending');
    
    const existingOverlay = container.querySelector('.image-preview-overlay');
    if (existingOverlay) {
        existingOverlay.remove();
    }
    
    // Restore original image or avatar
    <?php if (!empty($profile['profile_image']) && file_exists($upload_dir . $profile['profile_image'])): ?>
        preview.innerHTML = `<img src="<?php echo $upload_dir . htmlspecialchars($profile['profile_image']); ?>" alt="Profile Image" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">`;
    <?php else: ?>
        preview.innerHTML = `<div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #8ca0af 0%, #6c7a89 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: bold; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"><?php echo strtoupper(substr($profile['full_name'] ?? $_SESSION['user_name'] ?? 'T', 0, 1)); ?></div>`;
    <?php endif; ?>
    
    if (infoText) {
        infoText.innerHTML = 'Click the camera icon to upload or change your photo';
    }
}

// Clear pending state after successful form submission
document.getElementById('profileForm').addEventListener('submit', function() {
    // This will be handled by the page reload after successful submission
});

// Add ability to cancel image selection
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const fileInput = document.getElementById('profile_image');
        if (fileInput.files.length > 0) {
            fileInput.value = '';
            resetImagePreview();
        }
    }
});
    </script>
</body>
</html>

<?php
mysqli_close($conn);
?>