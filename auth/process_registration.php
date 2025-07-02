<?php
/**
 * Process Registration - EasyRent
 * Place this file in: auth/process_registration.php
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
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$user_type = $_POST['user_type'] ?? 'tenant'; // Default to tenant

// Validation
$errors = [];

if (empty($username)) {
    $errors[] = 'Username is required';
} elseif (strlen($username) < 3) {
    $errors[] = 'Username must be at least 3 characters long';
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = 'Username can only contain letters, numbers, and underscores';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
}

if (empty($password)) {
    $errors[] = 'Password is required';
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long';
} elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
    $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

if (!in_array($user_type, ['tenant', 'landlord'])) {
    $user_type = 'tenant'; // Default fallback
}

// Return validation errors
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
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
    
    // Attempt registration
    $result = $auth->register($username, $email, $password, $user_type);
    
    if ($result['success']) {
        // Send welcome email (optional - implement later)
        // sendWelcomeEmail($email, $username);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! You can now log in.',
            'redirect' => 'login.php'
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed. Please try again later.'
    ]);
}
?>