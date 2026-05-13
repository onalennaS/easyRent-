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
$landlord_id = $_SESSION['user_id'] ?? 1;

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
            'address' => 'Street Address',
            'city' => 'City',
            'province' => 'Province',
            'postal_code' => 'Postal Code',
            'id_number' => 'ID Number',
            'bank_name' => 'Bank Name',
            'account_number' => 'Account Number',
            'branch_code' => 'Branch Code',
            'account_holder' => 'Account Holder Name'
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
            // Don't process the form, just show the error
        }
        // If there are missing required fields, return error
        elseif (!empty($missing_fields)) {
            $error_message = "Missing required fields: " . implode(", ", $missing_fields);
            // Don't process the form, just show the error
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/profiles/';
            $documents_dir = '../uploads/documents/';
            
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
            
            // Check if directories are writable
            if (!is_writable($upload_dir)) {
                throw new Exception("Profiles upload directory is not writable");
            }
            if (!is_writable($documents_dir)) {
                throw new Exception("Documents upload directory is not writable");
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
            $tax_number = mysqli_real_escape_string($conn, $_POST['tax_number']);
            $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
            $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
            $branch_code = mysqli_real_escape_string($conn, $_POST['branch_code']);
            $account_holder = mysqli_real_escape_string($conn, $_POST['account_holder']);
            $business_registration = mysqli_real_escape_string($conn, $_POST['business_registration']);
            $experience_years = (int)$_POST['experience_years'];
            $property_count = (int)$_POST['property_count'];
            $about = mysqli_real_escape_string($conn, $_POST['about']);

            // Handle profile image upload
            $profile_image = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                if (in_array($_FILES['profile_image']['type'], $allowed_types)) {
                    $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $profile_image = 'profile_' . $landlord_id . '_' . time() . '.' . $file_extension;
                    move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $profile_image);
                }
            }

            // Check if profile exists
            $check_query = "SELECT id FROM landlord_profiles WHERE landlord_id = $landlord_id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Update existing profile
                $query = "UPDATE landlord_profiles SET 
                            full_name = '$full_name',
                            email = '$email',
                            phone = '$phone',
                            address = '$address',
                            city = '$city',
                            province = '$province',
                            postal_code = '$postal_code',
                            id_number = '$id_number',
                            tax_number = '$tax_number',
                            bank_name = '$bank_name',
                            account_number = '$account_number',
                            branch_code = '$branch_code',
                            account_holder = '$account_holder',
                            business_registration = '$business_registration',
                            experience_years = $experience_years,
                            property_count = $property_count,
                            about = '$about'";
                
                if ($profile_image) {
                    $query .= ", profile_image = '$profile_image'";
                }
                
                $query .= ", updated_at = NOW() WHERE landlord_id = $landlord_id";
            } else {
                // Insert new profile
                $query = "INSERT INTO landlord_profiles (
                            landlord_id, full_name, email, phone, address, city, province, postal_code,
                            id_number, tax_number, bank_name, account_number, branch_code, account_holder,
                            business_registration, experience_years, property_count, about";
                
                if ($profile_image) {
                    $query .= ", profile_image";
                }
                
                $query .= ", created_at, updated_at) VALUES (
                            $landlord_id, '$full_name', '$email', '$phone', '$address', '$city', '$province', '$postal_code',
                            '$id_number', '$tax_number', '$bank_name', '$account_number', '$branch_code', '$account_holder',
                            '$business_registration', $experience_years, $property_count, '$about'";
                
                if ($profile_image) {
                    $query .= ", '$profile_image'";
                }
                
                $query .= ", NOW(), NOW())";
            }

            if (mysqli_query($conn, $query)) {
                // Handle document uploads with detailed error checking
                $document_types = [
                    'id_document' => 'Identity Document',
                    'proof_of_address' => 'Proof of Address',
                    'bank_statement' => 'Bank Statement',
                    'tax_clearance' => 'Tax Clearance Certificate',
                    'business_license' => 'Business License',
                    'property_deed' => 'Property Deed/Title',
                    'insurance_certificate' => 'Insurance Certificate',
                    'credit_report' => 'Credit Report'
                ];

                $upload_errors = [];
                $upload_success = [];

                foreach ($document_types as $field => $doc_name) {
                    if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
                        $file = $_FILES[$field];
                        
                        // Check for upload errors
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
                        
                        // Check file size (max 10MB)
                        if ($file['size'] > 10 * 1024 * 1024) {
                            $upload_errors[] = "{$doc_name}: File too large. Maximum size is 10MB.";
                            continue;
                        }
                        
                        // Generate unique filename
                        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = $field . '_' . $landlord_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $file_path = $documents_dir . $filename;
                        
                        // Move uploaded file
                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            // Delete old file if exists
                            $old_file_query = "SELECT file_path FROM landlord_documents WHERE landlord_id = $landlord_id AND document_type = '$field'";
                            $old_file_result = mysqli_query($conn, $old_file_query);
                            if ($old_file_result && mysqli_num_rows($old_file_result) > 0) {
                                $old_file = mysqli_fetch_assoc($old_file_result);
                                $old_file_path = $documents_dir . $old_file['file_path'];
                                if (file_exists($old_file_path)) {
                                    unlink($old_file_path);
                                }
                            }
                            
                            // Insert or update document record
                            $doc_query = "INSERT INTO landlord_documents (landlord_id, document_type, document_name, file_path, status, uploaded_at)
                                         VALUES ($landlord_id, '$field', '" . mysqli_real_escape_string($conn, $doc_name) . "', '" . mysqli_real_escape_string($conn, $filename) . "', 'pending', NOW())
                                         ON DUPLICATE KEY UPDATE
                                         document_name = '" . mysqli_real_escape_string($conn, $doc_name) . "',
                                         file_path = '" . mysqli_real_escape_string($conn, $filename) . "',
                                         status = 'pending',
                                         uploaded_at = NOW()";
                            
                            if (mysqli_query($conn, $doc_query)) {
                                $upload_success[] = "{$doc_name} uploaded successfully";
                            } else {
                                $upload_errors[] = "{$doc_name}: Database error - " . mysqli_error($conn);
                                // Delete the uploaded file if database insert failed
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                            }
                        } else {
                            $upload_errors[] = "{$doc_name}: Failed to move uploaded file";
                        }
                    }
                }
                
                // Check if profile is complete for property listing eligibility
                $profile_complete = checkProfileCompleteness($conn, $landlord_id);
                
                // Prepare success/error messages
                $message_parts = [];
                if (!empty($upload_success)) {
                    $message_parts[] = "Profile completed, you may now proceed with adding your property listing.";
                    $message_parts[] = "Documents uploaded: " . implode(", ", $upload_success);
                }
                if (!empty($upload_errors)) {
                    $message_parts[] = "Upload errors: " . implode("; ", $upload_errors);
                }

                if (!$profile_complete) {
                    $message_parts[] = "Note: Complete your profile to start listing properties.";
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

// Function to check profile completeness
function checkProfileCompleteness($conn, $landlord_id) {
    $query = "SELECT * FROM landlord_profiles WHERE landlord_id = $landlord_id";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        error_log("Database error in checkProfileCompleteness: " . mysqli_error($conn));
        return false;
    }
    
    if (mysqli_num_rows($result) == 0) {
        return false;
    }
    
    $profile = mysqli_fetch_assoc($result);
    
    $required_fields = ['full_name', 'email', 'phone', 'address', 'city', 'province', 'postal_code', 'id_number', 'bank_name', 'account_number', 'branch_code', 'account_holder'];
    
    foreach ($required_fields as $field) {
        if (empty($profile[$field])) {
            return false;
        }
    }
    
    // Check for at least one required document
    $doc_query = "SELECT COUNT(*) as doc_count FROM landlord_documents WHERE landlord_id = $landlord_id";
    $doc_result = mysqli_query($conn, $doc_query);
    
    if (!$doc_result) {
        error_log("Database error in document count query: " . mysqli_error($conn));
        return false;
    }
    
    $doc_row = mysqli_fetch_assoc($doc_result);
    $doc_count = $doc_row ? $doc_row['doc_count'] : 0;
    
    return $doc_count > 0;
}

