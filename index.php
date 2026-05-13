<?php
session_start();

// Check if user is logged in and get their information
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';
$userRole = $isLoggedIn ? $_SESSION['user_type'] : '';
$userId = $isLoggedIn ? $_SESSION['user_id'] : '';

if ($isLoggedIn && $userRole === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Initialize profile alert variables
$showProfileAlert = false;
$missingFields = [];

if ($isLoggedIn) {
    // Connect to database
    $servername = "localhost";
    $username_db = "root";
    $password_db = "";
    $dbname = "easyrent_db";
    
    $conn = new mysqli($servername, $username_db, $password_db, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Fetch user profile from database
    $stmt = $conn->prepare("SELECT first_name, last_name, phone, date_of_birth, profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if profile is complete
        $profileComplete = true;
        $missingFields = [];
        
        // Required profile fields
        $requiredFields = ['first_name', 'last_name', 'phone', 'date_of_birth'];
        
        // Check if any required fields are missing
        foreach ($requiredFields as $field) {
            if (empty($user[$field])) {
                $profileComplete = false;
                $missingFields[] = str_replace('_', ' ', $field);
            }
        }
        
        // Check if profile image is default
        if (empty($user['profile_image']) || $user['profile_image'] === 'default.jpg') {
            $profileComplete = false;
            $missingFields[] = 'profile image';
        }
        
        // Update session with latest data
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['date_of_birth'] = $user['date_of_birth'];
        $_SESSION['profile_image'] = $user['profile_image'];
        
        // Store in session for later use
        $_SESSION['profile_complete'] = $profileComplete;
        $_SESSION['missing_fields'] = $missingFields;
        
        // Check if we should show the alert
        if (!$profileComplete && !isset($_SESSION['profile_alert_shown'])) {
            $showProfileAlert = true;
            // Set flag so it only shows once
            $_SESSION['profile_alert_shown'] = true;
        }
    }
    
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>L&T Connect - Professional Property Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <!-- Add SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background: white;
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        .property-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .property-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        /* FIXED NAVBAR STYLING */
        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .footer {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .hero-section {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem 0;
        }

        .property-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
        }

        .status-available { background-color: #10b981; }
        .status-occupied { background-color: #f59e0b; }
        .status-maintenance { background-color: #ef4444; }

        .loading-spinner {
            display: none;
            justify-content: center;
            align-items: center;
            height: 200px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
            margin: 2rem 0;
        }

        .no-properties {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #3b82f6;
            padding: 2rem;
            border-radius: 0.5rem;
            text-align: center;
            margin: 2rem 0;
        }

        /* FIXED USER DROPDOWN */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            min-width: 200px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            margin-top: 0.5rem;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
            font-weight: 500;
        }

        .dropdown-content a:hover {
            background-color: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }

        .dropdown-content a i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
            color: #6b7280;
        }

        .dropdown-content a:hover i {
            color: #1e40af;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 0.5rem 0;
        }

        .welcome-section {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }
        
        /* FIXED MOBILE NAVIGATION */
        .mobile-nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-nav-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .mobile-nav-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            background: white;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-100%);
            transition: transform 0.3s ease-in-out;
        }

        .mobile-nav-container.open {
            transform: translateY(0);
        }

        .mobile-nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .mobile-nav-content {
            max-height: calc(100vh - 80px);
            overflow-y: auto;
            background: white;
        }

        .mobile-nav-item {
            display: block;
            padding: 1rem 1.5rem;
            color: #374151;
            font-weight: 500;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s;
            text-decoration: none;
        }

        .mobile-nav-item:hover {
            background-color: #3b82f6;
            color: white;
        }

        .mobile-nav-user-section {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            border-top: 2px solid #e5e7eb;
        }

        .mobile-nav-username {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        

        /* MOBILE HAMBURGER BUTTON - FIXED */
        .mobile-menu-btn {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: none;
            border: none;
            padding: 0;
            margin: 0;
        }

        .mobile-menu-btn span {
            display: block;
            height: 3px;
            width: 100%;
            background: #374151;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .mobile-menu-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .mobile-menu-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .mobile-menu-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        /* SweetAlert2 Styling */
        .swal2-popup {
            border-radius: 0.75rem !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
            padding: 1.5rem !important;
            background: #ffffff !important;
            max-width: 400px !important;
        }

        .swal2-title {
            font-size: 1.25rem !important; 
            font-weight: 600 !important;
            color: #1e293b !important; 
            margin-bottom: 1rem !important;
        }

        .swal2-html-container {
            font-size: 1rem !important;
            color: #4b5563 !important;
            line-height: 1.5 !important;
            margin-bottom: 1.5rem !important;
        }

        .swal2-html-container a {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.6rem 1.25rem;
            background: #3b82f6;
            color: white !important;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .swal2-html-container a:hover {
            background: #2563eb;
            text-decoration: none;
        }

        .swal2-confirm {
            background: #3b82f6 !important;
            border: none !important;
            color: #fff !important;
            padding: 0.6rem 1.5rem !important;
            font-size: 0.9rem !important;
            border-radius: 0.375rem !important;
            box-shadow: none !important;
            transition: all 0.2s ease !important;
            font-weight: 500 !important;
        }

        .swal2-confirm:hover {
            background: #2563eb !important;
            transform: none !important;
        }

        /* Enhanced no properties display */
        .no-properties-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 2rem auto;
        }
        
        .no-properties-icon {
            font-size: 4rem;
            color: #3b82f6;
            margin-bottom: 1.5rem;
        }
        
        /* Colored SweetAlerts */
        .swal2-info {
            border-left: 4px solid #3b82f6 !important;
        }
        
        .swal2-success {
            border-left: 4px solid #10b981 !important;
        }
        
        .swal2-warning {
            border-left: 4px solid #f59e0b !important;
        }
        
        .swal2-error {
            border-left: 4px solid #ef4444 !important;
        }

        /* RESPONSIVE BREAKPOINTS - FIXED */
        @media (min-width: 769px) {
            .mobile-only {
                display: none !important;
            }
            
            .mobile-nav-container {
                display: none !important;
            }
            
            .mobile-menu-btn {
                display: none !important;
            }
        }

        @media (max-width: 768px) {
            .desktop-only {
                display: none !important;
            }
            
            .mobile-menu-btn {
                display: flex !important;
            }
            
            .properties-grid {
                grid-template-columns: 1fr !important;
            }
            
            .mobile-nav-item {
                padding: 0.8rem 1.2rem;
                font-size: 0.95rem;
            }
            
            .mobile-nav-user-section {
                padding: 0.8rem 1.2rem;
            }
            
            /* Hide desktop navigation on mobile */
            .navbar .hidden.md\\:flex {
                display: none !important;
            }
        }
        @media (max-width: 768px) {
    .desktop-only {
        display: none !important;
    }
    
    .mobile-menu-btn {
        display: flex !important;
    }
    
    /* Show the mobile menu button */
    .md\\:hidden {
        display: flex !important;
    }
    
    /* Hide desktop user section on mobile */
    .hidden.md\\:flex {
        display: none !important;
    }
}
/* Left-aligned mobile menu */
.mobile-nav-container {
    width: 300px; /* Fixed width for the menu */
    max-width: 80%; /* But never more than 80% of screen */
    height: 100vh; /* Full height */
    transform: translateX(-100%); /* Start off-screen to the left */
    border-radius: 0; /* Remove rounded corners */
    left: 0; /* Align to left */
    right: auto; /* Override any right positioning */
}

.mobile-nav-container.open {
    transform: translateX(0); /* Slide in from left */
}

/* Adjust overlay to work with left menu */
.mobile-nav-overlay.show {
    backdrop-filter: blur(5px);
}

/* Ensure content is properly sized */
.mobile-nav-content {
    height: 100%;
    overflow-y: auto;
}

/* Adjust header for left menu */
.mobile-nav-header {
    padding: 1rem;
    background: white;
    border-bottom: 1px solid #e5e7eb;
}

/* Adjust menu items */
.mobile-nav-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f3f4f6;
}

        /* User section at bottom */
        .mobile-nav-user-section {
            padding: 1rem;
            background: #f9fafb;
            margin-top: auto;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Pagination styling */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .pagination-btn {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 640px) {
            .pagination-container {
                gap: 0.25rem;
            }
            
            .pagination-btn {
                min-width: 36px;
                height: 36px;
                font-size: 0.875rem;
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Overlay -->
    <div id="mobileNavOverlay" class="mobile-nav-overlay" onclick="closeMobileNav()"></div>

    <!-- Mobile Navigation -->
    <div id="mobileNavContainer" class="mobile-nav-container">
        <div class="mobile-nav-header">
            <div class="flex items-center">
                <img src="logo.png" alt="L&T Connect Logo" class="h-45 w-auto">
            </div>
            <button onclick="closeMobileNav()" class="text-gray-700 hover:text-red-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="mobile-nav-content">
            <!-- Main Navigation Links -->
            <a href="#home" class="mobile-nav-item" onclick="closeMobileNav()">
                <i class="fas fa-home mr-3"></i>Home
            </a>
            <a href="#properties" class="mobile-nav-item" onclick="closeMobileNav()">
                <i class="fas fa-building mr-3"></i>Properties
            </a>
            <a href="#about" class="mobile-nav-item" onclick="closeMobileNav()">
                <i class="fas fa-info-circle mr-3"></i>About
            </a>
            <a href="#contact" class="mobile-nav-item" onclick="closeMobileNav()">
                <i class="fas fa-envelope mr-3"></i>Contact
            </a>
            
            <!-- User Section for Logged In Users -->
            <?php if($isLoggedIn): ?>
            <div class="mobile-nav-user-section">
                <div class="mobile-nav-username">
                    <i class="fas fa-user-circle mr-2"></i>
                    <?php echo htmlspecialchars($username); ?>
                </div>

                <!-- Dashboard Link -->
                <a href="<?php echo $userRole === 'tenant' ? 'dashboard/tenant_dashboard.php' : 'dashboard/landlord_dashboard.php'; ?>" class="mobile-nav-item" onclick="closeMobileNav()">
                    <?php echo $userRole === 'tenant' ? 'Tenant Dashboard' : 'Landlord Dashboard'; ?>
                </a>



                <a href="#" onclick="confirmLogout(); return false;" class="mobile-nav-item text-red-600 hover:bg-red-50">
                    <i class="fas fa-sign-out-alt mr-3"></i>Logout
                </a>
            </div>
            <?php else: ?>
            <!-- Guest User Section -->
            <div class="mobile-nav-user-section">
                <a href="auth/login.php" class="block w-full text-center bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold mb-3 hover:bg-blue-700 transition-colors" onclick="closeMobileNav()">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </a>
                <a href="auth/register.php" class="block w-full text-center border-2 border-blue-600 text-blue-600 py-3 px-4 rounded-lg font-semibold hover:bg-blue-50 transition-colors" onclick="closeMobileNav()">
                    <i class="fas fa-user-plus mr-2"></i>Register
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Desktop Navigation -->
<nav class="navbar fixed w-full top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Logo -->
            <div class="flex items-center">
                <div class="flex items-center">
                    <img src="logo.png" alt="L&T Connect Logo" class="h-20 w-auto">
                </div>
            </div>
            
            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="#home" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">Home</a>
                <a href="#properties" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">Properties</a>
                <a href="#about" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">About</a>
                <a href="#contact" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">Contact</a>
                
                
                <!-- Show Dashboard link when logged in -->
                <?php if($isLoggedIn): ?>
                <a href="<?php echo $userRole === 'tenant' ? 'dashboard/tenant_dashboard.php' : 'dashboard/landlord_dashboard.php'; ?>" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">
                    <?php echo $userRole === 'tenant' ? 'Tenant Dashboard' : 'Landlord Dashboard'; ?>
                </a>
                <?php endif; ?>
            </div>
            <!-- Desktop User Section -->
<div class="hidden md:flex items-center space-x-4">
    <?php if(!$isLoggedIn): ?>
    <!-- Guest Links -->
    <div class="flex items-center space-x-4">
        <a href="auth/login.php" class="text-gray-700 hover:text-blue-600 transition-colors font-medium">
            <i class="fas fa-sign-in-alt mr-2"></i>Login
        </a>
        <a href="auth/register.php" class="btn-primary px-4 py-2 rounded-lg text-white font-semibold">
            <i class="fas fa-user-plus mr-2"></i>Register
        </a>
    </div>
    <?php else: ?>
    <!-- Logged In Links -->
    <div class="flex items-center space-x-4">
       
<a href="#" class="text-gray-700 hover:text-blue-600 transition-colors font-medium" 
   onclick="confirmLogout(); return false;">
    <i class="fas fa-sign-out-alt mr-2"></i>Logout
</a>
    </div>
    <?php endif; ?>
</div>



            <!-- Mobile Menu Button - Moved outside desktop-only section -->
            <div class="md:hidden flex items-center">
                <button onclick="toggleMobileNav()" class="mobile-menu-btn" id="mobileMenuBtn">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>
</nav>

    <!-- Properties Section -->
    <section id="properties" class="py-16 bg-gradient-to-br from-slate-900 to-slate-800 mt-20">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-black mb-3">Available Properties</h2>
                <p class="text-black">Explore our latest approved rental listings</p>
            </div>
            
            <!-- Filter Section -->
            <div class="mb-8">
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-xl font-semibold text-black mb-4">
                        <i class="fas fa-filter mr-2"></i>Filter Properties
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Location Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-800 mb-2">Location</label>
                            <input type="text" id="locationFilter" placeholder="Search by location..." 
                                   class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-500">
                        </div>
                        
                        <!-- Bedrooms Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-800 mb-2">Min Bedrooms</label>
                            <select id="bedroomsFilter" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-900">
                                <option value="">Any</option>
                                <option value="1">1+</option>
                                <option value="2">2+</option>
                                <option value="3">3+</option>
                                <option value="4">4+</option>
                            </select>
                        </div>
                        
                        <!-- Price Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-800 mb-2">Max Price (R)</label>
                            <input type="number" id="maxPriceFilter" placeholder="Max price..." 
                                   class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-900 placeholder-gray-500">
                        </div>
                        
                        <!-- Clear Filters -->
                        <div class="flex items-end">
                            <button onclick="clearFilters()" class="w-full bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg text-white font-semibold transition-colors">
                                <i class="fas fa-times mr-2"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
            // DB Setup
            $servername = "localhost";
            $username_db = "root";
            $password_db = "";
            $dbname = "easyrent_db";

            try {
                $conn = new mysqli($servername, $username_db, $password_db, $dbname);
                if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);

                // Build the WHERE clause based on filters
                $whereConditions = ["p.admin_approved = 1"];
                $params = [];
                $types = "";

                // Location filter
                if (!empty($_GET['location'])) {
                    $whereConditions[] = "(p.title LIKE ? OR p.address LIKE ?)";
                    $searchTerm = "%" . $_GET['location'] . "%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $types .= "ss";
                }

                // Bedrooms filter
                if (!empty($_GET['bedrooms']) && is_numeric($_GET['bedrooms'])) {
                    $whereConditions[] = "p.bedrooms >= ?";
                    $params[] = (int)$_GET['bedrooms'];
                    $types .= "i";
                }

                // Price filter
                if (!empty($_GET['maxPrice']) && is_numeric($_GET['maxPrice'])) {
                    $whereConditions[] = "p.rent_amount <= ?";
                    $params[] = (int)$_GET['maxPrice'];
                    $types .= "i";
                }

                $whereClause = implode(" AND ", $whereConditions);
                
                // Helper function to build pagination URL
                function buildPaginationUrl($page) {
                    $params = $_GET;
                    $params['page'] = $page;
                    return $_SERVER['PHP_SELF'] . '?' . http_build_query($params);
                }
                
                // Pagination setup
                $itemsPerPage = 12;
                $currentPage = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
                $offset = ($currentPage - 1) * $itemsPerPage;
                
                // Get total count for pagination
                $countSql = "SELECT COUNT(*) as total FROM properties p WHERE $whereClause";
                $countStmt = $conn->prepare($countSql);
                if (!empty($params)) {
                    $countStmt->bind_param($types, ...$params);
                }
                $countStmt->execute();
                $countResult = $countStmt->get_result();
                $totalProperties = $countResult->fetch_assoc()['total'];
                $totalPages = ceil($totalProperties / $itemsPerPage);
                $countStmt->close();
                
                // Fetch properties with pagination
                $sql = "SELECT p.*, 
                        (SELECT image_url FROM property_images 
                         WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS main_image
                        FROM properties p
                        WHERE $whereClause 
                        ORDER BY p.created_at DESC 
                        LIMIT ? OFFSET ?";

                $stmt = $conn->prepare($sql);
                if (!empty($params)) {
                    $types .= "ii";
                    $stmt->bind_param($types, ...array_merge($params, [$itemsPerPage, $offset]));
                } else {
                    $stmt->bind_param("ii", $itemsPerPage, $offset);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    echo '<div id="propertiesGrid" class="grid gap-8 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 properties-grid">';
                    
                    while ($property = $result->fetch_assoc()) {
                        $property_image = !empty($property['main_image']) ? 'uploads/properties/' . htmlspecialchars($property['main_image']) : '';
                        $rent_amount = isset($property['rent_amount']) ? number_format($property['rent_amount']) : 'N/A';
                        $title = htmlspecialchars($property['title'] ?? 'Untitled Property');
                        $address = htmlspecialchars($property['address'] ?? 'Address not specified');
                        $bedrooms = htmlspecialchars($property['bedrooms'] ?? 'N/A');
                        $bathrooms = htmlspecialchars($property['bathrooms'] ?? 'N/A');
                        $description = htmlspecialchars($property['description'] ?? '');
                        $truncated_description = strlen($description) > 90 ? substr($description, 0, 90) . '...' : $description;

                        echo '<div class="bg-white rounded-2xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden flex flex-col">';
                        if ($property_image) {
                            echo '<div class="h-48 w-full bg-cover bg-center" style="background-image: url(\'' . $property_image . '\')"></div>';
                        } else {
                            echo '<div class="h-48 bg-gray-200 flex items-center justify-center text-gray-400 text-5xl"><i class="fas fa-home"></i></div>';
                        }

                        echo '<div class="p-5 flex-1 flex flex-col justify-between">';
                        echo '<div>';
                        echo '<span class="inline-block mb-2 text-xs text-green-600 font-semibold uppercase">Available</span>';
                        echo '<h3 class="text-xl font-bold text-gray-800">' . $title . '</h3>';
                        echo '<p class="text-gray-500 text-sm mt-1 flex items-center"><i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>' . $address . '</p>';
                        echo '<div class="flex items-center justify-between mt-3 text-gray-600 text-sm">';
                        echo '<span><i class="fas fa-bed mr-1"></i>' . $bedrooms . ' Beds</span>';
                        echo '<span><i class="fas fa-bath mr-1"></i>' . $bathrooms . ' Baths</span>';
                        echo '</div>';
                        echo '<p class="text-gray-600 text-sm mt-4">' . $truncated_description . '</p>';
                        echo '</div>';

                        echo '<div class="mt-4 flex items-center justify-between">';
                        echo '<span class="text-blue-600 font-bold text-lg">R' . $rent_amount . '</span>';
                        echo '<a href="dashboard/property_details.php?id=' . $property['id'] . '" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">';
                        echo '<i class="fas fa-eye mr-2"></i>View Details</a>';
                        echo '</div>';

                        echo '</div>';
                        echo '</div>';
                    }

                    echo '</div>';
                    
                    // Pagination controls
                    if ($totalPages > 1) {
                        echo '<div class="mt-12 pagination-container">';
                        
                        // Previous button
                        if ($currentPage > 1) {
                            $prevPage = $currentPage - 1;
                            $prevUrl = buildPaginationUrl($prevPage);
                            echo '<a href="' . htmlspecialchars($prevUrl) . '" class="pagination-btn px-3 sm:px-4 py-2 bg-white text-gray-700 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">';
                            echo '<i class="fas fa-chevron-left mr-1 sm:mr-2"></i><span class="hidden sm:inline">Previous</span>';
                            echo '</a>';
                        } else {
                            echo '<span class="pagination-btn px-3 sm:px-4 py-2 bg-gray-100 text-gray-400 rounded-lg border border-gray-200 cursor-not-allowed">';
                            echo '<i class="fas fa-chevron-left mr-1 sm:mr-2"></i><span class="hidden sm:inline">Previous</span>';
                            echo '</span>';
                        }
                        
                        // Page numbers
                        echo '<div class="flex space-x-1">';
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        // First page
                        if ($startPage > 1) {
                            $firstUrl = buildPaginationUrl(1);
                            echo '<a href="' . htmlspecialchars($firstUrl) . '" class="pagination-btn px-2 sm:px-3 py-2 bg-white text-gray-700 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="px-1 sm:px-2 py-2 text-gray-500">...</span>';
                            }
                        }
                        
                        // Page range
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $currentPage) {
                                echo '<span class="pagination-btn px-2 sm:px-3 py-2 bg-blue-600 text-white rounded-lg font-semibold">' . $i . '</span>';
                            } else {
                                $pageUrl = buildPaginationUrl($i);
                                echo '<a href="' . htmlspecialchars($pageUrl) . '" class="pagination-btn px-2 sm:px-3 py-2 bg-white text-gray-700 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">' . $i . '</a>';
                            }
                        }
                        
                        // Last page
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="px-1 sm:px-2 py-2 text-gray-500">...</span>';
                            }
                            $lastUrl = buildPaginationUrl($totalPages);
                            echo '<a href="' . htmlspecialchars($lastUrl) . '" class="pagination-btn px-2 sm:px-3 py-2 bg-white text-gray-700 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">' . $totalPages . '</a>';
                        }
                        
                        echo '</div>';
                        
                        // Next button
                        if ($currentPage < $totalPages) {
                            $nextPage = $currentPage + 1;
                            $nextUrl = buildPaginationUrl($nextPage);
                            echo '<a href="' . htmlspecialchars($nextUrl) . '" class="pagination-btn px-3 sm:px-4 py-2 bg-white text-gray-700 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors">';
                            echo '<span class="hidden sm:inline">Next</span><i class="fas fa-chevron-right ml-1 sm:ml-2"></i>';
                            echo '</a>';
                        } else {
                            echo '<span class="pagination-btn px-3 sm:px-4 py-2 bg-gray-100 text-gray-400 rounded-lg border border-gray-200 cursor-not-allowed">';
                            echo '<span class="hidden sm:inline">Next</span><i class="fas fa-chevron-right ml-1 sm:ml-2"></i>';
                            echo '</span>';
                        }
                        
                        echo '</div>';
                        
                        // Results info
                        $startItem = ($currentPage - 1) * $itemsPerPage + 1;
                        $endItem = min($currentPage * $itemsPerPage, $totalProperties);
                        echo '<div class="mt-4 text-center text-gray-300">';
                        echo '<p class="text-sm">Showing ' . $startItem . '-' . $endItem . ' of ' . $totalProperties . ' properties</p>';
                        echo '</div>';
                    }
                } else {
                    // Enhanced no properties display
                    echo '<div class="no-properties-container">';
                    echo '<div class="no-properties-icon">';
                    echo '<i class="fas fa-search"></i>';
                    echo '</div>';
                    echo '<h3 class="text-2xl font-bold text-gray-800 mb-3">No Properties Found</h3>';
                    echo '<p class="text-gray-600 mb-6">We couldn\'t find any properties matching your criteria. Try adjusting your filters or browse our complete collection.</p>';
                    echo '<button onclick="clearFilters()" class="btn-primary px-6 py-3 rounded-lg text-white font-semibold">';
                    echo '<i class="fas fa-redo mr-2"></i>Reset Filters';
                    echo '</button>';
                    echo '</div>';
                }

                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                echo '<div class="bg-red-100 text-red-700 p-4 rounded-lg mt-6 shadow">';
                echo '<i class="fas fa-exclamation-triangle mr-2"></i>';
                echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-12">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center mb-4">
                        <img src="logo.png" alt="L&T Connect Logo" class="h-20 w-auto">
                    </div>
                    <p class="text-gray-300 mb-4">
                        Professional property management solutions for landlords and tenants. Making rental processes simple and efficient.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-gray-300 hover:text-white transition-colors">Home</a></li>
                        <li><a href="#properties" class="text-gray-300 hover:text-white transition-colors">Properties</a></li>
                        <li><a href="#about" class="text-gray-300 hover:text-white transition-colors">About Us</a></li>
                        <li><a href="#contact" class="text-gray-300 hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>

                <!-- Services -->
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Services</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Property Listing</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Tenant Screening</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Lease Management</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Maintenance</a></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-t border-gray-700 mt-8 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400 text-sm">
                        &copy; <?php echo date('Y'); ?> L&T Connect. All rights reserved.
                    </p>
                    <div class="flex space-x-6 mt-4 md:mt-0">
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Privacy Policy</a>
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Terms of Service</a>
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Cookie Policy</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Add SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Navigation and Dropdown Functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initializeNavigation();
        

    });

    function initializeNavigation() {
        // Close mobile nav when clicking on navigation links
        document.querySelectorAll('.mobile-nav-item').forEach(link => {
            link.addEventListener('click', function(e) {
                // Only close if it's a navigation link, not a button
                if (this.getAttribute('href') && this.getAttribute('href') !== '#') {
                    closeMobileNav();
                }
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const userSection = document.querySelector('.user-dropdown');
            
            if (dropdown && dropdown.classList.contains('show') && 
                !event.target.closest('.user-dropdown')) {
                dropdown.classList.remove('show');
            }
        });

        // Handle escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMobileNav();
                const dropdown = document.getElementById('userDropdown');
                if (dropdown) dropdown.classList.remove('show');
            }
        });

        // Initialize filter functionality
        setupFilterEventListeners();
        loadFiltersFromURL();
    }

    // Mobile Navigation Functions
    function toggleMobileNav() {
        const container = document.getElementById('mobileNavContainer');
        const isOpen = container.classList.contains('open');
        
        if (isOpen) {
            closeMobileNav();
        } else {
            openMobileNav();
        }
    }

    function openMobileNav() {
        const container = document.getElementById('mobileNavContainer');
        const overlay = document.getElementById('mobileNavOverlay');
        const menuBtn = document.getElementById('mobileMenuBtn');
        
        container.classList.add('open');
        overlay.classList.add('show');
        menuBtn.classList.add('active');
        
        // Prevent body scrolling
        document.body.style.overflow = 'hidden';
    }

    function closeMobileNav() {
        const container = document.getElementById('mobileNavContainer');
        const overlay = document.getElementById('mobileNavOverlay');
        const menuBtn = document.getElementById('mobileMenuBtn');
        
        container.classList.remove('open');
        overlay.classList.remove('show');
        menuBtn.classList.remove('active');
        
        // Restore body scrolling
        document.body.style.overflow = '';
    }

    // Desktop Dropdown Functions
    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('show');
    }

    // Logout Confirmation
