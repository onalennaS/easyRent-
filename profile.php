<?php
// File: profile.php
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
$user = [];
$message = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $message = "User not found!";
    }
} catch (PDOException $e) {
    $message = "Error fetching profile: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - L&T Connect</title>
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
        
        .profile-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
        }
        
        .profile-sidebar {
            flex: 1;
            min-width: 300px;
        }
        
        .profile-main {
            flex: 2;
            min-width: 300px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            box-shadow: 0 0.15rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .profile-info {
            margin-left: 1.5rem;
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .profile-role {
            color: var(--secondary);
            margin-bottom: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1.2rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .info-item {
            padding: 1rem;
            border-left: 3px solid var(--primary);
            background: #f8f9fc;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: rgba(28, 200, 138, 0.2);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: rgba(246, 194, 62, 0.2);
            color: var(--warning);
        }
        
        .badge-info {
            background-color: rgba(54, 185, 204, 0.2);
            color: var(--info);
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
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-info {
                margin-left: 0;
                margin-top: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            nav ul {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">
                <img src="logo.png" alt="L&T Connect" style="max-height: 40px; width: auto;">
            </div>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">User Profile</h1>
                <a href="settings.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert <?= strpos($message, 'Error') === 0 ? 'alert-error' : 'alert-success' ?>">
                    <i class="fas <?= strpos($message, 'Error') === 0 ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div class="profile-container">
                <div class="profile-sidebar">
                    <div class="card">
                        <div class="profile-header">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] . '+' . $user['last_name']) ?>&background=4e73df&color=fff&size=128" 
                                 alt="Profile Image" class="profile-img">
                            <div class="profile-info">
                                <h2 class="profile-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                                <p class="profile-role"><?= ucfirst($user['user_type']) ?></p>
                                <p><i class="fas fa-calendar-alt"></i> Member since <?= date('M Y', strtotime($user['created_at'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Account Status</div>
                                    <div class="info-value">
                                        <span class="status-badge <?= $user['is_active'] ? 'badge-success' : 'badge-warning' ?>">
                                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Verification Status</div>
                                    <div class="info-value">
                                        <span class="status-badge <?= $user['is_verified'] ? 'badge-success' : 'badge-warning' ?>">
                                            <?= $user['is_verified'] ? 'Verified' : 'Pending' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Last Login</div>
                                    <div class="info-value">
                                        <?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'Never' ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Account Type</div>
                                    <div class="info-value">
                                        <span class="status-badge badge-info">
                                            <?= ucfirst($user['user_type']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-main">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-circle"></i> Personal Information
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">First Name</div>
                                    <div class="info-value"><?= htmlspecialchars($user['first_name']) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Last Name</div>
                                    <div class="info-value"><?= htmlspecialchars($user['last_name']) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Email Address</div>
                                    <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Date of Birth</div>
                                    <div class="info-value">
                                        <?= $user['date_of_birth'] ? date('d M Y', strtotime($user['date_of_birth'])) : 'Not provided' ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Username</div>
                                    <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Account Created</div>
                                    <div class="info-value"><?= date('d M Y', strtotime($user['created_at'])) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Last Updated</div>
                                    <div class="info-value">
                                        <?= $user['updated_at'] ? date('d M Y H:i', strtotime($user['updated_at'])) : 'Never' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-shield-alt"></i> Security Information
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Failed Login Attempts</div>
                                    <div class="info-value"><?= htmlspecialchars($user['failed_login_attempts'] ?? 0) ?></div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Last Failed Login</div>
                                    <div class="info-value">
                                        <?= $user['last_failed_login'] ? date('d M Y H:i', strtotime($user['last_failed_login'])) : 'Never' ?>
                                    </div>
                                </div>
                                
                                <div class="info-item">
                                    <div class="info-label">Password Last Changed</div>
                                    <div class="info-value">
                                        <?= $user['updated_at'] ? date('d M Y', strtotime($user['updated_at'])) : 'Unknown' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container footer-content">
            <div class="copyright">
                &copy; <?= date('Y') ?> L&T Connect. All rights reserved.
            </div>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </footer>
</body>
</html>