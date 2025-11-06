<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "Database connection successful!";
    
    // Test query to verify data access
    $query = "SELECT COUNT(*) as total FROM users";
    $stmt = $db->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal users: " . $result['total'];
    
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    // Print more detailed error information
    echo "\nError code: " . $e->getCode();
    echo "\nTrace: " . $e->getTraceAsString();
}