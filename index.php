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
        
        /* Custom SweetAlert styling */
        .swal2-confirm {
            background: linear-gradient(135deg, #1e40af, #3b82f6) !important;
            border: none !important;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3) !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 8px rgba(59, 130, 246, 0.4) !important;
        }
        
        .swal2-cancel {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-cancel:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            transform: translateY(-2px) !important;
        }
        /* SweetAlert2: Rounded popup with soft shadow */
.swal2-popup {
  border-radius: 1rem !important; /* Rounded corners */
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important; /* Soft shadow */
  padding: 2rem !important;
  background: #ffffff !important; /* Clean white background */
}

/* Keep your nice size */
.swal2-title {
  font-size: 2rem !important; 
  font-weight: 700 !important;
  color: #1e40af !important; 
}

.swal2-html-container,
.swal2-content {
  font-size: 1.25rem !important; 
  color: #374151 !important; 
}

/* Confirm button: gradient, NO shadow */
.swal2-confirm {
  background: linear-gradient(135deg, #1e40af, #3b82f6) !important;
  border: none !important;
  color: #fff !important;
  padding: 0.75rem 2rem !important;
  font-size: 1rem !important;
  border-radius: 0.5rem !important;
  box-shadow: none !important; /* No box shadow */
  transition: all 0.3s ease !important;
}

.swal2-confirm:hover {
  transform: translateY(-2px) !important;
  opacity: 0.95 !important; 
}

/* Cancel button: frosted glass style */
.swal2-cancel {
  background: rgba(255, 255, 255, 0.2) !important;
  border: 1px solid rgba(255, 255, 255, 0.4) !important;
  color: #1e40af !important;
  padding: 0.75rem 2rem !important;
  font-size: 1rem !important;
  border-radius: 0.5rem !important;
  backdrop-filter: blur(4px) !important; 
  transition: all 0.3s ease !important;
}

.swal2-cancel:hover {
  background: rgba(255, 255, 255, 0.35) !important;
  transform: translateY(-2px) !important;
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
    
    <!-- Welcome Section for Logged-in Users -->
    <?php if($isLoggedIn): ?>
    <section id="welcomeSection" class="pt-20 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="welcome-section">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-white mb-2">
                            <i class="fas fa-home mr-3 text-blue-400"></i>
                            Welcome back, <?php echo htmlspecialchars($username); ?>!
                        </h2>
                        <p class="text-blue-200" id="welcomeMessage">
                            <?php if($userRole === 'tenant'): ?>
                                Welcome to your tenant portal! Find your perfect home and manage your rental applications.
                            <?php else: ?>
                                Welcome to your landlord portal! Manage your properties and find reliable tenants.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="hidden md:block">
                        <a href="<?php echo $userRole === 'tenant' ? 'dashboard/tenant_dashboard.php' : 'dashboard/landlord_dashboard.php'; ?>" id="dashboardButton" class="btn-primary px-6 py-3 rounded-xl text-white font-semibold">
                            <i class="fas fa-tachometer-alt mr-2"></i>Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

   <!-- Updated Properties Section -->
<section id="properties" class="py-16 bg-gradient-to-br from-[#1e293b] to-[#0f172a]">
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <h2 class="text-4xl font-bold text-white mb-4">Available Properties</h2>
      <p class="text-blue-200">Approved listings ready for rental</p>
    </div>

    <?php
    $servername = "localhost";
    $username_db = "root";
    $password_db = "";
    $dbname = "easyrent_db";

    try {
      $conn = new mysqli($servername, $username_db, $password_db, $dbname);

      if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
      }

      $sql = "SELECT p.*, 
              (SELECT image_url FROM property_images 
               WHERE property_id = p.id AND is_primary = 1 LIMIT 1) AS main_image
              FROM properties p
              WHERE p.admin_approved = 1 
              ORDER BY p.created_at DESC 
              LIMIT 12";

      $result = $conn->query($sql);

      if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
      }

      if ($result->num_rows > 0) {
        echo '<div class="grid gap-8 sm:grid-cols-10 lg:grid-cols-5 xl:grid-cols-3">';

        while ($property = $result->fetch_assoc()) {
          $property_image = '';
          if (!empty($property['main_image'])) {
            $property_image = 'uploads/properties/' . htmlspecialchars($property['main_image']);
          }

          $rent_amount = isset($property['rent_amount']) ? number_format($property['rent_amount']) : 'N/A';
          $description = $property['description'] ?? '';
          $clean_description = htmlspecialchars($description);
          $truncated_description = strlen($clean_description) > 100 ? substr($clean_description, 0, 100) . '...' : $clean_description;

          echo '<div class="bg-white rounded-2xl shadow-md hover:shadow-2xl transition duration-300 flex flex-col overflow-hidden">';

          if ($property_image) {
            echo '<div class="h-52 w-full bg-cover bg-center" style="background-image: url(\'' . $property_image . '\')"></div>';
          } else {
            echo '<div class="h-52 w-full bg-gray-200 flex items-center justify-center">';
            echo '<i class="fas fa-home text-4xl text-gray-400"></i>';
            echo '</div>';
          }

          echo '<div class="p-6 flex flex-col flex-1">';
          echo '<span class="status-badge">Available</span>';

          echo '<h3 class="text-xl font-semibold text-gray-800 mb-1">' . htmlspecialchars($property['title'] ?? 'Untitled Property') . '</h3>';

          echo '<p class="text-gray-500 text-sm mb-4 flex items-center">';
          echo '<i class="fas fa-map-marker-alt mr-2 text-blue-500"></i>';
          echo htmlspecialchars($property['address'] ?? 'Address not specified');
          echo '</p>';

          echo '<div class="flex justify-between items-center mb-4">';
          echo '<div class="flex space-x-4 text-sm text-gray-600">';
          echo '<span class="flex items-center"><i class="fas fa-bed mr-1"></i>' . htmlspecialchars($property['bedrooms'] ?? 'N/A') . ' Bed</span>';
          echo '<span class="flex items-center"><i class="fas fa-bath mr-1"></i>' . htmlspecialchars($property['bathrooms'] ?? 'N/A') . ' Bath</span>';
          echo '</div>';
          echo '<div class="text-xl font-bold text-blue-600">$' . $rent_amount . '/mo</div>';
          echo '</div>';

          echo '<p class="text-gray-600 text-sm mb-6">' . $truncated_description . '</p>';

          echo '<a href="' . ($isLoggedIn ? 'dashboard/property_details.php?id=' . $property['id'] : 'auth/register.php') . '" class="inline-flex items-center justify-center px-4 py-2 w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition">';
          echo '<i class="fas ' . ($isLoggedIn ? 'fa-eye' : 'fa-user-plus') . ' mr-2"></i>' . ($isLoggedIn ? 'View Details' : 'Sign Up to View') . '</a>';

          echo '</div>'; // p-6
          echo '</div>'; // card
        }

        echo '</div>'; // grid
      } else {
        echo '<div class="text-center py-12">';
        echo '<i class="fas fa-home text-4xl text-gray-300 mb-4"></i>';
        echo '<h3 class="text-xl font-bold mb-2 text-white">No Approved Properties Found</h3>';
        echo '<p class="text-gray-300 mb-4">We couldn\'t find any approved properties in the database.</p>';

        $debug_sql = "SELECT COUNT(*) AS total, SUM(admin_approved) AS approved_count FROM properties";
        $debug_result = $conn->query($debug_sql);
        if ($debug_result && $debug_row = $debug_result->fetch_assoc()) {
          echo '<p class="text-sm text-gray-400">Debug: Total: ' . $debug_row['total'] . ', Approved: ' . $debug_row['approved_count'] . '</p>';
        }

        echo '</div>';
      }

      $conn->close();
    } catch (Exception $e) {
      echo '<div class="bg-red-100 text-red-700 p-4 rounded-lg">';
      echo '<i class="fas fa-exclamation-triangle mr-2"></i>';
      echo '<strong>Database Error:</strong> ' . htmlspecialchars($e->getMessage());
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
    });
    </script>

    <!-- Property Management Script -->
    <script>
  // Property data - replace this with your actual PHP data