// Create tables if they don't exist
$create_profile_table = "
    CREATE TABLE IF NOT EXISTS landlord_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landlord_id INT NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        province VARCHAR(100) NOT NULL,
        postal_code VARCHAR(10) NOT NULL,
        id_number VARCHAR(20) NOT NULL,
        tax_number VARCHAR(50),
        bank_name VARCHAR(100) NOT NULL,
        account_number VARCHAR(50) NOT NULL,
        branch_code VARCHAR(10) NOT NULL,
        account_holder VARCHAR(255) NOT NULL,
        business_registration VARCHAR(100),
        experience_years INT DEFAULT 0,
        property_count INT DEFAULT 0,
        about TEXT,
        profile_image VARCHAR(255),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_landlord (landlord_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$create_documents_table = "
    CREATE TABLE IF NOT EXISTS landlord_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landlord_id INT NOT NULL,
        document_type VARCHAR(100) NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_doc (landlord_id, document_type),
        INDEX idx_landlord_id (landlord_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

mysqli_query($conn, $create_profile_table);
mysqli_query($conn, $create_documents_table);

// Get existing profile data with error handling
$profile = [];
$profile_query = "SELECT * FROM landlord_profiles WHERE landlord_id = $landlord_id";
$profile_result = mysqli_query($conn, $profile_query);
if ($profile_result && mysqli_num_rows($profile_result) > 0) {
    $profile = mysqli_fetch_assoc($profile_result);
}

// Fetch email from users table if not set in profile
if (empty($profile['email'])) {
    $user_email_query = "SELECT email FROM users WHERE id = $landlord_id";
    $user_email_result = mysqli_query($conn, $user_email_query);
    if ($user_email_result && mysqli_num_rows($user_email_result) > 0) {
        $user_email = mysqli_fetch_assoc($user_email_result)['email'];
        $profile['email'] = $user_email;
    }
}

// Get existing documents with error handling
$existing_documents = [];
$documents_query = "SELECT id, landlord_id, document_type, document_name, file_path, status, rejection_reason, uploaded_at FROM landlord_documents WHERE landlord_id = $landlord_id";

$documents_result = mysqli_query($conn, $documents_query);
if ($documents_result) {
    while ($doc = mysqli_fetch_assoc($documents_result)) {
        $existing_documents[$doc['document_type']] = $doc;
    }
}

// Bank branch codes mapping
$bank_branches = [
    'ABSA' => '632005',
    'Standard Bank' => '051001',
    'FNB' => '250655',
    'Nedbank' => '198765',
    'Capitec' => '470010',
    'Discovery Bank' => '679000',
    'African Bank' => '430000',
    'Investec' => '580105'
];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Profile - L&T Connect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .form-input.valid {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-input.invalid {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
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
        
        /* Rest of your CSS remains the same */
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

        /* Sidebar */
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

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
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

        /* Form Styles */
        .profile-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
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

        .file-upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #3b82f6;
            background: #f8fafc;
        }

        .file-upload-area.dragover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .file-input {
            display: none;
        }

        .upload-icon {
            font-size: 2rem;
            color: #9ca3af;
            margin-bottom: 0.5rem;
        }

        .upload-text {
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            font-size: 0.875rem;
            color: #9ca3af;
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
            justify-content: between;
            gap: 0.5rem;
        }
        .profile-image-container {
    display: flex;
    justify-content: center;
    align-items: center;
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

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #dc2626;
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
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .compliance-notice {
            background: #fffbeb;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .compliance-notice h4 {
            color: #d97706;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .compliance-notice p {
            color: #92400e;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
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
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .document-grid {
                grid-template-columns: 1fr;
            }

            .submit-section {
                flex-direction: column;
                gap: 1rem;
            }
        }
        /* Profile Completion Status Styles */
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

.completion-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.completion-section {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.completion-section h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.completion-items {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.completion-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    color: #6b7280;
}

.completion-item.completed {
    color: #10b981;
}

.completion-item.completed i {
    color: #10b981;
}

.completion-item.incomplete {
    color: #ef4444;
}

.completion-item.incomplete i {
    color: #ef4444;
}

.completion-item.pending {
    color: #f59e0b;
}

.completion-item.pending i {
    color: #f59e0b;
}

.completion-item i {
    font-size: 1rem;
    color: #d1d5db;
}

@media (max-width: 768px) {
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
    </style>
    <style>
.rejection-reason {
    font-size: 0.75rem;
    color: #dc2626;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    background: #fef2f2;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    border-left: 3px solid #dc2626;
}

/* Update existing status badge styles */
.status-badge.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.status-rejected {
    background: #fee2e2;
    color: #dc2626;
}

.status-badge.status-pending {
    background: #fef3c7;
    color: #d97706;
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
            <li><a href="profile_landlord.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="landlord_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="my_properties.php"><i class="fas fa-building"></i> My Properties</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="add_property.php"><i class="fas fa-plus-circle"></i> Add Property</a></li>
            <li><a href="maintenance.php"><i class="fas fa-tools"></i> Maintenance</a></li>
            <li><a href="tenants.php"><i class="fas fa-users"></i> Tenants</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="#" onclick="confirmLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

   <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <i class="fas fa-user"></i>
                Landlord Profile
            </h1>
            <div class="landlord-info">
                <span>Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Landlord'); ?></span>
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'L', 0, 1)); ?></div>
            </div>
        </div>

        <!-- Profile Container -->
        <div class="profile-container">
<div class="profile-header">
    <?php if (!empty($profile['profile_image'])): ?>
        <div class="profile-image-container" style="margin-bottom: 1rem;">
            <img src="../uploads/profiles/<?php echo htmlspecialchars($profile['profile_image']); ?>" 
                 alt="Profile Image" 
                 style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid white; object-fit: cover;">
        </div>
    <?php endif; ?>
    <h2><i class="fas fa-user-circle"></i> Complete Your Landlord Profile</h2>
    <p>Provide accurate information and required documents to increase your approval chances</p>
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
    
    <div class="completion-grid">
        <div class="completion-section">
            <h4><i class="fas fa-user"></i> Personal Info</h4>
            <div class="completion-items">
                <div class="completion-item" id="personal-info">
                    <i class="fas fa-circle-check"></i>
                    <span>Basic Details</span>
                </div>
                <div class="completion-item" id="address-info">
                    <i class="fas fa-circle-check"></i>
                    <span>Address Information</span>
                </div>
                <div class="completion-item" id="banking-info">
                    <i class="fas fa-circle-check"></i>
                    <span>Banking Details</span>
                </div>
            </div>
        </div>
        
        <div class="completion-section">
            <h4><i class="fas fa-file-upload"></i> Documents</h4>
            <div class="completion-items">
                <div class="completion-item" id="required-docs">
                    <i class="fas fa-circle-check"></i>
                    <span>Required Documents (3/8)</span>
                </div>
                <div class="completion-item" id="optional-docs">
                    <i class="fas fa-circle-check"></i>
                    <span>Optional Documents</span>
                </div>
            </div>
        </div>
        
        <div class="completion-section">
            <h4><i class="fas fa-shield-check"></i> Verification</h4>
            <div class="completion-items">
                <div class="completion-item" id="profile-review">
                    <i class="fas fa-circle-check"></i>
                    <span>Profile Under Review</span>
                </div>
                <div class="completion-item" id="listing-ready">
                    <i class="fas fa-circle-check"></i>
                    <span>Ready to List Properties</span>
                </div>
            </div>
        </div>
    </div>
</div>

            <!-- Compliance Notice -->
            <div class="form-sections">
                <div class="compliance-notice">
                    <h4><i class="fas fa-info-circle"></i> Legal Compliance Notice</h4>
                    <p>As per South African Rental Housing Act and POPIA regulations, all information provided will be used solely for property rental purposes. Your personal data is protected and will not be shared with third parties without consent.</p>
                </div>

                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <!-- Personal Information Section -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                        
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
                                <label for="tax_number" class="form-label">Tax Number</label>
                                <input type="text" id="tax_number" name="tax_number" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['tax_number'] ?? ''); ?>">
                                <div id="tax_number_validation" class="validation-message"></div>
                            </div>

                           <div class="form-group">
    <label for="profile_image" class="form-label">Profile Image</label>
    <div style="display: flex; align-items: center; gap: 1.5rem;">
        <div class="image-preview-container">
            <?php if (!empty($profile['profile_image'])): ?>
                <img id="currentImage" src="../uploads/profiles/<?php echo htmlspecialchars($profile['profile_image']); ?>" 
                     alt="Current Profile Image" 
                     style="width: 80px; height: 80px; border-radius: 8px; border: 1px solid #e5e7eb; object-fit: cover;">
            <?php else: ?>
                <div id="noImagePlaceholder" style="width: 80px; height: 80px; border: 2px dashed #d1d5db; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #9ca3af;">
                    <i class="fas fa-user" style="font-size: 1.5rem;"></i>
                </div>
            <?php endif; ?>
            
            <!-- Hidden preview image that shows when file is selected -->
            <img id="imagePreview" 
                 style="width: 80px; height: 80px; border-radius: 8px; border: 1px solid #e5e7eb; object-fit: cover; display: none;" 
                 alt="Preview">
        </div>
        
        <div class="upload-section" style="flex: 1;">
            <input type="file" id="profile_image" name="profile_image" class="form-input" accept="image/*">
            <p id="imageStatus" style="font-size: 0.875rem; color: #9ca3af; margin-top: 0.5rem; margin-bottom: 0;">
                <?php if (!empty($profile['profile_image'])): ?>
                    Upload new image to replace current
                <?php else: ?>
                    Upload your profile image
                <?php endif; ?>
            </p>
            <button type="button" id="cancelImageChange" style="display: none; margin-top: 0.5rem; padding: 0.25rem 0.75rem; background: #6b7280; color: white; border: none; border-radius: 4px; font-size: 0.875rem; cursor: pointer;">
                Cancel Change
            </button>
        </div>
    </div>
    <div id="profile_image_validation" class="validation-message"></div>
</div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Address Information
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

                    <!-- Banking Information -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-university"></i>
                            Banking Information
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="bank_name" class="form-label required">Bank Name</label>
                                <select id="bank_name" name="bank_name" class="form-input" required>
                                    <option value="">Select Bank</option>
                                    <option value="ABSA" <?php echo ($profile['bank_name'] ?? '') == 'ABSA' ? 'selected' : ''; ?>>ABSA</option>
                                    <option value="Standard Bank" <?php echo ($profile['bank_name'] ?? '') == 'Standard Bank' ? 'selected' : ''; ?>>Standard Bank</option>
                                    <option value="FNB" <?php echo ($profile['bank_name'] ?? '') == 'FNB' ? 'selected' : ''; ?>>FNB</option>
                                    <option value="Nedbank" <?php echo ($profile['bank_name'] ?? '') == 'Nedbank' ? 'selected' : ''; ?>>Nedbank</option>
                                    <option value="Capitec" <?php echo ($profile['bank_name'] ?? '') == 'Capitec' ? 'selected' : ''; ?>>Capitec</option>
                                    <option value="Discovery Bank" <?php echo ($profile['bank_name'] ?? '') == 'Discovery Bank' ? 'selected' : ''; ?>>Discovery Bank</option>
                                    <option value="African Bank" <?php echo ($profile['bank_name'] ?? '') == 'African Bank' ? 'selected' : ''; ?>>African Bank</option>
                                    <option value="Investec" <?php echo ($profile['bank_name'] ?? '') == 'Investec' ? 'selected' : ''; ?>>Investec</option>
                                </select>
                                <div id="bank_name_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="account_number" class="form-label required">Account Number</label>
                                <input type="text" id="account_number" name="account_number" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['account_number'] ?? ''); ?>" required>
                                <div id="account_number_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="branch_code" class="form-label required">Branch Code</label>
                                <input type="text" id="branch_code" name="branch_code" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['branch_code'] ?? ''); ?>" required readonly>
                                <div id="branch_code_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="account_holder" class="form-label required">Account Holder Name</label>
                                <input type="text" id="account_holder" name="account_holder" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['account_holder'] ?? ''); ?>" required>
                                <div id="account_holder_validation" class="validation-message"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Information -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-briefcase"></i>
                            Business Information
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="business_registration" class="form-label">Business Registration Number</label>
                                <input type="text" id="business_registration" name="business_registration" class="form-input" 
                                       value="<?php echo htmlspecialchars($profile['business_registration'] ?? ''); ?>">
                                <div id="business_registration_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="experience_years" class="form-label">Years of Experience</label>
                                <input type="number" id="experience_years" name="experience_years" class="form-input" min="0" 
                                       value="<?php echo htmlspecialchars($profile['experience_years'] ?? '0'); ?>">
                                <div id="experience_years_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group">
                                <label for="property_count" class="form-label">Number of Properties Owned</label>
                                <input type="number" id="property_count" name="property_count" class="form-input" min="0" 
                                       value="<?php echo htmlspecialchars($profile['property_count'] ?? '0'); ?>">
                                <div id="property_count_validation" class="validation-message"></div>
                            </div>

                            <div class="form-group full-width">
                                <label for="about" class="form-label">About You</label>
                                <textarea id="about" name="about" class="form-input form-textarea" 
                                          placeholder="Tell us about your experience as a landlord and your commitment to providing quality rental properties..."><?php echo htmlspecialchars($profile['about'] ?? ''); ?></textarea>
                                <div id="about_validation" class="validation-message"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Required Documents Section -->
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-file-upload"></i>
                            Required Documents
                        </h3>
                        
                        <p style="margin-bottom: 2rem; color: #6b7280;">Upload the following documents to increase your approval chances. All documents must be clear and legible.</p>
                        
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
    <div class="existing-file" data-filename="<?php echo htmlspecialchars($existing_documents['id_document']['file_path']); ?>">
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($existing_documents['id_document']['document_name']); ?></div>
            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['id_document']['uploaded_at'])); ?></div>
            <?php if ($existing_documents['id_document']['status'] == 'rejected' && !empty($existing_documents['id_document']['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-triangle"></i>
                    Reason: <?php echo htmlspecialchars($existing_documents['id_document']['rejection_reason']); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $existing_documents['id_document']['status']; ?>">
            <?php echo ucfirst($existing_documents['id_document']['status']); ?>
        </span>
    </div>
<?php endif; ?>
                                
                                <input type="file" id="id_document" name="id_document" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="id_document_validation" class="validation-message"></div>
                            </div>

                            <!-- Proof of Address -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-home"></i>
                                    Proof of Address
                                </div>
                                <div class="document-description">
                                    Utility bill or municipal account (not older than 3 months)
                                </div>
                                
                                <?php if (isset($existing_documents['proof_of_address'])): ?>
    <div class="existing-file" data-filename="<?php echo htmlspecialchars($existing_documents['proof_of_address']['file_path']); ?>">
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($existing_documents['proof_of_address']['document_name']); ?></div>
            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['proof_of_address']['uploaded_at'])); ?></div>
            <?php if ($existing_documents['proof_of_address']['status'] == 'rejected' && !empty($existing_documents['proof_of_address']['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-triangle"></i>
                    Reason: <?php echo htmlspecialchars($existing_documents['proof_of_address']['rejection_reason']); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $existing_documents['proof_of_address']['status']; ?>">
            <?php echo ucfirst($existing_documents['proof_of_address']['status']); ?>
        </span>
    </div>
<?php endif; ?>
                                
                                <input type="file" id="proof_of_address" name="proof_of_address" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="proof_of_address_validation" class="validation-message"></div>
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
    <div class="existing-file" data-filename="<?php echo htmlspecialchars($existing_documents['bank_statement']['file_path']); ?>">
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($existing_documents['bank_statement']['document_name']); ?></div>
            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['bank_statement']['uploaded_at'])); ?></div>
            <?php if ($existing_documents['bank_statement']['status'] == 'rejected' && !empty($existing_documents['bank_statement']['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-triangle"></i>
                    Reason: <?php echo htmlspecialchars($existing_documents['bank_statement']['rejection_reason']); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $existing_documents['bank_statement']['status']; ?>">
            <?php echo ucfirst($existing_documents['bank_statement']['status']); ?>
        </span>
    </div>
<?php endif; ?>
                                
                                <input type="file" id="bank_statement" name="bank_statement" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="bank_statement_validation" class="validation-message"></div>
                            </div>

                            <!-- Tax Clearance -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-file-invoice"></i>
                                    Tax Clearance Certificate
                                </div>
                                <div class="document-description">
                                    Valid tax clearance certificate from SARS
                                </div>
                                
<?php if (isset($existing_documents['tax_clearance'])): ?>
    <div class="existing-file" data-filename="<?php echo htmlspecialchars($existing_documents['tax_clearance']['file_path']); ?>">
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($existing_documents['tax_clearance']['document_name']); ?></div>
            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['tax_clearance']['uploaded_at'])); ?></div>
            <?php if ($existing_documents['tax_clearance']['status'] == 'rejected' && !empty($existing_documents['tax_clearance']['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-triangle"></i>
                    Reason: <?php echo htmlspecialchars($existing_documents['tax_clearance']['rejection_reason']); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $existing_documents['tax_clearance']['status']; ?>">
            <?php echo ucfirst($existing_documents['tax_clearance']['status']); ?>
        </span>
    </div>
<?php endif; ?>

                                
                                <input type="file" id="tax_clearance" name="tax_clearance" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="tax_clearance_validation" class="validation-message"></div>
                            </div>

                            <!-- Business License -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-certificate"></i>
                                    Business License
                                </div>
                                <div class="document-description">
                                    Business registration or trading license (if applicable)
                                </div>
                                
                               <?php if (isset($existing_documents['business_license'])): ?>
    <div class="existing-file" data-filename="<?php echo htmlspecialchars($existing_documents['business_license']['file_path']); ?>">
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($existing_documents['business_license']['document_name']); ?></div>
            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['business_license']['uploaded_at'])); ?></div>
            <?php if ($existing_documents['business_license']['status'] == 'rejected' && !empty($existing_documents['business_license']['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-triangle"></i>
                    Reason: <?php echo htmlspecialchars($existing_documents['business_license']['rejection_reason']); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $existing_documents['business_license']['status']; ?>">
            <?php echo ucfirst($existing_documents['business_license']['status']); ?>
        </span>
    </div>
<?php endif; ?>
                                
                                <input type="file" id="business_license" name="business_license" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="business_license_validation" class="validation-message"></div>
                            </div>

                            <!-- Property Deed -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-scroll"></i>
                                    Property Deed/Title
                                </div>
                                <div class="document-description">
                                    Proof of property ownership or lease agreements
                                </div>
                                
                                <?php if (isset($existing_documents['property_deed'])): ?>
    <div class="existing-file" data-filename="<?php echo htmlspecialchars($existing_documents['property_deed']['file_path']); ?>">
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($existing_documents['property_deed']['document_name']); ?></div>
            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['property_deed']['uploaded_at'])); ?></div>
            <?php if ($existing_documents['property_deed']['status'] == 'rejected' && !empty($existing_documents['property_deed']['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-triangle"></i>
                    Reason: <?php echo htmlspecialchars($existing_documents['property_deed']['rejection_reason']); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $existing_documents['property_deed']['status']; ?>">
            <?php echo ucfirst($existing_documents['property_deed']['status']); ?>
        </span>
    </div>
<?php endif; ?>
                                
                                <input type="file" id="property_deed" name="property_deed" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="property_deed_validation" class="validation-message"></div>
                            </div>

                            <!-- Insurance Certificate -->
                            <div class="document-card">
                                <div class="document-title">
                                    <i class="fas fa-shield-alt"></i>
                                    Insurance Certificate
                                </div>
                                <div class="document-description">
                                    Property insurance or landlord liability insurance
                                </div>
                                
                                <?php if (isset($existing_documents['insurance_certificate'])): ?>
    <div class="existing-file" data-filename="<?php echo htmlspecialchars($existing_documents['insurance_certificate']['file_path']); ?>">
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($existing_documents['insurance_certificate']['document_name']); ?></div>
            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['insurance_certificate']['uploaded_at'])); ?></div>
            <?php if ($existing_documents['insurance_certificate']['status'] == 'rejected' && !empty($existing_documents['insurance_certificate']['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-triangle"></i>
                    Reason: <?php echo htmlspecialchars($existing_documents['insurance_certificate']['rejection_reason']); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $existing_documents['insurance_certificate']['status']; ?>">
            <?php echo ucfirst($existing_documents['insurance_certificate']['status']); ?>
        </span>
    </div>
<?php endif; ?>
                                
                                <input type="file" id="insurance_certificate" name="insurance_certificate" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="insurance_certificate_validation" class="validation-message"></div>
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
    <div class="existing-file" data-filename="<?php echo htmlspecialchars($existing_documents['credit_report']['file_path']); ?>">
        <div class="file-info">
            <div class="file-name"><?php echo htmlspecialchars($existing_documents['credit_report']['document_name']); ?></div>
            <div class="file-date">Uploaded: <?php echo date('M j, Y', strtotime($existing_documents['credit_report']['uploaded_at'])); ?></div>
            <?php if ($existing_documents['credit_report']['status'] == 'rejected' && !empty($existing_documents['credit_report']['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-triangle"></i>
                    Reason: <?php echo htmlspecialchars($existing_documents['credit_report']['rejection_reason']); ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $existing_documents['credit_report']['status']; ?>">
            <?php echo ucfirst($existing_documents['credit_report']['status']); ?>
        </span>
    </div>
<?php endif; ?>
                                
                                <input type="file" id="credit_report" name="credit_report" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="credit_report_validation" class="validation-message"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Section -->
                    <div class="submit-section">
                        <div>
                            <p style="color: #6b7280; margin-bottom: 0.5rem;">
                                <i class="fas fa-info-circle"></i>
                                Profile completion increases approval chances
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
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Bank branch codes mapping
        const bankBranches = <?php echo json_encode($bank_branches); ?>;
        
        // Auto-fill branch code when bank is selected
        document.getElementById('bank_name').addEventListener('change', function() {
            const selectedBank = this.value;
            const branchCodeField = document.getElementById('branch_code');
            
            if (selectedBank && bankBranches[selectedBank]) {
                branchCodeField.value = bankBranches[selectedBank];
            } else {
                branchCodeField.value = '';
            }
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const sidebar = document.querySelector('.sidebar');
        
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        // Enhanced form validation with SweetAlert
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default to check validation first
            
            const requiredFields = [
                { id: 'full_name', label: 'Full Name' },
                { id: 'email', label: 'Email Address' },
                { id: 'phone', label: 'Phone Number' },
                { id: 'address', label: 'Street Address' },
                { id: 'city', label: 'City' },
                { id: 'province', label: 'Province' },
                { id: 'postal_code', label: 'Postal Code' },
                { id: 'id_number', label: 'ID Number' },
                { id: 'bank_name', label: 'Bank Name' },
                { id: 'account_number', label: 'Account Number' },
                { id: 'branch_code', label: 'Branch Code' },
                { id: 'account_holder', label: 'Account Holder Name' }
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
                    confirmButtonColor: '#3b82f6'
                });
            }
            
            if (!isValid) {
                Swal.fire({
                    title: 'Missing Required Fields',
                    html: `<p>Please fill in the following required fields:</p>
                           <ul style="text-align: left; margin: 10px 0;">
                               ${missingFields.map(field => `<li>${field}</li>`).join('')}
                           </ul>`,
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Check if profile is incomplete and warn user
            const hasBasicInfo = requiredFields.every(field => 
                document.getElementById(field.id).value.trim()
            );
            
            const hasDocuments = Array.from(document.querySelectorAll('input[type="file"]'))
                .some(input => input.files.length > 0) || 
                <?php echo !empty($existing_documents) ? 'true' : 'false'; ?>;
            
            if (hasBasicInfo && !hasDocuments) {
                Swal.fire({
                    title: 'Profile Incomplete',
                    text: 'Your basic information will be saved, but you need to upload documents to complete your profile before you can list properties.',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Save Anyway',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
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
                    }
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
            
            // If all validations pass, submit the form
            this.submit();
        });

        // File upload validation with enhanced feedback
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Check file size (max 10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        Swal.fire({
                            title: 'File Too Large',
                            text: 'Please select a file smaller than 10MB',
                            icon: 'error',
                            confirmButtonColor: '#3b82f6'
                        });
                        this.value = '';
                        return;
                    }
                    
                    // Check file type
                    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                    const fileType = file.type;
                    
                    if (!allowedTypes.includes(fileType)) {
                        Swal.fire({
                            title: 'Invalid File Type',
                            text: 'Please select a PDF, JPG, or PNG file',
                            icon: 'error',
                            confirmButtonColor: '#3b82f6'
                        });
                        this.value = '';
                        return;
                    }
                    
                    // Update UI to show file selected
                    const card = this.closest('.document-card');
                    card.style.borderColor = '#10b981';
                    card.style.background = '#f0fdf4';
                    
                    // Show success feedback
                    const fileName = file.name;
                    const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                    
                    Swal.fire({
                        title: 'File Selected',
                        text: `${fileName} (${fileSize}) is ready to upload`,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
            });
        });

        // Email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#ef4444';
                document.getElementById('email_validation').textContent = 'Please enter a valid email address';
                Swal.fire({
                    title: 'Invalid Email Format',
                    text: 'Please enter a valid email address (e.g., user@example.com)',
                    icon: 'warning',
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                    background: '#fef2f2',
                    color: '#dc2626'
                });
            } else if (email) {
                this.style.borderColor = '#10b981';
                document.getElementById('email_validation').textContent = '';
            } else {
                this.style.borderColor = '#d1d5db';
                document.getElementById('email_validation').textContent = '';
            }
        });

        // Phone number validation
        document.getElementById('phone').addEventListener('blur', function() {
            const phone = this.value.replace(/[\s\-\(\)]/g, '');
            const phoneRegex = /^(\+27|0)[1-9]\d{8}$/;
            
            if (phone && !phoneRegex.test(phone)) {
                this.style.borderColor = '#ef4444';
                document.getElementById('phone_validation').textContent = 'Please enter a valid South African phone number';
                Swal.fire({
                    title: 'Invalid Phone Number',
                    html: `
                        <p>Please enter a valid South African phone number:</p>
                        <ul style="text-align: left; margin: 10px 0;">
                            <li>0123456789 (10 digits starting with 0)</li>
                            <li>+27123456789 (with country code)</li>
                        </ul>
                    `,
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6',
                    background: '#fef2f2'
                });
            } else if (phone) {
                this.style.borderColor = '#10b981';
                document.getElementById('phone_validation').textContent = '';
            } else {
                this.style.borderColor = '#d1d5db';
                document.getElementById('phone_validation').textContent = '';
            }
        });

        // ID Number validation
        document.getElementById('id_number').addEventListener('blur', function() {
            const idNumber = this.value.replace(/\s+/g, '');
            
            if (idNumber) {
                if (idNumber.length !== 13) {
                    this.style.borderColor = '#ef4444';
                    document.getElementById('id_number_validation').textContent = 'ID number must be exactly 13 digits';
                    Swal.fire({
                        title: 'Invalid ID Number Length',
                        text: 'South African ID number must be exactly 13 digits',
                        icon: 'error',
                        confirmButtonColor: '#3b82f6'
                    });
                } else if (!/^\d{13}$/.test(idNumber)) {
                    this.style.borderColor = '#ef4444';
                    document.getElementById('id_number_validation').textContent = 'ID number must contain only numbers';
                    Swal.fire({
                        title: 'Invalid ID Number Format',
                        text: 'ID number must contain only numbers (no spaces or special characters)',
                        icon: 'error',
                        confirmButtonColor: '#3b82f6'
                    });
                } else {
                    this.style.borderColor = '#10b981';
                    document.getElementById('id_number_validation').textContent = '';
                }
            } else {
                this.style.borderColor = '#d1d5db';
                document.getElementById('id_number_validation').textContent = '';
            }
        });

        // Account number validation
        document.getElementById('account_number').addEventListener('blur', function() {
            const accountNumber = this.value.replace(/[\s\-]/g, '');
            
            if (accountNumber) {
                if (!/^\d+$/.test(accountNumber)) {
                    this.style.borderColor = '#ef4444';
                    document.getElementById('account_number_validation').textContent = 'Account number must contain only numbers';
                    Swal.fire({
                        title: 'Invalid Account Number',
                        text: 'Account number must contain only numbers',
                        icon: 'warning',
                        confirmButtonColor: '#3b82f6'
                    });
                } else if (accountNumber.length < 8 || accountNumber.length > 11) {
                    this.style.borderColor = '#ef4444';
                    document.getElementById('account_number_validation').textContent = 'Account number should be between 8-11 digits';
                    Swal.fire({
                        title: 'Invalid Account Number Length',
                        text: 'Account number should be between 8-11 digits',
                        icon: 'warning',
                        confirmButtonColor: '#3b82f6'
                    });
                } else {
                    this.style.borderColor = '#10b981';
                    document.getElementById('account_number_validation').textContent = '';
                }
            } else {
                this.style.borderColor = '#d1d5db';
                document.getElementById('account_number_validation').textContent = '';
            }
        });

        // Postal code validation
        document.getElementById('postal_code').addEventListener('blur', function() {
            const postalCode = this.value.trim();
            
            if (postalCode && (postalCode.length !== 4 || !/^\d{4}$/.test(postalCode))) {
                this.style.borderColor = '#ef4444';
                document.getElementById('postal_code_validation').textContent = 'Postal code must be exactly 4 digits';
                Swal.fire({
                    title: 'Invalid Postal Code',
                    text: 'South African postal code must be exactly 4 digits',
                    icon: 'warning',
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else if (postalCode) {
                this.style.borderColor = '#10b981';
                document.getElementById('postal_code_validation').textContent = '';
            } else {
                this.style.borderColor = '#d1d5db';
                document.getElementById('postal_code_validation').textContent = '';
            }
        });

        // Logout confirmation
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

        // Success/Error messages from PHP
        <?php if ($success_message): ?>
            Swal.fire({
                title: 'Success!',
                html: '<?php echo str_replace("'", "\\'", $success_message); ?>',
                icon: 'success',
                confirmButtonColor: '#10b981',
                allowOutsideClick: false,
                allowEscapeKey: false
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
            if (window.innerWidth < 900 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !mobileMenuBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Enhanced file drag and drop
        document.querySelectorAll('.document-card').forEach(card => {
            const fileInput = card.querySelector('input[type="file"]');
            
            if (fileInput) {
                card.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    card.style.borderColor = '#3b82f6';
                    card.style.background = '#eff6ff';
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
        function viewDocument(documentType, fileName) {
            const documentPath = `../uploads/documents/${fileName}`;
            
            // Check if it's a PDF
            if (fileName.toLowerCase().endsWith('.pdf')) {
                // Open PDF in new tab
                window.open(documentPath, '_blank');
            } else {
                // Show image in modal
                Swal.fire({
                    title: 'Document Preview',
                    html: `<img src="${documentPath}" style="max-width: 100%; max-height: 400px; object-fit: contain;" alt="Document Preview">`,
                    width: 'auto',
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'document-preview-modal'
                    }
                });
            }
        }

        // Add click handlers to existing files
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.existing-file').forEach(fileElement => {
                fileElement.style.cursor = 'pointer';
                fileElement.title = 'Click to view document';
                
                fileElement.addEventListener('click', function() {
                    const actualFileName = this.getAttribute('data-filename');
                    const documentType = this.closest('.document-card').querySelector('input[type="file"]').name;
                    
                    if (actualFileName) {
                        viewDocument(documentType, actualFileName);
                    }
                });
            });
        });
        // Profile completion tracking
// Profile completion tracking - Updated to check document approval status
function updateCompletionStatus() {
    let completedItems = 0;
    let totalItems = 8;
    
    // Check personal info completion
    const personalFields = ['full_name', 'email', 'phone', 'id_number'];
    const personalComplete = personalFields.every(field => 
        document.getElementById(field).value.trim()
    );
    document.getElementById('personal-info').className = 
        `completion-item ${personalComplete ? 'completed' : 'incomplete'}`;
    if (personalComplete) completedItems++;
    
    // Check address completion
    const addressFields = ['address', 'city', 'province', 'postal_code'];
    const addressComplete = addressFields.every(field => 
        document.getElementById(field).value.trim()
    );
    document.getElementById('address-info').className = 
        `completion-item ${addressComplete ? 'completed' : 'incomplete'}`;
    if (addressComplete) completedItems++;
    
    // Check banking completion
    const bankingFields = ['bank_name', 'account_number', 'branch_code', 'account_holder'];
    const bankingComplete = bankingFields.every(field => 
        document.getElementById(field).value.trim()
    );
    document.getElementById('banking-info').className = 
        `completion-item ${bankingComplete ? 'completed' : 'incomplete'}`;
    if (bankingComplete) completedItems++;
    
    // Check documents (count existing + new uploads)
    const existingDocs = <?php echo count($existing_documents); ?>;
    const newUploads = Array.from(document.querySelectorAll('input[type="file"]'))
        .filter(input => input.files.length > 0).length;
    const totalDocs = existingDocs + newUploads;
    
    const requiredDocsComplete = totalDocs >= 3;
    document.getElementById('required-docs').className = 
        `completion-item ${requiredDocsComplete ? 'completed' : 'incomplete'}`;
    document.getElementById('required-docs').querySelector('span').textContent = 
        `Required Documents (${Math.min(totalDocs, 8)}/8)`;
    if (requiredDocsComplete) completedItems++;
    
    // Optional docs - mark as completed if 5+ docs uploaded
    const hasOptionalDocs = totalDocs >= 5;
    document.getElementById('optional-docs').className = 
        `completion-item ${hasOptionalDocs ? 'completed' : 'incomplete'}`;
    if (hasOptionalDocs) completedItems++;
    
    // Profile review - completed if basic info is filled and at least one document exists
    const profileReviewComplete = personalComplete && addressComplete && bankingComplete && totalDocs > 0;
    document.getElementById('profile-review').className = 
        `completion-item ${profileReviewComplete ? 'completed' : 'pending'}`;
    if (profileReviewComplete) completedItems++;
    
    // Ready to list properties - all requirements met
    const canListProperties = personalComplete && addressComplete && bankingComplete && requiredDocsComplete;
    document.getElementById('listing-ready').className = 
        `completion-item ${canListProperties ? 'completed' : 'incomplete'}`;
    if (canListProperties) completedItems++;
    
    // Profile image bonus - add extra completion if profile image exists
    const hasProfileImage = <?php echo !empty($profile['profile_image']) ? 'true' : 'false'; ?> || 
        document.getElementById('profile_image').files.length > 0;
    if (hasProfileImage) completedItems++;
    
    // Update progress bar and percentage
    const percentage = Math.round((completedItems / totalItems) * 100);
    document.getElementById('completionPercent').textContent = `${percentage}%`;
    document.getElementById('progressFill').style.width = `${percentage}%`;
    
    // Make progress bar green when 100%
    const progressFill = document.getElementById('progressFill');
    if (percentage === 100) {
        progressFill.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
    } else {
        progressFill.style.background = 'linear-gradient(135deg, #10b981 0%, #3b82f6 100%)';
    }
}

// Call on page load and form changes
document.addEventListener('DOMContentLoaded', updateCompletionStatus);
document.querySelectorAll('input, select, textarea').forEach(element => {
    element.addEventListener('change', updateCompletionStatus);
    element.addEventListener('input', updateCompletionStatus);
});
// Profile image preview functionality
document.getElementById('profile_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    const currentImage = document.getElementById('currentImage');
    const noImagePlaceholder = document.getElementById('noImagePlaceholder');
    const imageStatus = document.getElementById('imageStatus');
    const cancelBtn = document.getElementById('cancelImageChange');
    
    if (file) {
        // Validate file
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            Swal.fire({
                title: 'File Too Large',
                text: 'Please select an image smaller than 5MB',
                icon: 'error',
                confirmButtonColor: '#3b82f6'
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
                confirmButtonColor: '#3b82f6'
            });
            this.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            
            // Hide current image or placeholder
            if (currentImage) currentImage.style.display = 'none';
            if (noImagePlaceholder) noImagePlaceholder.style.display = 'none';
            
            // Update status text and show cancel button
            imageStatus.textContent = 'New image selected - Save to confirm';
            imageStatus.style.color = '#3b82f6';
            cancelBtn.style.display = 'inline-block';
        };
        reader.readAsDataURL(file);
        
    } else {
        resetImagePreview();
    }
});

// Cancel image change functionality
document.getElementById('cancelImageChange').addEventListener('click', function() {
    document.getElementById('profile_image').value = '';
    resetImagePreview();
});

function resetImagePreview() {
    const preview = document.getElementById('imagePreview');
    const currentImage = document.getElementById('currentImage');
    const noImagePlaceholder = document.getElementById('noImagePlaceholder');
    const imageStatus = document.getElementById('imageStatus');
    const cancelBtn = document.getElementById('cancelImageChange');
    
    // Hide preview
    preview.style.display = 'none';
    
    // Show original image or placeholder
    if (currentImage) currentImage.style.display = 'block';
    if (noImagePlaceholder) noImagePlaceholder.style.display = 'flex';
    
    // Reset status text
    <?php if (!empty($profile['profile_image'])): ?>
        imageStatus.textContent = 'Upload new image to replace current';
    <?php else: ?>
        imageStatus.textContent = 'Upload your profile image';
    <?php endif; ?>
    imageStatus.style.color = '#9ca3af';
    cancelBtn.style.display = 'none';
}
    </script>
</body>
</html>