function confirmLogout() {
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
            window.location.href = 'auth/logout.php';
        }
    });
}

    // Property Filter Functions
    function setupFilterEventListeners() {
        document.getElementById('locationFilter').addEventListener('input', debounce(applyFilters, 500));
        document.getElementById('bedroomsFilter').addEventListener('change', applyFilters);
        document.getElementById('maxPriceFilter').addEventListener('input', debounce(applyFilters, 500));
        
        // Enter key listeners
        document.getElementById('locationFilter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
        
        document.getElementById('maxPriceFilter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function applyFilters() {
        const location = document.getElementById('locationFilter').value.trim();
        const bedrooms = document.getElementById('bedroomsFilter').value;
        const maxPrice = document.getElementById('maxPriceFilter').value.trim();
        
        // Build URL with filters
        const url = new URL(window.location.href);
        url.searchParams.delete('location');
        url.searchParams.delete('bedrooms');
        url.searchParams.delete('maxPrice');
        url.searchParams.delete('page'); // Reset to page 1 when filters change
        
        if (location) url.searchParams.set('location', location);
        if (bedrooms) url.searchParams.set('bedrooms', bedrooms);
        if (maxPrice) url.searchParams.set('maxPrice', maxPrice);
        
        // Reload page with new filters
        window.location.href = url.toString();
    }

    function clearFilters() {
        document.getElementById('locationFilter').value = '';
        document.getElementById('bedroomsFilter').value = '';
        document.getElementById('maxPriceFilter').value = '';
        
        // Remove all filter parameters from URL
        const url = new URL(window.location.href);
        url.searchParams.delete('location');
        url.searchParams.delete('bedrooms');
        url.searchParams.delete('maxPrice');
        url.searchParams.delete('page'); // Reset to page 1 when clearing filters
        
        window.location.href = url.toString();
    }

    function loadFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        const location = urlParams.get('location');
        const bedrooms = urlParams.get('bedrooms');
        const maxPrice = urlParams.get('maxPrice');
        
        if (location) document.getElementById('locationFilter').value = location;
        if (bedrooms) document.getElementById('bedroomsFilter').value = bedrooms;
        if (maxPrice) document.getElementById('maxPriceFilter').value = maxPrice;
    }
    </script>
</body>
</html>