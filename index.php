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
    <title>EasyRent - Professional Property Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <!-- Add SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body {
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(30, 58, 138, 0.9)),
                url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=2073&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
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

        .navbar {
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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

        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            min-width: 200px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            z-index: 1000;
            margin-top: 0.5rem;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            color: white;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
        }

        .dropdown-content a:hover {
            background-color: rgba(59, 130, 246, 0.2);
        }

        .welcome-section {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
        }
        
        /* SweetAlert2 Styling */
        .swal2-popup {
          border-radius: 1rem !important;
          box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
          padding: 2rem !important;
          background: #ffffff !important;
        }

        .swal2-title {
          font-size: 1.5rem !important; 
          font-weight: 700 !important;
          color: #1e40af !important; 
          margin-bottom: 1.5rem !important;
        }

        .swal2-html-container {
          font-size: 1.1rem !important;
          color: #374151 !important;
          line-height: 1.6 !important;
          margin-bottom: 1.5rem !important;
        }

        .swal2-html-container a {
          display: inline-block;
          margin-top: 1rem;
          padding: 0.75rem 1.5rem;
          background: linear-gradient(135deg, #1e40af, #3b82f6);
          color: white !important;
          border-radius: 0.5rem;
          text-decoration: none;
          font-weight: 600;
          transition: all 0.3s ease;
          box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
        }

        .swal2-html-container a:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 8px rgba(59, 130, 246, 0.4);
          text-decoration: none;
        }

        .swal2-confirm {
          background: linear-gradient(135deg, #1e40af, #3b82f6) !important;
          border: none !important;
          color: #fff !important;
          padding: 0.75rem 2rem !important;
          font-size: 1rem !important;
          border-radius: 0.5rem !important;
          box-shadow: none !important;
          transition: all 0.3s ease !important;
        }

        .swal2-confirm:hover {
          transform: translateY(-2px) !important;
          opacity: 0.95 !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar fixed w-full top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <img src="https://cdn-icons-png.flaticon.com/512/489/489870.png" alt="EasyRent Logo" class="w-10 h-10 object-contain" />
                    <div>
                        <h1 class="text-2xl font-bold text-white">EasyRent</h1>
                        <p class="text-xs text-blue-200">Property Management</p>
                    </div>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#home" class="text-white hover:text-blue-300 transition-colors">Home</a>
                    <a href="#properties" class="text-white hover:text-blue-300 transition-colors">Properties</a>
                    <a href="#about" class="text-white hover:text-blue-300 transition-colors">About</a>
                    <a href="#contact" class="text-white hover:text-blue-300 transition-colors">Contact</a>
                    <a href="profile.php" class="text-white hover:text-blue-300 transition-colors">Profile</a>
                    
                    <!-- Show Tenant Dashboard link when logged in as tenant -->
                    <?php if($isLoggedIn && $userRole === 'tenant'): ?>
                    <div id="tenantDashboardLink">
                        <a href="dashboard/tenant_dashboard.php" class="text-blue-300 hover:text-blue-100 transition-colors font-semibold">
                            <i class="fas fa-tachometer-alt mr-2"></i>Tenant Dashboard
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Show Landlord Dashboard link when logged in as landlord -->
                    <?php if($isLoggedIn && $userRole === 'landlord'): ?>
                    <div id="landlordDashboardLink">
                        <a href="dashboard/landlord_dashboard.php" class="text-blue-300 hover:text-blue-100 transition-colors font-semibold">
                            <i class="fas fa-tachometer-alt mr-2"></i>Landlord Dashboard
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- User Authentication Section -->
                <div class="flex items-center space-x-4">
                    <!-- Show when not logged in -->
                    <?php if(!$isLoggedIn): ?>
                    <div id="authLinks" class="flex items-center space-x-4">
                        <a href="auth/login.php" class="text-white hover:text-blue-300 transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login
                        </a>
                        <a href="auth/register.php" class="btn-primary px-4 py-2 rounded-lg text-white font-semibold">
                            <i class="fas fa-user-plus mr-2"></i>Register
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Show when logged in -->
                    <?php if($isLoggedIn): ?>
                    <div id="userSection" class="user-dropdown">
                        <button onclick="toggleUserDropdown()" class="flex items-center space-x-2 text-white hover:text-blue-300 transition-colors">
                            <i class="fas fa-user-circle text-2xl"></i>
                            <span id="userName"><?php echo htmlspecialchars($username); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div id="userDropdown" class="dropdown-content">
                            <a href="<?php echo $userRole === 'tenant' ? 'dashboard/tenant_dashboard.php' : 'dashboard/landlord_dashboard.php'; ?>" id="dashboardLink">
                                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                            </a>
                            <a href="profile.php">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="settings.php">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </a>
                            <div style="border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 0.5rem 0;"></div>
                            <a href="#" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobileMenuButton" class="text-white hover:text-blue-300">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mobileMenu" class="hidden md:hidden bg-black bg-opacity-90">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="#home" class="block px-3 py-2 text-white hover:text-blue-300">Home</a>
                <a href="#properties" class="block px-3 py-2 text-white hover:text-blue-300">Properties</a>
                <a href="#about" class="block px-3 py-2 text-white hover:text-blue-300">About</a>
                <a href="#contact" class="block px-3 py-2 text-white hover:text-blue-300">Contact</a>
                
                <!-- Mobile Tenant Dashboard Link -->
                <?php if($isLoggedIn && $userRole === 'tenant'): ?>
                <div id="mobileTenantDashboard">
                    <a href="dashboard/tenant_dashboard.php" class="block px-3 py-2 text-blue-300 hover:text-blue-100 font-semibold">
                        <i class="fas fa-tachometer-alt mr-2"></i>Tenant Dashboard
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Mobile Landlord Dashboard Link -->
                <?php if($isLoggedIn && $userRole === 'landlord'): ?>
                <div id="mobileLandlordDashboard">
                    <a href="dashboard/landlord_dashboard.php" class="block px-3 py-2 text-blue-300 hover:text-blue-100 font-semibold">
                        <i class="fas fa-tachometer-alt mr-2"></i>Landlord Dashboard
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Mobile Auth Links -->
                <?php if(!$isLoggedIn): ?>
                <div id="mobileAuthLinks" class="border-t border-gray-600 pt-2 mt-2">
                    <a href="auth/login.php" class="block px-3 py-2 text-white hover:text-blue-300">Login</a>
                    <a href="auth/register.php" class="block px-3 py-2 text-white hover:text-blue-300">Register</a>
                </div>
                <?php endif; ?>
                
                <!-- Mobile User Menu -->
                <?php if($isLoggedIn): ?>
                <div id="mobileUserMenu" class="border-t border-gray-600 pt-2 mt-2">
                    <div class="px-3 py-2 text-blue-300 font-semibold">
                        <i class="fas fa-user-circle mr-2"></i><span id="mobileUserName"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <a href="<?php echo $userRole === 'tenant' ? 'dashboard/tenant_dashboard.php' : 'dashboard/landlord_dashboard.php'; ?>" id="mobileDashboardLink" class="block px-3 py-2 text-white hover:text-blue-300">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="profile.php" class="block px-3 py-2 text-white hover:text-blue-300">
                        <i class="fas fa-user mr-2"></i>Profile
                    </a>
                    <a href="settings.php" class="block px-3 py-2 text-white hover:text-blue-300">
                        <i class="fas fa-cog mr-2"></i>Settings
                    </a>
                    <a href="#" onclick="confirmLogout()" class="block px-3 py-2 text-white hover:text-blue-300">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Properties Section -->
    <section id="properties" class="py-16 bg-gradient-to-br from-slate-900 to-slate-800">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-white mb-3">Available Properties</h2>
                <p class="text-blue-200">Explore our latest approved rental listings</p>
            </div>
            
            <!-- Filter Section -->
            <div class="mb-8">
                <div class="glass-card rounded-xl p-6">
                    <h3 class="text-xl font-semibold text-white mb-4">
                        <i class="fas fa-filter mr-2"></i>Filter Properties
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Location Filter -->
                        <div>
                            <label class="block text-sm font-medium text-blue-200 mb-2">Location</label>
                            <input type="text" id="locationFilter" placeholder="Search by location..." 
                                   class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-black placeholder-black-200">
                        </div>
                        
                        <!-- Bedrooms Filter -->
                        <div>
                            <label class="block text-sm font-medium text-blue-200 mb-2">Min Bedrooms</label>
                            <select id="bedroomsFilter" class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-black">
                                <option value="">Any</option>
                                <option value="1">1+</option>
                                <option value="2">2+</option>
                                <option value="3">3+</option>
                                <option value="4">4+</option>
                            </select>
                        </div>
                        
                        <!-- Price Filter -->
                        <div>
                            <label class="block text-sm font-medium text-blue-200 mb-2">Max Price (R)</label>
                            <input type="number" id="maxPriceFilter" placeholder="Max price..." 
                                   class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-black placeholder-black-200">
                        </div>
                        
                        <!-- Clear Filters -->
                        <div class="flex items-end">
                            <button onclick="clearFilters()" class="w-full btn-secondary px-4 py-2 rounded-lg text-white font-semibold">
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
                
                $sql = "SELECT p.*, 
                        (SELECT image_url FROM property_images 
                         WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS main_image
                        FROM properties p
                        WHERE $whereClause 
                        ORDER BY p.created_at DESC 
                        LIMIT 12";

                $stmt = $conn->prepare($sql);
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    echo '<div id="propertiesGrid" class="grid gap-8 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">';
                    
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
                        echo '<a href="' . ($isLoggedIn ? 'dashboard/property_details.php?id=' . $property['id'] : 'auth/register.php') . '" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">';
                        echo '<i class="fas ' . ($isLoggedIn ? 'fa-eye' : 'fa-user-plus') . ' mr-2"></i>' . ($isLoggedIn ? 'View' : 'Sign Up to View') . '</a>';
                        echo '</div>';

                        echo '</div>';
                        echo '</div>';
                    }

                    echo '</div>';
                } else {
                    echo '<div class="text-center py-16">';
                    echo '<div class="inline-block bg-blue-800 p-6 rounded-full shadow-lg animate-bounce mb-6">';
                    echo '<i class="fas fa-search text-white text-5xl"></i>';
                    echo '</div>';
                    echo '<h3 class="text-2xl font-bold text-white mb-2">No Properties Found</h3>';
                    echo '<p class="text-blue-200 text-sm">Try adjusting your search criteria or clear the filters.</p>';
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

    <!-- About Section -->
    <section id="about" class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="animate-slideUp">
                    <h2 class="text-4xl font-bold text-white mb-6">Why Choose EasyRent?</h2>
                    <div class="space-y-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <i class="fas fa-shield-alt text-blue-400 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-white mb-2">Secure & Reliable</h3>
                                <p class="text-blue-200">Advanced security measures protect your data and transactions</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <i class="fas fa-users text-blue-400 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-white mb-2">Professional Support</h3>
                                <p class="text-blue-200">24/7 customer support to help you manage your properties</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <i class="fas fa-mobile-alt text-blue-400 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-white mb-2">Mobile Friendly</h3>
                                <p class="text-blue-200">Access your properties anywhere, anytime from any device</p>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="footer py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center space-x-4 mb-6">
                        <img src="https://cdn-icons-png.flaticon.com/512/489/489870.png" alt="EasyRent Logo" class="w-12 h-12 object-contain" />
                        <div>
                            <h3 class="text-2xl font-bold text-white">EasyRent</h3>
                            <p class="text-blue-200">Professional Property Management</p>
                        </div>
                    </div>
                    <p class="text-blue-200 mb-4">
                        Making property rental simple, secure, and efficient for landlords and tenants worldwide.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-blue-400 hover:text-blue-300 transition-colors">
                            <i class="fab fa-facebook text-2xl"></i>
                        </a>
                        <a href="#" class="text-blue-400 hover:text-blue-300 transition-colors">
                            <i class="fab fa-twitter text-2xl"></i>
                        </a>
                        <a href="#" class="text-blue-400 hover:text-blue-300 transition-colors">
                            <i class="fab fa-instagram text-2xl"></i>
                        </a>
                        <a href="#" class="text-blue-400 hover:text-blue-300 transition-colors">
                            <i class="fab fa-linkedin text-2xl"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-blue-200 hover:text-blue-300 transition-colors">Home</a></li>
                        <li><a href="#properties" class="text-blue-200 hover:text-blue-300 transition-colors">Properties</a></li>
                        <li><a href="auth/login.php" class="text-blue-200 hover:text-blue-300 transition-colors">Login</a></li>
                        <li><a href="auth/register.php" class="text-blue-200 hover:text-blue-300 transition-colors">Register</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Contact Info</h4>
                    <ul class="space-y-2 text-blue-200">
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-3 text-blue-400"></i>
                            info@easyrent.com
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone mr-3 text-blue-400"></i>
                            +1 (555) 123-4567
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-3 text-blue-400"></i>
                            123 Business Ave, City
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Add SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Navigation and Dropdown Functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle user dropdown
        window.toggleUserDropdown = function() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const userSection = document.getElementById('userSection');
            
            if (dropdown && dropdown.classList.contains('show') && 
                !event.target.closest('#userSection')) {
                dropdown.classList.remove('show');
            }
        });

        // Mobile menu toggle
        document.getElementById('mobileMenuButton').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        });

        // Confirm logout
        window.confirmLogout = function() {
            Swal.fire({
                title: 'Logout?',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'btn-primary',
                    cancelButton: 'btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'auth/logout.php';
                }
            });
        }
        
 <?php if ($showProfileAlert): ?>
// Show profile completion alert
Swal.fire({
    title: 'Complete Your Profile',
    html: `Your profile information is incomplete.<br><br>
           Please complete your profile to access all features and ensure the best experience.<br><br>
           <a href="settings.php" class="text-blue-500 underline font-medium">Click here to complete your profile</a>`,
    icon: 'info',
    confirmButtonText: 'OK',
    customClass: {
        confirmButton: 'btn-primary',
        popup: 'swal2-rounded'
    },
    buttonsStyling: false,
    allowOutsideClick: false
});
<?php endif; ?>
    });
    </script>

    <!-- Property Management Script -->
    <script>
    // Setup filter event listeners
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
    
    // Initialize filters
    document.addEventListener('DOMContentLoaded', function() {
        setupFilterEventListeners();
        loadFiltersFromURL();
    });
    </script>
</body>
</html>