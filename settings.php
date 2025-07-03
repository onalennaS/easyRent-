<?php
// File: settings.php
require_once __DIR__ . '/config/database.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$errors = [];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $errors[] = "User not found!";
    }
} catch (PDOException $e) {
    $errors[] = "Error fetching user data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $date_of_birth = trim($_POST['date_of_birth']);

    // Validate inputs
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!empty($phone) && !preg_match('/^\+?[0-9]{10,15}$/', $phone)) $errors[] = "Invalid phone format";
    if (!empty($date_of_birth) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) $errors[] = "Invalid date format (YYYY-MM-DD)";

    // Handle file upload
    $profile_image = $user['profile_image'] ?? 'default.jpg';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . "/uploads/profiles/";
        $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $new_filename = "user_{$user_id}_" . time() . ".$file_ext";
        $target_file = $target_dir . $new_filename;
        
        // Validate image
        $check = getimagesize($_FILES['profile_image']['tmp_name']);
        if ($check === false) {
            $errors[] = "File is not an image";
        } elseif ($_FILES['profile_image']['size'] > 2000000) {
            $errors[] = "Image is too large (max 2MB)";
        } else {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                $profile_image = $new_filename;
                // Remove old image if not default
                if ($user['profile_image'] !== 'default.jpg' && file_exists($target_dir . $user['profile_image'])) {
                    @unlink($target_dir . $user['profile_image']);
                }
            } else {
                $errors[] = "Error uploading image";
            }
        }
    }

    // Update database if no errors
    if (empty($errors)) {
        try {
            $update_stmt = $pdo->prepare("UPDATE users SET 
                first_name = ?, 
                last_name = ?, 
                phone = ?, 
                date_of_birth = ?, 
                profile_image = ?, 
                updated_at = NOW() 
                WHERE id = ?");
            
            $update_stmt->execute([
                $first_name,
                $last_name,
                $phone,
                $date_of_birth,
                $profile_image,
                $user_id
            ]);
            
            $_SESSION['message'] = "Profile updated successfully!";
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - EasyRent</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #2e59d9;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
            --shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            --border-radius: 0.35rem;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8f9fc;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1rem 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 1.5rem;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: opacity 0.3s;
        }
        
        nav ul li a:hover {
            opacity: 0.9;
        }
        
        nav ul li a i {
            margin-right: 5px;
        }
        
        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.75rem;
            color: var(--dark);
            font-weight: 600;
        }
        
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .settings-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
        }
        
        .settings-sidebar {
            flex: 1;
            min-width: 300px;
        }
        
        .settings-main {
            flex: 2;
            min-width: 300px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="file"],
        select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #d1d3e2;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .img-preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 1rem 0;
        }
        
        .img-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 0.15rem 1rem rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.8rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #d1d3e2;
            color: var(--dark);
        }
        
        .btn-outline:hover {
            background: #f8f9fc;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-group {
            display: flex;
            gap: 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .alert-success {
            background-color: rgba(28, 200, 138, 0.2);
            border-left: 4px solid var(--success);
            color: #155724;
        }
        
        .alert-error {
            background-color: rgba(231, 74, 59, 0.2);
            border-left: 4px solid var(--danger);
            color: #721c24;
        }
        
        .error {
            color: var(--danger);
            font-size: 0.9rem;
            margin-top: 0.3rem;
            display: block;
        }
        
        .info-note {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-top: 0.3rem;
        }
        
        /* Footer */
        footer {
            background: white;
            border-top: 1px solid #e3e6f0;
            padding: 1.5rem 0;
            margin-top: 2rem;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .copyright {
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        .social-links a {
            color: var(--secondary);
            margin-left: 1rem;
            font-size: 1.2rem;
            transition: color 0.3s;
        }
        
        .social-links a:hover {
            color: var(--primary);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            nav ul {
                justify-content: center;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .btn-group {
                width: 100%;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">
                <i class="fas fa-home"></i>
                <span>EasyRent</span>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Account Settings</h1>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <div class="settings-sidebar">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-shield"></i> Security Settings
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Email Verification</label>
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <span>
                                        <?= $user['is_verified'] ? 'Verified' : 'Not Verified' ?>
                                    </span>
                                    <button class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                                        <?= $user['is_verified'] ? 'Change' : 'Verify' ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Two-Factor Authentication</label>
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <span>Not Enabled</span>
                                    <button class="btn btn-outline" style="padding: 0.4rem 0.8rem;">
                                        Enable
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Password</label>
                                <p class="info-note">Last changed: <?= $user['updated_at'] ? date('d M Y', strtotime($user['updated_at'])) : 'Unknown' ?></p>
                                <button class="btn btn-outline" style="width: 100%;">
                                    <i class="fas fa-lock"></i> Change Password
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label>Login Activity</label>
                                <p class="info-note">Last login: <?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'Never' ?></p>
                                <button class="btn btn-outline" style="width: 100%;">
                                    <i class="fas fa-history"></i> View Activity
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-exclamation-triangle"></i> Danger Zone
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Deactivate Account</label>
                                <p class="info-note">Temporarily disable your account</p>
                                <button class="btn btn-outline" style="width: 100%; color: var(--danger); border-color: var(--danger);">
                                    <i class="fas fa-ban"></i> Deactivate Account
                                </button>
                            </div>
                            
                            <div class="form-group">
                                <label>Delete Account</label>
                                <p class="info-note">Permanently delete your account and data</p>
                                <button class="btn btn-outline" style="width: 100%; color: var(--danger); border-color: var(--danger);">
                                    <i class="fas fa-trash-alt"></i> Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="settings-main">
                    <form action="settings.php" method="POST" enctype="multipart/form-data">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-user-edit"></i> Profile Information
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="profile_image">Profile Picture</label>
                                    <div class="img-preview-container">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] . '+' . $user['last_name']) ?>&background=4e73df&color=fff&size=128" 
                                             alt="Profile Preview" class="img-preview" id="imgPreview">
                                        <input type="file" name="profile_image" id="profile_image" accept="image/*">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                                    <p class="info-note">Contact support to change your email address</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                           placeholder="+27 123 456 7890">
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" 
                                           value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-map-marker-alt"></i> Address Information
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="address">Street Address</label>
                                    <input type="text" id="address" name="address" 
                                           value="<?= htmlspecialchars($user['address'] ?? '') ?>"
                                           placeholder="123 Main Street">
                                </div>
                                
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" 
                                           value="<?= htmlspecialchars($user['city'] ?? '') ?>"
                                           placeholder="Cape Town">
                                </div>
                                
                                <div class="form-group">
                                    <label for="province">Province</label>
                                    <select id="province" name="province">
                                        <option value="">Select Province</option>
                                        <option value="EC" <?= (isset($user['province']) && $user['province'] === 'EC') ? 'selected' : '' ?>>Eastern Cape</option>
                                        <option value="FS" <?= (isset($user['province']) && $user['province'] === 'FS') ? 'selected' : '' ?>>Free State</option>
                                        <option value="GP" <?= (isset($user['province']) && $user['province'] === 'GP') ? 'selected' : '' ?>>Gauteng</option>
                                        <option value="KZN" <?= (isset($user['province']) && $user['province'] === 'KZN') ? 'selected' : '' ?>>KwaZulu-Natal</option>
                                        <option value="LP" <?= (isset($user['province']) && $user['province'] === 'LP') ? 'selected' : '' ?>>Limpopo</option>
                                        <option value="MP" <?= (isset($user['province']) && $user['province'] === 'MP') ? 'selected' : '' ?>>Mpumalanga</option>
                                        <option value="NC" <?= (isset($user['province']) && $user['province'] === 'NC') ? 'selected' : '' ?>>Northern Cape</option>
                                        <option value="NW" <?= (isset($user['province']) && $user['province'] === 'NW') ? 'selected' : '' ?>>North West</option>
                                        <option value="WC" <?= (isset($user['province']) && $user['province'] === 'WC') ? 'selected' : '' ?>>Western Cape</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" 
                                           value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>"
                                           placeholder="8000">
                                </div>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="profile.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container footer-content">
            <div class="copyright">
                &copy; <?= date('Y') ?> EasyRent. All rights reserved.
            </div>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </footer>
    
    <script>
        // Image preview functionality
        document.getElementById('profile_image').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imgPreview').src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>