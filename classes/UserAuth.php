<?php
/**
 * UserAuth Class - Enhanced with Admin Support
 * Place this file in: classes/UserAuth.php
 */

class UserAuth {
    private $conn;
    private $table_name = "users";
    private $max_login_attempts = 5;
    private $lockout_duration = 900; // 15 minutes in seconds
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Register a new user (tenant or landlord only)
     */
    public function register($username, $email, $password, $user_type = 'tenant') {
        try {
            // Ensure admin cannot be registered through public registration
            if ($user_type === 'admin') {
                return [
                    'success' => false,
                    'message' => 'Invalid user type specified'
                ];
            }
            
            // Check if username already exists
            $stmt = $this->conn->prepare("SELECT id FROM " . $this->table_name . " WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return [
                    'success' => false,
                    'message' => 'Username already exists'
                ];
            }
            
            // Check if email already exists
            $stmt = $this->conn->prepare("SELECT id FROM " . $this->table_name . " WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return [
                    'success' => false,
                    'message' => 'Email already exists'
                ];
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $this->conn->prepare("
                INSERT INTO " . $this->table_name . " 
                (username, email, password, user_type, created_at, status) 
                VALUES (?, ?, ?, ?, NOW(), 'active')
            ");
            
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $user_type);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Registration successful',
                    'user_id' => $this->conn->insert_id
                ];
            } else {
                throw new Exception('Failed to create user account');
            }
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }
    
    /**
     * Login user with rate limiting and admin support
     */
    public function login($email, $password) {
        try {
            // Check for rate limiting
            if ($this->isAccountLocked($email)) {
                return [
                    'success' => false,
                    'message' => 'Account temporarily locked due to too many failed attempts. Please try again later.'
                ];
            }
            
            // Get user from database
            $stmt = $this->conn->prepare("
                SELECT id, username, email, password, user_type, status, failed_login_attempts, last_failed_login
                FROM " . $this->table_name . " 
                WHERE email = ? AND status = 'active'
            ");
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $this->recordFailedLogin($email);
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            $user = $result->fetch_assoc();
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->recordFailedLogin($email);
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Reset failed login attempts on successful login
            $this->resetFailedLoginAttempts($email);
            
            // Start session and store user data
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Update last login time
            $this->updateLastLogin($user['id']);
            
            // Log successful login (especially important for admin logins)
            $this->logLoginActivity($user['id'], $user['user_type'], 'success');
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'user_type' => $user['user_type']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ];
        }
    }
    
    /**
     * Create admin user programmatically (should only be run once)
     */
    public function createAdminUser($username, $email, $password) {
        try {
            // Check if admin already exists
            $stmt = $this->conn->prepare("SELECT id FROM " . $this->table_name . " WHERE user_type = 'admin'");
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return [
                    'success' => false,
                    'message' => 'Admin user already exists'
                ];
            }
            
            // Hash password with higher cost for admin
            $hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
            
            // Insert admin user
            $stmt = $this->conn->prepare("
                INSERT INTO " . $this->table_name . " 
                (username, email, password, user_type, created_at, status) 
                VALUES (?, ?, ?, 'admin', NOW(), 'active')
            ");
            
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $this->logAdminCreation($this->conn->insert_id);
                return [
                    'success' => true,
                    'message' => 'Admin user created successfully',
                    'admin_id' => $this->conn->insert_id
                ];
            } else {
                throw new Exception('Failed to create admin user');
            }
            
        } catch (Exception $e) {
            error_log("Admin creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create admin user'
            ];
        }
    }
    
    /**
     * Check if account is locked due to failed login attempts
     */
    private function isAccountLocked($email) {
        $stmt = $this->conn->prepare("
            SELECT failed_login_attempts, last_failed_login 
            FROM " . $this->table_name . " 
            WHERE email = ?
        ");
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $user = $result->fetch_assoc();
        
        if ($user['failed_login_attempts'] >= $this->max_login_attempts) {
            $lockout_end = strtotime($user['last_failed_login']) + $this->lockout_duration;
            return time() < $lockout_end;
        }
        
        return false;
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedLogin($email) {
        $stmt = $this->conn->prepare("
            UPDATE " . $this->table_name . " 
            SET failed_login_attempts = failed_login_attempts + 1,
                last_failed_login = NOW()
            WHERE email = ?
        ");
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
    }
    
    /**
     * Reset failed login attempts
     */
    private function resetFailedLoginAttempts($email) {
        $stmt = $this->conn->prepare("
            UPDATE " . $this->table_name . " 
            SET failed_login_attempts = 0,
                last_failed_login = NULL
            WHERE email = ?
        ");
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
    }
    
    /**
     * Update last login time
     */
    private function updateLastLogin($user_id) {
        $stmt = $this->conn->prepare("
            UPDATE " . $this->table_name . " 
            SET last_login = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    /**
     * Log login activity for security audit
     */
    private function logLoginActivity($user_id, $user_type, $status) {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt = $this->conn->prepare("
                INSERT INTO login_logs 
                (user_id, user_type, status, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param("issss", $user_id, $user_type, $status, $ip_address, $user_agent);
            $stmt->execute();
            
        } catch (Exception $e) {
            // Log to error file if login_logs table doesn't exist yet
            error_log("Login activity logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log admin user creation
     */
    private function logAdminCreation($admin_id) {
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            $stmt = $this->conn->prepare("
                INSERT INTO admin_logs 
                (admin_id, action, details, ip_address, created_at) 
                VALUES (?, 'admin_created', 'New admin user created', ?, NOW())
            ");
            
            $stmt->bind_param("is", $admin_id, $ip_address);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Admin creation logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Log logout activity
        if (isset($_SESSION['user_id'])) {
            $this->logLoginActivity($_SESSION['user_id'], $_SESSION['user_type'] ?? 'unknown', 'logout');
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Remove remember me cookie if exists
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $this->isLoggedIn() && 
               isset($_SESSION['user_type']) && 
               $_SESSION['user_type'] === 'admin';
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'user_type' => $_SESSION['user_type'] ?? null
        ];
    }
}
?>