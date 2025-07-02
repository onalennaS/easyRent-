<?php
/**
 * Process Login - EasyRent
 * Place this file in: auth/process_login.php
 */

require_once '../config/database.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember_me']);

// Validation
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;    
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
    
    // Attempt login
    $result = $auth->login($email, $password);
    
    if ($result['success']) {
        // Handle "Remember Me" functionality
        if ($remember_me) {
            $token = bin2hex(random_bytes(32));
            // Store token in database (implement remember_tokens table if needed)
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true); // 30 days
        }
        
        // Determine redirect URL based on user type
        $redirect_url = '';
        switch ($result['user']['user_type']) {
            case 'landlord':
                $redirect_url = '../index.php';
                break;
            case 'tenant':
                $redirect_url = '../index.php';
                break;
            case 'admin':
                $redirect_url = '../dashboard/admin_dashboard.php';
                break;
            default:
                $redirect_url = '../index.php';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'redirect' => $redirect_url,
            'user_type' => $result['user']['user_type']
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Login failed. Please try again later.'
    ]);
}
?>