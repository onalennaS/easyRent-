<?php
/**
 * Database Configuration for L&T Connect
 * Place this file in: config/database.php
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'easyrent_db';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

/**
 * Helper function to safely start session
 */
function safeSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * User Authentication Helper Class
 */
class UserAuth {
    private $conn;
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    /**
     * Register a new user
     */
    public function register($username, $email, $password, $user_type = 'tenant') {
        try {
            // Check if user already exists
            $check_query = "SELECT id FROM users WHERE email = :email OR username = :username";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                return array('success' => false, 'message' => 'User already exists with this email or username');
            }
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $query = "INSERT INTO users (username, email, password_hash, user_type) VALUES (:username, :email, :password_hash, :user_type)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':user_type', $user_type);
            
            if ($stmt->execute()) {
                $user_id = $this->conn->lastInsertId();
                
                // Create user profile
                $profile_query = "INSERT INTO user_profiles (user_id) VALUES (:user_id)";
                $profile_stmt = $this->conn->prepare($profile_query);
                $profile_stmt->bindParam(':user_id', $user_id);
                $profile_stmt->execute();
                
                return array('success' => true, 'message' => 'User registered successfully', 'user_id' => $user_id);
            }
            
        } catch(PDOException $exception) {
            return array('success' => false, 'message' => 'Registration failed: ' . $exception->getMessage());
        }
        
        return array('success' => false, 'message' => 'Registration failed');
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        try {
            $query = "SELECT id, username, email, password_hash, user_type, is_active FROM users WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                if (!$user['is_active']) {
                    return array('success' => false, 'message' => 'Account is deactivated');
                }
                
                if (password_verify($password, $user['password_hash'])) {
                    // Start session safely and store user data
                    safeSessionStart();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['logged_in'] = true;
                    
                    return array(
                        'success' => true, 
                        'message' => 'Login successful',
                        'user' => array(
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'user_type' => $user['user_type']
                        )
                    );
                } else {
                    return array('success' => false, 'message' => 'Invalid password');
                }
            } else {
                return array('success' => false, 'message' => 'User not found');
            }
            
        } catch(PDOException $exception) {
            return array('success' => false, 'message' => 'Login failed: ' . $exception->getMessage());
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        safeSessionStart();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user info
     */
    public function getCurrentUser() {
        safeSessionStart();
        if ($this->isLoggedIn()) {
            return array(
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'user_type' => $_SESSION['user_type']
            );
        }
        return null;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        safeSessionStart();
        session_destroy();
        return true;
    }
}

/**
 * Helper function to get database connection
 */
function getDatabaseConnection() {
    $database = new Database();
    return $database->getConnection();
}

/**
 * Helper function to redirect based on user type
 */
function redirectToDashboard($user_type) {
    switch ($user_type) {
        case 'landlord':
            header("Location: ../dashboard/landlord_dashboard.php");
            break;
        case 'tenant':
            header("Location: ../dashboard/tenant_dashboard.php");
            break;
        case 'admin':
            header("Location: ../dashboard/admin_dashboard.php");
            break;
        default:
            header("Location: ../dashboard/tenant_dashboard.php");
    }
    exit();
}

/**
 * Format currency (South African Rand)
 */
function formatCurrency($amount) {
    return 'R ' . number_format($amount, 2);
}

/**
 * Calculate days between dates
 */
function daysBetween($date1, $date2) {
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return $interval->days;
}

/**
 * Generate random string for file names
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}
?>