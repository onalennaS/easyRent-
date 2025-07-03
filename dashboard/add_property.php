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

$success_message = '';
$error_message = '';

// Create upload directory if not exists
$upload_dir = '../uploads/properties/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $property_type = mysqli_real_escape_string($conn, $_POST['property_type']);
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $state = mysqli_real_escape_string($conn, trim($_POST['state']));
    $postal_code = mysqli_real_escape_string($conn, trim($_POST['postal_code']));
    $bedrooms = (int)$_POST['bedrooms'];
    $bathrooms = (float)$_POST['bathrooms'];
    $square_meters = (float)$_POST['square_meters'];
    $rent_amount = (float)$_POST['rent_amount'];
    $deposit_amount = (float)$_POST['deposit_amount'];
    $utilities_included = isset($_POST['utilities_included']) ? 1 : 0;
    $parking_available = isset($_POST['parking_available']) ? 1 : 0;
    $pet_friendly = isset($_POST['pet_friendly']) ? 1 : 0;
    $furnished = isset($_POST['furnished']) ? 1 : 0;
    $available_from = $_POST['available_from'];
    $lease_duration_months = (int)$_POST['lease_duration_months'];
    
    // Basic validation
    if (empty($title) || empty($description) || empty($address) || empty($city) || 
        empty($state) || empty($postal_code) || $rent_amount <= 0) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Insert property into database
        $insert_query = "
            INSERT INTO properties (
                landlord_id, title, description, property_type, address, city, state, 
                postal_code, bedrooms, bathrooms, square_meters, rent_amount, deposit_amount,
                utilities_included, parking_available, pet_friendly, furnished, available_from,
                lease_duration_months, is_available, is_featured, view_count, created_at, updated_at,
                admin_approved
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, NOW(), NOW(), 0
            )
        ";
        
        $stmt = mysqli_prepare($conn, $insert_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issssssiidddiiiissi", 
                $landlord_id, $title, $description, $property_type, $address, $city, $state,
                $postal_code, $bedrooms, $bathrooms, $square_meters, $rent_amount, $deposit_amount,
                $utilities_included, $parking_available, $pet_friendly, $furnished, $available_from,
                $lease_duration_months
            );
            
            if (mysqli_stmt_execute($stmt)) {
                $property_id = mysqli_insert_id($conn);
                $success_message = "Property added successfully! It will be available after admin approval.";
                
                // Process image uploads
                $main_image = '';
                $additional_images = [];
                
                // Process main image
                if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $main_image = uploadImage($_FILES['main_image'], $upload_dir);
                }
                
                // Process additional images
                if (!empty($_FILES['additional_images'])) {
                    foreach ($_FILES['additional_images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['additional_images']['name'][$key],
                                'type' => $_FILES['additional_images']['type'][$key],
                                'tmp_name' => $tmp_name,
                                'error' => $_FILES['additional_images']['error'][$key],
                                'size' => $_FILES['additional_images']['size'][$key]
                            ];
                            $image_url = uploadImage($file, $upload_dir);
                            if ($image_url) {
                                $additional_images[] = $image_url;
                            }
                        }
                    }
                }
                
                // Save images to database
                if ($main_image) {
                    saveImageToDB($conn, $property_id, $main_image, 1);
                }
                
                foreach ($additional_images as $image_url) {
                    saveImageToDB($conn, $property_id, $image_url, 0);
                }
                
                // Clear form by redirecting
                header("Location: add_property.php?success=1");
                exit();
            } else {
                $error_message = "Error adding property: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error preparing statement: " . mysqli_error($conn);
        }
    }
}

// Image upload function
function uploadImage($file, $upload_dir) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . $extension;
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $filename;
    }
    
    return false;
}