const allProperties = [
    // This array should be populated with data from your PHP backend
    // Example structure:
    // {
    //     id: 1,
    //     title: "Property Title",
    //     location: "Location",
    //     bedrooms: 2,
    //     bathrooms: 2,
    //     price: 2500,
    //     status: "available", // available, occupied, maintenance
    //     description: "Property description",
    //     image: "image_url"
    // }
];

let filteredProperties = [...allProperties];
let currentPage = 1;
const propertiesPerPage = 6;

    // Initialize properties on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializeProperties();
        setupFilterEventListeners();
    });

    // Setup filter event listeners
    function setupFilterEventListeners() {
        // Add event listeners to filter inputs
        document.getElementById('locationFilter').addEventListener('input', debounce(applyFilters, 300));
        document.getElementById('bedroomsFilter').addEventListener('change', applyFilters);
        document.getElementById('maxPriceFilter').addEventListener('input', debounce(applyFilters, 300));
        
        // Add Enter key listener for search inputs
        document.getElementById('locationFilter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        document.getElementById('maxPriceFilter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }

    // Debounce function to limit API calls
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

    // Initialize properties display
    function initializeProperties() {
        displayProperties();
    }

    // Apply filters to properties
    function applyFilters() {
        const location = document.getElementById('locationFilter').value.toLowerCase().trim();
        const bedrooms = document.getElementById('bedroomsFilter').value;
        const maxPrice = document.getElementById('maxPriceFilter').value;

        filteredProperties = allProperties.filter(property => {
            // Location filter - check if location contains the search term
            const matchesLocation = !location || 
                property.location.toLowerCase().includes(location) ||
                property.title.toLowerCase().includes(location);
            
            // Bedrooms filter - property must have at least the specified number of bedrooms
            const matchesBedrooms = !bedrooms || property.bedrooms >= parseInt(bedrooms);
            
            // Price filter - property price must be less than or equal to max price
            const matchesPrice = !maxPrice || property.price <= parseInt(maxPrice);
            
            return matchesLocation && matchesBedrooms && matchesPrice;
        });

        // Reset to first page when filters are applied
        currentPage = 1;
        displayProperties();
        
        // Update URL with current filters (optional)
        updateURLWithFilters(location, bedrooms, maxPrice);
    }

    // Update URL with current filter parameters
    function updateURLWithFilters(location, bedrooms, maxPrice) {
        const url = new URL(window.location);
        
        // Clear existing filter parameters
        url.searchParams.delete('location');
        url.searchParams.delete('bedrooms');
        url.searchParams.delete('maxPrice');
        
        // Add new filter parameters if they have values
        if (location) url.searchParams.set('location', location);
        if (bedrooms) url.searchParams.set('bedrooms', bedrooms);
        if (maxPrice) url.searchParams.set('maxPrice', maxPrice);
        
        // Update URL without reloading the page
        window.history.replaceState({}, '', url);
    }

    // Load filters from URL parameters
    function loadFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        const location = urlParams.get('location');
        const bedrooms = urlParams.get('bedrooms');
        const maxPrice = urlParams.get('maxPrice');
        
        if (location) document.getElementById('locationFilter').value = location;
        if (bedrooms) document.getElementById('bedroomsFilter').value = bedrooms;
        if (maxPrice) document.getElementById('maxPriceFilter').value = maxPrice;
        
        // Apply filters if any were found in URL
        if (location || bedrooms || maxPrice) {
            applyFilters();
        }
    }

    // Display properties with loading animation
    function displayProperties() {
        const grid = document.getElementById('propertiesGrid');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const errorMessage = document.getElementById('errorMessage');
        const noProperties = document.getElementById('noProperties');
        const loadMoreSection = document.getElementById('loadMoreSection');

        // Show loading spinner
        loadingSpinner.style.display = 'flex';
        errorMessage.classList.add('hidden');
        noProperties.classList.add('hidden');
        loadMoreSection.style.display = 'none';

        // Clear existing properties if starting fresh
        if (currentPage === 1) {
            grid.innerHTML = '';
        }

        // Simulate loading delay for better UX
        setTimeout(() => {
            loadingSpinner.style.display = 'none';

            // Check if no properties match the filters
            if (filteredProperties.length === 0) {
                noProperties.classList.remove('hidden');
                updateResultsCount(0, 0);
                return;
            }

            // Calculate properties to show
            const startIndex = (currentPage - 1) * propertiesPerPage;
            const endIndex = startIndex + propertiesPerPage;
            const propertiesToShow = filteredProperties.slice(startIndex, endIndex);

            // If starting fresh (page 1), clear the grid
            if (currentPage === 1) {
                grid.innerHTML = '';
            }

            // Add new properties to the grid
            propertiesToShow.forEach((property, index) => {
                const propertyCard = createPropertyCard(property);
                
                // Add fade-in animation
                propertyCard.style.opacity = '0';
                propertyCard.style.transform = 'translateY(20px)';
                grid.appendChild(propertyCard);
                
                // Trigger animation
                setTimeout(() => {
                    propertyCard.style.transition = 'all 0.5s ease';
                    propertyCard.style.opacity = '1';
                    propertyCard.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Update results count
            const totalShown = Math.min(endIndex, filteredProperties.length);
            updateResultsCount(totalShown, filteredProperties.length);

            // Show/hide load more button
            if (endIndex < filteredProperties.length) {
                loadMoreSection.style.display = 'block';
                const loadMoreBtn = document.getElementById('loadMoreBtn');
                const remaining = filteredProperties.length - endIndex;
                loadMoreBtn.innerHTML = `<i class="fas fa-plus mr-3"></i>Load More Properties (${remaining} remaining)`;
            } else {
                loadMoreSection.style.display = 'none';
            }

        }, 500); // Loading delay
    }

    // Update results count display
    function updateResultsCount(shown, total) {
        let resultsCountElement = document.getElementById('resultsCount');
        
        // Create results count element if it doesn't exist
        if (!resultsCountElement) {
            resultsCountElement = document.createElement('div');
            resultsCountElement.id = 'resultsCount';
            resultsCountElement.className = 'text-center text-blue-200 mb-6';
            
            const propertiesSection = document.getElementById('properties');
            const grid = document.getElementById('propertiesGrid');
            propertiesSection.insertBefore(resultsCountElement, grid);
        }
        
        if (total === 0) {
            resultsCountElement.textContent = 'No properties found matching your criteria';
        } else if (shown === total) {
            resultsCountElement.textContent = `Showing all ${total} properties`;
        } else {
            resultsCountElement.textContent = `Showing ${shown} of ${total} properties`;
        }
    }

    // Create property card HTML
    function createPropertyCard(property) {
        const card = document.createElement('div');
        card.className = 'property-card rounded-2xl overflow-hidden relative';
        
        const statusClass = `status-${property.status}`;
        const statusText = property.status.charAt(0).toUpperCase() + property.status.slice(1);

        card.innerHTML = `
            <div class="property-image" style="background-image: url('${property.image}')"></div>
            <div class="status-badge ${statusClass}">${statusText}</div>
            <div class="p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-2">${property.title}</h3>
                <p class="text-gray-600 mb-3 flex items-center">
                    <i class="fas fa-map-marker-alt mr-2 text-blue-500"></i>
                    ${property.location}
                </p>
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-4 text-sm text-gray-600">
                        <span><i class="fas fa-bed mr-1"></i>${property.bedrooms} Bed</span>
                        <span><i class="fas fa-bath mr-1"></i>${property.bathrooms} Bath</span>
                    </div>
                    <div class="text-2xl font-bold text-blue-600">$${property.price.toLocaleString()}/mo</div>
                </div>
                <p class="text-gray-600 text-sm mb-4">${property.description}</p>
                <div class="flex space-x-3">
                    <button class="flex-1 btn-primary px-4 py-2 rounded-lg text-white font-semibold text-sm hover:bg-blue-700 transition-colors" 
                            onclick="viewProperty(${property.id})">
                        <i class="fas fa-eye mr-2"></i>View Details
                    </button>
                    ${property.status === 'available' ? 
                        `<button class="flex-1 btn-secondary px-4 py-2 rounded-lg text-blue-600 font-semibold text-sm hover:bg-blue-50 transition-colors" 
                                onclick="contactAboutProperty(${property.id})">
                            <i class="fas fa-envelope mr-2"></i>Contact
                        </button>`
                     : ''}
                </div>
            </div>
        `;

        return card;
    }

    // Load more properties
    function loadMoreProperties() {
        currentPage++;
        displayProperties();
        
        // Smooth scroll to new properties
        setTimeout(() => {
            const newProperties = document.querySelectorAll('.property-card');
            if (newProperties.length > 0) {
                const targetProperty = newProperties[newProperties.length - propertiesPerPage];
                if (targetProperty) {
                    targetProperty.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }
            }
        }, 600);
    }

    // Clear all filters
    function clearFilters() {
        document.getElementById('locationFilter').value = '';
        document.getElementById('bedroomsFilter').value = '';
        document.getElementById('maxPriceFilter').value = '';
        
        filteredProperties = [...allProperties];
        currentPage = 1;
        displayProperties();
        
        // Clear URL parameters
        const url = new URL(window.location);
        url.searchParams.delete('location');
        url.searchParams.delete('bedrooms');
        url.searchParams.delete('maxPrice');
        window.history.replaceState({}, '', url);
    }

    // View property details
    function viewProperty(propertyId) {
        const property = allProperties.find(p => p.id === propertyId);
        if (property) {
            // In a real application, this would redirect to a property details page
            Swal.fire({
                title: property.title,
                html: `
                    <div class="text-left">
                        <img src="${property.image}" alt="${property.title}" class="w-full h-48 object-cover rounded-lg mb-4">
                        <p class="mb-2"><strong>Location:</strong> ${property.location}</p>
                        <p class="mb-2"><strong>Bedrooms:</strong> ${property.bedrooms}</p>
                        <p class="mb-2"><strong>Bathrooms:</strong> ${property.bathrooms}</p>
                        <p class="mb-2"><strong>Price:</strong> $${property.price.toLocaleString()}/month</p>
                        <p class="mb-2"><strong>Status:</strong> ${property.status}</p>
                        <p class="mb-2"><strong>Description:</strong> ${property.description}</p>
                    </div>
                `,
                showCloseButton: true,
                showCancelButton: property.status === 'available',
                confirmButtonText: property.status === 'available' ? 'Contact About This Property' : 'Close',
                cancelButtonText: 'Close',
                customClass: {
                    confirmButton: 'btn-primary',
                    cancelButton: 'btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed && property.status === 'available') {
                    contactAboutProperty(propertyId);
                }
            });
        }
    }

    // Contact about property
    function contactAboutProperty(propertyId) {
        const property = allProperties.find(p => p.id === propertyId);
        if (property) {
            Swal.fire({
                title: 'Contact About Property',
                html: `
                    <div class="text-left">
                        <p class="mb-4">Interested in: <strong>${property.title}</strong></p>
                        <form id="contactForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Your Name</label>
                                <input type="text" id="contactName" class="w-full px-3 py-2 border rounded-lg" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Email</label>
                                <input type="email" id="contactEmail" class="w-full px-3 py-2 border rounded-lg" required>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Phone</label>
                                <input type="tel" id="contactPhone" class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2">Message</label>
                                <textarea id="contactMessage" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="I'm interested in this property..."></textarea>
                            </div>
                        </form>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Send Message',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'btn-primary',
                    cancelButton: 'btn-secondary'
                },
                buttonsStyling: false,
                preConfirm: () => {
                    const name = document.getElementById('contactName').value;
                    const email = document.getElementById('contactEmail').value;
                    
                    if (!name || !email) {
                        Swal.showValidationMessage('Please fill in all required fields');
                        return false;
                    }
                    
                    return {
                        name: name,
                        email: email,
                        phone: document.getElementById('contactPhone').value,
                        message: document.getElementById('contactMessage').value
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // In a real application, this would send the contact form data to the server
                    Swal.fire({
                        title: 'Message Sent!',
                        text: 'Your inquiry has been sent to the property owner. They will contact you soon.',
                        icon: 'success',
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn-primary'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    }

    // Initialize filters from URL on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadFiltersFromURL();
    });
    </script>
</body>
</html>