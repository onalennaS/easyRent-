<?php
/**
 * Admin Setup Script - RUN ONCE ONLY
 * Place this file in: setup/create_admin.php
 * 
 * IMPORTANT: Delete this file after running it successfully!
 */

// Uncomment the line below only when you're ready to create the admin
// define('ALLOW_ADMIN_CREATION', true);

if (!defined('ALLOW_ADMIN_CREATION')) {
    die('Admin creation is disabled. Uncomment the ALLOW_ADMIN_CREATION line to enable.');
}

require_once '../config/database.php';
require_once '../classes/UserAuth.php';

// Admin credentials - CHANGE THESE!
$admin_username = 'superadmin';
$admin_email = 'admin@easyrent.local';  // Use a secure email
$admin_password = 'Admin@2024!SecurePass';  // Use a very strong password

// Security check - ensure this is run from command line or localhost only
if (php_sapi_name() !== 'cli') {
    $allowed_ips = ['127.0.0.1', '::1', 'localhost'];
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!in_array($client_ip, $allowed_ips)) {
        die('Access denied. This script can only be run from localhost.');
    }
}

try {
    // Get database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Initialize UserAuth
    $auth = new UserAuth($conn);
    
    // Create admin user
    $result = $auth->createAdminUser($admin_username, $admin_email, $admin_password);
    
    if ($result['success']) {
        echo "✅ Admin user created successfully!\n";
        echo "📧 Email: " . $admin_email . "\n";
        echo "👤 Username: " . $admin_username . "\n";
        echo "🔐 Password: [REDACTED FOR SECURITY]\n\n";
        echo "⚠️  IMPORTANT SECURITY STEPS:\n";
        echo "1. Delete this file immediately: " . __FILE__ . "\n";
        echo "2. Change the admin password after first login\n";
        echo "3. Use a secure email for admin notifications\n";
        echo "4. Enable two-factor authentication if available\n\n";
        echo "🔗 Admin login URL: /auth/login.php\n";
        
        // Comment out the ALLOW_ADMIN_CREATION line automatically
        $this_file = file_get_contents(__FILE__);
        $updated_file = str_replace(
            "define('ALLOW_ADMIN_CREATION', true);",
            "// define('ALLOW_ADMIN_CREATION', true);",
            $this_file
        );
        file_put_contents(__FILE__, $updated_file);
        
        echo "✅ Admin creation has been automatically disabled.\n";
        
    } else {
        echo "❌ Failed to create admin user: " . $result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Admin creation error: " . $e->getMessage());
}

// Additional security reminder
echo "\n🛡️  SECURITY REMINDER:\n";
echo "- Never commit admin credentials to version control\n";
echo "- Use environment variables for sensitive configuration\n";
echo "- Regularly update admin passwords\n";
echo "- Monitor admin login logs\n";
?>