// Save image to database function
function saveImageToDB($conn, $property_id, $image_url, $is_primary) {
    $query = "INSERT INTO property_images (property_id, image_url, is_primary) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isi", $property_id, $image_url, $is_primary);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success_message = "Property added successfully! It will be available after admin approval.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Property - Easy Rent</title>
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
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #06b6d4 100%);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="70" r="2" fill="rgba(255,255,255,0.1)"/></svg>');
        }

        .page-header-content {
            position: relative;
            z-index: 2;
        }

        .page-title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        /* Alert Messages */
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

        /* Form Styles */
        .form-grid {
            display: grid;
            gap: 2rem;
        }

        .form-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-row.single {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .required {
            color: #ef4444;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            border-color: #3b82f6;
            background: #f8fafc;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
        }

        .checkbox-item label {
            cursor: pointer;
            font-weight: 500;
        }

        /* Input Icons */
        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            z-index: 2;
        }

        .input-icon input {
            padding-left: 2.5rem;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .form-section {
                padding: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            .nav-container {
                padding: 0 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }


       .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        
        .preview-item {
            position: relative;
            width: 120px;
            height: 90px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
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
                <li><a href="landlord_dashboard.php" class="active">Dashboard</a></li>
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
            <div class="page-header-content">
                <h1 class="page-title">
                    <i class="fas fa-plus-circle"></i>
                    Add New Property
                </h1>
                <p class="page-subtitle">List your property and start attracting tenants</p>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container">
            <form method="POST" action="add_property.php" enctype="multipart/form-data">
                <div class="form-grid">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Basic Information
                        </h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="title">
                                    Property Title <span class="required">*</span>
                                </label>
                                <input type="text" id="title" name="title" class="form-input" 
                                       placeholder="e.g., Modern 2BR Apartment in Downtown" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="property_type">
                                    Property Type <span class="required">*</span>
                                </label>
                                <select id="property_type" name="property_type" class="form-select" required>
                                    <option value="">Select Property Type</option>
                                    <option value="apartment">Apartment</option>
                                    <option value="house">House</option>
                                    <option value="condo">Condo</option>
                                    <option value="townhouse">Townhouse</option>
                                    <option value="studio">Studio</option>
                                    <option value="duplex">Duplex</option>
                                    <option value="villa">Villa</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row single">
                            <div class="form-group">
                                <label class="form-label" for="description">
                                    Description <span class="required">*</span>
                                </label>
                                <textarea id="description" name="description" class="form-textarea" 
                                          placeholder="Describe your property, its features, and amenities..." required></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Location
                        </h2>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label class="form-label" for="address">
                                    Street Address <span class="required">*</span>
                                </label>
                                <div class="input-icon">
                                    <i class="fas fa-home"></i>
                                    <input type="text" id="address" name="address" class="form-input" 
                                           placeholder="e.g., 123 Main Street" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="city">
                                    City <span class="required">*</span>
                                </label>
                                <input type="text" id="city" name="city" class="form-input" 
                                       placeholder="e.g., New York" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="state">
                                    State/Province <span class="required">*</span>
                                </label>
                                <input type="text" id="state" name="state" class="form-input" 
                                       placeholder="e.g., NY" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="postal_code">
                                    Postal Code <span class="required">*</span>
                                </label>
                                <input type="text" id="postal_code" name="postal_code" class="form-input" 
                                       placeholder="e.g., 10001" required>
                            </div>
                        </div>
                    </div>

                    <!-- Property Details -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-building"></i>
                            Property Details
                        </h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="bedrooms">
                                    Bedrooms
                                </label>
                                <select id="bedrooms" name="bedrooms" class="form-select">
                                    <option value="0">Studio</option>
                                    <option value="1">1 Bedroom</option>
                                    <option value="2">2 Bedrooms</option>
                                    <option value="3">3 Bedrooms</option>
                                    <option value="4">4 Bedrooms</option>
                                    <option value="5">5+ Bedrooms</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="bathrooms">
                                    Bathrooms
                                </label>
                                <select id="bathrooms" name="bathrooms" class="form-select">
                                    <option value="1">1 Bathroom</option>
                                    <option value="1.5">1.5 Bathrooms</option>
                                    <option value="2">2 Bathrooms</option>
                                    <option value="2.5">2.5 Bathrooms</option>
                                    <option value="3">3 Bathrooms</option>
                                    <option value="3.5">3.5 Bathrooms</option>
                                    <option value="4">4+ Bathrooms</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="square_meters">
                                    Square Meters
                                </label>
                                <div class="input-icon">
                                    <i class="fas fa-expand-arrows-alt"></i>
                                    <input type="number" id="square_meters" name="square_meters" class="form-input" 
                                           placeholder="e.g., 85" min="0" step="0.1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
<div class="form-section">
    <h2 class="section-title">
        <i class="fas fa-random"></i> <!-- optional: change icon if needed -->
        Pricing
    </h2>
    
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="rent_amount">
                Monthly Rent <span class="required">*</span>
            </label>
            <div class="input-icon">
                <span class="currency-symbol">R</span>
                <input type="number" id="rent_amount" name="rent_amount" class="form-input" 
                       placeholder="e.g., 1200" min="0" step="0.01" required>
            </div>
        </div>
    </div>
</div>

                            
                            <div class="form-group">
    <label class="form-label" for="deposit_amount">
        Security Deposit
    </label>
    <div class="input-icon">
        <span class="currency-symbol">R</span>
        <input type="number" id="deposit_amount" name="deposit_amount" class="form-input" 
               placeholder="e.g., 1200" min="0" step="0.01">
    </div>
</div>

<div class="form-group">
    <label class="form-label" for="lease_duration_months">
        Lease Duration (Months)
    </label>
    <select id="lease_duration_months" name="lease_duration_months" class="form-select">
        <option value="12">12 Months</option>
        <option value="6">6 Months</option>
        <option value="24">24 Months</option>
        <option value="36">36 Months</option>
        <option value="0">Month-to-Month</option>
    </select>
</div>


                    <!-- Features & Amenities -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-star"></i>
                            Features & Amenities
                        </h2>
                        
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="utilities_included" name="utilities_included" value="1">
                                <label for="utilities_included">
                                    <i class="fas fa-lightbulb"></i>
                                    Utilities Included
                                </label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="parking_available" name="parking_available" value="1">
                                <label for="parking_available">
                                    <i class="fas fa-car"></i>
                                    Parking Available
                                </label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="pet_friendly" name="pet_friendly" value="1">
                                <label for="pet_friendly">
                                    <i class="fas fa-paw"></i>
                                    Pet Friendly
                                </label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="furnished" name="furnished" value="1">
                                <label for="furnished">
                                    <i class="fas fa-couch"></i>
                                    Furnished
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Availability -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Availability
                        </h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="available_from">
                                    Available From
                                </label>
                                <input type="date" id="available_from" name="available_from" class="form-input" 
                                       value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Property Images -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-images"></i>
                            Property Images
                        </h2>
                        
                        <div class="form-row single">
                            <div class="form-group">
                                <label class="form-label" for="main_image">
                                    Main Image <span class="required">*</span>
                                </label>
                                <input type="file" id="main_image" name="main_image" class="form-input" 
                                       accept="image/*" required>
                                <div class="image-preview" id="main-preview"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="image_1">
                                    Additional Image 1
                                </label>
                                <input type="file" id="image_1" name="additional_images[]" class="form-input" 
                                       accept="image/*">
                                <div class="image-preview" id="preview-1"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="image_2">
                                    Additional Image 2
                                </label>
                                <input type="file" id="image_2" name="additional_images[]" class="form-input" 
                                       accept="image/*">
                                <div class="image-preview" id="preview-2"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="image_3">
                                    Additional Image 3
                                </label>
                                <input type="file" id="image_3" name="additional_images[]" class="form-input" 
                                       accept="image/*">
                                <div class="image-preview" id="preview-3"></div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="image_4">
                                    Additional Image 4
                                </label>
                                <input type="file" id="image_4" name="additional_images[]" class="form-input" 
                                       accept="image/*">
                                <div class="image-preview" id="preview-4"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="image_5">
                                    Additional Image 5
                                </label>
                                <input type="file" id="image_5" name="additional_images[]" class="form-input" 
                                       accept="image/*">
                                <div class="image-preview" id="preview-5"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="landlord_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add Property
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Image preview functionality
        function setupImagePreview(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            
            input.addEventListener('change', function() {
                preview.innerHTML = '';
                
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        
                        const previewItem = document.createElement('div');
                        previewItem.className = 'preview-item';
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.className = 'remove-image';
                        removeBtn.innerHTML = '×';
                        removeBtn.onclick = function() {
                            preview.removeChild(previewItem);
                            input.value = '';
                        };
                        
                        previewItem.appendChild(img);
                        previewItem.appendChild(removeBtn);
                        preview.appendChild(previewItem);
                    }
                    
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Set up previews for all image inputs
        setupImagePreview('main_image', 'main-preview');
        for (let i = 1; i <= 5; i++) {
            setupImagePreview('image_' + i, 'preview-' + i);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
  document.getElementById('logoutLink').addEventListener('click', function(e) {
    e.preventDefault(); // prevent default link behavior

    Swal.fire({
      title: 'Are you sure?',
      text: 'You will be logged out from your account.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6', // blue
      cancelButtonColor: '#d33',     // red
      confirmButtonText: 'Yes, log out',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        // ✅ Perform your logout action here
        window.location.href = '../auth/logout.php'; // Replace with your logout URL
      }
    });
  });
</script>
</body>
</html>